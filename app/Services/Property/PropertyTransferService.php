<?php

namespace App\Services\Property;

use App\Enums\OwnershipStatus;
use App\Enums\PlanType;
use App\Helpers\GetArea;
use App\Models\PrepertyTransferFee;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\PropertyReservedPaymentPlan;
use App\Models\PropertyTransfer;
use App\Models\PropertyUnit;
use App\Models\Society;
use App\Models\User;
use App\Services\FileServices;
use App\Services\PropertyRangeService;
use App\Support\PaymentLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Member-initiated property transfers: eligibility (fully paid, no pending
 * transfer), the society fee quote frozen onto the transfer, and the single
 * approval transaction that ends the outgoing ownership, starts the incoming
 * one and creates the transfer-fee plan.
 */
class PropertyTransferService
{
    public function __construct(private PropertyRangeService $ranges) {}

    /**
     * The ownerable's pending transfer, if one is open.
     */
    public function pendingRequest(Property|PropertyUnit $ownerable): ?PropertyTransfer
    {
        return PropertyTransfer::query()
            ->whereMorphedTo('propertyOwnerable', $ownerable)
            ->where('status', 'pending')
            ->latest('id')
            ->first();
    }

    /**
     * Everything the view needs to decide whether the Transfer button shows:
     * facts only (permissions are checked by @can in the blade).
     */
    public function eligibility(Property|PropertyUnit $ownerable): array
    {
        $activeOwner = $ownerable->ownerships()->active()->latest('id')->first();
        $plan = $ownerable->reservedPaymentPlans()
            ->whereHas('propertyOwner', fn ($q) => $q->active())
            ->latest('id')
            ->first();
        $pending = $this->pendingRequest($ownerable);

        $reasons = [];
        if (! $activeOwner) {
            $reasons[] = 'no_active_ownership';
        }
        if (! $plan) {
            $reasons[] = 'no_plan';
        } elseif ($plan->status !== 'completed') {
            $reasons[] = 'payments_incomplete';
        }
        if ($pending) {
            $reasons[] = 'pending_exists';
        }

        return [
            'society_id' => $this->societyIdFor($ownerable),
            'activeOwner' => $activeOwner,
            'owner' => $activeOwner?->owner ?? $ownerable->owner,
            'plan' => $plan,
            'pending' => $pending,
            'quote' => $this->quote($ownerable, $plan),
            'reasons' => $reasons,
            'can_transfer' => $reasons === [],
        ];
    }

    /**
     * Society transfer fee for a property/unit: the fee row whose size range
     * contains the area, the base it applies to, and the computed amount —
     * all as they would be frozen onto a new transfer.
     */
    public function quote(Property|PropertyUnit $ownerable, ?PropertyReservedPaymentPlan $plan = null): array
    {
        $societyId = $this->societyIdFor($ownerable);

        $marla = $this->ranges->areaInMarla($ownerable);
        $squareYards = GetArea::calculate($ownerable->dimensions) / 9;

        $feeRow = $societyId
            ? PrepertyTransferFee::query()->where('society_id', $societyId)->get()
                ->first(function ($fee) use ($marla, $squareYards) {
                    $area = $fee->size_unit === 'square_yard' ? $squareYards : $marla;

                    return ($fee->min_size === null || $area >= (float) $fee->min_size)
                        && ($fee->max_size === null || $area <= (float) $fee->max_size);
                })
            : null;

        $base = (float) ($plan?->listed_price ?? $ownerable->listing?->price ?? 0);
        $currency = $plan?->price_unit ?? $ownerable->listing?->unit ?? 'PKR';

        $percent = $feeRow && $feeRow->is_percentage ? (float) $feeRow->fee : 0.0;
        $amount = $feeRow
            ? ($feeRow->is_percentage ? round($base * $percent / 100, 2) : (float) $feeRow->fee)
            : 0.0;

        return [
            'society_id' => $societyId,
            'fee_id' => $feeRow?->id,
            'is_percentage' => (bool) ($feeRow?->is_percentage),
            'percent' => $percent,
            'base' => $base,
            'amount' => $amount,
            'currency' => $currency,
            'matched' => (bool) $feeRow,
        ];
    }

    /**
     * Open the transfer with the fee frozen at today's configuration.
     */
    public function create(
        Property|PropertyUnit $ownerable,
        PropertyReservedPaymentPlan $plan,
        PropertyOwner $currentOwner,
        User $requester,
        ?User $recipient,
        array $quote
    ): PropertyTransfer {
        return DB::transaction(function () use ($ownerable, $plan, $currentOwner, $requester, $recipient, $quote) {
            if ($this->pendingRequest($ownerable)) {
                throw new \RuntimeException('A transfer is already pending for this property.');
            }

            // When the fee is a percentage, transfer_fee freezes the RATE (the
            // amount is stored in fee_amount), so later fee-config edits can
            // never rewrite an already-submitted transfer.
            return PropertyTransfer::create([
                'reference_no' => $this->referenceNumber(),
                'property_ownerable_type' => $ownerable->getMorphClass(),
                'property_ownerable_id' => $ownerable->id,
                'from_ownership_id' => $currentOwner->id,
                'from_plan_id' => $plan->id,
                'requested_by_user_id' => $requester->id,
                'recipient_user_id' => $recipient?->id,
                'society_id' => $quote['society_id'],
                'transfer_fee_id' => $quote['fee_id'],
                'transfer_fee' => $quote['is_percentage'] ? $quote['percent'] : $quote['amount'],
                'transfer_fee_in_per' => $quote['is_percentage'],
                'fee_base' => $quote['base'],
                'fee_amount' => $quote['amount'],
                'currency' => $quote['currency'],
                'status' => 'pending',
                'pending_marker' => (string) Str::uuid(),
            ]);
        });
    }

    /**
     * End the outgoing ownership and start the recipient's, in one locked
     * transaction. Every re-validation happens under the lock so a concurrent
     * cancellation/transfer cannot slip past.
     */
    public function approve(PropertyTransfer $transfer, User $actor): void
    {
        DB::transaction(function () use ($transfer, $actor) {
            $fresh = PropertyTransfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($fresh->status !== 'pending') {
                throw new \RuntimeException('This transfer has already been acted on.');
            }

            $ownerable = $fresh->propertyOwnerable()->first();
            if (! $ownerable) {
                throw new \RuntimeException('The property/unit for this transfer no longer exists.');
            }

            $currentOwner = PropertyOwner::query()
                ->whereKey($fresh->from_ownership_id)
                ->lockForUpdate()
                ->first();

            if (! $currentOwner || $currentOwner->ended_at !== null) {
                throw new \RuntimeException('The ownership for this property has changed — the transfer is no longer valid.');
            }

            $plan = $fresh->fromPlan;
            if (! $plan || $plan->status !== 'completed') {
                throw new \RuntimeException('The payment plan for this transfer is no longer fully paid.');
            }

            $recipient = User::find($fresh->recipient_user_id);
            if (! $recipient) {
                if (! $fresh->recipient_user_id) {
                    // Return to society - no recipient, owner becomes null
                } else {
                    throw new \RuntimeException('The recipient member no longer exists.');
                }
            }
            if ($recipient && $recipient->id === $currentOwner->owner_id) {
                throw new \RuntimeException('The recipient already owns this property.');
            }

            $currentOwner->ended_at = now();
            $currentOwner->status = OwnershipStatus::Transferred;
            $currentOwner->save();

            if (! $recipient) {
                // Return to society - no new owner
                $ownerable->owner_id = null;
                $ownerable->save();

                $fresh->update([
                    'status' => 'approved',
                    'acted_by_user_id' => $actor->id,
                    'acted_at' => now(),
                    'pending_marker' => null,
                ]);
                return;
            }

            $newOwner = PropertyOwner::create([
                'property_ownerable_type' => $ownerable->getMorphClass(),
                'property_ownerable_id' => $ownerable->id,
                'owner_id' => $recipient->id,
                'started_at' => now()->toDateString(),
                'status' => OwnershipStatus::Active,
                'transfer_id' => $fresh->id,
            ]);

            $ownerable->owner_id = $recipient->id;
            $ownerable->save();

            // Keep the recipient reachable in the society (mirrors the
            // reservation flow's membership sync).
            if ($fresh->society_id && $recipient->id !== $fresh->society?->owner_id) {
                $recipient->memberSocieties()->syncWithoutDetaching([$fresh->society_id]);
            }

            $newPlan = $this->createTransferPlan($fresh, $plan, $newOwner);

            $fresh->update([
                'status' => 'approved',
                'acted_by_user_id' => $actor->id,
                'acted_at' => now(),
                'pending_marker' => null,
                'new_plan_id' => $newPlan->id,
            ]);
        });
    }

    /**
     * The transfer-fee plan created on approval. It mirrors the source plan's
     * ownerable + currency, carries only the frozen society transfer fee, and
     * is exempt from FBR tax. A zero-fee transfer is created already completed
     * with no ledger row.
     *
     * The listed price of the property is stored with the plan as well: it is
     * the base the fee was calculated from and it is shown in the plan summary
     * next to the fee (the fee itself is what the incoming owner pays).
     */
    private function createTransferPlan(
        PropertyTransfer $transfer,
        PropertyReservedPaymentPlan $source,
        PropertyOwner $newOwner
    ): PropertyReservedPaymentPlan {
        $fee = self::round2((float) $transfer->feeAmount());

        $ownerable = $transfer->propertyOwnerable;

        $listedPrice = (float) ($transfer->fee_base > 0
            ? $transfer->fee_base
            : ($source->listed_price ?? $ownerable?->listing?->price ?? 0));

        $plan = PropertyReservedPaymentPlan::create([
            'property_ownerable_type' => $source->property_ownerable_type,
            'property_ownerable_id' => $source->property_ownerable_id,
            'property_owner_id' => $newOwner->id,
            'plan_type' => PlanType::Transfer,
            'parent_plan_id' => $source->id,
            'listed_price' => $listedPrice,
            'fee_base' => $transfer->fee_base,
            'transfer_fee' => $fee,
            'transfer_fee_in_per' => false,
            'transfer_fee_surplus' => false,
            'final_price' => $fee,
            'price_unit' => $source->price_unit,
            'fbr_tax_rate_percent' => 0,
            'status' => $fee > 0 ? 'active' : 'completed',
        ]);

        if ($fee > 0) {
            app(PaymentLedgerService::class)->createLedgerFromSnapshot($plan);
        }

        return $plan;
    }

    private static function round2(float $value): float
    {
        return round($value, 2);
    }

    /**
     * Close the transfer without touching ownership.
     */
    public function reject(PropertyTransfer $transfer, User $actor, ?string $remarks = null): void
    {
        DB::transaction(function () use ($transfer, $actor, $remarks) {
            $fresh = PropertyTransfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($fresh->status !== 'pending') {
                throw new \RuntimeException('This transfer has already been acted on.');
            }

            $fresh->update([
                'status' => 'rejected',
                'remarks' => $remarks ?: $fresh->remarks,
                'acted_by_user_id' => $actor->id,
                'acted_at' => now(),
                'pending_marker' => null,
            ]);
        });
    }

    /**
     * Society id behind a property/unit (unit → floor → property → block).
     */
    public function societyIdFor(Property|PropertyUnit $ownerable): ?int
    {
        $parent = $ownerable instanceof PropertyUnit
            ? $ownerable->floor?->property
            : $ownerable;

        return $parent?->block?->society_id;
    }

    /**
     * Human sentence for the first eligibility blocker, shared by the
     * controller responses and the fee-info cards.
     */
    public function eligibilityMessage(array $reasons): string
    {
        $messages = [
            'no_active_ownership' => 'There is no active ownership on this property to transfer.',
            'no_plan' => 'No committed payment plan was found for this ownership.',
            'payments_incomplete' => 'All payments for this plan must be complete before the property can be transferred.',
            'pending_exists' => 'A transfer is already pending for this property.',
        ];

        foreach ($reasons as $reason) {
            if (isset($messages[$reason])) {
                return $messages[$reason];
            }
        }

        return 'This property cannot be transferred right now.';
    }

    /**
     * Resolve the transfer recipient from the request: a brand-new member is
     * created (role + society membership attached), an existing member is
     * picked up from the submitted ids or the lookup term.
     *
     * "Existing" means a row in the `users` table - the society a person
     * belongs to is not part of the test. The recipient is attached to the
     * society of the property, because that is where they are about to own it.
     */
    public function resolveRecipient(Request $request, ?Society $society): ?User
    {
        if ($request->input('mode') === 'return_to_society') {
            return null;
        }

        if ($request->input('mode') === 'new') {
            return $this->createMember($request, $society);
        }

        $id = $request->input('existing_user_id') ?: $request->input('recipient_user_id');
        if (! $id && $request->filled('fetch_term')) {
            $found = \App\Support\MemberLookup::find($request->input('fetch_term'));
            $id = $found?->id;
            if ($id) {
                $request->merge(['existing_user_id' => $id]);
            }
        }
        if (! $id) {
            return null;
        }

        $recipient = User::find($id);
        if (! $recipient) {
            return null;
        }

        if ($society && $recipient->id !== $society->owner_id) {
            $recipient->memberSocieties()->syncWithoutDetaching([$society->id]);
        }

        return $recipient;
    }

    private function createMember(Request $request, ?Society $society): User
    {
        $user = User::create([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'contact' => $request->input('contact'),
            'cnic_passport' => $request->input('cnic_passport'),
            'country' => $request->input('country'),
            'gender' => $request->input('gender'),
            'permanent_address' => $request->input('permanent_address'),
            'profession' => $request->input('profession'),
            'dob' => $request->input('dob'),
            'is_filer' => (bool) $request->boolean('is_filer'),
        ]);

        $memberRole = Role::where('name', 'Society Member')->first();
        if ($memberRole) {
            $user->assignRole($memberRole);
        }

        if ($request->hasFile('picture')) {
            app(FileServices::class)->compressAndStore($request->file('picture'), $user, true, true);
        }

        // A member created as the recipient of a property in this society
        // always belongs to that society.
        if ($society) {
            $user->memberSocieties()->syncWithoutDetaching([$society->id]);
        }

        return $user;
    }

    private function referenceNumber(): string
    {
        return 'TRF-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 10));
    }
}
