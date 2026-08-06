<?php

namespace App\Http\Controllers;

use App\Helpers\SocietyAccessResolver;
use App\Http\Controllers\frontend\concerns\ResolvesVisitor;
use App\Models\Property;
use App\Models\PropertyInquiry;
use App\Models\PropertyListing;
use App\Models\PropertyUnit;
use App\Models\Society;
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
                        Property::class => ['block.society'],
                        PropertyUnit::class => ['floor.property.block.society'],
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

                    return '<a href="'.e($url).'" title="'.e(ucwords($name)).'" class="badge bg-label-info text-truncate d-inline-block cursor-pointer">'
                      .e(ucwords($shortName)).'</a>';
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

        return view('content.property_management.inquiries.show', compact('listing', 'block', 'isOwner', 'property', 'unit', 'propertyName', 'user_type', 'society', 'inquiries'));
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
}
