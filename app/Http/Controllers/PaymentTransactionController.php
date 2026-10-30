<?php

namespace App\Http\Controllers;

use App\Helpers\GetArea;
use App\Helpers\SocietyAccessResolver;
use App\Models\PaymentTransaction;
use App\Models\PaymentTransactionAllocation;
use App\Models\Property;
use App\Models\PropertyPaymentLedger;
use App\Models\PropertyReservedPaymentPlan;
use App\Models\PropertyUnit;
use App\Models\Society;
use App\Models\User;
use App\Services\Property\PaymentVisibilityService;
use App\Services\PropertyRangeService;
use App\Support\PaymentLedgerService;
use App\Support\PaymentPlanPresenter;
use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class PaymentTransactionController extends Controller
{
    private const PAGES = [
        'my_payments' => ['title' => 'My Payments', 'permission' => 'my_payments_listing'],
        'all_transactions' => ['title' => 'All Transactions', 'permission' => 'all_transactions_listing'],
    ];

    private const PERIOD_DAYS = ['week' => 7, 'month' => 30, 'year' => 365];

    public function index(Request $request, string $slug)
    {
        $page = $this->page($slug, $request->user());
        $ids = $this->allowedSocietyIds($request->user());

        $societies = $slug === 'all_transactions'
          ? Society::query()->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
              ->orderBy('name')->get(['id', 'name'])
          : collect();

        // Only a Super Admin may narrow the list down to one society owner.
        $isSuperAdmin = $request->user()->hasRole('Super Admin');
        $societyOwners = $isSuperAdmin
          ? User::query()->whereHas('societies')
              ->orderBy('first_name')->orderBy('last_name')
              ->get(['id', 'first_name', 'last_name'])
          : collect();

        // The first page of transactions is rendered with the same paginator
        // the properties page uses; the table itself is refreshed through the
        // data endpoint after the page has loaded.
        $perPage = 25;
        $planFilter = $this->planFilter($request, $slug, $request->user());
        [$from, $to] = $this->dateRange($request);
        $needle = ($q = trim((string) $request->input('q', ''))) !== ''
          ? '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%' : null;

        $total = PaymentTransaction::query()
            ->whereHas('ownershipPlan', $planFilter)
            ->where('paid_at', '>=', $from)->where('paid_at', '<=', $to)
            ->when($request->input('method'), fn ($b, $m) => $b->where('payment_method', $m))
            ->when($needle, fn ($b) => $b->where(fn (Builder $w) => $w
                ->where('reference_no', 'like', $needle)
                ->orWhereHas('allocations.ledgerRow', fn ($l) => $l->where('display_name', 'like', $needle))))
            ->count();

        $transactions = new LengthAwarePaginator(array_fill(0, min($perPage, $total), null),
            $total,
            $perPage,
            1,
            ['path' => route('payments.data', $slug), 'query' => $request->query()]
        );

        return view('content.payments.index', [
            'slug' => $slug,
            'title' => $page['title'],
            'societies' => $societies,
            'societyOwners' => $societyOwners,
            'isSuperAdmin' => $isSuperAdmin,
            'currency' => 'PKR',
            'methods' => self::PAYMENT_METHODS,
            'transactions' => $transactions,
        ]);
    }

    public function data(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        $this->page($slug, $user);

        $request->validate([
            'tab' => 'nullable|in:all,paid,pending,refunds',
            'period' => 'nullable|in:week,month,year',
            'society_id' => 'nullable|integer',
            'owner' => 'nullable|string|max:100',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'size_min' => 'nullable|numeric|min:0',
            'size_max' => 'nullable|numeric|min:0',
            'size_unit' => 'nullable|in:marla,square_yard',
            'q' => 'nullable|string|max:100',
            'property' => 'nullable|string|max:100',
            'society_owner' => 'nullable|integer',
            'method' => 'nullable|in:'.implode(',', self::PAYMENT_METHODS),
            'per_page' => 'nullable|integer',
        ]);

        $tab = $request->input('tab', 'all');
        $perPage = min(200, max(5, (int) $request->input('per_page', 25)));
        $planFilter = $this->planFilter($request, $slug, $user);
        [$from, $to] = $this->dateRange($request);
        $explicit = $request->filled('from') || $request->filled('to');

        $needle = ($q = trim((string) $request->input('q', ''))) !== ''
          ? '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%' : null;

        $txn = fn () => PaymentTransaction::query()
            ->whereHas('ownershipPlan', $planFilter)
            ->where('paid_at', '>=', $from)->where('paid_at', '<=', $to)
            ->when($request->input('method'), fn ($b, $m) => $b->where('payment_method', $m))
            ->when($needle, fn ($b) => $b->where(fn (Builder $w) => $w
                ->where('reference_no', 'like', $needle)
                ->orWhereHas('allocations.ledgerRow', fn ($l) => $l->where('display_name', 'like', $needle))));

        $ledger = fn () => PropertyPaymentLedger::query()->whereHas('ownershipPlan', $planFilter);

        // Cards: scoped + filtered + period, never affected by the active tab.
        $paid = $txn()->where('status', 'paid')
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(amount_paid),0) as t')->first();
        $ref = $txn()->where('status', 'returned')
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(amount_paid),0) as t')->first();
        $over = $ledger()->whereIn('payment_status', ['late', 'current_due'])
            ->where('remaining_balance', '>', 0)
            ->where('due_date', '>=', $from)->where('due_date', '<=', $to)
            ->selectRaw('COUNT(*) as c, COUNT(DISTINCT ownership_payment_plan_id) as p, COALESCE(SUM(remaining_balance),0) as t')
            ->first();

        if ($tab === 'pending') {
            $pager = $ledger()->where('remaining_balance', '>', 0)
                ->when($explicit, fn ($b) => $b->where('due_date', '>=', $from)->where('due_date', '<=', $to))
                ->with(['ownershipPlan.propertyOwner.owner', 'ownershipPlan.propertyOwnerable' => $this->morphEager()])
                ->orderBy('due_date')->paginate($perPage);

            $kind = 'charges';
            $rows = collect($pager->items())->map(fn ($l) => $this->planInfo($l->ownershipPlan) + [
                'charge' => $l->display_name,
                'installment_no' => $l->installment_no,
                'due_date' => $l->due_date ? Carbon::parse($l->due_date)->format('d M Y') : '—',
                'due_date_iso' => $l->due_date ? Carbon::parse($l->due_date)->format('Y-m-d') : null,
                'amount_due' => round((float) $l->amount_due, 2),
                'amount_paid' => round((float) $l->amount_paid, 2),
                'remaining' => round((float) $l->remaining_balance, 2),
                'status' => ucfirst(str_replace('_', ' ', (string) $l->payment_status)),
                'status_raw' => (string) $l->payment_status,
            ]);
        } else {
            $pager = $txn()
                ->when($tab === 'paid', fn ($b) => $b->where('status', 'paid'))
                ->when($tab === 'refunds', fn ($b) => $b->where('status', 'returned'))
                ->with([
                    'allocations' => fn ($a) => $a->withTrashed(),
                    'allocations.ledgerRow',
                    'recordedBy',
                    'ownershipPlan.cancellationReversal.reversedBy',
                    'ownershipPlan.propertyOwner.owner',
                    'ownershipPlan.propertyOwnerable' => $this->morphEager(),
                ])
                ->orderByDesc('paid_at')->orderByDesc('id')->paginate($perPage);

            $kind = 'transactions';
            $rows = collect($pager->items())->map(function (PaymentTransaction $t) {
                $rev = $t->ownershipPlan?->cancellationReversal;
                $charges = $t->allocations->map(fn ($a) => $a->ledgerRow?->display_name ?? 'Charge')->all();

                return $this->planInfo($t->ownershipPlan) + [
                    'status' => $t->status ?? 'paid',
                    'status_label' => ucfirst(str_replace('_', ' ', (string) ($t->status ?? 'paid'))),
                    'reversal_reason' => $t->status === 'returned' ? $rev?->reason : null,
                    'reversed_by' => $rev?->reversedBy?->full_name,
                    'paid_at_label' => $t->paid_at?->format('d M Y') ?? '—',
                    'paid_at_full' => $t->paid_at?->format('d M Y, h:i A') ?? '—',
                    'paid_at_iso' => $t->paid_at?->format('Y-m-d') ?? null,
                    'charges' => $charges,
                    'charges_count' => count($charges),
                    'method_label' => str_replace('_', ' ', (string) ($t->payment_method ?? '—')),
                    'reference_no' => $t->reference_no,
                    'description' => $t->description,
                    'recorded_by' => $t->recordedBy?->full_name,
                    'charge_subtotal' => round((float) $t->charge_subtotal, 2),
                    'tax_amount' => round((float) $t->tax_amount, 2),
                    'late_fee_amount' => round((float) $t->late_fee_amount, 2),
                    'tax_rate_percent' => round((float) $t->tax_rate_percent, 2),
                    'amount_paid' => round((float) $t->amount_paid, 2),
                    'receipt_url' => route('ownership_payment_plans.payments.receipt', $t),
                    'receipt_print_url' => route('ownership_payment_plans.payments.receipt', ['transaction' => $t, 'print' => 1]),
                    'download_url' => route('ownership_payment_plans.payments.receipt.download', $t),
                ];
            });
        }

        return response()->json([
            'ok' => true,
            'kind' => $kind,
            'cards' => [
                'all' => ['count' => (int) $paid->c, 'total' => round((float) $paid->t, 2)],
                'overdue' => ['count' => (int) $over->c, 'plans' => (int) $over->p, 'total' => round((float) $over->t, 2)],
                'refunds' => ['count' => (int) $ref->c, 'total' => round((float) $ref->t, 2)],
            ],
            'rows' => $rows,
            // The very same pagination the other list pages of the app use
            // (forum, properties, favourites): bootstrap-5 with the query string.
            'pagination_html' => $pager->withQueryString()->links('pagination::bootstrap-5')->toHtml(),
            'pagination' => [
                'current_page' => $pager->currentPage(),
                'last_page' => $pager->lastPage(),
                'from' => $pager->firstItem(),
                'to' => $pager->lastItem(),
                'total' => $pager->total(),
            ],
        ]);
    }

    /** Permission check for the slug; returns its config. */
    private function page(string $slug, User $user): array
    {
        $page = self::PAGES[$slug] ?? abort(404);
        abort_unless($user->can($page['permission']), 403);

        return $page;
    }

    /** null = every society (Super Admin and their created users), array = only these. */
    private function allowedSocietyIds(User $user): ?array
    {
        if ($user->hasRole('Super Admin')) {
            return null;
        }

        $access = SocietyAccessResolver::resolver($user);

        return $access['isSocietyScoped'] ? $access['ownedSocietyIds']->all() : null;
    }

    /** Access scope + society filter, as a closure for whereHas('ownershipPlan', ...). */
    private function planScope(Request $request, string $slug, User $user): Closure
    {
        if ($slug === 'my_payments') {
            // Only the ownership that is active right now belongs to the member.
            // Ended ownerships are the previous owners' records and must never
            // leak into "My Payments".
            return fn (Builder $plan) => $plan->whereHas('propertyOwner', fn ($o) => $o
                ->where('owner_id', $user->id)
                ->whereNotNull('active_marker'));
        }

        $ids = $this->allowedSocietyIds($user);

        // A society_id outside the allowed set is ignored.
        if (($sid = $request->integer('society_id')) && ($ids === null || in_array($sid, $ids, true))) {
            $ids = [$sid];
        }

        return function (Builder $plan) use ($ids) {
            if ($ids === null) {
                return;
            }

            $plan->whereHasMorph('propertyOwnerable', [Property::class, PropertyUnit::class],
                function ($q) use ($ids) {
                    $q->getModel() instanceof PropertyUnit
                      ? $q->whereHas('floor.property.block', fn ($b) => $b->whereIn('society_id', $ids))
                      : $q->whereHas('block', fn ($b) => $b->whereIn('society_id', $ids));
                });
        };
    }

    /** Scope + owner/property/society-owner + size filters, all at plan level. */
    private function planFilter(Request $request, string $slug, User $user): Closure
    {
        $scope = $this->planScope($request, $slug, $user);
        $owner = $slug === 'all_transactions' ? trim((string) $request->input('owner', '')) : '';
        $property = trim((string) $request->input('property', ''));
        $societyOwner = $user->hasRole('Super Admin') ? $request->integer('society_owner') : 0;
        $sizeIds = $this->sizePlanIds($request, $scope);

        return function (Builder $plan) use ($scope, $owner, $property, $societyOwner, $sizeIds) {
            $plan->where($scope);

            if ($owner !== '') {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $owner).'%';
                $plan->whereHas('propertyOwner.owner', fn ($u) => $u
                    ->whereRaw("CONCAT(first_name,' ',last_name) like ?", [$like]));
            }

            if ($property !== '') {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $property).'%';

                // One field matches either the property name or its number.
                $plan->whereHasMorph('propertyOwnerable', [Property::class, PropertyUnit::class],
                    function ($q) use ($like) {
                        if ($q->getModel() instanceof PropertyUnit) {
                            $q->where('unit_name', 'like', $like);
                        } else {
                            $q->where(fn (Builder $w) => $w
                                ->where('name', 'like', $like)
                                ->orWhere('property_no', 'like', $like));
                        }
                    });
            }

            if ($societyOwner > 0) {
                $plan->whereHasMorph('propertyOwnerable', [Property::class, PropertyUnit::class],
                    function ($q) use ($societyOwner) {
                        $q->getModel() instanceof PropertyUnit
                          ? $q->whereHas('floor.property.block.society', fn ($s) => $s->where('owner_id', $societyOwner))
                          : $q->whereHas('block.society', fn ($s) => $s->where('owner_id', $societyOwner));
                    });
            }

            if ($sizeIds !== null) {
                $plan->whereIn('id', $sizeIds);
            }
        };
    }

    /** Area is derived from dimensions, so the size filter is resolved in PHP. */
    private function sizePlanIds(Request $request, Closure $scope): ?array
    {
        $min = $request->filled('size_min') ? (float) $request->input('size_min') : null;
        $max = $request->filled('size_max') ? (float) $request->input('size_max') : null;

        if ($min === null && $max === null) {
            return null;
        }

        $sqYd = $request->input('size_unit') === 'square_yard';
        $ranges = app(PropertyRangeService::class);

        return PropertyReservedPaymentPlan::query()->where($scope)->with('propertyOwnerable')->get()
            ->filter(function ($plan) use ($min, $max, $sqYd, $ranges) {
                $o = $plan->propertyOwnerable;
                if (! $o) {
                    return false;
                }
                $area = $sqYd ? GetArea::calculate($o->dimensions) / 9 : $ranges->areaInMarla($o);

                return ($min === null || $area >= $min) && ($max === null || $area <= $max);
            })->pluck('id')->all();
    }

    /** Explicit From/To win; otherwise the period (week/month/year, default month). */
    private function dateRange(Request $request): array
    {
        if ($request->filled('from') || $request->filled('to')) {
            return [
                $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : Carbon::create(1970),
                $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfDay(),
            ];
        }

        $days = self::PERIOD_DAYS[$request->input('period', 'month')] ?? 30;

        return [now()->subDays($days - 1)->startOfDay(), now()->endOfDay()];
    }

    private function morphEager(): Closure
    {
        return fn (MorphTo $m) => $m->morphWith([
            Property::class => ['block.society'],
            PropertyUnit::class => ['floor.property.block.society'],
        ]);
    }

    private function planInfo(?PropertyReservedPaymentPlan $plan): array
    {
        $o = $plan?->propertyOwnerable;
        $block = $o instanceof PropertyUnit ? $o->floor?->property?->block : $o?->block;
        $name = $o ? trim((string) ($o->name ?? '')) : '';
        $no = $o ? ($o->property_no ?? $o->unit_no ?? null) : null;

        return [
            'property' => $o ? trim(($o->name ?? '').' '.($o->property_no ?? $o->unit_no ?? '')) : '—',
            // Name and number on their own, so a table can show them in two lines.
            'property_name' => $name !== '' ? $name : '—',
            'property_no' => $no,
            'society' => $block?->society?->name ?? '—',
            'owner' => $plan?->propertyOwner?->owner?->full_name ?? '—',
        ];
    }

    /**
     * Payment methods accepted by the ledger service. Kept here so the history
     * filter can validate its input without duplicating the enum list.
     */
    private const PAYMENT_METHODS = [
        'cash', 'cheque', 'bank_transfer', 'card', 'mobile_wallet',
        'adjustment', 'other',
    ];

    /**
     * Record a payment (full, partial or multi-charge) against a committed
     * ownership plan's ledger.
     *
     * Two application modes, both carrying a user-editable amount:
     *   amount  - spread the amount over every outstanding charge in order.
     *   charges - spread the same amount over the selected charges only.
     */
    public function store(Request $request, PropertyReservedPaymentPlan $snapshot)
    {
        $validator = Validator::make($request->all(), [
            'payment_mode' => 'required|in:amount,charges',
            'amount' => 'required|numeric|gt:0',
            'payment_method' => 'required|in:cash,cheque,bank_transfer,card,mobile_wallet,adjustment,other',
            'paid_at' => 'nullable|date',
            'reference_no' => 'nullable|required_unless:payment_method,cash|string|max:255',
            'description' => 'nullable|string|max:1000',
            'ledger_ids' => 'required_if:payment_mode,charges|array|min:1',
            'ledger_ids.*' => 'integer|distinct',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Please check the payment form: '.collect($validator->errors()->all())->implode(' '));
        }

        $mode = $request->input('payment_mode');

        $targetIds = $mode === 'charges'
          ? array_values(array_unique(array_map('intval', (array) $request->input('ledger_ids', []))))
          : null;

        try {
            $transaction = app(PaymentLedgerService::class)->applyPayment(
                $snapshot,
                $request->all(),
                $targetIds
            );

            $currency = $snapshot->price_unit ?? 'PKR';
            $scope = $targetIds === null
              ? ''
              : ' across '.count($targetIds).' selected charge'.(count($targetIds) === 1 ? '' : 's');
            $tax = (float) $transaction->tax_amount;
            $taxNote = $tax > 0
              ? ' (Incl. FBR tax '.number_format($tax, 2).' '.$currency.')'
              : '';
            $lateFee = (float) $transaction->late_fee_amount;
            $lateFeeNote = $lateFee > 0
              ? ' (Incl. late fee '.number_format($lateFee, 2).' '.$currency.')'
              : '';

            $redirect = redirect()->back()->with(
                'success',
                'Payment of '.number_format((float) $transaction->amount_paid, 2).' '.$currency
                .$taxNote.$lateFeeNote.' recorded'.$scope.' (Ref '.($request->input('reference_no') ?: '-').').'
            );

            // Print through the shared hidden frame on the page we return to,
            // exactly like the receipt button in payment history. The plain
            // receipt URL is used because the frame triggers printing itself.
            if ($request->boolean('print_receipt')) {
                $redirect->with(
                    'print_document_url',
                    route('ownership_payment_plans.payments.receipt', $transaction)
                );
            }

            return $redirect;
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Live payment preview: what the current amount would settle, the FBR tax
     * and any late fees. Read-only - nothing is written here.
     */
    public function lateFeePreview(Request $request, PropertyReservedPaymentPlan $snapshot): JsonResponse
    {
        app(PaymentVisibilityService::class)->authorizePlan($snapshot, $request->user());

        $validator = Validator::make($request->all(), [
            'payment_mode' => 'required|in:amount,charges',
            'amount' => 'required|numeric|gt:0',
            'paid_at' => 'nullable|date',
            'ledger_ids' => 'required_if:payment_mode,charges|array|min:1',
            'ledger_ids.*' => 'integer|distinct',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => collect($validator->errors()->all())->implode(' '),
            ], 422);
        }

        $targetIds = $request->input('payment_mode') === 'charges'
          ? array_values(array_unique(array_map('intval', (array) $request->input('ledger_ids', []))))
          : null;

        try {
            $preview = app(PaymentLedgerService::class)->previewPayment(
                $snapshot,
                $request->all(),
                $targetIds
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true] + $preview);
    }

    /**
     * Printable receipt for ONE transaction.
     *
     * Renders only the charges this transaction actually paid - never the
     * plan's other unpaid charges - and never creates or mutates anything.
     */
    public function receipt(Request $request, PaymentTransaction $transaction)
    {
        $snapshot = $transaction->ownershipPlan;
        abort_unless($snapshot, 404);

        // A member may only print a receipt for their own payments.
        app(PaymentVisibilityService::class)->authorizePlan($snapshot, $request->user());

        $lines = $this->receiptLines($transaction);

        return view('components.property.payment-receipt', [
            'snapshot' => $snapshot,
            'transaction' => $transaction,
            'lines' => $lines,
            'planData' => $this->planData($snapshot),
            'owner' => $snapshot->propertyOwner?->owner,
            'currency' => $snapshot->price_unit ?? 'PKR',
            'autoPrint' => $request->boolean('print'),
            'backUrl' => $this->backUrl($snapshot),
        ]);
    }

    /**
     * Every recorded transaction for a plan, for the Payment History modal.
     *
     * Returns JSON rather than a re-rendered page so the modal can filter,
     * page and refresh without reloading the property details page. Reads
     * existing rows only - nothing here creates or mutates a payment.
     *
     * Scope 'current' limits the rows to the plan being viewed. Scope 'all'
     * widens it to every ownership this property or unit has ever had, so
     * payments made by previous owners are listed alongside the current one.
     *
     * A society member only ever reads their OWN records: the plan they are
     * opening has to be one of their ownerships, and the widened scope stops
     * at the plans of their own ownerships too.
     *
     * Filters (all optional): q, method, from, to, per_page.
     */
    public function history(Request $request, PropertyReservedPaymentPlan $snapshot): JsonResponse
    {
        $visibility = app(PaymentVisibilityService::class);
        $viewer = $request->user();

        // A member may only read the payments of their own ownership.
        $visibility->authorizePlan($snapshot, $viewer);

        $perPage = min(200, max(5, (int) $request->input('per_page', 25)));

        $scope = $request->input('scope') === 'all' ? 'all' : 'current';
        $planIds = $scope === 'all' ? $this->siblingPlanIds($snapshot, $viewer) : [$snapshot->id];

        $query = PaymentTransaction::query()
            ->whereIn('ownership_payment_plan_id', $planIds)
            ->with([
                'allocations' => fn ($q) => $q->withTrashed(),
                'allocations.ledgerRow',
                'recordedBy',
                'ownershipPlan.cancellationReversal.reversedBy',
                'ownershipPlan.propertyOwner.owner',
                'ownershipPlan.propertyOwnerable' => $this->morphEager(),
            ]);

        if ($search = trim((string) $request->input('q', ''))) {
            $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

            $query->where(function (Builder $q) use ($needle) {
                $q->where('reference_no', 'like', $needle)
                    ->orWhereHas('allocations.ledgerRow', fn ($l) => $l->where('display_name', 'like', $needle));
            });
        }

        if ($method = $request->input('method')) {
            if (in_array($method, self::PAYMENT_METHODS, true)) {
                $query->where('payment_method', $method);
            }
        }

        if ($request->filled('from')) {
            $query->whereDate('paid_at', '>=', Carbon::parse($request->input('from'))->toDateString());
        }

        if ($request->filled('to')) {
            $query->whereDate('paid_at', '<=', Carbon::parse($request->input('to'))->toDateString());
        }

        // Totals are computed over the whole filtered set, not just the visible
        // page, so the modal footer always shows what the filters actually match.
        // Reversed/unpaid transactions are listed for the record but never counted.
        $totals = (clone $query)
            ->where('status', 'paid')
            ->selectRaw('COALESCE(SUM(amount_paid), 0) as total_paid')
            ->selectRaw('COALESCE(SUM(charge_subtotal), 0) as total_charges')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) as total_tax')
            ->selectRaw('COALESCE(SUM(late_fee_amount), 0) as total_late_fee')
            ->selectRaw('COUNT(*) as total_count')
            ->first();

        $rows = $query
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'ok' => true,
            'scope' => $scope,
            'currency' => $snapshot->price_unit ?? 'PKR',
            'summary' => [
                'count' => (int) ($totals->total_count ?? 0),
                'charges' => round((float) ($totals->total_charges ?? 0), 2),
                'tax' => round((float) ($totals->total_tax ?? 0), 2),
                'late_fee' => round((float) ($totals->total_late_fee ?? 0), 2),
                'total' => round((float) ($totals->total_paid ?? 0), 2),
            ],
            'transactions' => collect($rows->items())->map(function (PaymentTransaction $txn) use ($snapshot) {
                $plan = $txn->ownershipPlan;
                $owner = $plan?->propertyOwner;
                $reversal = $plan?->cancellationReversal;

                return $this->planInfo($plan) + [
                    'id' => $txn->id,
                    'uuid' => $txn->uuid,
                    'status' => $txn->status ?? 'paid',
                    'status_label' => ucfirst(str_replace('_', ' ', (string) ($txn->status ?? 'paid'))),
                    'reversal_reason' => $txn->status === 'returned' ? $reversal?->reason : null,
                    'reversed_by' => $reversal?->reversedBy?->full_name,
                    'reversed_at' => $reversal?->reversed_at?->format('d M Y H:i'),
                    'paid_at' => $txn->paid_at?->format('Y-m-d'),
                    'paid_at_label' => $txn->paid_at?->format('d M Y') ?? '—',
                    'paid_at_full' => $txn->paid_at?->format('d M Y, h:i A') ?? '—',
                    'charges' => $txn->allocations->map(fn ($a) => $a->ledgerRow?->display_name ?? 'Charge')->all(),
                    'method' => $txn->payment_method,
                    'method_label' => str_replace('_', ' ', (string) ($txn->payment_method ?? '—')),
                    'reference_no' => $txn->reference_no,
                    'recorded_by' => $txn->recordedBy?->full_name,
                    'charge_subtotal' => round((float) $txn->charge_subtotal, 2),
                    'tax_amount' => round((float) $txn->tax_amount, 2),
                    'late_fee_amount' => round((float) $txn->late_fee_amount, 2),
                    'tax_rate_percent' => round((float) $txn->tax_rate_percent, 2),
                    'amount_paid' => round((float) $txn->amount_paid, 2),
                    'description' => $txn->description,
                    // Which ownership the money was received under, so a row
                    // from a previous owner is identifiable in the "all" scope.
                    'is_current_plan' => $plan?->id === $snapshot->id,
                    'owner_name' => $owner?->owner?->full_name,
                    'ownership_period' => $owner
                      ? trim(($owner->started_at?->format('d M Y') ?? '').' → '.($owner->ended_at?->format('d M Y') ?? 'ongoing'), '→')
                      : null,
                    'receipt_url' => route('ownership_payment_plans.payments.receipt', $txn),
                    'receipt_print_url' => route('ownership_payment_plans.payments.receipt', ['transaction' => $txn, 'print' => 1]),
                    'download_url' => route('ownership_payment_plans.payments.receipt.download', $txn),
                ];
            }),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
                'total' => $rows->total(),
            ],
            // The same pagination markup the other list pages of the app use.
            'pagination_html' => $rows->withQueryString()->links('pagination::bootstrap-5')->toHtml(),
        ]);
    }

    /**
     * Every committed plan for the same property or unit as the given plan
     * that the viewer is allowed to read, including the given plan itself.
     *
     * This is what makes "all payments" reach beyond the current ownership.
     * Falls back to just the given plan when the ownerable cannot be resolved,
     * so the history modal never widens to a different property by accident.
     *
     * @return list<int>
     */
    private function siblingPlanIds(PropertyReservedPaymentPlan $snapshot, ?User $viewer): array
    {
        $ownerable = $snapshot->propertyOwnerable;

        if (! $ownerable) {
            return [$snapshot->id];
        }

        $ids = app(PaymentVisibilityService::class)->visiblePlanIds($ownerable, $viewer);

        $ids[] = $snapshot->id;

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Download one transaction's receipt as a PDF file.
     *
     * Renders the same charges-only receipt as receipt(), but through DomPDF so
     * the file can be saved or emailed rather than printed from the browser.
     */
    public function downloadReceipt(Request $request, PaymentTransaction $transaction)
    {
        $snapshot = $transaction->ownershipPlan;
        abort_unless($snapshot, 404);

        // A member may only download a receipt for their own payments.
        app(PaymentVisibilityService::class)->authorizePlan($snapshot, $request->user());

        $lines = $this->receiptLines($transaction);

        // Standalone view (no app layout): DomPDF can't run the layout's CSS/JS,
        // so using the layout view put the sidebar and navbar into the PDF.
        $pdf = Pdf::loadView('components.property.payment-receipt', [
            'snapshot' => $snapshot,
            'transaction' => $transaction,
            'lines' => $lines,
            'planData' => $this->planData($snapshot),
            'owner' => $snapshot->propertyOwner?->owner,
            'currency' => $snapshot->price_unit ?? 'PKR',
        ])->setPaper('a4', 'portrait');

        $filename = 'receipt-'.str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * The charge lines a receipt prints.
     *
     * Reversed payments soft-delete their allocations, so they must be loaded
     * with their trashed rows to keep the original charges on the receipt.
     */
    private function receiptLines(PaymentTransaction $transaction): array
    {
        $transaction->load([
            'allocations' => function ($query) use ($transaction) {
                if ($transaction->status === 'returned') {
                    $query->withTrashed();
                }

                $query->orderBy('id');
            },
            'allocations.ledgerRow',
            'lateFees',
            'recordedBy',
            'ownershipPlan.propertyOwner.owner',
        ]);

        $tax = (float) $transaction->tax_amount;

        // A fully returned payment zeroes its stored totals, but its soft-deleted
        // allocations still carry the tax that was originally paid on each charge.
        if ($transaction->status === 'returned') {
            $paidTax = round((float) $transaction->allocations
                ->sum(fn ($allocation) => (float) ($allocation->tax_amount ?? 0)), 2);

            if ($paidTax > 0) {
                $tax = $paidTax;
            }
        }

        $taxShares = app(PaymentLedgerService::class)->allocationTaxShares(
            $transaction->allocations,
            $tax
        );

        return $transaction->allocations->values()->map(fn ($allocation, $i) => [
            'ledger_id' => $allocation->property_payment_ledger_id,
            'name' => $allocation->ledgerRow?->display_name ?? 'Charge',
            'charge_type' => $allocation->ledgerRow?->charge_type,
            'amount' => round((float) $allocation->amount_allocated, 2),
            'tax' => $taxShares[$i] ?? 0.0,
        ])->all();
    }

    /**
     * Printable payment report for a date range.
     *
     * Reads existing ledger and transaction rows only. Charge status is derived
     * from the actual allocations dated on or before the range end, so a charge
     * is never shown as paid just because it exists in the plan.
     */
    public function printReport(Request $request, PropertyReservedPaymentPlan $snapshot)
    {
        // Printing a period report is its own permission.
        abort_unless($request->user()->can('payment_print_by_period'), 403);

        // A member may only print the report of their own plan.
        app(PaymentVisibilityService::class)->authorizePlan($snapshot, $request->user());

        [$from, $to, $preset] = $this->resolveDateRange($request);

        $transactions = PaymentTransaction::query()
            ->where('ownership_payment_plan_id', $snapshot->id)
            ->whereBetween('paid_at', [$from, $to])
            ->with('allocations.ledgerRow')
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();

        $service = app(PaymentLedgerService::class);
        $ledgerRows = $snapshot->ledgerRows()->orderBy('sequence_order')->get();

        $charges = $ledgerRows->map(function ($row) use ($to) {
            $paidToDate = round((float) PaymentTransactionAllocation::query()
                ->where('property_payment_ledger_id', $row->id)
                ->whereHas('transaction', fn ($q) => $q->where('paid_at', '<=', $to))
                ->sum('amount_allocated'), 2);

            $due = round((float) $row->amount_due, 2);
            $remaining = round($due - $paidToDate, 2);

            return [
                'name' => $row->display_name,
                'due_date' => $row->due_date,
                'amount' => $due,
                'paid' => $paidToDate,
                'remaining' => max(0, $remaining),
                'status' => $paidToDate <= 0 ? 'unpaid' : ($remaining <= 0.004 ? 'paid' : 'partial'),
            ];
        });

        $summary = $service->summary($snapshot);

        return view('components.property.payment-report', [
            'snapshot' => $snapshot,
            'planData' => $this->planData($snapshot),
            'owner' => $snapshot->propertyOwner?->owner,
            'currency' => $snapshot->price_unit ?? 'PKR',
            'from' => $from,
            'to' => $to,
            'preset' => $preset,
            'transactions' => $transactions,
            'charges' => $charges,
            'summary' => $summary,
            'autoPrint' => $request->boolean('print'),
            'backUrl' => $this->backUrl($snapshot),
        ]);
    }

    /**
     * Where the "back" link on a printed document should go.
     *
     * These documents are opened from the Payments tab of the property/unit
     * details page, which has no dedicated index route of its own. Null when the
     * ownerable has no UUID, in which case the views omit the link rather than
     * guessing a route.
     */
    private function backUrl(PropertyReservedPaymentPlan $snapshot): ?string
    {
        $uuid = $snapshot->propertyOwnerable?->uuid;

        return $uuid ? route('owner.property.details', $uuid) : null;
    }

    /**
     * Property identity for print documents, reusing the presenter's labels so
     * the printed report matches the printed plan.
     */
    private function planData(PropertyReservedPaymentPlan $snapshot): array
    {
        try {
            return PaymentPlanPresenter::buildFromSnapshot($snapshot);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon, 2: string}
     */
    private function resolveDateRange(Request $request): array
    {
        $preset = (string) $request->input('preset', 'month');

        if ($preset === 'custom' && $request->filled('from') && $request->filled('to')) {
            $from = Carbon::parse($request->input('from'))->startOfDay();
            $to = Carbon::parse($request->input('to'))->endOfDay();

            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }

            return [$from, $to, $preset];
        }

        $preset = in_array($preset, ['day', 'week', 'fifteen', 'month'], true) ? $preset : 'month';
        $to = now()->endOfDay();

        $from = match ($preset) {
            'day' => now()->startOfDay(),
            'week' => now()->subDays(6)->startOfDay(),
            'fifteen' => now()->subDays(14)->startOfDay(),
            default => now()->subMonth()->startOfDay(),
        };

        return [$from, $to, $preset];
    }

    /**
     * Generate (or regenerate) the full payment ledger of a committed plan —
     * all charges and their due dates. Rebuilds only while no payments exist.
     */
    public function reschedule(Request $request, PropertyReservedPaymentPlan $snapshot)
    {
        if ($snapshot->isTransfer()) {
            return redirect()->back()->with('error', 'A transfer-fee plan has a fixed single charge and cannot be rescheduled.');
        }

        try {
            $service = app(PaymentLedgerService::class);
            $anchor = $request->filled('reschedule_at')
              ? \Illuminate\Support\Carbon::parse($request->input('reschedule_at'))
              : null;

            if (! $snapshot->ledgerRows()->exists()) {
                $service->createLedgerFromSnapshot($snapshot, force: false, anchor: $anchor);
                $message = 'Payments rescheduled — all charges and due dates have been generated.';
            } elseif ($snapshot->hasLivePayments()) {
                return redirect()->back()
                    ->with('error', 'Payments have already been recorded for this plan — the ledger cannot be reschedule.');
            } else {
                $service->rebuildFromSnapshot($snapshot, $anchor);
                $message = 'Payment charges reschedule with updated due dates.';
            }

            return redirect()->back()->with('success', $message);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reverse a set of charges picked in the ledger table.
     *
     * The selection is by CHARGE, so a charge that was settled as part of a bulk
     * payment can be un-paid on its own without disturbing the charges paid
     * alongside it. Scoped to the plan being viewed: a previous owner's charges
     * are listed in the "all payments" view but are not reversible from here.
     */
    public function reverseCharges(Request $request, PropertyReservedPaymentPlan $snapshot): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ledger_uuids' => 'required|array|min:1',
            'ledger_uuids.*' => 'string|distinct',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Select at least one charge to reverse.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $uuids = array_values(array_unique($request->input('ledger_uuids', [])));

        $rows = PropertyPaymentLedger::query()
            ->where('ownership_payment_plan_id', $snapshot->id)
            ->whereIn('uuid', $uuids)
            ->get();

        if ($rows->count() !== count($uuids)) {
            return response()->json([
                'ok' => false,
                'message' => 'One or more selected charges are not part of this payment plan.',
            ], 422);
        }

        $service = app(PaymentLedgerService::class);
        $wasCompleted = $snapshot->status === 'completed';

        try {
            $result = $service->reverseCharges($rows);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $count = $rows->count();
        $freshPlan = $snapshot->fresh();
        $summary = $service->summary($freshPlan);

        // Reopening a fully-paid plan puts the "Edit Values" controls back, but
        // they are rendered server-side - reload once so the reserved-plan tab
        // reflects the reopened state.
        $reload = $wasCompleted && $freshPlan->status !== 'completed';

        $removed = $result['removed'] > 0
          ? ' '.$result['removed'].' payment'.($result['removed'] === 1 ? ' was' : 's were').' fully reversed.'
          : '';

        $taxReleased = (float) ($result['tax_released'] ?? 0.0);
        $currency = $snapshot->price_unit ?? 'PKR';
        $taxNote = $taxReleased > 0
          ? ' FBR tax '.number_format($taxReleased, 2).' '.$currency.' refunded with '.($count === 1 ? 'it' : 'them').'.'
          : '';

        return response()->json([
            'ok' => true,
            'message' => $count.' charge'.($count === 1 ? '' : 's').' un-paid and reopened.'.$removed.$taxNote,
            'rows' => $result['rows'],
            'summary' => $this->summaryPayload($summary),
            'reload' => $reload,
        ]);
    }

    /**
     * The summary fields the payments panel refreshes in place after a change.
     */
    private function summaryPayload(array $summary): array
    {
        return [
            'total_paid' => $summary['total_paid'],
            'remaining' => $summary['remaining'],
            'paid_percent' => $summary['paid_percent'],
            'overdue' => $summary['by_status']['late'] + $summary['by_status']['current_due'],
            'next_due_date' => $summary['next_due_date']?->format('d M Y') ?? '—',
            'tax_rate_percent' => $summary['tax_rate_percent'],
            'tax_collected' => $summary['tax_collected'],
            'tax_remaining' => $summary['tax_remaining'],
            'total_received' => $summary['total_received'],
        ];
    }

    /**
     * Reverse a payment recorded against a single ledger charge. Returns JSON
     * so the payments section can update in place without a full page reload.
     */
    public function unpay(Request $request, PropertyPaymentLedger $row)
    {
        try {
            $txns = PaymentTransaction::query()
                ->whereHas('allocations', fn ($q) => $q->where('property_payment_ledger_id', $row->id))
                ->orderByDesc('paid_at')->orderByDesc('id')
                ->get();

            if ($txns->isEmpty()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No payments found to reverse for this charge.',
                ], 422);
            }

            $service = app(PaymentLedgerService::class);
            foreach ($txns as $txn) {
                $service->reversePayment($txn);
            }

            $plan = $row->ownershipPlan;
            if (! $plan) {
                return response()->json([
                    'ok' => false,
                    'message' => 'This charge is not linked to a payment plan.',
                ], 422);
            }

            $service->refreshStatuses($plan);
            $row->refresh();
            $summary = $service->summary($plan);

            return response()->json([
                'ok' => true,
                'message' => 'Payment reversed for '.$row->display_name.'.',
                'row' => [
                    'id' => $row->id,
                    'uuid' => $row->uuid,
                    'amount_paid' => (float) $row->amount_paid,
                    'remaining_balance' => (float) $row->remaining_balance,
                    'payment_status' => $row->payment_status,
                ],
                'summary' => $this->summaryPayload($summary),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
