# COMMUNITY_HUB — Payments, Reserved Plans & Property Ownership

> **Purpose of this document:** This is a self-contained technical handoff written so that an
> engineer or an AI assistant **with no prior context** can understand how the money side of
> this application works. It explains the domain, the database model, every flow step-by-step,
> the files involved, and the rules/invariants that must never be broken.

---

## 1. Tech & environment

| | |
|---|---|
| Language / framework | PHP 8 + Laravel |
| Database | MySQL / MariaDB (test env uses `COMMUNITY` @ `127.0.0.1:3307`, user `root`, empty password) |
| Frontend | Blade templates + Bootstrap / Vuexy admin theme + vanilla JS (inline in partials) |
| Auth / ACL | Spatie Laravel Permission (`permission:xxx` route middleware, `@can` in blades) |
| Repo root | `C:\xampp\htdocs\COMMUNITY_HUB` (Windows) |
| Naming quirk | Several classes/tables are misspelled (`PrepertyTransferFee`, `preperty_transfer_fees`) — this is intentional baggage, do not try to "fix" silently. |

---

## 2. System overview (read this first)

This application manages real-estate societies. A **property** (or a **property unit**) goes
through this lifecycle:

```
1. PAYMENT PLANNING   (seller prepares a "for-sale" price breakdown)
        property_payment_plans  +  property_installment_plans
        │
        │  "Reserve Property" (choose/create a member)
        ▼
2. RESERVATION / OWNERSHIP  (member becomes owner; snapshot + ledger created)
        property_owners                  (the ownership record)
        property_reserved_payment_plans  (IMMUTABLE committed plan — one per ownership)
        property_payment_ledgers         (one row per charge to be collected)
        │
        │  "Record Payment" (repeatedly, until fully paid)
        ▼
3. PAYMENTS / LEDGER
        payment_transactions                   (one row per payment)
        payment_transaction_allocations        (which charges a payment settled)
        │
        ├── FBR TAX is collected ON TOP of each payment (govt tax slabs)
        └── Ownership can later be CANCELLED (money returned) or TRANSFERRED (WIP)
```

**The one service that matters most:** `app/Support/PaymentLedgerService.php`.
It is the **only** component that builds the collectible ledger and records/reverses payments.

**Golden rule / invariant:**
```
SUM(property_payment_ledgers.amount_due) === property_reserved_payment_plans.final_price
```
Every payment feature is built around preserving this invariant.

---

## 3. Domain glossary

| Term | Meaning |
|---|---|
| **Plan (draft)** | The seller's editable price breakdown for a property before it is reserved. Table `property_payment_plans`. |
| **Snapshot / Reserved plan** | An immutable copy of the plan committed to one ownership at reservation. Table `property_reserved_payment_plans`. |
| **Ownership** | A row linking a member (`User`) to a property/unit with `start_date`/`end_date`. Table `property_owners`. Current ownership = `end_date IS NULL`. |
| **Ledger row / Charge** | One collectible line item derived from the snapshot (booking fee, installment #3, possession fee, etc.). Table `property_payment_ledgers`. |
| **Transaction / Payment** | One real payment recorded by staff. Table `payment_transactions`. |
| **Allocation** | The link showing how much of one payment went to one charge. Table `payment_transaction_allocations`. |
| **FBR Tax** | Pakistani Federal Board of Revenue tax added **on top** of charges, based on a price slab and whether the buyer is a "filer". |
| **Reversal** | Un-paying something. Nothing is hard-deleted; transactions become `returned`, allocations are soft-deleted, ledger rows reopen. |
| **Cancellation** | Ending an ownership and returning the paid money. Creates an `ownership_cancellation_reversals` record. |
| **Transfer** | Handing an ownership to another member (member-initiated, society-approved). **Currently half-built / being redesigned.** |

---

## 4. Data model

### 4.1 Entity relationship (textual)

```
Property  or  PropertyUnit          (morph target used everywhere)
  │
  ├── property_payment_plans                    (DRAFT plan; morph "planable"; uuid)
  │      └── property_installment_plans         (pivot; snapshots installment template values)
  │
  ├── property_owners                           (OWNERSHIP; morph "property_ownerable")
  │      └── property_reserved_payment_plans    (one IMMUTABLE snapshot; unique property_owner_id)
  │             ├── property_payment_ledgers             (charge rows; FK ownership_payment_plan_id)
  │             ├── payment_transactions                 (payments; FK ownership_payment_plan_id)
  │             │      └── payment_transaction_allocations (pivot; soft-deletes)
  │             └── ownership_cancellation_reversals      (cancel record; FK plan_id)
  │
  └── preperty_transfer_fees                    (society-level transfer fee configuration)
```

### 4.2 Tables (key columns)

**`property_payment_plans`** (draft plan) — created `2026_07_30_171646_create_property_payment_plans_table.php`
- `id`, `uuid`, `planable_type`, `planable_id` (morph → Property/PropertyUnit)
- `listing_id`, `payment_template_id` (nullable; null ⇒ "custom values")
- Charge columns: `booking_fee`, `development_fee`, `down_payment`, `confirmation_fee`, `possession_fee` (+ `_in_per`, and `_surplus` for development/possession)
- Premium columns: `corner_premium`, `park_facing_premium`, `main_boulevard_premium` (+ `_in_per`)
- `listed_price`, `listed_unit` (added `2026_09_03_000001_...`)
- **Contract:** a null charge column means "the linked `payment_template` supplies the value".

**`property_installment_plans`** (pivot; renamed from `property_payment_plan_installment_templates` by `2026_09_11_160727_...`)
- Links a draft plan to installment templates, **snapshotting** `name`, `number_of_installments`, `installment_frequency`, `additional_installment_amount(_in_per)`, `balloon_payment(_in_per)`, `balloon_payment_frequency` per row.
- `installment_template_id` null ⇒ a "custom" installment plan.

**`property_owners`** (ownership)
- `id`, morph `property_ownerable_type/_id`, `owner_id` (→ users), `start_date`, `end_date` (null = current), timestamps.
- ⚠️ Verify the actual morph column prefix against the live DB (`ownerable_*` vs `property_ownerable_*`); the controller writes `property_ownerable_*`.

**`property_reserved_payment_plans`** (committed snapshot; originally `property_ownership_payment_plans`, renamed `2026_09_18_173822_...`)
- `id`, `uuid`, morph `property_ownerable_type/_id`, **`property_owner_id` (UNIQUE)**
- All charge + premium columns **materialised** (no template reference)
- Single flattened installment: `with_installment`, `name`, `number_of_installments`, `installment_frequency`, `additional_installment_amount(_in_per)`, `balloon_payment(_in_per)`, `balloon_payment_frequency`
- `final_price`, `price_unit`
- `status` enum: `active`, `in-progress`, `completed` (+ `reversed`, `cancelled` from `2026_10_15_000004_...`)
- FBR: `fbr_tax_rate_percent`, `fbr_tax_amount`, `is_filer_at_commit` (`2026_09_30_190000_...`)

**`property_payment_ledgers`** (charges)
- `id`, `uuid`, **`ownership_payment_plan_id`** (FK → reserved plan), `charge_type` (enum), `display_name`, `sequence_order`, `installment_no`, `balloon_no`, `amount_due`, `amount_paid`, `remaining_balance`, `due_date`, `payment_status` (enum), `paid_at`, `notes`, soft-deletes.
- `charge_type` enum (after `2026_10_13_153302_...`): `booking_fee, development_fee, down_payment, confirmation_fee, possession_fee, installment, balloon, balance, other`. **(Note: `transfer_fee` was REMOVED — see §12 gotchas.)**
- `payment_status` enum: `pending, current_due, late, paid` (+ `reversed`).

**`payment_transactions`** (payments)
- `id`, `uuid`, **`ownership_payment_plan_id`**, `amount_paid`, `payment_method` (enum), `reference_no`, `recorded_by_user_id`, `paid_at`, `description`, soft-deletes.
- FBR: `charge_subtotal`, `tax_amount`, `tax_rate_percent`, `tax_capped` (`2026_09_30_190000_...`).
- `status` string default `paid` (`returned` after reversal) (`2026_10_14_183923_...`).

**`payment_transaction_allocations`** (pivot payment↔charge)
- `id`, `payment_transaction_id`, `property_payment_ledger_id`, `amount_allocated`, `tax_amount`, timestamps, soft-deletes.

**`ownership_cancellation_reversals`**
- `id`, `uuid`, `plan_id` (→ reserved plan), `status`, `type`, `reason`, `remarks`, `reversed_at`, `reversed_by`, `return_amount`, `return_tax`, `return_total`, `payment_count`, `currency`, `commission_type/value/base/amount`, soft-deletes.

**`fbr_tax_rates`** — slabs: `role`, `filer_status`, `value_from`, `value_to` (null = unbounded), `rate_percent`.
**`society_settings`** — `include_fbr_tax` toggle per society (default ON).
**`preperty_transfer_fees`** — society-level transfer fee config (size range + fixed or % fee).

> **Legacy naming warning:** the child column is still named `ownership_payment_plan_id` even though
> the parent table is now `property_reserved_payment_plans`. Existing FK constraints still reference
> the old table name; MariaDB continues to honour the rename. Keep this in mind when writing SQL.

---

## 5. Flow A — Payment Planning (draft plan, BEFORE reservation)

Admin prepares the price breakdown on the property/unit detail page.

- **Controller:** `app/Http/Controllers/PropertyPaymentPlanController.php`
- **Routes:** `routes/web.php:399-427` (all guarded by `permission:installment_template_listing`)
- **Service (read model):** `app/Services/Property/PaymentPlanStructureService.php::build()`
- **Presenter (calculations):** `app/Support/PaymentPlanPresenter.php` (`build()` = draft; `buildFromSnapshot()` = committed)
- **Calculator:** `app/Support/InstallmentPlanCalculator.php` (`calculate()` — schedule + per-installment/per-balloon)

Key controller methods:
| Method | Controller line | Route name | What it does |
|---|---|---|---|
| `store` | `:37-122` | `payment_plan.apply` | Bulk apply a plan to many properties/units (society/block/property scope). |
| `applySingle` | `:311-381` | `payment_plan.apply_single` | Apply plan to ONE property/unit (from the details tab modal). |
| `update` | `:231-286` | `payment_plan.update` | Edit charge values; drops the template link if any value changed. |
| `attachInstallment` | `:387-407` | `payment_plan.attach-installment` | Add installment template(s) to the plan. |
| `storeInstallment` | `:580-602` | `payment_plan.store-installment` | Add a custom installment plan. |
| `updateInstallment` | `:558-574` | `payment_plan.update-installment` | Edit an installment (converts it to custom). |
| `detachInstallment` | `:292-303` | `payment_plan.detach-installment` | Remove an installment attachment. |
| `destroy` | `:539-551` | `payment_plan.destroy` | Delete the whole draft plan. |
| `printPlan` | `:762-793` | `payment_plan.print` | Print the draft plan (`components.property.payment-plans-print`). |
| `printPlans` | `:795-809` | `payment_plans.print` | **Dead/incomplete** (returns nothing). |

**Template-vs-custom contract** (critical):
- If a `payment_template_id` is linked and all values still match it → the template stays linked and the plan's charge columns are stored as null (template supplies them).
- If any value is edited → the template link is dropped and the edited values are snapshotted into the plan columns.

**Block cascade** (optional): `BlockPaymentPlan` (`block_payment_plans` table) defines defaults that
`app/Services/PropertyRangeService.php::applyBlockPaymentPlan()` (`:149-174`) fans out to matching
owner-less properties/units; `syncBlockPaymentPlanFlag()` (`:278-305`) keeps `is_block_payment_plan_applied` accurate.

---

## 6. Flow B — Reservation → Snapshot → Ledger  **(THE core flow)**

When staff clicks **"Reserve Property"**:

- **Controller:** `app/Http/Controllers/PropertyOwnerController.php`
- **Route:** `POST reserve-property-owners` → name `reserve.property_owner.store` (`routes/web.php:81`)
- **Modal/UI:** `resources/views/_partials/_modals/reserve_property_ownership.blade.php`
  (form posts at `:11`; member lookup JS at `:290-312`; AJAX submit at `:465-506`)
- **Triggered from:** `resources/views/components/property/payments-planning.blade.php:62-67`

### Step-by-step (`PropertyOwnerController::store()` `:90-196`)

1. **Stale-lookup guard** (`:95-104`) — `mode=new` clears `existing_user_id`; `mode=existing` re-resolves the member from `fetch_term`.
2. **Validation** via `Validator::make(..., rules())` (`:106-120`, rules at `:621-658`).
3. **Already-reserved guard** (`:132-143`) — reject if the property already has an open ownership (`whereNull('end_date')`).
4. **DB transaction** (`:146-175`):
   a. `resolveMember()` (`:663-702`) — reuse an existing `User` (any row of `users` — no society condition, see 12), or create a new one with the `Society Member` role, optional picture (`FileServices`); either way the member is attached to the property's `society`.
   b. Create `PropertyOwner` row (`:156-162`): `property_ownerable_type/_id`, `owner_id`, `start_date = today`, `end_date = null`.
   c. Set `$ownerable->owner_id = $user->id` and save (`:165-166`).
   d. **`snapshotPaymentPlan($ownerable, $ownership, $request)` (`:170`)** ← creates the snapshot **and** the ledger.
   e. Mark the property listing `closed = true` (`:174`).
5. **Notify** the member (`notifyReservation()` `:178`, `PropertyReservedMail`).

### `snapshotPaymentPlan()` (`:726-834`) — the ONLY place a snapshot is created

1. `$plan = $ownerable->paymentPlan()->first()`; **returns silently if there is no draft plan** (`:728-731`).
2. Resolve each charge: **template value first, draft-plan value as fallback** (`:733-749`).
3. Copy all charges + premiums + `_in_per`/`_surplus` flags (`:751-774`).
4. Optionally copy the committed installment (only if `with_installment` + `installment_attachment_id`) (`:776-812`).
5. `PropertyReservedPaymentPlan::create([...])` (`:817-825`).
6. `final_price = PaymentPlanPresenter::buildFromSnapshot($snapshot)['final_price']` (`:829`, helper `:840-843`).
7. **`PaymentLedgerService::createLedgerFromSnapshot($snapshot)` (`:833`)** → the collectible ledger is born.

The original draft plan and its attachments are **never mutated or deleted**, so cancelling a
reservation restores the for-sale plan exactly as it was.

### Editing a committed plan
All of these are **blocked once any payment exists** and all rebuild the ledger via `rebuildFromSnapshot()`:
- `updateOwnershipPlan()` `:409-453` (`ownership_payment_plans.update`, perm `payment_plan_edit`)
- `addOwnershipInstallment()` `:460-533`
- `updateOwnershipInstallment()` `:538-583`
- `removeInstallment()` `:589-616`

---

## 7. Flow C — Payments / Ledger

### 7.1 Building the ledger — `PaymentLedgerService::createLedgerFromSnapshot()`

`app/Support/PaymentLedgerService.php:36-109`
- Anchor date = owner's `start_date` (or a supplied reschedule date, or today) (`:63`).
- Emits charge rows in this order:
  1. **Booking Fee + Confirmation Fee** — due at the anchor (`:66-68`).
  2. **Development Fee + Down Payment** — on their own due dates (`:73-80`).
  3. **Installment / Balloon schedule** — via `buildRemainderRows()` (`:860-907`); if there is no schedule, a single **`balance`** lump row is emitted.
  4. **Possession Fee** — due after the last schedule date (or +3 months) (`:86-101`).
  5. **Balance adjustment** — `balanceTo()` (`:912-924`) nudges the last row so the sum equals `final_price`.
- Persists each row with `ownership_payment_plan_id` (`:105-108`).
- `rebuildFromSnapshot()` (`:804-807`) = force rebuild; **only allowed while no payments exist**.

### 7.2 Recording a payment — `PaymentLedgerService::applyPayment()`

`app/Support/PaymentLedgerService.php:122-280`
- Modes: `payment_mode=amount` (spread across all unpaid rows) or `payment_mode=charges` (only the selected `ledger_ids`).
- Blocks plans whose status is `completed`, `reversed`, or `cancelled` (`:131-137`).
- Cascades the amount in sequence order; updates `amount_paid`/`remaining_balance`; marks a row `paid` when it hits zero (`:189-226`).
- Creates a `PaymentTransaction` with `charge_subtotal`, `tax_amount`, `tax_rate_percent`, `tax_capped`, `amount_paid = subtotal + tax` (`:242-254`).
- Creates `PaymentTransactionAllocation` rows with each charge's proportional `tax_amount` (`:265-272`).
- Marks the plan `completed` when remaining ≤ 0 (`:274-276`).

### 7.3 Reversals (un-paying) — nothing is ever hard-deleted
- `reversePayment($transaction)` (`:463-500`) — reopens every charge that payment touched, soft-deletes allocations, flips the transaction to `returned`.
- `reverseAllocations($transaction, $allocationIds)` (`:516-585`) — reverses only selected charges of a bulk payment.
- `reverseCharges($rows)` (`:600-651`) — reverses charges chosen directly in the ledger UI, across payments.
- `refreshStatuses()` (`:686-721`) + `determineStatus()` (`:426-450`) — recompute `pending / current_due / late / paid`.

### 7.4 Controller & routes — `PaymentTransactionController.php`
Routes at `routes/web.php:103-135`. The **write** routes are guarded by `permission:payment_collect`; the **read** routes by `permission:payment_collect|payment_history` (a society member may read the records of their OWN ownership — see §7.5):
| Method | Line | Route name |
|---|---|---|
| `store` | `:37-94` | `payment.store` |
| `reschedule` | `:454-477` | `payments.reschedule` |
| `unpay` | `:570-620` | `ownership_payment_plans.payments.unpay` |
| `reverseCharges` | `:487-546` | `ownership_payment_plans.payments.reverse_charges` |
| `history` | `:147-261` | `ownership_payment_plans.payments.history` (JSON) |
| `receipt` | `:102-132` | `ownership_payment_plans.payments.receipt` |
| `downloadReceipt` | `:297-330` | `ownership_payment_plans.payments.receipt.download` (PDF) |
| `printReport` | `:339-389` | `ownership_payment_plans.payments.report` (perm **`payment_print_by_period`**) |
| `index` / `data` | see §15 | `payments.index` / `payments.data` (the standalone "My Payments" / "All Transactions" listing) |

**Rescheduling** (`reschedule()` `:454-477`): if no ledger exists → create it; if payments exist → refuse; otherwise → rebuild from snapshot with a new anchor date.

### 7.5 Role-based visibility — who may see which payments
**Single source of truth:** `app/Services/Property/PaymentVisibilityService.php`.

| Viewer | Sees |
|---|---|
| Staff (society owner, admin, anything without the `Society Member` role) | every committed plan of the property/unit — the current ownership AND every previous one. The Payments section shows "Latest / Current" + "All payments". |
| `Society Member` (buyer / transfer recipient) | **only** the plans belonging to their OWN ownership: the reservation plan they bought on, or the transfer-fee plan they were handed when the property changed hands. Nothing else — not another owner's plan, not a previous owner's plan. |

- `isRestricted($user)` — true for the `Society Member` role.
- `activeOwnership($ownerable)` / `visiblePlans($ownerable, $viewer)` / `visiblePlanIds($ownerable, $viewer)` / `canViewPlan($plan, $viewer)` / `authorizePlan($plan, $viewer)` (403 via `abort_unless`).
- Enforcement is **server-side**: `PaymentTransactionController::history()`/`receipt()`/`downloadReceipt()`/`printReport()` and `PropertyOwnerController::cancellationSummary()`/`printOwnerPlan()` all call `authorizePlan()`. The "all payments" scope resolves through `visiblePlanIds()`, so widening the scope for a member never reaches another owner's rows.
- Printing is permission-driven: the period report (`ownership_payment_plans.payments.report`) needs **`payment_print_by_period`** (route middleware + a check in `printReport()`), the transfer slip needs **`property_transfer_print`**. The buttons in the UI are gated with the same `@can(...)`.
- UI: `resources/views/components/property/payments.blade.php` always renders the Payments section — for a member without an ownership it shows "No ownership payments yet", for a former owner "This property is no longer yours". Member labels: "My Latest Plan" / "All My Plans (History)" / "All My Payments". The Pay/Unpay buttons stay hidden because the ledger table gates them behind `Gate::allows('payment_collect')`.
- Covered by `tests/Feature/PaymentVisibilityTest.php` (member scoping, 403 on another owner's history/receipt, staff seeing everything, and the transfer scenario: new owner sees only the transfer fee, previous owner keeps their own records, staff sees both).


---

## 8. Flow D — FBR Tax (added on top of payments)

- **Rate slabs:** `app/Support/FbrTaxRateResolver.php`, model `app/Models/FbrTaxRate.php` (table `fbr_tax_rates`).
- **Society toggle:** `app/Models/SocietySetting.php::includeFbrTax()` (`:81-88`).
- **Freeze the rate on the plan (once):** `PropertyReservedPaymentPlan::fbrTaxRate()` (`:98-119`) stores `fbr_tax_rate_percent` + `is_filer_at_commit`. The rate is selected by price slab + buyer filer status.
- **Collect per payment:** `PaymentLedgerService::taxForChargeSubtotal()` (`:287-313`) + `app/Support/FbrTaxCalculator.php`. The calculator prorates tax and, on the payment that settles the plan, absorbs the remaining headroom so the plan-wide tax total is exact. A `lockForUpdate` read prevents two concurrent payments from overspending headroom.
- **Important:** tax is **orthogonal** to the ledger invariant — it is added on top of `amount_due`, never inside it.

---

## 9. Ownership cancellation

- **Controller:** `PropertyOwnerController`
  - `cancellationSummary()` (`:205-237`) — returns paid totals to prefill the cancel modal.
  - `cancelReservationWithSettlement()` (`:248-361`, perm `property_reserve_owner`) — the transaction: reverse all paid transactions → set the snapshot `reversed` (if money was paid) or `cancelled` → create `OwnershipCancellationReversal` (returns + optional commission) → end the ownership (`end_date = today`, clear `owner_id`) → relist the property.
  - `cancellationReceipt()` (`:366-376`) — printable receipt.
- **Modal/UI:** `resources/views/components/property/_cancel-ownership-modal.blade.php` (summary URL `:214-251`, commission math `:163-167`).
- **Routes:** `routes/web.php:82-86`.

---

## 10. The two "plan" tables and how they relate (important design topic)

There are effectively **two plan tables** feeding the money system:

| | `property_payment_plans` (draft) | `property_reserved_payment_plans` (snapshot) |
|---|---|---|
| Meaning | Seller's for-sale plan, editable, pre-reservation | Immutable committed plan for one ownership |
| Created by | `PropertyPaymentPlanController` / `PropertyRangeService` | `PropertyOwnerController::snapshotPaymentPlan()` |
| Installments | Multiple, in `property_installment_plans` pivot | Single, flattened into columns |
| Mutability | Editable / deletable | Never linked back to templates |

The **ledger, transactions, and the "Current vs All Payments (History)" dropdown all hang off the
snapshot** (`property_reserved_payment_plans`) via `ownership_payment_plan_id`. Each ownership has
exactly one snapshot, so "history" is simply the set of snapshots for a property ordered by `id`,
and "current" is the snapshot whose `propertyOwner.end_date IS NULL`.

`resources/views/components/property/payments.blade.php` reads:
- `$allSnapshots = $property->reservedPaymentPlans()->orderByDesc('id')->get()`
- `$activePlan` = latest snapshot whose owner has `end_date = null`
- The dropdown (`payment-view-switch`) toggles `.payment-panel-current` and `.payment-panel-all`.

> A **planned transfer** feature will introduce a *third* concept — a "transfer plan" that carries
> the society transfer fee as a charge. The intended design is that BOTH plan kinds (reserved plan,
> transfer plan) appear in the same current/history dropdown so the UI treats them uniformly. This
> is **not yet implemented** — see §11.

---

## 11. Transfer flow — removed, replaced by ownership history

The first request-based transfer stack (`property_transfer_requests`, its model/controller/
service/notification, routes, views and `PropertyTransferTest`) has been removed. It is being
replaced by:

- an append-only `property_ownership_history` table (renamed from `property_owners`), and
- a new `property_transfers` table that freezes the society transfer fee and links the outgoing
  ownership, the superseded plan and the new transfer-fee plan.

The society fee configuration (`preperty_transfer_fees`) and its settings UI are unchanged.

### 11.1 The transfer slip (printable)
`route('property_transfers.print', $transfer)` → `PropertyTransferController::print()` →
`resources/views/components/property/transfer-request-print.blade.php`.

- A standalone A4 document (no app layout, same styling as the payment receipt and the ownership
  cancellation receipt): parties, the frozen society fee (base + rate/amount), the outgoing plan, the
  incoming transfer-fee plan with what has been paid of it, and the approval trail.
- Opened with `?print=1` the slip prints itself (`data-receipt-auto-print` marker + `window.print()`);
  without it, no print dialog pops up.
- Reachable from the transfers list, from the "Print Pending Transfer" button of the reserved-plans view
  and from the Payments tab of the incoming owner (the transfer-fee plan's ownership carries
  `transfer_id` → `PropertyOwner::incomingTransfer()`).
- Who may print it: staff with the **`property_transfer_print`** permission, plus the requester and the
  recipient (the slip is their own paper trail) — everyone else gets a 403. The print buttons (the
  transfers list, the reserved-plans view, the Payments tab) are hidden without the permission too.

### 11.2 Approving a transfer request bills the transfer fee
`POST route('property_transfers.approve', $transfer)` → `PropertyTransferService::approve()` — one
locked transaction that does all of this:

1. The outgoing ownership ends (`status = transferred`), the recipient's ownership starts
   (`status = active`, `transfer_id` points back at the request).
2. A **transfer-fee plan** is created for the recipient (`plan_type = transfer`, `final_price` = the
   fee frozen on the request, `parent_plan_id` = the superseded plan). A zero-fee transfer creates the
   plan already `completed` and with no ledger row.
3. The fee becomes a **payable charge**: `PaymentLedgerService::createLedgerFromSnapshot()` writes one
   ledger row (`charge_type = transfer_fee`, `display_name = Transfer Fee`, due on the approval day,
   no FBR tax). The recipient pays it from the Payments tab; a full payment flips the plan to
   `completed`.
4. The request itself becomes `approved` (`acted_by_user_id`, `acted_at`, `pending_marker = null`,
   `new_plan_id`).

Rejecting (`property_transfers.reject`) only closes the request — no ownership change, no plan, no
charge.

Where it shows up:

- The transfers list shows the frozen fee plus its live state (`Outstanding …` / `Fee paid`) and a
  **Collect Fee** shortcut to the property's Payments tab (`?tab=payments` → the tab activator in
  `components/property/_tab-activator.blade.php`).
- The recipient's Payments *Planning* card (`components/property/_transfer-fee-info.blade.php`) shows
  the **frozen** fee of their transfer plan (not a freshly computed quote) and says it is payable to
  the society now. Staff see the same card with the wording "payable to the society by the incoming
  owner".
- The recipient's Payments tab shows the plan as "Transfer Fee Plan" and the fee as an outstanding
  charge.

### 11.3 The reserved view of a transfer plan

A transfer plan is **read only**: it carries the society transfer fee and nothing else, so it is never
edited and never gets an installment plan attached.

- `PropertyTransferService::createTransferPlan()` stores the property's **listed price** with the plan
  (`listed_price`, taken from `transfer.fee_base` — the base the fee was calculated from — falling back
  to the superseded plan's `listed_price` / the listing price). Before this, a transfer plan had
  `listed_price = null` and the reserved view showed `0`.
- `PaymentPlanPresenter::buildFromSnapshot()` returns that stored `listing_price` for transfer plans
  (it used to hard-code `0`). The **gross amount, the included total and the final amount stay equal to
  the fee** — the ledger of a transfer plan only ever holds the single `transfer_fee` row, so nothing in
  the money flow changes.
- `components/property/plan-structure-summary.blade.php` accepts two extra keys:
  - `transferFee` → a **Transfer Fee** row right under **Listed Price**;
  - `isTransferPlan` → replaces the "No installment plan attached" note with a transfer specific one.
- `components/property/reserved-payment-plans.blade.php` detects the transfer plan
  (`$ownershipPlan->isTransfer()`), passes both keys to the summary and **hides** the
  "Edit Values" buttons (charges card + installment card), the "Add Installment Plan" button and the
  add/edit installment modals. The installment card shows a transfer specific empty state instead.
- The summary therefore reads, top to bottom: **Listed Price → Transfer Fee → Gross Amount (= the fee)
  → One Time Charges → Included Charges: Transfer Fee → Total One-time → Final Amount (= the fee)**.
- Only the **reserved** view is affected. The draft **planning** view
  (`components/property/payments-planning.blade.php`) and every regular reserved plan keep their
  "Edit Values" and "Add Installment Plan" controls.

Tests: `tests/Feature/PropertyTransferApprovalTest.php` —
`the transfer plan stores the listed price of the property`,
`the reserved plan of a transfer shows the listed price the fee and the final amount`,
`the reserved plan of a transfer has no edit or installment controls`,
`a regular reserved plan still offers the edit and installment controls`.

---

## 12. Member lookup — any user of the platform (no society condition)

The member picker behind the **Reserve Property** and **Transfer Property** modals does **not** look
a person up *inside* a society. Existence in `users` is enough.

- `app/Support/MemberLookup.php` → `MemberLookup::find($term)` searches the whole `users` table:
  exact e-mail → exact contact → digit-normalised contact (`0300 1234567` finds `03001234567`).
  No `society_members` join, no society id, no role filter.
- `PropertyOwnerController::lookup()` (`GET property-owners/lookup?term=…`) answers
  `{found, existing, user:{id, first_name, last_name, email, contact}}`. `existing: true` only means
  "there is a row in `users`" — the modal then renders the **Existing Member** badge. The old
  `society` query parameter is gone.
- `PropertyTransferController::store()` no longer demands that the recipient is a member of the
  property's society. The only recipient rejection left is "the selected member already owns this
  property" (i.e. the current owner). `PropertyTransferService::resolveRecipient()` therefore
  resolves the recipient by id (`existing_user_id` / `recipient_user_id`) and **attaches** them to
  the property's society (`memberSocieties()->syncWithoutDetaching()`), unless they are the society
  owner.
- `PropertyOwnerController::store()` (reserve) resolves the member with `User::findOrFail()` and
  attaches them to the requested `society` the same way.

Membership of a society is therefore a *result* of owning a property in it, not a precondition for
being picked. `tests/Feature/MemberLookupTest.php` locks this behaviour in.

---

## 13. Invariants, rules & gotchas (do not break these)

1. **Ledger invariant:** `SUM(ledger.amount_due) === snapshot.final_price`. `PaymentLedgerService` is the only writer.
2. **The snapshot is immutable and is the single source of truth** for committed payments; it is computed solely from `PaymentPlanPresenter::buildFromSnapshot()`.
3. **Template-vs-custom contract:** a null charge column means "the linked template supplies the value". Editing any value drops the template link.
4. **Reversals never delete:** transactions → `returned`; allocations → soft-deleted; ledger rows reopen (`pending`) or close (`reversed`).
5. **The snapshot is only created at reservation**, and only if the property already has a draft plan.
6. **Editing a committed plan is blocked once any payment exists** (ledger can't be rebuilt safely).
7. **Table rename drift:** the child FK `ownership_payment_plan_id` still points at the old table name `property_ownership_payment_plans`; the live table is `property_reserved_payment_plans`.
8. **`transfer_fee` was removed** from the ledger `charge_type` enum (`2026_10_13_153302_...`) and from `property_payment_plans`. It must be re-added to bill transfer fees as a ledger row.
9. **FBR tax is on top** of `amount_due` and does not affect the invariant.
10. **Known anomalies:**
    - `PropertyPaymentPlanController::printPlans()` returns nothing; its route `payment_plans.print` has no callers.
    - `PaymentPlanStructureService::build()` has a key collision: when an active ownership exists, the reserved branch overrides `grossAmount/excludedTotal/includedTotal/remainingAmount`.
    - Permission names differ between routes (`installment_template_listing`) and blade gates (`payment_plan_apply/edit/...`).
    - `PaymentPlanPresenter` still lists a legacy `transfer_fee` key whose column was dropped (renders null).
11. **Verify** the `property_owners` morph column names against the live DB before touching ownership queries.

---

## 14. The navbar property search (replaces the old Algolia autocomplete)

The old navbar search was a third-party Algolia autocomplete (`@algolia/autocomplete-js`) driven by
static JSON indexes (`public/assets/json/search-horizontal.json` / `search-vertical.json`) plus a
global **Ctrl+K** handler. It is **removed** — the vendor lib, the JSON indexes, the Vite import
and the `SearchConfig` block in `resources/assets/js/main.js` are gone.

What replaced it is a small modal that queries the **live** database:

- **Route:** `GET property/quick-search?term=…` → `PropertyQuickSearchController` (invokable),
  named `property.quick_search`, inside the authenticated `PropertiesController` group of
  `routes/web.php`.
- **Matching:** `properties.name`, `properties.property_no`, and the owner's
  `first_name` / `last_name` / `CONCAT_WS(' ', first_name, last_name)`. Minimum 2 characters,
  at most 20 results, ordered by name.
- **Scoping** (`App\Helpers\SocietyAccessResolver::resolver()`):
  - Super Admin → every property;
  - society-scoped staff (`Society Owner` / `Society Manager`) → only the properties in the blocks
    of the societies they own (`ownedSocietyIds`);
  - members → only their own properties (`properties.owner_id` or an active `ownerships` row).
  A search can therefore never leak a property the viewer may not see.
- **Result payload:** `{uuid, name, property_no, owner_name, url}` where `url` is
  `society.property.details` for staff, `owner.property.details` for a member and
  `property.details` as the fallback.
- **UI:** `resources/views/_partials/_modals/property_search.blade.php` (modal id
  `propertySearchModal`) — debounced (300 ms) fetch, results rendered as DOM nodes (no HTML
  injection of database values), a click opens the property in the same tab. The modal partial is
  included by `resources/views/layouts/commonMaster.blade.php` **directly under `<body>`** — inside
  the navbar it would be trapped in the stacking context of `.layout-navbar` (z-index 2) and the
  bootstrap backdrop (z-index 1040) would cover the whole modal. The navbar
  (`resources/views/layouts/sections/navbar/navbar-partial.blade.php`) only holds the toggler that
  opens the modal.
- **Tests:** `tests/Feature/PropertyQuickSearchTest.php` locks the matching, the scoping and the
  result URLs in place.

---

## 15. The payments listing pages ("My Payments" / "All Transactions")

Two menu entries (`resources/menu/verticalMenu.json` → "My Payments", "All Transactions") render
**one blade** with two scopes:

- **Routes** (`routes/web.php:107-111`, prefix `payments`, slug restricted to
  `my_payments|all_transactions`):
  - `GET payments/{slug}` → `payments.index` → `PaymentTransactionController::index()`
  - `GET payments/{slug}/data` → `payments.data` → `PaymentTransactionController::data()` (JSON)
- **Permissions:** `my_payments` needs `my_payments_listing`, `all_transactions` needs
  `all_transactions_listing` (both seeded in `database/seeders/PermissionsSeeder.php`).
- **View:** `resources/views/content/payments/index.blade.php` — it follows the app layout:
  `@extends('layouts.layoutMaster')`, `@section('title')`, `@section('page-style')`,
  `@section('content')` and the `@push('scripts')` stack for the inline JS.
- **Page layout:** Vuexy page header (`d-flex align-items-center justify-content-between bg-light
  rounded-3 p-4 mb-4 overflow-hidden position-relative` with the `breadcumb_section_pic` icon,
  an icon avatar, the period select and a refresh button), three summary cards
  (all payments / overdue / refunds), a tab strip (`all` / `paid` / `pending` / `refunds`) in the
  `card-header` (`px-4 pt-4 pb-0` + `card-body p-4`, so the content starts **below** the tabs with
  space in between), a filter panel (`bg-body-tertiary border rounded-3`), a `.table-responsive`
  table and a pagination footer.
- **Filters:** society + owner (only on *All Transactions*), free text, method, **date range with
  flatpickr** (`#f_from` / `#f_to`, `monthSelectorType: 'static'`, `dateFormat: 'Y-m-d'`), size
  range and unit, plus a per-page select in the footer.
- **Table:** one column per piece of information — Date, Status, Property (name + number),
  Society, Owner, Charges Settled, Method, Reference (+ description), Recorded By, Charges, Tax
  (+ rate), Total (+ reversal reason) and Receipt (view / print / download icons, enlarged with
  `#payBody .receipt-btn i { font-size: 1.45rem }`).
- **Data:** the table is filled by `payments.data` (JSON):
  `{kind, cards, rows, pagination_html, pagination}`. `kind` is `transactions` (all/paid/refunds)
  or `charges` (pending tab). Rows carry `property`, `property_name`, `property_no`, `society`,
  `owner`, `charges[]`, `charges_count`, `status`, `status_label`, `paid_at_label`, `paid_at_full`,
  `method_label`, `reference_no`, `description`, `recorded_by`, `charge_subtotal`, `tax_amount`,
  `tax_rate_percent`, `amount_paid`, `receipt_url`, `receipt_print_url`, `download_url`.
- **Pagination:** the **server** renders it — `data()` returns
  `pagination_html` = `$pager->withQueryString()->links('pagination::bootstrap-5')->toHtml()`,
  the very same markup the forum pages use (`resources/views/content/forum/index.blade.php:98-104`).
  The JS puts that HTML into `#payPagination` and intercepts the clicks on `a.page-link` to read
  `?page=` instead of leaving the page. The payment-history modal does the same with
  `#historyPagination`.
- **Scope:** staff see the transactions of the societies they own; a member sees only the
  transactions of their own ownerships.
- **"Charges Settled" column:** the list of charges can be long, so the cell is cut to
  `CHARGE_LIMIT = 40` characters (`CHARGE_MAX_WIDTH = 220` px) and rendered as
  `<span class="d-inline-block text-truncate align-middle" data-bs-toggle="tooltip" title="{full}">`.
  The **full** list always stays in the tooltip, so no information is lost and the row never
  grows. Because the rows are rendered by JS after page load, the tooltips have to be
  re-initialised after every render (`refreshTooltips()`); the theme only initialises the
  tooltips of the elements that exist on page load (`resources/assets/js/main.js:308-311`).
- **Blade-section gotcha (important):** a child `@section('page-style')` **replaces** the
  parent's default block (the `page-auth.scss` bundle) — the parent content only survives through
  **`@parent`** (one `@`; `@@parent` is escaped by the compiler and prints a literal `@parent`).
  The same goes for `@section('page-script')`, so the inline JS goes into `@push('scripts')`
  (rendered by `@stack('scripts')` at the end of `layouts/commonMaster.blade.php`);
  `@section('vendor-style')` / `@section('vendor-script')` / `@section('page-script')` are reserved
  for `@vite` imports.
- **flatpickr gotcha:** the theme loads flatpickr with the deferred module scripts, which run
  **after** the inline `@push('scripts')` block, so the pickers are initialised from
  `document.readyState === 'loading' ? DOMContentLoaded : init()`.
- **Tests:** `tests/Feature/PaymentsListingTest.php` — page opens with the permission, 403
  without it, the theme bundle is still loaded, the data endpoint returns the rows with their
  charges, a member only sees their own payments, the pending tab lists the open charges, the
  charge cell keeps its limit + tooltip markup, the page uses the date calendar, the style of the
  page lands in the `<head>` and the data endpoint returns the pagination of the app.
  `tests/Feature/PaymentHistoryTest.php` — the history returns the pagination of the app and the
  modal uses the date calendar.

---

## 16. Routes reference (money-related)

```
# Reservation / ownership (PropertyOwnerController)          routes/web.php:78-93
GET  property-owners/societies                     property_owners.societies
GET  property-owners/lookup                         property_owners.lookup
POST reserve-property-owners                        reserve.property_owner.store
GET  property-owners/cancellation-summary           property_owners.cancellation.summary
POST property-owners/cancel-reservation-settlement  property_owners.cancel_reservation_settlement   (perm property_reserve_owner)
GET  property-owners/cancellation-receipt/{reversal} property_owners.cancellation.receipt
GET  property-owners/plan-print/{snapshot}          property_owners.plan.print
PUT  ownership-payment-plans/{snapshot}             ownership_payment_plans.update            (perm payment_plan_edit)
POST ownership-payment-plans/{snapshot}/installment  ownership_payment_plans.installment.store (perm installment_plan_add)
PUT  ownership-payment-plans/{snapshot}/installment  ownership_payment_plans.installment.update
DEL  ownership-payment-plans/{snapshot}/installment  ownership_payment_plans.installment.remove

# Transfers (PropertyTransferController)                     routes/web.php:95-104
GET  property-transfers/                          property_transfers.index    (perm property_transfer_listing)
POST property-transfers/                          property_transfers.store    (perm property_transfer_create)
POST property-transfers/{transfer}/approve        property_transfers.approve  (perm property_transfer_approve)
POST property-transfers/{transfer}/reject         property_transfers.reject   (perm property_transfer_approve)
GET  property-transfers/{transfer}/print          property_transfers.print    (perm property_transfer_print)

# Payments (PaymentTransactionController)                    routes/web.php:103-135   (perm payment_collect)
POST ownership-payment-plans/{snapshot}/payments           payment.store
POST reserved-payment-plans/{snapshot}/payments/reschedule  payments.reschedule
POST payments/{row}/unpay                                   ownership_payment_plans.payments.unpay
POST ownership-payment-plans/{snapshot}/payments/reverse-charges ...reverse_charges
GET  ownership-payment-plans/{snapshot}/payments/history    ownership_payment_plans.payments.history
GET  payments/{transaction}/receipt                         ownership_payment_plans.payments.receipt
GET  payments/{transaction}/receipt/download                ownership_payment_plans.payments.receipt.download
GET  ownership-payment-plans/{snapshot}/payments/report     ownership_payment_plans.payments.report   (perm payment_print_by_period)

# Draft plans (PropertyPaymentPlanController)                routes/web.php:399-427   (perm installment_template_listing)
POST payment_plan/apply                                     payment_plan.apply
POST payment_plan/apply-single                              payment_plan.apply_single
POST payment_plan/{paymentPlan}/update                      payment_plan.update
POST payment_plan/{paymentPlan}/installment/{att}/update    payment_plan.update-installment
DEL  payment_plan/{paymentPlan}/installment/{att}           payment_plan.detach-installment
POST payment_plan/{paymentPlan}/installment/attach          payment_plan.attach-installment
POST payment_plan/{paymentPlan}/installment/store           payment_plan.store-installment
DEL  payment_plan/{paymentPlan}                             payment_plan.destroy
GET  payment_plan/print/{uuid}                              payment_plan.print

# Property search (navbar modal)                             routes/web.php
GET  property/quick-search                                 property.quick_search

# Payments listing (PaymentTransactionController)             routes/web.php:107-111
GET  payments/{slug}                                       payments.index   (slug my_payments|all_transactions)
GET  payments/{slug}/data                                  payments.data    (JSON, same perms)
```

---

## 17. Complete file index

### Controllers
- `app/Http/Controllers/PropertyOwnerController.php` — reservation, snapshot, cancel, plan edits, lookup
- `app/Http/Controllers/PaymentTransactionController.php` — record/reverse/history/receipt/report
- `app/Http/Controllers/PropertyPaymentPlanController.php` — draft plan CRUD + print
- `app/Http/Controllers/PaymentTemplateController.php`, `InstallmentTemplateController.php` — template CRUD
- `app/Http/Controllers/BlocksController.php` — block-level plan cascade + print
- `app/Http/Controllers/PrepertyTransferFeeController.php` — society transfer fee config
- `app/Http/Controllers/PropertyQuickSearchController.php` — **navbar property search (name / property no / owner name)**

### Services / Support / Helpers
- `app/Support/PaymentLedgerService.php` — **the ledger engine (read first)**
- `app/Support/PaymentPlanPresenter.php` — plan calculations (`build`, `buildFromSnapshot`)
- `app/Support/InstallmentPlanCalculator.php` — installment schedule math
- `app/Support/FbrTaxCalculator.php`, `FbrTaxRateResolver.php` — FBR tax
- `app/Support/BlockPaymentPlanPresenter.php`
- `app/Support/MemberLookup.php` — **global e-mail/contact lookup in `users` (no society condition)**
- `app/Services/Property/PaymentPlanStructureService.php` — read model for planning UI
- `app/Services/Property/PaymentVisibilityService.php` — **who may see which payments (read before changing the Payments tab)**
- `app/Services/PropertyRangeService.php` — block→property cascade
- `app/Services/FileServices.php`, `app/Helpers/SocietyAccessResolver.php`, `app/Helpers/GetArea.php`

### Models
- `Property.php`, `PropertyUnit.php`, `PropertyOwner.php`
- `PropertyReservedPaymentPlan.php` (snapshot), `PropertyPaymentPlan.php` (draft), `PropertyPaymentPlanInstallmentTemplate.php`
- `PropertyPaymentLedger.php`, `PaymentTransaction.php`, `PaymentTransactionAllocation.php`
- `OwnershipCancellationReversal.php`, `PropertyListing.php`
- `PaymentTemplate.php`, `InstallmentTemplate.php`, `BlockPaymentPlan.php`
- `FbrTaxRate.php`, `SocietySetting.php`, `PrepertyTransferFee.php`

### Migrations (under `database/migrations/`)
- Draft plan: `2026_07_30_171646_create_property_payment_plans_table.php`, `2026_08_03_100001`, `2026_08_03_100002`, `2026_08_11_000001`, `2026_09_03_000001`, `2026_09_11_160727`
- Snapshot: `2026_09_11_170000_create_property_ownership_payment_plans_table.php`, `2026_09_18_173822_rename_property_ownership_payment_plans_table_name.php`
- Ledger/transactions: `2026_09_14_100001`, `2026_09_14_100002`, `2026_09_14_100003`, `2026_10_07_000001`, `2026_10_13_153302`, `2026_10_14_183923`, `2026_10_14_214848`, `2026_10_15_000005`
- FBR: `2026_09_24_200305_create_fbr_tax_rates_table.php`, `2026_09_30_190000`
- Ownership/cancel: `2026_09_02_000001_create_property_owners_table.php`, `2026_10_15_000001`–`000004`
- Transfer fees: `2026_10_15_190219_create_preperty_transfer_fees_table.php` (retained)

### Views (`resources/views/`)
- `components/property/payments.blade.php` — payments tab (current vs history, all modals)
- `components/property/payments-planning.blade.php` — planning tab (apply/edit/print modals)
- `components/property/reserved-payment-plans.blade.php` — reserved view
- `components/property/ledger-table.blade.php` — charge table (shared current/history)
- `components/property/plan-structure-summary.blade.php` — summary sidebar
- `components/property/payment-receipt.blade.php`, `payment-report.blade.php`
- `components/property/owner-payment-plan-print.blade.php`, `payment-plans-print.blade.php`
- `components/property/ownership-cancellation-receipt.blade.php`, `_cancel-ownership-modal.blade.php`
- `components/property/transfer-request-print.blade.php` (the transfer slip)
- `components/property/_transfer-fee-info.blade.php` (society transfer-fee card), `_tab-activator.blade.php` (`?tab=payments`)
- `components/property/_charges-explanation.blade.php`, `_planning-styles.blade.php`
- `_partials/_modals/reserve_property_ownership.blade.php`, `apply_payment_plan.blade.php`, `_partials/_modals/transfer_property.blade.php`, `_partials/_modals/property_search.blade.php` (navbar search modal)
- `layouts/sections/navbar/navbar-partial.blade.php` (search toggler → `propertySearchModal`), `layouts/commonMaster.blade.php` (includes the modal under `<body>`)
- `content/payments/index.blade.php` — **the "My Payments" / "All Transactions" listing page** (header, summary cards, tabs with space below them, filter panel with flatpickr dates, full-info table, enlarged receipt icons, server-rendered pagination)
- Hosts: `content/property_management/blocks/properties/property_details.blade.php` (tabs `#payments_planning` `:1072`, `#payments` `:1086`), `.../unit_details.blade.php`

### Assets / docs / tests
- `resources/assets/js/installment-plan-calculator.js`, `resources/assets/css/print-plan.css`
- `PAYMENT_DB_WORKFLOW.txt` (narrative schema notes)
- `tests/Feature/`: `OwnershipCancellationReversalTest.php`, `PaymentTransactionTest.php`, `PaymentUnpayChargesTest.php`, `PaymentHistoryTest.php`, `PaymentVisibilityTest.php`, `PaymentPrintViewTest.php`, `PropertyTransferPrintTest.php`, `PropertyTransferApprovalTest.php`, `MemberLookupTest.php`, `PropertyQuickSearchTest.php`, `PaymentsListingTest.php`, `FbrTaxTest.php`, `BlockPaymentPlanApplyTest.php`

---

## 18. Suggested reading order (for a new engineer or AI)

1. `app/Support/PaymentLedgerService.php` — the engine.
2. `app/Models/PropertyReservedPaymentPlan.php` + its migration — the snapshot shape.
3. `app/Http/Controllers/PropertyOwnerController.php` → `store()` + `snapshotPaymentPlan()` — how a plan gets committed.
4. `app/Http/Controllers/PaymentTransactionController.php` + `app/Support/PaymentPlanPresenter.php` — how payments are recorded and how amounts are derived.
5. `resources/views/components/property/payments.blade.php` + `app/Services/Property/PaymentVisibilityService.php` — how it all surfaces in the UI (current vs history) and who is allowed to read it.
6. `resources/views/content/payments/index.blade.php` + `PaymentTransactionController::index()/data()` — the standalone payments/transactions listing (see §15).
7. `app/Services/Property/PropertyTransferService.php` → `approve()` — how a transfer moves the ownership and bills the fee.
8. `app/Support/FbrTaxCalculator.php` — the tax layer.
9. `app/Support/MemberLookup.php` + `PropertyTransferService::resolveRecipient()` — how a member is picked (any row of `users`) and attached to the society.

---

*End of handoff.*
