<?php

namespace App\Http\Controllers;

use App\Helpers\SocietyAccessResolver;
use App\Http\Controllers\frontend\concerns\ResolvesVisitor;
use App\Models\Property;
use App\Models\PropertyInquiry;
use App\Models\PropertyListing;
use App\Models\PropertyUnit;
use App\Models\Society;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;

class PropertyInquiryController extends Controller
{
    use ResolvesVisitor;

    public function index(Request $request)
    {
        $login_user = Auth::user();
        if ($request->ajax()) {
            $query = PropertyListing::query()
                ->with(['listable' => function ($morphTo) {
                    $morphTo->morphWith([
                        Property::class => ['block.society', 'reservedPaymentPlans.propertyOwner'],
                        PropertyUnit::class => ['floor.property.block.society', 'reservedPaymentPlans.propertyOwner'],
                    ]);
                }])
                ->withCount('inquiries')
                ->has('inquiries');
            //            dd($query);

            $scope = SocietyAccessResolver::resolver($login_user);
            if ($scope['isSocietyScoped']) {
                $ownedSocietyIds = $scope['ownedSocietyIds'];
                $query->where(function ($q) use ($ownedSocietyIds) {
                    $q->where(function ($q2) use ($ownedSocietyIds) {
                        $q2->where('listable_type', Property::class)
                            ->whereIn('listable_id', function ($sub) use ($ownedSocietyIds) {
                                $sub->select('properties.id')
                                    ->from('properties')
                                    ->join('blocks', 'properties.block_id', '=', 'blocks.id')
                                    ->whereIn('blocks.society_id', $ownedSocietyIds);
                            });
                    })
                        ->orWhere(function ($q2) use ($ownedSocietyIds) {
                            $q2->where('listable_type', PropertyUnit::class)
                                ->whereIn('listable_id', function ($sub) use ($ownedSocietyIds) {
                                    $sub->select('property_units.id')
                                        ->from('property_units')
                                        ->join('floors', 'property_units.floor_id', '=', 'floors.id')
                                        ->join('properties', 'floors.property_id', '=', 'properties.id')
                                        ->join('blocks', 'properties.block_id', '=', 'blocks.id')
                                        ->whereIn('blocks.society_id', $ownedSocietyIds);
                                });
                        });
                });
            }
            // else: no filter — show all listings

            return DataTables::of($query)
                ->addColumn('checkbox', function ($listing) {
                    return '<input type="checkbox" class="form-check-input checkbox" value="'.$listing->id.'">';
                })
                ->addColumn('property_name', function ($listing) {
                    if (! $listing->listable) {
                        return '';
                    }

                    if ($listing->listable_type === Property::class) {
                        $name = $listing->listable->name ?: ucwords($listing->listable->type);
                        $url = route('property.details', $listing->listable->uuid);
                    } elseif ($listing->listable_type === PropertyUnit::class) {
                        $name = $listing->listable->unit_name;
                        $url = route('unit.details', $listing->listable->uuid);
                    } else {
                        return '';
                    }

                    $shortName = Str::limit($name, 55, '...');

                    $soldOut = $listing->listable->reservedPaymentPlans
                        ->contains(fn ($plan) => $plan->propertyOwner?->isCurrent());

                    $soldBadge = $soldOut
                      ? ' <span class="badge bg-success"><i class="ti tabler-shopping-cart-x me-1"></i>Sold Out</span>'
                      : '';

                    return '<a href="'.e($url).'" title="'.e(ucwords($name)).'" class="badge bg-label-info text-truncate d-inline-block cursor-pointer">'
                      .e(ucwords($shortName)).'</a>'.$soldBadge;
                })
                ->addColumn('listing_type', function ($listing) {
                    return '<span  class="badge bg-label-secondary text-truncate d-inline-block">'
                      .e(ucfirst($listing->listing_type)).'</span>';
                })
                ->addColumn('type', function ($listing) {
                    if ($listing->listable_type === Property::class) {
                        return 'Property';
                    }
                    if ($listing->listable_type === PropertyUnit::class) {
                        return 'Unit';
                    }

                    return '';
                })
                ->editColumn('no_of_reports', function ($listing) {
                    return $listing->inquiries_count;
                })
                ->addColumn('actions', function ($listing) {
                    $url = route('inquiries.show', $listing->uuid);

                    return '<a href="'.$url.'" class="pe-3">
                      <i class="fa-solid fa-eye text-primary" role="button" title="View details"></i>
                    </a>';
                })
                ->rawColumns(['checkbox', 'property_name', 'actions', 'listing_type'])
                ->make(true);
        }

        $can_see = $login_user->can('see_inquiries');
        $show_actions = $can_see;

        return view('content.property_management.inquiries.index', compact('show_actions'));
    }

    public function storeOrUpdate(Request $request)
    {
        $searchParams = $request->input('search', []);
        $redirectUrl = $request->input('redirect_url');
        $fallback = fn () => $redirectUrl
          ? redirect()->to($redirectUrl)
          : redirect()->route('property.search', $searchParams);

        $validator = Validator::make($request->all(), [
            'listing_id' => 'required|integer|exists:property_listings,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'contact_number' => 'required|string|max:255',
            'message' => 'required|string',
            'status' => 'nullable|string|in:pending,approved,rejected',
        ]);

        if ($validator->fails()) {
            return $fallback()
                ->withInput()
                ->withErrors($validator)
                ->with('error', 'Please check the form and try again.');
        }

        try {
            $visitor = $this->getOrCreateVisitor();
            PropertyInquiry::updateOrCreate(
                ['visitor_id' => $visitor->id, 'listing_id' => $request->listing_id],
                [
                    'name' => $request->name,
                    'email' => $request->email,
                    'contact_number' => $request->contact_number,
                    'message' => $request->message,
                    'status' => $request->status ?? 'pending',
                ]
            );

            return $fallback()
                ->withInput()
                ->with('success', 'Your inquiry submitted successfully.');
        } catch (\Exception $e) {
            return $fallback()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function inquiriesWithListing(PropertyListing $listing)
    {
        return $this->show($listing, null, null, null, null, null);
    }

    public function propertyInquiriesFromSociety($user_type, $society_uuid, PropertyListing $listing)
    {

        return $this->show($listing, null, null, $user_type, $society_uuid, true);
    }

    public function propertyInquiries($type, $property_uuid, $listing_uuid)
    {

        $listing = PropertyListing::where('uuid', $listing_uuid)->first();

        return $this->show($listing, $type, $property_uuid, null, null, true);
    }

    public function search(PropertyListing $listing, Request $request)
    {
        if (! $request->ajax()) {
            abort(404);
        }

        $term = trim((string) $request->input('q'));

        $query = $listing->inquiries()->latest();

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%')
                    ->orWhere('contact_number', 'like', '%'.$term.'%');
            });
        }

        $inquiries = $query->with('visitor')->get();

        $memberInquiryIds = $this->memberInquiryIdMap($listing, $inquiries);

        $ownerContext = $this->ownerContextForListing($listing);

        $isSoldOut = $listing->listable
          && $listing->listable->reservedPaymentPlans()
              ->whereHas('propertyOwner', fn ($q) => $q->active())
              ->latest('id')
              ->exists();

        $html = '';
        foreach ($inquiries as $inquiry) {
            $html .= $this->renderInquiryItem($inquiry, $memberInquiryIds, $ownerContext, $isSoldOut);
        }

        if ($html === '') {
            $html = '<div class="text-center py-5">
                <i class="ti tabler-mail-off fs-1 text-muted opacity-50 mb-3"></i>
                <h6 class="text-muted">No inquiries found for this listing</h6>
              </div>';
        }

        return response()->json([
            'html' => $html,
            'count' => $inquiries->count(),
        ]);
    }

    private function renderInquiryItem($inquiry, $memberInquiryIds, array $ownerContext, bool $isSoldOut = false): string
    {
        $member = (isset($memberInquiryIds[$inquiry->id]))
          ? $memberInquiryIds[$inquiry->id]
          : null;

        $pic = '';
        if ($member && $member->attachment?->link) {
            $pic = asset('storage/'.$member->attachment->link);
        }

        $ownerableType = $ownerContext['ownerableType'] ?? '';
        $ownerableId = $ownerContext['ownerableId'] ?? '';
        $propertySocietyId = $ownerContext['propertySocietyId'] ?? '';

        $infoName = 'title="'.e(ucwords($inquiry->name)).'"';
        $initial = strtoupper(substr($inquiry->name, 0, 1));
        $avatar = $pic
          ? '<img src="'.e($pic).'" class="rounded-circle object-fit-cover" width="46" height="46" alt="'.e($inquiry->name).'">'
          : '<div class="rounded-circle bg-label-primary d-flex align-items-center justify-content-center fw-bold" style="width:46px;height:46px;font-size:1.1rem;">'.e($initial).'</div>';

        $memberBadge = $member
          ? '<span class="badge bg-label-success"><i class="ti tabler-user-check me-1"></i>Already Society Member</span>'
          : '';

        $ownerAction = $isSoldOut
          ? ''
          : ($member
            ? '<button type="button" class="btn btn-sm btn-outline-success ms-1 assign-member-btn"
                 data-ownerable-type="'.e($ownerableType).'"
                 data-ownerable-id="'.e($ownerableId).'"
                 data-member-id="'.e($member->id).'"
                 data-member-label="Linking '.e($member->first_name.' '.$member->last_name).' as owner.">
                 <i class="ti tabler-user-check me-1"></i><span class="d-none d-sm-inline">Assign As Owner</span>
               </button>'
            : '<button type="button" class="btn btn-sm btn-outline-primary ms-1 create-member-btn"
                 data-ownerable-type="'.e($ownerableType).'"
                 data-ownerable-id="'.e($ownerableId).'"
                 data-prefill-name="'.e($inquiry->name).'"
                 data-prefill-email="'.e($inquiry->email).'"
                 data-prefill-contact="'.e($inquiry->contact_number).'">
                 <i class="ti tabler-user-plus me-1"></i><span class="d-none d-sm-inline">Create Member</span>
               </button>');

        $dismissForm = '<form action="'.route('inquiries.dismiss', $inquiry->id).'" method="POST" class="d-inline-block">
            '.csrf_field().method_field('DELETE').'
            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(event, null)">
              <i class="ti tabler-trash"></i> <span class="d-none d-sm-inline">Delete</span>
            </button>
          </form>';

        return '<div class="rounded-3 border inquiry-item mb-3 overflow-hidden">
          <div class="d-flex align-items-center justify-content-between gap-3 p-3 bg-light border-bottom flex-wrap">
            <div class="d-flex align-items-center gap-3">
              <div class="flex-shrink-0">'.$avatar.'</div>
              <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <span class="fw-semibold fs-6">'.e(ucwords($inquiry->name)).'</span>
                  '.$memberBadge.'
                </div>
                <div class="text-muted small">
                  <i class="ti tabler-clock icon-xs me-1"></i>'.$inquiry->created_at->format('d M Y, h:i A').'
                  <span class="mx-1">&middot;</span>'.$inquiry->created_at->diffForHumans().'
                </div>
              </div>
            </div>
            <div>'.$ownerAction.$dismissForm.'</div>
          </div>
          <div class="p-3">
            <div class="d-flex align-items-center gap-3 flex-wrap mb-3 pb-3 border-bottom">
              <a href="mailto:'.e($inquiry->email).'" class="d-flex align-items-center gap-2 text-dark text-decoration-none small">
                <i class="ti tabler-mail icon-sm text-primary"></i>'.e($inquiry->email).'
              </a>
              <a href="tel:'.e($inquiry->contact_number).'" class="d-flex align-items-center gap-2 text-dark text-decoration-none small">
                <i class="ti tabler-phone icon-sm text-primary"></i>'.e($inquiry->contact_number).'
              </a>
            </div>
            <div class="text-muted text-uppercase small fw-bold mb-2"><i class="ti tabler-message-2 icon-xs me-2"></i>Message</div>
            <div class="rounded-2 p-3 border-start border-3 border-primary">
              <p class="mb-0 text-dark" style="white-space: pre-wrap; word-break: break-word;">'.e(ucfirst($inquiry->message)).'</p>
            </div>
          </div>
        </div>';
    }

    public function show($listing, $type = null, $property_uuid = null, $user_type = null, $society_uuid = null, $isOwner = null)
    {
        $society = Society::where('uuid', $society_uuid)->first();
        $unit = null;
        $property = null;
        $block = null;

        if ($type === 'unit') {
            $unit = PropertyUnit::where('uuid', $property_uuid)->first();
            $block = $unit->floor->property->block;
        }
        if ($type === 'property') {
            $property = Property::where('uuid', $property_uuid)->first();
            $block = $property->block;
        }
        $listing?->load(['listable' => function ($morphTo) {
            $morphTo->morphWith([
                Property::class => ['block.society'],
                PropertyUnit::class => ['floor.property.block.society'],
            ]);
        }, 'inquiries' => function ($q) {
            $q->latest();
        }, 'inquiries.visitor']);
        if ($listing?->listable_type === Property::class) {
            $propertyName = $listing->listable->name ?: ucwords($listing->listable->type);
        } elseif ($listing?->listable_type === PropertyUnit::class) {
            $propertyName = $listing->listable->unit_name;
        } else {
            $propertyName = 'Unknown';
        }
        $inquiries = $listing?->inquiries;

        $memberInquiryIds = $this->memberInquiryIdMap($listing, $inquiries);

        $ownerContext = $this->ownerContextForListing($listing);

        // Current committed-plan snapshot for the listing's ownerable (if the
        // open reserved has one). 'completed' means the sale is final.
        $reservedPlan = null;
        $isSoldOut = false;
        $canCancelReservation = false;
        if ($listing?->listable) {
            $reservedPlan = $listing->listable->reservedPaymentPlans()
                ->whereHas('propertyOwner', fn ($q) => $q->active())
                ->latest('id')
                ->first();
            $isSoldOut = $reservedPlan !== null;
            $canCancelReservation = $reservedPlan !== null
                && ! in_array($reservedPlan->status, ['completed', 'reversed', 'cancelled'], true);
        }

        $canClearInquiries = $inquiries !== null && $inquiries->isNotEmpty();

        return view('content.property_management.inquiries.show', compact(
            'listing', 'block', 'isOwner', 'property', 'unit', 'propertyName', 'user_type', 'society',
            'inquiries', 'memberInquiryIds', 'ownerContext',
            'reservedPlan', 'isSoldOut', 'canCancelReservation', 'canClearInquiries'
        ));
    }

    /**
     * Resolve the ownerable (property/unit) + its society + the login user's
     * owned societies, needed to link/create a member as the current owner.
     */
    private function ownerContextForListing(?PropertyListing $listing): array
    {
        $listable = $listing?->listable;
        $ownerableType = '';
        $ownerableId = null;
        $propertySocietyId = null;

        if ($listable) {
            $ownerableType = $listable->getMorphClass();
            $ownerableId = $listable->id;

            if ($listable instanceof Property) {
                $propertySocietyId = $listable->block?->society_id;
            } elseif ($listable instanceof PropertyUnit) {
                $propertySocietyId = $listable->floor?->property?->block?->society_id;
            }
        }

        $login_user = Auth::user();
        $scope = SocietyAccessResolver::resolver($login_user);
        $ownedSocietyIds = $scope['ownedSocietyIds'] ?? collect();
        $memberSocieties = $ownedSocietyIds->count()
          ? Society::whereIn('id', $ownedSocietyIds)->get(['id', 'name'])
          : collect();

        return [
            'ownerableType' => $ownerableType,
            'ownerableId' => $ownerableId,
            'propertySocietyId' => $propertySocietyId,
            'memberSocieties' => $memberSocieties,
        ];
    }

    public function dismiss(PropertyInquiry $inquiry)
    {
        try {
            $inquiry->delete();

            return redirect()->back()->with('success', 'Inquiry deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function clearInquiries(PropertyListing $listing)
    {
        try {
            $listing->inquiries()->delete();

            return redirect()->back()->with('success', 'All inquiries for this listing have been cleared.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Owner of the society for the property that the listing belongs to.
     * Searching only happens across societies owned by this owner.
     */
    private function societyOwnerIdForListing(?PropertyListing $listing): ?int
    {
        if (! $listing) {
            return null;
        }

        $listable = $listing->listable;
        if (! $listable) {
            return null;
        }

        if ($listable instanceof Property) {
            $society = $listable->block?->society;
        } elseif ($listable instanceof PropertyUnit) {
            $society = $listable->floor?->property?->block?->society;
        } else {
            return null;
        }

        return $society?->owner_id;
    }

    /**
     * Returns a map of inquiry ID => existing member User for inquiries whose
     * visitor already exists as a "Society Member" user across the societies
     * owned by the property's society owner. Matches by name, phone number and
     * e-mail (when present).
     *
     * @return array<int, User>
     */
    private function memberInquiryIdMap(?PropertyListing $listing, $inquiries): array
    {
        if (! $listing || $inquiries === null || $inquiries->count() === 0) {
            return [];
        }

        $ownerId = $this->societyOwnerIdForListing($listing);
        if (! $ownerId) {
            return [];
        }

        $societyIds = Society::where('owner_id', $ownerId)->pluck('id');

        // Only users that carry the "Society Member" role and are linked to one
        // of the societies owned by the property's society owner count as
        // "already a society member".
        $members = User::with('attachment')
            ->whereHas('roles', function ($q) {
                $q->where('name', 'Society Member');
            })
            ->whereHas('memberSocieties', function ($q) use ($societyIds) {
                $q->whereIn('member_societies.society_id', $societyIds);
            })->get();

        $memberNames = [];
        $memberEmails = [];
        $memberContacts = [];

        foreach ($members as $member) {
            $fullName = trim($member->first_name.' '.$member->last_name);
            if ($fullName !== '') {
                $memberNames[self::norm($fullName)][] = $member;
            }
            if (! empty($member->email)) {
                $memberEmails[self::norm($member->email)][] = $member;
            }
            if (! empty($member->contact)) {
                $memberContacts[self::phoneKey($member->contact)][] = $member;
            }
        }

        if (empty($memberNames) && empty($memberEmails) && empty($memberContacts)) {
            return [];
        }

        $matched = [];
        foreach ($inquiries as $inquiry) {
            $inquiryName = self::norm((string) $inquiry->name);
            $email = self::norm((string) $inquiry->email);
            $phone = self::phoneKey((string) $inquiry->contact_number);

            $member = null;

            // 1) Email match alone is enough — already a member.
            if ($email !== '' && isset($memberEmails[$email])) {
                $member = $memberEmails[$email][0];
            } else {
                // 2) Otherwise BOTH name AND phone must match the same member.
                if (
                    $inquiryName !== '' && isset($memberNames[$inquiryName]) &&
                    $phone !== '' && isset($memberContacts[$phone])
                ) {
                    $nameIds = array_map(fn ($m) => $m->id, $memberNames[$inquiryName]);
                    $phoneIds = array_map(fn ($m) => $m->id, $memberContacts[$phone]);

                    foreach ($phoneIds as $pid) {
                        if (in_array($pid, $nameIds, true)) {
                            $member = $memberContacts[$phone][0];
                            foreach ($memberContacts[$phone] as $m) {
                                if ($m->id === $pid) {
                                    $member = $m;
                                    break;
                                }
                            }
                            break;
                        }
                    }
                }
            }

            if ($member) {
                $matched[$inquiry->id] = $member;
            }
        }

        return $matched;
    }

    private static function norm(string $value): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($value)));
    }

    private static function phoneKey(string $value): string
    {
        return preg_replace('/[^0-9]/', '', $value);
    }
}
