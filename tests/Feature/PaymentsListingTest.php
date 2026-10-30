<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\PaymentTransaction;
use App\Models\PaymentTransactionAllocation;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\PropertyPaymentLedger;
use App\Models\PropertyReservedPaymentPlan;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The "My Payments" / "All Transactions" pages: the listing page itself and
 * the json endpoint that fills its table.
 */
class PaymentsListingTest extends TestCase
{
    use DatabaseTransactions;

    private User $staff;

    private User $member;

    private User $other;

    private Society $society;

    private Property $property;

    private PropertyReservedPaymentPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = $this->makeUser('Staff', 'Approver');
        $this->staff->assignRole(Role::findOrCreate('Society Owner'));
        $this->staff->givePermissionTo(['all_transactions_listing', 'my_payments_listing']);

        $this->member = $this->makeUser('Bilal', 'Member');
        $this->member->givePermissionTo('my_payments_listing');

        $this->other = $this->makeUser('Sana', 'Member');
        $this->other->givePermissionTo('my_payments_listing');

        $this->society = Society::create([
            'name' => 'Payments Society',
            'address' => 'Address',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'owner_id' => $this->staff->id,
        ]);

        $block = Block::create([
            'society_id' => $this->society->id,
            'name' => 'Block A',
        ]);

        $this->property = Property::create([
            'block_id' => $block->id,
            'name' => 'Palm Avenue',
            'property_no' => 'P-5001',
            'type' => 'residential',
            'category' => 'residential',
            'construction_status' => 'constructed',
            'street' => 'Street 1',
            'owner_id' => $this->member->id,
        ]);

        $ownership = PropertyOwner::create([
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $this->property->id,
            'owner_id' => $this->member->id,
            'started_at' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);

        $this->plan = PropertyReservedPaymentPlan::create([
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $this->property->id,
            'property_owner_id' => $ownership->id,
            'with_installment' => false,
            'final_price' => 300_000,
            'price_unit' => 'PKR',
            'status' => 'in-progress',
        ]);

        $this->payTransaction([
            'Booking Fee',
            'Confirmation Fee Of The Society Which Is Quite Long',
            'Installment #1',
        ]);
    }

    public function test_the_all_transactions_page_is_opened_by_a_user_with_the_permission(): void
    {
        $response = $this->actingAs($this->staff)
            ->get(route('payments.index', ['slug' => 'all_transactions']));

        $response->assertOk();

        // The page follows the layout of the app: breadcrumb, cards, tabs, table.
        $response->assertSee('All Transactions');
        $response->assertSee('breadcrumb', false);
        $response->assertSee('tableBody', false);
        $response->assertSee('statusTabs', false);

        // The title of the page reaches the layout.
        preg_match('/<title>(.*?)<\/title>/s', (string) $response->getContent(), $title);
        $this->assertStringContainsString('All Transactions', $title[1] ?? '');
    }

    public function test_the_page_is_forbidden_without_the_permission(): void
    {
        $this->actingAs($this->member)
            ->get(route('payments.index', ['slug' => 'all_transactions']))
            ->assertForbidden();
    }

    public function test_the_page_uses_the_date_calendar_of_the_app(): void
    {
        $response = $this->actingAs($this->staff)
            ->get(route('payments.index', ['slug' => 'all_transactions']));

        $response->assertOk();

        // The from/to filters are date pickers (flatpickr), like on the user form.
        $response->assertSee('id="filter-date-from"', false);
        $response->assertSee('id="filter-date-to"', false);
        $response->assertSee('tabler-calendar', false);
        $response->assertSee('flatpickr', false);
    }

    public function test_the_styles_of_the_page_are_rendered_in_the_head(): void
    {
        $response = $this->actingAs($this->staff)
            ->get(route('payments.index', ['slug' => 'all_transactions']));

        $response->assertOk();

        $html = (string) $response->getContent();
        $head = substr($html, 0, (int) strpos($html, '</head>'));

        // The style of the page is part of the head, not of the body.
        $this->assertStringContainsString('searchable-select', $head);

        // The default style of the layout is still there (@@parent).
        $this->assertStringContainsString('page-auth', $head);
    }

    public function test_the_my_payments_page_is_opened_by_a_member(): void
    {
        $response = $this->actingAs($this->member)
            ->get(route('payments.index', ['slug' => 'my_payments']));

        $response->assertOk();
        $response->assertSee('My Payments');

        // The member sees his own payments only - no society filter.
        $response->assertDontSee('All societies');
    }

    public function test_the_page_does_not_replace_the_script_bundle_of_the_theme(): void
    {
        $response = $this->actingAs($this->staff)
            ->get(route('payments.index', ['slug' => 'all_transactions']));

        $response->assertOk();

        // The script of the page is pushed, the bundle of the theme still loads.
        $response->assertSee('pages-auth', false);
        $response->assertSee('openTransactionDetails', false);
    }

    public function test_the_data_endpoint_lists_the_transactions_with_their_charges(): void
    {
        $response = $this->actingAs($this->staff)
            ->getJson(route('payments.data', ['slug' => 'all_transactions']));

        $response->assertOk();
        $response->assertJsonPath('kind', 'transactions');
        $response->assertJsonCount(1, 'rows');

        $row = $response->json('rows.0');

        $this->assertSame('Palm Avenue P-5001', $row['property']);
        $this->assertSame('Payments Society', $row['society']);
        $this->assertSame('Bilal Member', $row['owner']);
        $this->assertSame(
            ['Booking Fee', 'Confirmation Fee Of The Society Which Is Quite Long', 'Installment #1'],
            $row['charges']
        );
    }

    public function test_a_member_only_sees_their_own_payments(): void
    {
        $response = $this->actingAs($this->member)
            ->getJson(route('payments.data', ['slug' => 'my_payments']));

        $response->assertOk();
        $response->assertJsonCount(1, 'rows');

        // Somebody else has no payments of their own.
        $response = $this->actingAs($this->other)
            ->getJson(route('payments.data', ['slug' => 'my_payments']));

        $response->assertOk();
        $response->assertJsonCount(0, 'rows');
    }

    public function test_my_payments_ignores_previous_owners_of_the_same_property(): void
    {
        $previous = $this->makeUser('Previous', 'Owner');
        $previous->givePermissionTo('my_payments_listing');

        $ownership = PropertyOwner::create([
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $this->property->id,
            'owner_id' => $previous->id,
            'started_at' => now()->subYear()->toDateString(),
            'ended_at' => now()->subMonth()->toDateString(),
            'status' => 'cancelled',
        ]);

        $oldPlan = PropertyReservedPaymentPlan::create([
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $this->property->id,
            'property_owner_id' => $ownership->id,
            'with_installment' => false,
            'final_price' => 100_000,
            'price_unit' => 'PKR',
            'status' => 'in-progress',
        ]);

        PaymentTransaction::create([
            'ownership_payment_plan_id' => $oldPlan->id,
            'amount_paid' => 50_000,
            'charge_subtotal' => 50_000,
            'tax_amount' => 0,
            'payment_method' => 'cash',
            'reference_no' => 'PREV-1',
            'recorded_by_user_id' => $this->staff->id,
            'paid_at' => now(),
            'status' => 'paid',
        ]);

        // The current owner sees only the payments under her own ownership.
        $mine = $this->actingAs($this->member)
            ->getJson(route('payments.data', ['slug' => 'my_payments']))
            ->assertOk()
            ->json();

        $this->assertCount(1, $mine['rows']);
        $this->assertNotSame('PREV-1', $mine['rows'][0]['reference_no']);

        // The former owner no longer owns the property, so nothing is "theirs".
        $theirs = $this->actingAs($previous)
            ->getJson(route('payments.data', ['slug' => 'my_payments']))
            ->assertOk()
            ->json();

        $this->assertCount(0, $theirs['rows']);

        // Staff still see the full record on All Transactions.
        $all = $this->actingAs($this->staff)
            ->getJson(route('payments.data', ['slug' => 'all_transactions']))
            ->assertOk()
            ->json();

        $this->assertCount(2, $all['rows']);
    }

    public function test_the_property_filter_matches_the_name_or_the_number(): void
    {
        $byName = $this->actingAs($this->staff)
            ->getJson(route('payments.data', ['slug' => 'all_transactions', 'property' => 'Palm']))
            ->assertOk()
            ->json();

        $this->assertCount(1, $byName['rows']);

        $byNumber = $this->actingAs($this->staff)
            ->getJson(route('payments.data', ['slug' => 'all_transactions', 'property' => 'P-5001']))
            ->assertOk()
            ->json();

        $this->assertCount(1, $byNumber['rows']);

        $none = $this->actingAs($this->staff)
            ->getJson(route('payments.data', ['slug' => 'all_transactions', 'property' => 'no-such-property']))
            ->assertOk()
            ->json();

        $this->assertCount(0, $none['rows']);
    }

    public function test_the_society_owner_filter_is_only_available_to_a_super_admin(): void
    {
        $admin = $this->makeUser('Super', 'Admin');
        $admin->assignRole(Role::findOrCreate('Super Admin'));
        $admin->givePermissionTo('all_transactions_listing');

        $filtered = $this->actingAs($admin)
            ->getJson(route('payments.data', [
                'slug' => 'all_transactions',
                'society_owner' => $this->staff->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertCount(1, $filtered['rows']);

        $none = $this->actingAs($admin)
            ->getJson(route('payments.data', [
                'slug' => 'all_transactions',
                'society_owner' => $this->other->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertCount(0, $none['rows']);

        // The filter is offered to the Super Admin only.
        $this->actingAs($admin)
            ->get(route('payments.index', ['slug' => 'all_transactions']))
            ->assertOk()
            ->assertSee('id="filter-society-owner"', false);

        $this->actingAs($this->staff)
            ->get(route('payments.index', ['slug' => 'all_transactions']))
            ->assertOk()
            ->assertDontSee('id="filter-society-owner"', false);
    }

    public function test_the_pending_tab_lists_the_open_charges(): void
    {
        $response = $this->actingAs($this->staff)
            ->getJson(route('payments.data', ['slug' => 'all_transactions', 'tab' => 'pending']));

        $response->assertOk();
        $response->assertJsonPath('kind', 'charges');
        $response->assertJsonCount(3, 'rows');
    }

    public function test_the_data_endpoint_returns_the_pagination_of_the_app(): void
    {
        // More transactions than fit on one page, so the pager shows up.
        for ($i = 0; $i < 6; $i++) {
            $this->payTransaction(['Booking Fee']);
        }

        $response = $this->actingAs($this->staff)
            ->getJson(route('payments.data', ['slug' => 'all_transactions', 'per_page' => 5]));

        $response->assertOk();
        $response->assertJsonCount(5, 'rows');
        $response->assertJsonPath('pagination.last_page', 2);

        // The very same pagination markup the forum pages render.
        $html = (string) $response->json('pagination_html');

        $this->assertStringContainsString('class="pagination"', $html);
        $this->assertStringContainsString('page-link', $html);
    }

    public function test_a_row_carries_every_information_of_the_table(): void
    {
        $response = $this->actingAs($this->staff)
            ->getJson(route('payments.data', ['slug' => 'all_transactions']));

        $response->assertOk();

        $row = $response->json('rows.0');

        foreach ([
            'property', 'property_name', 'property_no', 'society', 'owner',
            'status', 'status_label', 'paid_at_label', 'paid_at_full', 'charges', 'charges_count',
            'method_label', 'reference_no', 'recorded_by', 'charge_subtotal', 'tax_amount',
            'tax_rate_percent', 'amount_paid', 'receipt_url', 'receipt_print_url', 'download_url',
        ] as $key) {
            $this->assertArrayHasKey($key, $row);
        }

        $this->assertSame('Palm Avenue', $row['property_name']);
        $this->assertSame('P-5001', $row['property_no']);
        $this->assertSame(3, $row['charges_count']);
        $this->assertSame('Paid', $row['status_label']);
    }

    public function test_the_transactions_table_keeps_the_summary_columns_and_opens_the_details_modal(): void
    {
        $response = $this->actingAs($this->staff)
            ->get(route('payments.index', ['slug' => 'all_transactions']));

        $response->assertOk();

        // The table is a summary: the eye button opens the full record instead
        // of linking straight to the receipt.
        $response->assertSee('data-view-row', false);
        $response->assertSee('id="transactionDetailsModal"', false);
        $response->assertSee('openTransactionDetails', false);
        $response->assertSee('id="transactionDetailsPrintBtn"', false);
        $response->assertSee('id="transactionDetailsDownloadBtn"', false);
        $response->assertSee('row.receipt_url', false);
        $response->assertSee('row.download_url', false);
        $response->assertDontSee("['Recorded By'", false);
        $response->assertDontSee('CHARGE_LIMIT', false);
    }

    /**
     * A paid transaction with one allocation per charge.
     *
     * @param  array<int, string>  $charges
     */
    private function payTransaction(array $charges): void
    {
        $ledger = collect($charges)->values()->map(fn ($name, $i) => PropertyPaymentLedger::create([
            'ownership_payment_plan_id' => $this->plan->id,
            'charge_type' => 'installment',
            'display_name' => $name,
            'sequence_order' => $i + 1,
            'installment_no' => $i + 1,
            'amount_due' => 100_000,
            'amount_paid' => 0,
            'remaining_balance' => 100_000,
            'due_date' => now()->addMonths($i + 1)->toDateString(),
        ]));

        $transaction = PaymentTransaction::create([
            'ownership_payment_plan_id' => $this->plan->id,
            'amount_paid' => 300_000,
            'charge_subtotal' => 300_000,
            'tax_amount' => 0,
            'payment_method' => 'cash',
            'reference_no' => 'REF-'.uniqid(),
            'recorded_by_user_id' => $this->staff->id,
            'paid_at' => now(),
            'status' => 'paid',
        ]);

        $ledger->each(function (PropertyPaymentLedger $row) use ($transaction) {
            PaymentTransactionAllocation::create([
                'payment_transaction_id' => $transaction->id,
                'property_payment_ledger_id' => $row->id,
                'amount_allocated' => 100_000,
                'tax_amount' => 0,
            ]);
        });
    }

    private function makeUser(string $firstName, string $lastName): User
    {
        return User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => strtolower($firstName.$lastName).'.'.uniqid().'@example.com',
            'password' => 'password',
            'cnic_passport' => 'CNIC-'.uniqid(),
            'gender' => 'male',
            'marital_status' => 'married',
            'permanent_address' => 'Permanent Address',
            'present_address' => 'Present Address',
            'contact' => '0300'.random_int(1000000, 9999999),
            'is_filer' => false,
        ]);
    }
}
