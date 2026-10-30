<?php

namespace App\Http\Controllers;

use App\Helpers\SocietyAccessResolver;
use App\Models\PaymentLateFeeRule;
use App\Models\Society;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class PaymentLateFeeRuleController extends Controller
{
    public function index(Request $request)
    {
        $login_user = auth()->user();
        $societies_ids = $this->allowedSocietyIds($login_user);

        if ($request->ajax()) {
            return DataTables::of(
                PaymentLateFeeRule::with('society')
                    ->when($societies_ids !== null, fn ($q) => $q->whereIn('society_id', $societies_ids))
                    ->orderBy('id', 'desc')
            )
                ->addColumn('checkbox', fn ($rule) => '<input type="checkbox" class="form-check-input checkbox" value="'.$rule->id.'">')
                ->addColumn('society', fn ($rule) => $rule->society?->name ?? '—')
                ->addColumn('grace_period', fn ($rule) => $rule->isOpenEnded()
                  ? 'Beyond last tier'
                  : $rule->grace_period_days.' days')
                ->addColumn('amount', fn ($rule) => $rule->fine_type === PaymentLateFeeRule::TYPE_PERCENTAGE
                  ? rtrim(rtrim(number_format((float) $rule->fine_amount, 2, '.', ''), '0'), '.').'%'
                  : number_format((float) $rule->fine_amount))
                ->addColumn('actions', function ($rule) use ($login_user) {
                    $edit = '';
                    $delete = '';

                    if ($login_user->can('payment_late_fee_rule_edit')) {
                        $data = htmlspecialchars(json_encode([
                            'id' => $rule->id,
                            'society_id' => $rule->society_id,
                            'grace_period_days' => $rule->grace_period_days, // null = no limit
                            'fine_type' => $rule->fine_type,
                            'fine_amount' => $rule->fine_amount,
                        ]), ENT_QUOTES, 'UTF-8');

                        $edit = '<button type="button" class="btn btn-sm btn-outline-primary me-1 edit-late-fee-rule-btn" data-rule="'.$data.'" title="Edit">
                            <i class="ti tabler-edit"></i>
                        </button>';
                    }

                    if ($login_user->can('payment_late_fee_rule_delete')) {
                        $delete = '<form action="'.route('late_fee_rules.destroy', $rule->id).'" method="POST" style="display:inline;">'
                          .csrf_field().method_field('DELETE')
                          .'<button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(event)" title="Delete">
                                <i class="ti tabler-trash"></i>
                            </button>'
                          .'</form>';
                    }

                    return $edit.' '.$delete;
                })
                ->rawColumns(['checkbox', 'actions'])
                ->make(true);
        }

        $can_edit = $login_user->can('payment_late_fee_rule_edit');
        $can_delete = $login_user->can('payment_late_fee_rule_delete');

        $societies = Society::query()
            ->when($societies_ids !== null, fn ($q) => $q->whereIn('id', $societies_ids))
            ->orderBy('name')
            ->get();

        $societyRuleMeta = PaymentLateFeeRule::query()
            ->when($societies_ids !== null, fn ($q) => $q->whereIn('society_id', $societies_ids))
            ->get(['id', 'society_id', 'fine_type'])
            ->groupBy('society_id')
            ->map(fn ($group) => [
                'type' => $group->first()->fine_type,
                'count' => $group->count(),
            ]);

        $show_actions = $can_edit || $can_delete;
        $can_create = $login_user->can('payment_late_fee_rule_create');

        return view('content.payment_late_fee_rules.index', compact(
            'societies', 'show_actions', 'can_create', 'societyRuleMeta'
        ));
    }

    public function store(Request $request)
    {
        $login_user = auth()->user();
        $societies_ids = $this->allowedSocietyIds($login_user);
        $isPercentage = $request->input('fine_type') === PaymentLateFeeRule::TYPE_PERCENTAGE;

        abort_unless(
            $login_user->can($request->filled('id') ? 'payment_late_fee_rule_edit' : 'payment_late_fee_rule_create'),
            403,
            'Unauthorized.'
        );

        $feeAmountRules = ['required', 'numeric', 'gt:0'];
        if ($isPercentage) {
            $feeAmountRules[] = 'max:100';
        }

        $validated = $request->validate([
            'id' => ['nullable', 'integer', Rule::exists('payment_late_fee_rules', 'id')],
            'society_id' => ['required', 'integer', $this->societyRule($societies_ids)],
            // Empty = "No limit": the rule that applies beyond every other tier.
            'grace_period_days' => ['nullable', 'integer', 'min:0'],
            'fine_type' => ['required', 'in:'.PaymentLateFeeRule::TYPE_FIXED.','.PaymentLateFeeRule::TYPE_PERCENTAGE],
            'fine_amount' => $feeAmountRules,
        ], [
            'fine_amount.max' => 'Percentage must be between 0-100',
        ]);

        $existing = null;
        if (! empty($validated['id'])) {
            $existing = PaymentLateFeeRule::findOrFail($validated['id']);
            abort_unless($this->canAccessSociety($societies_ids, $existing->society_id), 403, 'Unauthorized.');
        }

        $grace = isset($validated['grace_period_days']) ? (int) $validated['grace_period_days'] : null;

        try {
            // The society's rules are locked while checking, so two requests
            // cannot both add the same grace period or a second null rule
            // (a unique index cannot protect NULL values).
            $result = DB::transaction(function () use ($validated, $existing, $grace) {
                $siblings = PaymentLateFeeRule::query()
                    ->where('society_id', $validated['society_id'])
                    ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                    ->lockForUpdate()
                    ->get();

                if ($siblings->contains(fn ($r) => $r->grace_period_days === $grace)) {
                    return ['ok' => false, 'message' => $grace === null
                      ? 'This society already has a rule with no grace limit (beyond all other tiers).'
                      : 'A late fee rule with this grace period already exists in the selected society.'];
                }

                // One society, one rule type.
                $mismatch = $siblings->first(fn ($r) => $r->fine_type !== $validated['fine_type']);
                if ($mismatch) {
                    return ['ok' => false, 'message' => 'This society already uses '.$mismatch->fine_type
                      .' rules. Delete its other rules before changing the type.'];
                }

                $attributes = [
                    'society_id' => $validated['society_id'],
                    'grace_period_days' => $grace,
                    'fine_type' => $validated['fine_type'],
                    'fine_amount' => $validated['fine_amount'],
                ];

                if ($existing) {
                    $existing->update($attributes);

                    return ['ok' => true, 'created' => false];
                }

                PaymentLateFeeRule::create($attributes);

                return ['ok' => true, 'created' => true];
            });
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) == 1062) {
                return back()->withInput()->with('error', 'A late fee rule with this grace period already exists in the selected society.');
            }

            report($e);

            return back()->withInput()->with('error', 'The late fee rule could not be saved.');
        }

        if (! $result['ok']) {
            return back()->withInput()->with('error', $result['message']);
        }

        return back()->with('success', $result['created']
          ? 'Late fee rule created successfully.'
          : 'Late fee rule updated successfully.');
    }

    public function destroy($id)
    {
        $login_user = auth()->user();
        $societies_ids = $this->allowedSocietyIds($login_user);

        $rule = PaymentLateFeeRule::findOrFail($id);
        abort_unless($this->canAccessSociety($societies_ids, $rule->society_id), 403, 'Unauthorized.');
        $rule->delete();

        return back()->with('success', 'Late fee rule deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        try {
            PaymentLateFeeRule::whereIn('id', $request->ids)->delete();

            return response()->json(['success' => true, 'message' => 'Selected Late Fee Rules deleted successfully.']);
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * null = every society (Super Admin, and users that are not society scoped),
     * array = only these. Same rule the payments pages use.
     */
    private function allowedSocietyIds(User $user): ?array
    {
        if ($user->hasRole('Super Admin')) {
            return null;
        }

        $access = SocietyAccessResolver::resolver($user);

        return ($access['isSocietyScoped'] ?? false)
          ? collect($access['ownedSocietyIds'] ?? [])->map(fn ($id) => (int) $id)->all()
          : null;
    }

    private function canAccessSociety(?array $ids, $societyId): bool
    {
        return $ids === null || in_array((int) $societyId, $ids, true);
    }

    /** Validation rule for society_id that respects the user's access. */
    private function societyRule(?array $ids)
    {
        return $ids === null ? Rule::exists('societies', 'id') : Rule::in($ids);
    }
}
