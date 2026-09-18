<?php

namespace App\Http\Controllers;

use App\Helpers\SocietyAccessResolver;
use App\Mail\OwnerAssignedMail;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\PropertyOwnershipPaymentPlan;
use App\Models\PropertyUnit;
use App\Models\InstallmentTemplate;
use App\Models\Society;
use App\Models\User;
use App\Services\FileServices;
use App\Support\PaymentPlanPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class PropertyOwnerController extends Controller
{
    protected $compressor;

    public function __construct(FileServices $compressor)
    {
        $this->compressor = $compressor;
    }

    /**
     * Get societies accessible by current user (owner or assigned role).
     */
    public function societies()
    {
        $access = SocietyAccessResolver::resolver(Auth::user());

        return $access['ownedSocietyIds'] ?? collect();
    }

    /**
     * Lookup existing member by email or contact.
     */
    public function lookup(Request $request)
    {
        $term = trim((string) $request->query('term', ''));
        if (empty($term)) {
            return response()->json(['found' => false]);
        }

        $user = $this->findMemberByTerm($term);
        if (! $user) {
            return response()->json(['found' => false]);
        }

        $societyId = $request->integer('society') ?: null;

        // Only treat the user as "already a society member" when they carry the
        // "Society Member" role and belong to a society owned by the society
        // owner of the property's block's society (property -> block -> society).
        $ownerId = $societyId ? Society::whereKey($societyId)->value('owner_id') : null;
        $ownedSocietyIds = $ownerId ? Society::where('owner_id', $ownerId)->pluck('id') : collect();
        $alreadyMember = $ownedSocietyIds->isNotEmpty()
            && $user->hasRole('Society Member')
            && $user->memberSocieties()
                ->whereIn('societies.id', $ownedSocietyIds)
                ->exists();

        return response()->json([
            'found' => true,
            'already_member' => $alreadyMember,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'contact' => $user->contact,
            ],
        ]);
    }

    /**
     * Assign owner or create new member and assign as owner.
     */
    public function store(Request $request)
    {
        // Resolve existing member if needed
        if (! $request->filled('existing_user_id') && $request->filled('fetch_term')) {
            $userId = $this->findMemberByTerm($request->input('fetch_term'))?->id;
            $request->merge(['existing_user_id' => $userId]);
        }

        $validated = $this->validateOwnerRequest($request);
        if (! $validated) {
            return redirect()->back()
                ->withInput()
                ->withErrors(Validator::make($request->all(), $this->rules())->errors())
                ->with('error', 'Please check the form and try again.');
        }

        $user = null;
        $ownerable = null;
        $plainPassword = $request->filled('existing_user_id') ? null : $request->password;

        // A property that already has an active owner is sold out and must be
        // unlinked before a member can be linked (or a new member created).
        $ownerable = $request->ownerable_type === PropertyUnit::class
            ? PropertyUnit::find($request->ownerable_id)
            : Property::find($request->ownerable_id);

        if (
            $ownerable
            && $ownerable->ownerships()->whereNull('end_date')->exists()
        ) {
            return redirect()->back()->with('error', 'This property is sold out. Unlink the current owner before linking another member.');
        }

        try {
            DB::transaction(function () use ($request, &$user, &$ownerable) {
                // Create or get member
                $user = $this->resolveMember($request);

                // Get ownerable
                $ownerable = $request->ownerable_type === PropertyUnit::class
                  ? PropertyUnit::findOrFail($request->ownerable_id)
                  : Property::findOrFail($request->ownerable_id);

                // Create the ownership record
                $ownership = PropertyOwner::create([
                    'property_ownerable_type' => $ownerable->getMorphClass(),
                    'property_ownerable_id' => $ownerable->id,
                    'owner_id' => $user->id,
                    'start_date' => now()->toDateString(),
                    'end_date' => null,
                ]);

                // Set owner
                $ownerable->owner_id = $user->id;
                $ownerable->save();

                // Snapshot the committed payment/installment plan (does not
                // mutate or delete the seller's plan).
                $this->snapshotPaymentPlan($ownerable, $ownership, $request);

                // Close the listing (ownership means it is no longer for sale)
                // instead of deleting it, so inquiries/history are preserved.
                $ownerable->listing()?->update(['closed' => true]);
            });

            // Send notification email
            $this->notifyOwner($user, $ownerable, $plainPassword);

            return redirect()->back()->with('success', 'Member linked as owner with payment plan successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Unlink the current owner: end the open ownership, keep the committed-plan
     * snapshot + payment ledger (history) and relist the property/unit for
     * sale. The seller's source plan is untouched, so it is exactly what gets
     * re-used after re-listing.
     */
    public function unlinkOwner(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ownerable_type' => 'required|in:'.Property::class.','.PropertyUnit::class,
            'ownerable_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', 'Invalid ownerable reference.');
        }

        try {
            DB::transaction(function () use ($request) {
                $ownerable = $request->ownerable_type === PropertyUnit::class
                    ? PropertyUnit::findOrFail($request->ownerable_id)
                    : Property::findOrFail($request->ownerable_id);

                // End the current ownership. The snapshot + ledger stay intact
                // so payment history is preserved for the society owner.
                $ownership = $ownerable->ownerships()->whereNull('end_date')->first();
                if ($ownership) {
                    $ownership->update(['end_date' => now()->toDateString()]);
                }

                // Back to an owner-less, for-sale state.
                $ownerable->owner_id = null;
                $ownerable->save();
                $ownerable->listing()?->update(['closed' => false]);
            });

            return redirect()->back()->with('success', 'Owner unlinked. The listing is back on the market.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Print the whole committed payment plan for a member after ownership
     * assignment. Reads ONLY from the immutable ownership snapshot, so the
     * printed contract exactly matches what the owner committed.
     */
    public function printOwnerPlan(Request $request, PropertyOwnershipPaymentPlan $snapshot)
    {
        $ownerable = $snapshot->propertyOwnerable;
        abort_unless($ownerable, 404);

        $mode = 'brief';

        $data = PaymentPlanPresenter::buildFromSnapshot($snapshot);

        $owner = $snapshot->propertyOwner?->owner;
        $propertyOwner = $snapshot->propertyOwner;

        $user = auth()->user();
        $fullName = trim(($user?->first_name ?? '').' '.($user?->last_name ?? ''));
        $printedByName = $fullName !== '' ? $fullName : null;
        $printedByRole = $user?->getRoleNames()->join(', ') ?: null;

        return view('components.property.owner-payment-plan-print', compact(
            'data', 'owner', 'propertyOwner', 'mode', 'printedByName', 'printedByRole'
        ));
    }

    /**
     * Update the committed charge values on an ownership snapshot. Only the
     * snapshot row is touched; the seller's source plan stays untouched.
     */
    public function updateOwnershipPlan(Request $request, PropertyOwnershipPaymentPlan $snapshot)
    {
        if ($snapshot->paymentTransactions()->exists()) {
            return redirect()->back()->with('error', 'This plan already has recorded payments. Finish or unlink the ownership before editing.');
        }

        $chargeKeys = [
            'booking_fee',
            'development_fee',
            'down_payment',
            'transfer_fee',
            'confirmation_fee',
            'possession_fee',
        ];
        $surplusKeys = ['development_fee', 'transfer_fee', 'possession_fee'];
        $premiumKeys = ['corner_premium', 'park_facing_premium', 'main_boulevard_premium'];

        $values = [];
        foreach ($chargeKeys as $key) {
            $values[$key] = $this->nullableNumber($request->input($key));
            $values[$key.'_in_per'] = $request->boolean($key.'_in_per');
            if (in_array($key, $surplusKeys)) {
                $values[$key.'_surplus'] = $request->boolean($key.'_surplus');
            }
        }
        foreach ($premiumKeys as $key) {
            $values[$key] = $this->nullableNumber($request->input($key));
            $values[$key.'_in_per'] = true;
        }

        $values['listed_price'] = $this->nullableNumber($request->input('listed_price'));
        $values['price_unit'] = $request->input('price_unit') ?: 'PKR';

        try {
            DB::transaction(function () use ($snapshot, $values) {
                $snapshot->update($values);
                $snapshot->update(['final_price' => $this->ownerFinalPrice($snapshot)]);
                app(\App\Support\PaymentLedgerService::class)->rebuildFromSnapshot($snapshot);
            });

            return redirect()->back()->with('success', 'Committed payment plan updated.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Attach a single installment plan (from an installment template, or a
     * plan already attached to the ownerable's payment plan) to an ownership
     * snapshot that does not have an installment plan yet.
     */
    public function addOwnershipInstallment(Request $request, PropertyOwnershipPaymentPlan $snapshot)
    {
        if ($snapshot->paymentTransactions()->exists()) {
            return redirect()->back()->with('error', 'This plan already has recorded payments. Finish or unlink the ownership before editing.');
        }

        $source = (string) $request->input('installment_source', '');
        [$type, $id] = array_pad(explode(':', $source, 2), 2, null);

        if ($type === 'template' && $id) {
            $template = InstallmentTemplate::find($id);
            if (! $template) {
                return redirect()->back()->with('error', 'Please choose an installment plan.');
            }

            $values = [
                'with_installment' => true,
                'name' => $template->name,
                'number_of_installments' => $this->nullableNumber($template->number_of_installments),
                'installment_frequency' => $template->installment_frequency,
                'additional_installment_amount' => $this->nullableNumber($template->additional_installment_amount),
                'additional_installment_amount_in_per' => (bool) $template->additional_installment_amount_in_per,
                'balloon_payment' => $this->nullableNumber($template->balloon_payment),
                'balloon_payment_in_per' => (bool) $template->balloon_payment_in_per,
                'balloon_payment_frequency' => $template->balloon_payment_frequency,
            ];
        } elseif ($type === 'attachment' && $id) {
            $plan = $snapshot->propertyOwnerable?->paymentPlan;
            $attachment = $plan?->installmentAttachments()->find($id);
            if (! $attachment) {
                return redirect()->back()->with('error', 'Please choose an installment plan.');
            }

            // Snapshot the attachment's own values with a fallback to its
            // linked template (mirrors the owner-assignment snapshot logic).
            $template = $attachment->installmentTemplate;
            $isLinked = $template !== null && ! is_null($attachment->installment_template_id);
            $attr = function ($key) use ($attachment, $template, $isLinked) {
                $primary = $isLinked ? $template : $attachment;
                $value = $primary->{$key};
                if ($value !== null && $value !== '') {
                    return $value;
                }

                $fallback = $isLinked ? $attachment : $template;

                return $fallback?->{$key};
            };

            $values = [
                'with_installment' => true,
                'name' => $attr('name') ?: 'Installment Plan',
                'number_of_installments' => $this->nullableNumber($attr('number_of_installments')),
                'installment_frequency' => $attr('installment_frequency'),
                'additional_installment_amount' => $this->nullableNumber($attr('additional_installment_amount')),
                'additional_installment_amount_in_per' => (bool) ($attr('additional_installment_amount_in_per') ?? false),
                'balloon_payment' => $this->nullableNumber($attr('balloon_payment')),
                'balloon_payment_in_per' => (bool) ($attr('balloon_payment_in_per') ?? false),
                'balloon_payment_frequency' => $attr('balloon_payment_frequency'),
            ];
        } else {
            return redirect()->back()->with('error', 'Please choose an installment plan.');
        }

        try {
            $snapshot->update($values);
            $snapshot->update(['final_price' => $this->ownerFinalPrice($snapshot)]);
            app(\App\Support\PaymentLedgerService::class)->rebuildFromSnapshot($snapshot);

            return redirect()->back()->with('success', 'Installment plan attached to the committed plan.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Update the single installment plan on an ownership snapshot.
     */
    public function updateOwnershipInstallment(Request $request, PropertyOwnershipPaymentPlan $snapshot)
    {
        if ($snapshot->paymentTransactions()->exists()) {
            return redirect()->back()->with('error', 'This plan already has recorded payments. Finish or unlink the ownership before editing.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'number_of_installments' => 'required|integer|min:1',
            'installment_frequency' => 'required|in:monthly,quarterly,biannually,annually',
            'additional_installment_amount' => 'nullable|numeric|min:0',
            'balloon_payment' => 'nullable|numeric|min:0',
            'balloon_payment_frequency' => 'nullable|in:quarterly,biannually,annually',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withInput()->with('error', 'Please check the installment form and try again.');
        }

        $frequency = $request->input('installment_frequency');
        $balloonFrequency = $request->input('balloon_payment_frequency') ?: null;
        if (! \App\Support\InstallmentPlanCalculator::balloonFrequencyValid($frequency, $balloonFrequency)) {
            return redirect()->back()->with('error', 'Balloon frequency must be slower than the installment frequency (e.g. quarterly installments → biannual/annual balloons).');
        }

        try {
            $snapshot->update([
                'with_installment' => true,
                'name' => $request->input('name') ?: 'Installment Plan',
                'number_of_installments' => (int) $request->input('number_of_installments'),
                'installment_frequency' => $frequency,
                'additional_installment_amount' => $this->nullableNumber($request->input('additional_installment_amount')),
                'additional_installment_amount_in_per' => $request->boolean('additional_installment_amount_in_per'),
                'balloon_payment' => $this->nullableNumber($request->input('balloon_payment')),
                'balloon_payment_in_per' => $request->boolean('balloon_payment_in_per'),
                'balloon_payment_frequency' => $balloonFrequency,
            ]);

            $snapshot->update(['final_price' => $this->ownerFinalPrice($snapshot)]);
            app(\App\Support\PaymentLedgerService::class)->rebuildFromSnapshot($snapshot);

            return redirect()->back()->with('success', 'Installment plan updated.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the installment plan from an ownership snapshot, resetting all
     * installment fields to null/defaults.
     */
    public function removeInstallment(PropertyOwnershipPaymentPlan $snapshot)
    {
        if ($snapshot->paymentTransactions()->exists()) {
            return redirect()->back()->with('error', 'This plan already has recorded payments. Finish or unlink the ownership before editing.');
        }

        try {
            DB::transaction(function () use ($snapshot) {
                $snapshot->update([
                    'with_installment' => false,
                    'name' => null,
                    'number_of_installments' => null,
                    'installment_frequency' => null,
                    'additional_installment_amount' => null,
                    'additional_installment_amount_in_per' => false,
                    'balloon_payment' => null,
                    'balloon_payment_in_per' => false,
                    'balloon_payment_frequency' => null,
                ]);
                $snapshot->update(['final_price' => $this->ownerFinalPrice($snapshot)]);
                app(\App\Support\PaymentLedgerService::class)->rebuildFromSnapshot($snapshot);
            });

            return redirect()->back()->with('success', 'Installment plan removed.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Validation rules for owner assignment.
     */
    private function rules(): array
    {
        return [
            'ownerable_type' => 'required|in:'.Property::class.','.PropertyUnit::class,
            'ownerable_id' => 'required|integer',
            'existing_user_id' => 'nullable|integer|exists:users,id',
            'society' => 'nullable|exists:societies,id',
            'fetch_term' => 'nullable|string|max:255',

            'first_name' => 'required_without:existing_user_id|string|max:255|min:2',
            'last_name' => 'nullable|string|max:255',
            'email' => [
                'required_without:existing_user_id',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => 'required_without:existing_user_id|string|min:8',
            'contact' => 'required_without:existing_user_id|string|max:20',
            'cnic_passport' => [
                'required_without:existing_user_id',
                'string',
                'max:50',
                Rule::unique('users', 'cnic_passport'),
            ],
            'country' => 'required_without:existing_user_id|string|max:50',
            'gender' => 'required_without:existing_user_id|in:male,female,other',
            'permanent_address' => 'required_without:existing_user_id|string|max:255',
            'profession' => 'nullable|string|max:100',
            'dob' => 'nullable|date',
            'picture' => 'nullable|image|max:5120',
            'payment_plan_id' => 'nullable|exists:property_payment_plans,id',
            'with_installment' => 'nullable|boolean',
            'installment_attachment_id' => 'nullable|integer',
        ];
    }

    /**
     * Validate owner request.
     */
    private function validateOwnerRequest(Request $request): bool
    {
        $validator = Validator::make($request->all(), $this->rules());

        return ! $validator->fails();
    }

    /**
     * Resolve member (get existing or create new).
     */
    private function resolveMember(Request $request): User
    {
        if ($request->filled('existing_user_id')) {
            $user = User::findOrFail($request->existing_user_id);
            $this->syncMemberSociety($user, $request);

            return $user;
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'contact' => $request->contact,
            'cnic_passport' => $request->cnic_passport,
            'country' => $request->country,
            'gender' => $request->gender,
            'permanent_address' => $request->permanent_address,
            'profession' => $request->profession,
            'dob' => $request->dob,
        ]);

        // Assign role
        $memberRole = Role::where('name', 'Society Member')->first();
        if ($memberRole) {
            $user->assignRole($memberRole);
        }

        // Handle profile picture
        if ($request->hasFile('picture')) {
            app(FileServices::class)->compressAndStore($request->file('picture'), $user, true, true);
        }

        // Sync society
        $this->syncMemberSociety($user, $request);

        return $user;
    }

    /**
     * Sync member to society (only if user has access to society).
     */
    private function syncMemberSociety(User $user, Request $request): void
    {
        if (! $request->filled('society')) {
            return;
        }

        $access = SocietyAccessResolver::resolver(Auth::user());
        $societyId = $request->integer('society');

        if ($access['ownedSocietyIds']?->contains($societyId)) {
            $user->memberSocieties()->syncWithoutDetaching([$societyId]);
        }
    }

    /**
     * Snapshot the committed payment + installment plan onto the ownership.
     * The seller's source plan and installment attachments are left untouched,
     * so un-assigning an owner restores the for-sale plan exactly as it was.
     */
    private function snapshotPaymentPlan($ownerable, PropertyOwner $ownership, Request $request): void
    {
        $plan = $ownerable->paymentPlan()->first();
        if (! $plan) {
            return;
        }

        $template = $plan->paymentTemplate;
        $isLinked = $template !== null && ! is_null($plan->payment_template_id);

        // When template-linked the template is the source of truth; otherwise
        // the plan row holds its own (custom) values. Blank values optionally
        // fall back to the other source.
        $attributes = function ($key) use ($plan, $template, $isLinked) {
            $primary = $isLinked ? $template : $plan;
            $value = $primary->{$key};
            if ($value !== null && $value !== '') {
                return $value;
            }

            $fallback = $isLinked ? $plan : $template;

            return $fallback?->{$key};
        };

        $chargeKeys = [
            'booking_fee',
            'development_fee',
            'down_payment',
            'transfer_fee',
            'confirmation_fee',
            'possession_fee',
        ];
        $premiumKeys = ['corner_premium', 'park_facing_premium', 'main_boulevard_premium'];
        $surplusKeys = ['development_fee', 'transfer_fee', 'possession_fee'];

        $values = array_fill_keys($chargeKeys, null);
        $values = array_merge($values, array_fill_keys(array_map(fn ($k) => $k.'_in_per', $chargeKeys), false));
        $values = array_merge($values, array_fill_keys(array_map(fn ($k) => $k.'_surplus', $surplusKeys), false));
        $values = array_merge($values, array_fill_keys($premiumKeys, null));
        $values = array_merge($values, array_fill_keys(array_map(fn ($k) => $k.'_in_per', $premiumKeys), true));

        foreach (array_merge($chargeKeys, $premiumKeys) as $key) {
            $values[$key] = $this->nullableNumber($attributes($key));
            $values[$key.'_in_per'] = (bool) ($attributes($key.'_in_per') ?? (in_array($key, $premiumKeys) ? true : false));
            if (in_array($key, $surplusKeys)) {
                $values[$key.'_surplus'] = (bool) ($attributes($key.'_surplus') ?? false);
            }
        }

        // Resolve the committed installment attachment (if any)
        $attachment = null;
        if ($request->boolean('with_installment') && $request->filled('installment_attachment_id')) {
            $attachment = $plan->installmentAttachments()->find($request->installment_attachment_id);
        }

        $installment = [];
        if ($attachment) {
            $installmentTemplate = $attachment->installmentTemplate;
            $installmentLinked = $installmentTemplate !== null && ! is_null($attachment->installment_template_id);

            // Template-linked → template is the source of truth; custom
            // (installment_template_id null) → the pivot row's own values.
            $attr = function ($key) use ($attachment, $installmentTemplate, $installmentLinked) {
                $primary = $installmentLinked ? $installmentTemplate : $attachment;
                $value = $primary->{$key};
                if ($value !== null && $value !== '') {
                    return $value;
                }

                $fallback = $installmentLinked ? $attachment : $installmentTemplate;

                return $fallback?->{$key};
            };

            $installment = [
                'with_installment' => true,
                'name' => $attr('name') ?: 'Installment Plan',
                'number_of_installments' => $this->nullableNumber($attr('number_of_installments')),
                'installment_frequency' => $attr('installment_frequency'),
                'additional_installment_amount' => $this->nullableNumber($attr('additional_installment_amount')),
                'additional_installment_amount_in_per' => (bool) ($attr('additional_installment_amount_in_per') ?? false),
                'balloon_payment' => $this->nullableNumber($attr('balloon_payment')),
                'balloon_payment_in_per' => (bool) ($attr('balloon_payment_in_per') ?? false),
                'balloon_payment_frequency' => $attr('balloon_payment_frequency'),
            ];
        }

        $listing = $ownerable->listing;
        $priceUnit = $attributes('listed_unit') ?? $listing?->unit ?? 'PKR';

        $snapshot = PropertyOwnershipPaymentPlan::create(array_merge([
            'property_ownerable_type' => $ownerable->getMorphClass(),
            'property_ownerable_id' => $ownerable->id,
            'property_owner_id' => $ownership->id,
            'listed_price' => $this->nullableNumber($attributes('listed_price') ?? $listing?->price),
            'price_unit' => $priceUnit,
            'status' => 'active',
            'with_installment' => false,
        ], $values, $installment));

        // final_price always matches the summary's "Final Amount":
        // Gross + Excluded + (Extra in case of Installment, when attached).
        $snapshot->update(['final_price' => $this->ownerFinalPrice($snapshot)]);

        // Build the collectible ledger (down payment/fees/installments/balloons)
        // so the committed plan is immediately ready for payments.
        app(\App\Support\PaymentLedgerService::class)->createLedgerFromSnapshot($snapshot);
    }

    /**
     * Final price as computed by the presenter (Gross + Excluded + Extra).
     * Matches the summary sidebar and print "Final Amount" exactly.
     */
    private function ownerFinalPrice(PropertyOwnershipPaymentPlan $snapshot): float
    {
        return (float) PaymentPlanPresenter::buildFromSnapshot($snapshot)['final_price'];
    }

    private function nullableNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * Send owner assignment notification.
     */
    private function notifyOwner(User $user, $ownerable, ?string $plainPassword): void
    {
        if (! $user->email) {
            return;
        }

        try {
            $plan = $ownerable->paymentPlan()->first();
            Mail::to($user->email)->send(new OwnerAssignedMail(
                $user,
                $this->getOwnerableLabel($ownerable),
                $plan?->paymentTemplate?->name ?? ($plan ? 'Custom Plan' : null),
                $plainPassword,
            ));
        } catch (\Exception) {
            // Mail failure should not break owner assignment
        }
    }

    /**
     * Find member by email or contact.
     */
    private function findMemberByTerm(?string $term): ?User
    {
        $term = trim((string) $term);
        if (empty($term)) {
            return null;
        }

        // Try exact email match
        if (filter_var($term, FILTER_VALIDATE_EMAIL)) {
            return User::where('email', $term)->first();
        }

        // Try exact contact match
        $user = User::where('contact', $term)->first();
        if ($user) {
            return $user;
        }

        // Try contact without formatting
        $digits = preg_replace('/\D+/', '', $term);
        if ($digits) {
            return User::whereRaw('REGEXP_REPLACE(contact, "[^0-9]", "") = ?', [$digits])->first();
        }

        return null;
    }

    /**
     * Get human-readable label for property or unit.
     */
    private function getOwnerableLabel($ownerable): string
    {
        if ($ownerable instanceof PropertyUnit) {
            $parent = $ownerable->floor?->property;
            $label = $ownerable->unit_name ?: 'Unit';
            if ($parent?->property_no) {
                $label .= ' (Plot #'.$parent->property_no.')';
            }

            return $label;
        }

        $label = $ownerable->name ?: 'Property';
        if ($ownerable->property_no) {
            $label .= ' (Plot #'.$ownerable->property_no.')';
        }

        return $label;
    }
}
