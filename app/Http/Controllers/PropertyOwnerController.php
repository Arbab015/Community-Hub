<?php

namespace App\Http\Controllers;

use App\Helpers\SocietyAccessResolver;
use App\Mail\PropertyReservedMail;
use App\Models\InstallmentTemplate;
use App\Models\OwnershipCancellationReversal;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\PropertyReservedPaymentPlan;
use App\Models\PropertyUnit;
use App\Models\Society;
use App\Models\User;
use App\Services\FileServices;
use App\Support\PaymentLedgerService;
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
     * Lookup an existing member by email or contact.
     *
     * The check is global on purpose: any row of the `users` table counts as an
     * existing member. The society a person belongs to is not part of the test,
     * so a member of another society - or of none yet - can still be picked and
     * is attached to the society of the property afterwards.
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

        return response()->json([
            'found' => true,
            'existing' => true,
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
     * Reserve a property/unit for ownership by an existing member or a newly
     * created member. The member becomes the prospective owner and the current
     * payment plan is committed as the reservation snapshot. Responds with JSON
     * for the fast (AJAX) reservation modal, otherwise falls back to a redirect.
     */
    public function store(Request $request)
    {
        $wantsJson = $request->expectsJson();

        // A new member must never inherit a member resolved from a stale lookup.
        if ($request->input('mode') === 'new') {
            $request->merge(['existing_user_id' => null]);
        }

        // Resolve existing member from the lookup term only when the user chose
        // to link an existing member and no id was already resolved client-side.
        if ($request->input('mode') === 'existing' && ! $request->filled('existing_user_id') && $request->filled('fetch_term')) {
            $userId = $this->findMemberByTerm($request->input('fetch_term'))?->id;
            $request->merge(['existing_user_id' => $userId]);
        }

        $validator = Validator::make($request->all(), $this->rules());
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

        $user = null;
        $ownerable = null;
        $plainPassword = $request->filled('existing_user_id') ? null : $request->password;

        // A property/unit that already has an active ownership is taken and must
        // be cancelled before a new reservation can be made.
        $ownerable = $request->ownerable_type === PropertyUnit::class
          ? PropertyUnit::find($request->ownerable_id)
          : Property::find($request->ownerable_id);

        if (
            $ownerable
            && $ownerable->ownerships()->active()->exists()
        ) {
            $message = 'This property is already reserved/sold out. End the current ownership before reserving it again.';

            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return redirect()->back()->with('error', $message);
        }

        try {
            DB::transaction(function () use ($request, &$user, &$ownerable) {
                // Create or get member
                $user = $this->resolveMember($request);

                // Get ownerable
                $ownerable = $request->ownerable_type === PropertyUnit::class
                  ? PropertyUnit::findOrFail($request->ownerable_id)
                  : Property::findOrFail($request->ownerable_id);

                // Create the ownership (reservation) record
                $ownership = PropertyOwner::create([
                    'property_ownerable_type' => $ownerable->getMorphClass(),
                    'property_ownerable_id' => $ownerable->id,
                    'owner_id' => $user->id,
                    'started_at' => now()->toDateString(),
                    'status' => \App\Enums\OwnershipStatus::Active->value,
                ]);

                // Set owner
                $ownerable->owner_id = $user->id;
                $ownerable->save();

                // Snapshot the committed payment/installment plan (does not
                // mutate or delete the seller's plan).
                $this->snapshotPaymentPlan($ownerable, $ownership, $request);

                // Close the listing (a reserved/owned property is no longer for sale)
                // instead of deleting it, so inquiries/history are preserved.
                $ownerable->listing()?->update(['closed' => true]);
            });

            // Send reservation notification email
            $this->notifyReservation($user, $ownerable, $plainPassword);

            $message = 'Property reserved for ownership successfully.';

            if ($wantsJson) {
                return response()->json(['success' => true, 'message' => $message]);
            }

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Return everything the current owner paid against the committed plan.
     *
     * The cancellation modal uses this to prefill the refund figures: the charges
     * paid, the FBR tax paid, and the gross paid total. A fixed reduction is taken
     * as entered; a percentage reduction is calculated on the plan's FINAL PRICE
     * (commission_base). Reads the plan's PAID transactions only - reversed/returned
     * ones no longer count.
     */
    public function cancellationSummary(Request $request)
    {
        $ownerable = $request->input('ownerable_type') === PropertyUnit::class
          ? PropertyUnit::find($request->input('ownerable_id'))
          : Property::find($request->input('ownerable_id'));

        if (! $ownerable) {
            return response()->json(['ok' => false, 'message' => 'Property/unit not found.'], 404);
        }

        $ownership = $ownerable->ownerships()->active()->first();
        $snapshot = $ownership?->paymentPlan;

        // A society member may only read the settlement summary of their own
        // ownership.
        $visibility = app(\App\Services\Property\PaymentVisibilityService::class);
        if ($visibility->isRestricted($request->user())) {
            abort_unless($snapshot && $visibility->canViewPlan($snapshot, $request->user()), 403);
        }

        $paid = $snapshot
          ? $snapshot->paymentTransactions()->where('status', 'paid')->get()
          : collect();

        $amount = round((float) $paid->sum('charge_subtotal'), 2);
        $tax = round((float) $paid->sum('tax_amount'), 2);
        $paidTotal = round($amount + $tax, 2);
        $finalPrice = round((float) ($snapshot?->final_price ?? 0), 2);

        return response()->json([
            'ok' => true,
            'currency' => $snapshot?->price_unit ?? 'PKR',
            'return_amount' => $amount,
            'return_tax' => $tax,
            'return_total' => $paidTotal,      // gross paid (charges + tax)
            'payment_count' => $paid->count(),
            'commission_base' => $finalPrice,  // percentage reduction is applied on the plan final price
            'final_price' => $finalPrice,
            'has_payment' => $paid->count() > 0,
        ]);
    }

    /**
     * Cancel the ownership reservation WITH a settlement: return the money the
     * owner paid (charges + FBR tax) minus an optional commission / reduction.
     *
     * A fixed reduction is taken as entered; a percentage reduction is calculated
     * on the payment plan's final price. The net refund can therefore be negative
     * (e.g. nothing was paid but a reduction applies) - a negative figure means
     * the amount is to be received from the owner:
     *
     *   net refund = return_total - commission_amount
     *
     * Payments are NEVER deleted - each paid transaction is flipped to a
     * "returned" status so the whole trail stays auditable. A settlement record
     * is written and a printable return + commission receipt is produced.
     */
    public function cancelReservationWithSettlement(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ownerable_type' => 'required|in:'.Property::class.','.PropertyUnit::class,
            'ownerable_id' => 'required|integer',
            'commission_mode' => 'nullable|in:none,amount,percentage',
            'commission_value' => 'nullable|numeric|min:0|required_if:commission_mode,amount,percentage',
            'remarks' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', 'Please check the cancellation form.');
        }

        try {
            $reversal = DB::transaction(function () use ($request) {
                $ownerable = $request->input('ownerable_type') === PropertyUnit::class
                  ? PropertyUnit::findOrFail($request->input('ownerable_id'))
                  : Property::findOrFail($request->input('ownerable_id'));

                $ownership = $ownerable->ownerships()->active()->first();
                if (! $ownership) {
                    throw new \RuntimeException('No active ownership found for this property/unit.');
                }

                $snapshot = $ownership->paymentPlan;
                if (! $snapshot) {
                    throw new \RuntimeException('No committed payment plan found for this ownership.');
                }

                if ($snapshot->isTransfer()) {
                    throw new \RuntimeException('A transfer-fee plan cannot be cancelled - the ownership was already transferred.');
                }

                if ($snapshot->status === 'completed') {
                    throw new \RuntimeException('A completed ownership plan cannot be cancelled - use resale instead.');
                }

                if (in_array($snapshot->status, ['reversed', 'cancelled'], true)) {
                    throw new \RuntimeException('This ownership reservation is already cancelled.');
                }

                $paid = $snapshot->paymentTransactions()->where('status', 'paid')->get();

                // Gross amount the owner paid (charges + FBR tax).
                $returnAmount = round((float) $paid->sum('charge_subtotal'), 2);
                $returnTax = round((float) $paid->sum('tax_amount'), 2);
                $returnTotal = round($returnAmount + $returnTax, 2);

                // Commission / reduction (optional) - a percentage is applied on the
                // payment plan's FINAL PRICE.
                $mode = $request->input('commission_mode', 'none');
                $value = round((float) $request->input('commission_value', 0), 2);
                $base = round((float) ($snapshot->final_price ?? 0), 2);

                $commissionType = null;
                $commissionAmount = 0.0;

                if ($mode === 'amount' && $value > 0) {
                    $commissionType = 'amount';
                    $commissionAmount = $value;
                } elseif ($mode === 'percentage' && $value > 0) {
                    if ($value > 100) {
                        throw new \RuntimeException('A percentage reduction cannot be more than 100%.');
                    }
                    $commissionType = 'percentage';
                    $commissionAmount = round($base * $value / 100, 2);
                }

                // Net refund (paid total - reduction). It may be negative when the
                // reduction is more than what was paid (e.g. nothing was paid).
                $commissionAmount = round($commissionAmount, 2);
                $netRefund = round($returnTotal - $commissionAmount, 2);

                // Flip every paid transaction to "returned" and close its ledger
                // rows as "reversed" - nothing is ever deleted, only statused.
                $service = app(PaymentLedgerService::class);
                foreach ($paid as $txn) {
                    $service->reversePayment($txn, [
                        'status' => 'returned',
                        'ledger_status' => 'reversed',
                    ]);
                }

                $snapshot->update([
                    'status' => $paid->count() > 0 ? 'reversed' : 'cancelled',
                ]);

                $reversal = OwnershipCancellationReversal::create([
                    'plan_id' => $snapshot->id,
                    'status' => 'returned',
                    'type' => 'ownership_cancel_return',
                    'reason' => $commissionAmount > 0
                      ? 'Ownership reservation cancelled: the paid amount (charges + FBR tax) was settled after a reduction of '
                      .number_format($commissionAmount, 2).' '.($snapshot->price_unit ?? 'PKR').'. Net refund: '
                      .number_format($netRefund, 2).'.'
                      : 'Ownership reservation cancelled: the full paid amount (charges + FBR tax) was returned to the owner.',
                    'remarks' => $request->input('remarks'),
                    'reversed_at' => now(),
                    'reversed_by' => Auth::id(),
                    'return_amount' => $returnAmount,
                    'return_tax' => $returnTax,
                    'return_total' => $returnTotal,          // gross paid; net = return_total - commission_amount
                    'payment_count' => $paid->count(),
                    'currency' => $snapshot->price_unit ?? 'PKR',
                    'commission_type' => $commissionType,
                    'commission_value' => $value,
                    'commission_base' => $base,              // plan final price
                    'commission_amount' => $commissionAmount,
                ]);

                // End the ownership and relist the property/unit.
                $ownership->update([
                    'ended_at' => now()->toDateString(),
                    'status' => \App\Enums\OwnershipStatus::Cancelled->value,
                ]);
                $ownerable->owner_id = null;
                $ownerable->save();
                $ownerable->listing()?->update(['closed' => false]);

                return $reversal;
            });

            return redirect()->back()
                ->with('success', 'Ownership cancelled. Net amount settled and receipt generated.')
                ->with('print_document_url', route('property_owners.cancellation.receipt', [
                    'reversal' => $reversal,
                    'print' => 1,
                ]));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Printable return + commission receipt for an ownership cancellation.
     */
    public function cancellationReceipt(Request $request, OwnershipCancellationReversal $reversal)
    {
        $reversal->loadMissing(['plan.propertyOwner', 'plan.propertyOwnerable', 'reversedBy']);

        return view('components.property.ownership-cancellation-receipt', [
            'reversal' => $reversal,
            'plan' => $reversal->plan,
            'ownerable' => $reversal->plan?->propertyOwnerable,
            'autoPrint' => $request->boolean('print'),
        ]);
    }

    /**
     * Print the whole committed payment plan for a member after ownership
     * assignment. Reads ONLY from the immutable ownership snapshot, so the
     * printed contract exactly matches what the owner committed.
     */
    public function printOwnerPlan(Request $request, PropertyReservedPaymentPlan $snapshot)
    {
        $ownerable = $snapshot->propertyOwnerable;
        abort_unless($ownerable, 404);

        // A society member may only print the committed plan of their own
        // ownership.
        app(\App\Services\Property\PaymentVisibilityService::class)
            ->authorizePlan($snapshot, $request->user());

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
    public function updateOwnershipPlan(Request $request, PropertyReservedPaymentPlan $snapshot)
    {
        if ($snapshot->isTransfer()) {
            return redirect()->back()->with('error', 'A transfer-fee plan cannot be edited.');
        }

        if ($snapshot->hasLivePayments()) {
            return redirect()->back()->with('error', 'This plan already has recorded payments. Finish or cancel the reservation before editing.');
        }

        $chargeKeys = [
            'booking_fee',
            'development_fee',
            'down_payment',

            'confirmation_fee',
            'possession_fee',
        ];
        $surplusKeys = ['development_fee', 'possession_fee'];
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
    public function addOwnershipInstallment(Request $request, PropertyReservedPaymentPlan $snapshot)
    {
        if ($snapshot->isTransfer()) {
            return redirect()->back()->with('error', 'A transfer-fee plan has no installment schedule.');
        }

        if ($snapshot->hasLivePayments()) {
            return redirect()->back()->with('error', 'This plan already has recorded payments. Finish or cancel the reservation before editing.');
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
    public function updateOwnershipInstallment(Request $request, PropertyReservedPaymentPlan $snapshot)
    {
        if ($snapshot->isTransfer()) {
            return redirect()->back()->with('error', 'A transfer-fee plan has no installment schedule.');
        }

        if ($snapshot->hasLivePayments()) {
            return redirect()->back()->with('error', 'This plan already has recorded payments. Finish or cancel the reservation before editing.');
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
    public function removeInstallment(PropertyReservedPaymentPlan $snapshot)
    {
        if ($snapshot->isTransfer()) {
            return redirect()->back()->with('error', 'A transfer-fee plan has no installment schedule.');
        }

        if ($snapshot->hasLivePayments()) {
            return redirect()->back()->with('error', 'This plan already has recorded payments. Finish or cancel the reservation before editing.');
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
            'mode' => 'nullable|in:existing,new',
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
            'is_filer' => 'nullable|boolean',
            'picture' => 'nullable|image|max:5120',
            'payment_plan_id' => 'nullable|exists:property_payment_plans,id',
            'with_installment' => 'nullable|boolean',
            'installment_attachment_id' => 'nullable|integer',
        ];
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
            'is_filer' => (bool) $request->boolean('is_filer'),
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
     * Attach the member to the society of the property they now own.
     *
     * The lookup that found the member is global (any row of `users` counts) -
     * the society is only written afterwards, because the new owner belongs to
     * the society of the property they just received.
     */
    private function syncMemberSociety(User $user, Request $request): void
    {
        if (! $request->filled('society')) {
            return;
        }

        $societyId = $request->integer('society');

        if (Society::whereKey($societyId)->exists()) {
            $user->memberSocieties()->syncWithoutDetaching([$societyId]);
        }
    }

    /**
     * Snapshot the committed payment + installment plan onto the ownership.
     * The seller's source plan and installment attachments are left untouched,
     * so cancelling a reservation restores the for-sale plan exactly as it was.
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

            'confirmation_fee',
            'possession_fee',
        ];
        $premiumKeys = ['corner_premium', 'park_facing_premium', 'main_boulevard_premium'];
        $surplusKeys = ['development_fee', 'possession_fee'];

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

        $snapshot = PropertyReservedPaymentPlan::create(array_merge([
            'property_ownerable_type' => $ownerable->getMorphClass(),
            'property_ownerable_id' => $ownerable->id,
            'property_owner_id' => $ownership->id,
            'listed_price' => $this->nullableNumber($attributes('listed_price') ?? $listing?->price),
            'price_unit' => $priceUnit,
            'status' => 'active',
            'with_installment' => false,
        ], $values, $installment));

        // final_price always matches the summary's "Final Amount":
        // Gross + (Extra in case of Installment, when attached).
        $snapshot->update(['final_price' => $this->ownerFinalPrice($snapshot)]);

        // Build the collectible ledger (down payment/fees/installments/balloons)
        // so the committed plan is immediately ready for payments.
        app(\App\Support\PaymentLedgerService::class)->createLedgerFromSnapshot($snapshot);
    }

    /**
     * Final price as computed by the presenter (Gross + Extra).
     * Matches the summary sidebar and print "Final Amount" exactly.
     */
    private function ownerFinalPrice(PropertyReservedPaymentPlan $snapshot): float
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
     * Send the ownership-reservation notification email.
     */
    private function notifyReservation(User $user, $ownerable, ?string $plainPassword): void
    {
        if (! $user->email) {
            return;
        }

        try {
            $plan = $ownerable->paymentPlan()->first();
            Mail::to($user->email)->send(new PropertyReservedMail(
                $user,
                $this->getOwnerableLabel($ownerable),
                $plan?->paymentTemplate?->name ?? ($plan ? 'Custom Plan' : null),
                $plainPassword,
            ));
        } catch (\Exception) {
            // Mail failure should not break the reservation
        }
    }

    /**
     * Find member by email or contact.
     */
    private function findMemberByTerm(?string $term): ?User
    {
        return \App\Support\MemberLookup::find($term);
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
