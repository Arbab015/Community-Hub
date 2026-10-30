<?php

namespace Tests\Feature;

use App\Models\PropertyOwner;
use App\Models\PropertyReservedPaymentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertyLedgerRemainingAmountTest extends TestCase
{
    use DatabaseTransactions;

    public function test_no_installment_ledger_uses_remaining_amount_due_with_other_charges(): void
    {
        $ownerUser = User::create([
            'first_name' => 'R', 'last_name' => 'O',
            'email' => 'r'.uniqid().'@example.com', 'password' => 'password',
            'cnic_passport' => 'C-'.uniqid(), 'gender' => 'male', 'marital_status' => 'married',
            'permanent_address' => 'P', 'present_address' => 'P', 'contact' => '03001234567',
        ]);

        $startedAt = now()->subDays(2)->startOfDay();

        $owner = PropertyOwner::create([
            'id' => (int) (DB::table('property_ownership_history')->max('id') ?? 0) + 1,
            'property_ownerable_type' => 'App\\Models\\Property',
            'property_ownerable_id' => random_int(1_000_000, 9_999_999),
            'owner_id' => $ownerUser->id,
            'started_at' => $startedAt->toDateString(),
        ]);

        $snapshot = PropertyReservedPaymentPlan::create([
            'property_ownerable_type' => 'App\\Models\\Property',
            'property_ownerable_id' => 1,
            'property_owner_id' => $owner->id,
            'with_installment' => false,
            'number_of_installments' => null,
            'listed_price' => 100000,
            'booking_fee' => 5000,
            'booking_fee_in_per' => false,
            'final_price' => 105000,
            'price_unit' => 'PKR',
            'status' => 'in-progress',
        ]);

        app(\App\Support\PaymentLedgerService::class)->createLedgerFromSnapshot($snapshot);

        $rows = $snapshot->ledgerRows()->orderBy('sequence_order')->get();

        $data = \App\Support\PaymentPlanPresenter::buildFromSnapshot($snapshot);
        $expectedFinal = (float) $data['final_price'];
        $booking = (float) collect($data['fee_rows'])->firstWhere('label', 'Booking Fee')['amount'];

        $this->assertGreaterThanOrEqual(2, $rows->count());

        $balance = $rows->firstWhere('charge_type', 'balance');
        $this->assertNotNull($balance);
        $this->assertSame('Remaining Amount', $balance->display_name);
        $this->assertSame($expectedFinal - $booking, (float) $balance->amount_due);

        foreach ($rows as $row) {
            $this->assertSame(
                $startedAt->toDateString(),
                $row->due_date->toDateString(),
                $row->display_name.' should be due with the other charges'
            );
        }

        $this->assertSame($expectedFinal, (float) $rows->sum('amount_due'));
    }
}
