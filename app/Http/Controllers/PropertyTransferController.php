<?php

namespace App\Http\Controllers;

use App\Helpers\SocietyAccessResolver;
use App\Models\Property;
use App\Models\PropertyTransfer;
use App\Models\PropertyUnit;
use App\Models\Society;
use App\Models\User;
use App\Notifications\PropertyTransferNotification;
use App\Services\Property\PropertyTransferService;
use App\Support\MemberLookup;
use App\Support\PaymentPlanPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PropertyTransferController extends Controller
{
    public function __construct(private PropertyTransferService $transfers) {}

    /**
     * The society owner's approval queue: open transfers first, decided ones
     * kept below as the paper trail.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $societyIds = SocietyAccessResolver::resolver($user)['ownedSocietyIds'] ?? [];
        $search = trim((string) $request->input('q', ''));

        $transfers = PropertyTransfer::with([
            'propertyOwnerable',
            'requestedBy',
            'recipient',
            'society',
            'fromOwnership.owner',
            'fromPlan',
            'newPlan',
            'actedBy',
        ])
            ->whereIn('society_id', $societyIds)
            ->when($search !== '', function (Builder $query) use ($search) {
                $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

                $person = fn (Builder $person) => $person->where('first_name', 'like', $needle)
                    ->orWhere('last_name', 'like', $needle)
                    ->orWhere('email', 'like', $needle);

                $query->where(function (Builder $query) use ($needle, $person) {
                    $query->whereHasMorph('propertyOwnerable', [Property::class], function (Builder $property) use ($needle) {
                        $property->where(function (Builder $property) use ($needle) {
                            $property->where('name', 'like', $needle)
                                ->orWhere('property_no', 'like', $needle);
                        });
                    })
                        ->orWhereHasMorph('propertyOwnerable', [PropertyUnit::class], fn (Builder $unit) => $unit
                            ->where('unit_name', 'like', $needle))
                        ->orWhereHas('fromOwnership.owner', $person)
                        ->orWhereHas('recipient', $person);
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('content.property_management.transfers.index', compact('transfers'));
    }

    /**
     * Member submits the transfer with the society fee frozen onto it. The
     * recipient is either an existing society member picked by lookup or a
     * brand-new member created on the spot (only the society transfer fee
     * applies to the incoming ownership).
     */
    public function store(Request $request)
    {
        $wantsJson = $request->expectsJson();

        // A new member must never inherit an id resolved from a stale lookup.
        if ($request->input('mode') === 'return_to_society') {
            $request->merge(['existing_user_id' => null, 'recipient_user_id' => null, 'fetch_term' => null]);
            $request->request->remove('first_name');
            $request->request->remove('last_name');
            $request->request->remove('email');
            $request->request->remove('password');
            $request->request->remove('contact');
            $request->request->remove('cnic_passport');
            $request->request->remove('country');
            $request->request->remove('gender');
            $request->request->remove('permanent_address');
            $request->request->remove('profession');
            $request->request->remove('dob');
            $request->request->remove('picture');
        } elseif ($request->input('mode') === 'new') {
            $request->merge(['existing_user_id' => null, 'recipient_user_id' => null, 'fetch_term' => null]);
        } elseif (
            ! $request->filled('existing_user_id')
            && ! $request->filled('recipient_user_id')
            && $request->filled('fetch_term')
        ) {
            $found = MemberLookup::find($request->input('fetch_term'));
            if ($found) {
                $request->merge([
                    'recipient_user_id' => $found->id,
                    'existing_user_id' => $found->id,
                ]);
            }
        }

        $validator = Validator::make($request->all(), [
            'ownerable_type' => ['required', 'in:'.Property::class.','.PropertyUnit::class],
            'ownerable_id' => ['required', 'integer'],
            'mode' => ['nullable', 'in:existing,new,return_to_society'],
            'existing_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'recipient_user_id' => [
                'exclude_if:mode,new',
                'exclude_if:mode,return_to_society',
                'nullable',
                'integer',
                'exists:users,id',
                'required_without_all:existing_user_id,fetch_term',
            ],
            'fetch_term' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],

            'first_name' => ['required_if:mode,new', 'nullable', 'string', 'max:255', 'min:2'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required_if:mode,new', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required_if:mode,new', 'nullable', 'string', 'min:8'],
            'contact' => ['required_if:mode,new', 'nullable', 'string', 'max:20'],
            'cnic_passport' => [
                'required_if:mode,new',
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'cnic_passport'),
            ],
            'country' => ['required_if:mode,new', 'nullable', 'string', 'max:50'],
            'gender' => ['required_if:mode,new', 'nullable', 'in:male,female,other'],
            'permanent_address' => ['required_if:mode,new', 'nullable', 'string', 'max:255'],
            'profession' => ['nullable', 'string', 'max:100'],
            'dob' => ['nullable', 'date'],
            'is_filer' => ['nullable', 'boolean'],
            'picture' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($validator->fails()) {
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please check the form and try again.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            return redirect()->back()
                ->withInput()
                ->withErrors($validator->errors())
                ->with('error', 'Please check the form and try again.');
        }

        $ownerable = $request->ownerable_type === PropertyUnit::class
            ? PropertyUnit::find($request->ownerable_id)
            : Property::find($request->ownerable_id);

        if (! $ownerable) {
            return $this->respond($request, false, 'The property/unit could not be found.');
        }

        $eligibility = $this->transfers->eligibility($ownerable);

        if (! $eligibility['can_transfer']) {
            return $this->respond($request, false, $this->transfers->eligibilityMessage($eligibility['reasons']));
        }

        $societyId = $eligibility['society_id'];
        $currentOwner = $eligibility['activeOwner'];
        $user = $request->user();

        // The requester must belong to the society that is transferring.
        $society = $societyId ? Society::find($societyId) : null;
        $requesterInSociety = $society && (
            $user->id === $society->owner_id
            || $user->memberSocieties()->where('societies.id', $societyId)->exists()
        );
        if (! $requesterInSociety) {
            return $this->respond($request, false, 'You can only transfer properties in your own society.');
        }

        // Only the current owner or the society owner may hand the property over.
        $isStakeholder = ($currentOwner && $user->id === $currentOwner->owner_id)
            || $user->id === $society->owner_id
            || $user->hasRole('Super Admin');
        if (! $isStakeholder) {
            return $this->respond($request, false, 'Only the current owner or the society owner can initiate a transfer for this property.');
        }

        if ($request->input('mode') !== 'new' && $request->input('mode') !== 'return_to_society' && ! $request->filled('existing_user_id') && ! $request->filled('recipient_user_id')) {
            // try lookup by fetch_term as fallback
            if ($request->filled('fetch_term')) {
                $found = \App\Support\MemberLookup::find($request->input('fetch_term'));
                if ($found) {
                    $request->merge(['existing_user_id' => $found->id, 'recipient_user_id' => $found->id]);
                }
            }
        }
        if ($request->input('mode') !== 'new' && $request->input('mode') !== 'return_to_society' && ! $request->filled('existing_user_id') && ! $request->filled('recipient_user_id')) {
            return $this->respond($request, false, 'No member found with that email or contact.');
        }

        try {
            $transfer = DB::transaction(function () use ($request, $ownerable, $eligibility, $currentOwner, $society, $user) {
                $recipient = $this->transfers->resolveRecipient($request, $society);

                if ($request->input('mode') === 'return_to_society') {
                    // returning to society - no recipient
                } elseif (! $recipient) {
                    throw new \RuntimeException('No member found with that email or contact.');
                }

                // The recipient only has to exist in the users table: no society
                // condition decides who may receive the property.
                if ($currentOwner && $recipient && $recipient->id === $currentOwner->owner_id) {
                    throw new \RuntimeException('The selected member already owns this property.');
                }

                return $this->transfers->create(
                    $ownerable,
                    $eligibility['plan'],
                    $currentOwner,
                    $user,
                    $recipient,
                    $eligibility['quote']
                );
            });
        } catch (\RuntimeException $e) {
            return $this->respond($request, false, $e->getMessage());
        }

        if ($society->owner_id && $society->owner_id !== $user->id) {
            User::find($society->owner_id)?->notify(new PropertyTransferNotification($transfer, 'requested'));
        }

        return $this->respond(
            $request,
            true,
            'Transfer '.$transfer->reference_no.' submitted — awaiting the society owner\'s approval.',
            ['reference_no' => $transfer->reference_no]
        );
    }

    /**
     * Society owner approves: outgoing ownership ends, recipient becomes owner.
     */
    public function approve(Request $request, PropertyTransfer $transfer)
    {
        $this->authorizeSociety($request, $transfer);

        try {
            $this->transfers->approve($transfer, $request->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $transfer->refresh();
        $transfer->requestedBy?->notify(new PropertyTransferNotification($transfer, 'approved'));
        if ($transfer->recipient_user_id !== $transfer->requested_by_user_id) {
            $transfer->recipient?->notify(new PropertyTransferNotification($transfer, 'approved'));
        }

        return back()->with('success', 'Transfer '.$transfer->reference_no.' approved — ownership moved to the recipient.');
    }

    /**
     * Society owner rejects with optional remarks; ownership is untouched.
     */
    public function reject(Request $request, PropertyTransfer $transfer)
    {
        $this->authorizeSociety($request, $transfer);

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->transfers->reject($transfer, $request->user(), $validated['remarks'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $transfer->refresh();
        $transfer->requestedBy?->notify(new PropertyTransferNotification($transfer, 'rejected'));
        $transfer->recipient?->notify(new PropertyTransferNotification($transfer, 'rejected'));

        return back()->with('success', 'Transfer '.$transfer->reference_no.' rejected.');
    }

    /**
     * Printable copy of the transfer for either side.
     *
     * Renders the transfer slip: the parties, the frozen society fee, the
     * outgoing and incoming plans and the approval trail. Opened with
     * ?print=1 the slip prints itself, like the payment receipt does.
     */
    public function print(Request $request, PropertyTransfer $transfer)
    {
        $user = $request->user();
        $society = $transfer->society;

        // Printing the slip is its own permission: staff need
        // `property_transfer_print`. The two parties may always print their
        // own transfer (the slip is their paper trail).
        $allowed = $user->can('property_transfer_print')
            || $user->id === $transfer->requested_by_user_id
            || $user->id === $transfer->recipient_user_id;

        abort_unless($allowed, 403);

        $plan = $transfer->fromPlan;

        // The property/unit and the outgoing plan may be gone by now - the slip
        // then prints without those sections rather than failing.
        $planData = [];
        if ($plan) {
            try {
                $planData = PaymentPlanPresenter::buildFromSnapshot($plan);
            } catch (\Throwable $e) {
                $planData = [];
            }
        }

        return view('components.property.transfer-request-print', [
            'transfer' => $transfer,
            'ownerable' => $transfer->propertyOwnerable,
            'plan' => $plan,
            'newPlan' => $transfer->newPlan,
            'society' => $society,
            'fromOwner' => $transfer->fromOwnership?->owner,
            'recipient' => $transfer->recipient,
            'planData' => $planData,
            'autoPrint' => $request->boolean('print'),
        ]);
    }

    private function authorizeSociety(Request $request, PropertyTransfer $transfer): void
    {
        $societyIds = collect(SocietyAccessResolver::resolver($request->user())['ownedSocietyIds'] ?? []);

        abort_unless(
            $societyIds->contains($transfer->society_id),
            403,
            'You can only act on transfers from your own society.'
        );
    }

    private function respond(Request $request, bool $ok, string $message, array $extra = [])
    {
        if ($request->expectsJson()) {
            return response()->json($ok
                ? array_merge(['success' => true, 'message' => $message], $extra)
                : ['success' => false, 'message' => $message], $ok ? 200 : 422);
        }

        return redirect()->back()->with($ok ? 'success' : 'error', $message);
    }
}
