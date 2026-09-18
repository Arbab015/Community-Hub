<?php

namespace App\Http\Controllers;

use App\Helpers\SocietyAccessResolver;
use App\Models\Block;
use App\Models\BlockPaymentPlan;
use App\Models\BlockPriceRange;
use App\Models\InstallmentTemplate;
use App\Models\PaymentTemplate;
use App\Models\Property;
use App\Models\Society;
use App\Support\BlockPaymentPlanPresenter;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class BlocksController extends Controller
{
    public function index(Request $request)
    {
        $login_user = auth()->user();
        $scope = SocietyAccessResolver::resolver($login_user);
        $societies_ids = $scope['ownedSocietyIds'];
        abort_if(! auth()->user()->can('listing_block'), 403, 'Unauthorized.');
        if ($request->ajax()) {
            return DataTables::of(Block::whereIn('society_id', $societies_ids)->orderBy('id', 'desc'))
                ->addColumn('checkbox', function ($block) {
                    return '<input type="checkbox" class="form-check-input checkbox" title="Select Record" value="'.$block->id.'" data-uuid="'.$block->uuid.'">';
                })
                ->addColumn('society', function ($block) {
                    return $block->society->name;
                })
                ->addColumn('actions', function ($block) use ($login_user) {
                    $edit = '';
                    $delete = '';
                    $view = '';
                    $details = '';
                    $print = '';
                    if ($login_user->can('view_block')) {
                        $view = '<a href="'.route('block.view', $block->uuid).'" class="pe-2">
                        <i class="fa-solid fa-eye text-primary" role="button" title="View details"></i>
                    </a>';
                    }
                    if ($login_user->can('payment_plan_print')) {
                        $details = "<i class='fa-solid fa-circle-info text-info cursor-pointer me-2 block_details_btn'
                        title='Pricing and payment plans information'
                        data-uuid='{$block->uuid}'
                        data-name='".e($block->name)."'>
                    </i>";
                    }
                    if ($login_user->can('payment_plan_print')) {
                        $print = "<i class='fa-solid fa-print text-success cursor-pointer me-2 block_print_plan_btn'
                        title='Print Payment Plan'
                        data-uuid='{$block->uuid}'
                        data-name='".e($block->name)."'>
                    </i>";
                    }
                    if ($login_user->can('edit_block')) {
                        $edit = "<i class='fa-solid fa-pen-to-square text-primary edit_block_btn cursor-pointer me-2'
            title='Edit'
            data-id='{$block->id}'
            data-name='".e($block->name)."'
            data-society_id='{$block->society_id}'>
        </i>";
                    }
                    if ($login_user->can('delete_block')) {
                        $delete = '<form action="'.route('blocks.destroy', $block->uuid).'"
                          method="POST" style="display:inline;">
                          '.csrf_field().method_field('DELETE').'
                          <i class="fa-solid fa-trash text-danger cursor-pointer me-2" title="Delete"
                             onclick="confirmDelete(event)">
                          </i>
                       </form>';
                    }

                    return $view.''.$edit.'  '.$delete.' '.$print.' '.$details;
                })
                ->rawColumns(['checkbox', 'actions', 'society'])
                ->make(true);
        }
        $can_edit = $login_user->can('edit_block');
        $can_delete = $login_user->can('delete_block');
        $can_view = $login_user->can('view_block');
        $show_actions = $can_edit || $can_delete || $can_view;
        $societies = Society::whereIn('id', $societies_ids)->get();
        $payment_templates = PaymentTemplate::where('owner_id', auth()->id())->get();
        $installment_templates = InstallmentTemplate::where('owner_id', auth()->id())->get();

        return view('content.property_management.blocks.index',
            compact('show_actions', 'societies', 'payment_templates', 'installment_templates'));
    }

    public function store(Request $request)
    {
        $login_user = auth()->user();
        $scope = SocietyAccessResolver::resolver($login_user);
        $societies_ids = $scope['ownedSocietyIds'];
        $request->validate([
            'name' => 'required|string|max:255',
            'society_id' => ['required', 'integer', Rule::in($societies_ids)],
        ]);
        try {
            Block::updateOrCreate(
                [
                    'id' => $request->id,
                ],
                [
                    'name' => $request->name,
                    'society_id' => $request->society_id,
                ]
            );

            return back()->with('success', $request->id ? 'Block updated successfully.' : 'Block created successfully.');
        } catch (QueryException $e) {
            if ($e->errorInfo[1] == 1062) {
                return back()->withInput()->with('error', 'Block already exists in this society.');
            }
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy($uuid)
    {
        try {
            $block = block::where('uuid', $uuid)->first();
            if ($block->properties()->exists()) {
                return back()->with('error', 'Unable to delete this block due to existing property associations.');
            }
            $block->delete();

            return back()->with('success', 'Block deleted successfully. ');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function allIds(Request $request, $uuid)
    {
        $block = Block::where('uuid', $uuid)->firstOrFail();

        $ids = Property::where('block_id', $block->id)
            ->when($request->search_content, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search_content.'%')
                    ->orWhere('property_no', 'like', '%'.$request->search_content.'%');
            }))
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->pluck('uuid');

        return response()->json(['ids' => $ids]);
    }

    public function bulkDelete(Request $request)
    {
        try {
            $blocks = Block::whereIn('id', $request->ids)->get();
            $notDeleted = [];
            $deletedIds = [];

            foreach ($blocks as $block) {
                if ($block->properties()->exists()) {
                    $notDeleted[] = $block->name;
                } else {
                    $deletedIds[] = $block->id;
                }
            }

            if (! empty($deletedIds)) {
                Block::whereIn('id', $deletedIds)->delete();
            }

            return response()->json([
                'success' => true,
                'message' => count($deletedIds).' blocks deleted successfully.',
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Distinct payment templates currently used by the block's properties.
     */
    public function paymentPlanTemplates($uuid)
    {
        try {
            $block = Block::where('uuid', $uuid)
                ->with('properties.paymentPlan.paymentTemplate')
                ->firstOrFail();

            $templates = $block->properties
                ->filter(fn ($property) => $property->paymentPlan?->paymentTemplate)
                ->map(fn ($property) => $property->paymentPlan->paymentTemplate)
                ->unique('id')
                ->values()
                ->map(fn ($template) => [
                    'id' => $template->id,
                    'name' => $template->name,
                ]);

            return response()->json(['templates' => $templates]);
        } catch (\Exception $e) {
            return response()->json(['templates' => [], 'message' => $e->getMessage()], 500);
        }
    }

    public function printPaymentPlans(Request $request, $uuid)
    {
        $request->validate([
            'plan_ids' => 'required|array|min:1',
            'plan_ids.*' => 'integer',
        ]);

        try {
            $block = Block::where('uuid', $uuid)->firstOrFail();

            return redirect()->route('payment-plans.print', [
                'block' => $block->uuid,
                'plan_ids' => $request->plan_ids,
            ]);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Quick check used by the print modal to show/hide the print button live.
     */
    public function printBlockPlanAvailable(Request $request, Block $block)
    {
        $plan = $this->printPlanContext($request, $block);

        return response()->json([
            'available' => $plan['ok'],
            'message' => $plan['message'],
        ]);
    }

    public function printBlockPlan(Request $request, Block $block)
    {
        $plan = $this->printPlanContext($request, $block);
        abort_unless($plan['ok'], 404, $plan['message']);

        try {
            $data = BlockPaymentPlanPresenter::build($block, $plan['blockPlan'], $plan['range'], $plan['size'], $plan['features'] ?? []);

            $user = auth()->user();
            $printedByName = trim(($user?->first_name ?? '').' '.($user?->last_name ?? '')) ?: null;
            $printedByRole = $user?->getRoleNames()->join(', ') ?: null;

            return view('components.block.payment-plans-print', [
                'block' => $block,
                'blockPlan' => $plan['blockPlan'],
                'data' => $data,
                'size' => $plan['size'],
                'unit' => $plan['unit'],
                'isCommercial' => $plan['isCommercial'],
                'printedByName' => $printedByName,
                'printedByRole' => $printedByRole,
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'Unable to generate the print view: '.$e->getMessage());
        }
    }

    /**
     * Validates the print request and resolves the matching price range and block
     * payment plan. Returns ok=false with a human friendly message when unavailable.
     */
    private function printPlanContext(Request $request, Block $block): array
    {
        $request->validate([
            'type' => 'required|in:commercial,non_commercial',
            'size' => 'required|numeric|min:0',
            'unit' => 'required|in:marla,square_yard',
        ]);

        $plan = [
            'isCommercial' => $request->input('type') === 'commercial',
            'size' => (float) $request->input('size'),
            'unit' => $request->input('unit'),
            'features' => $this->parseFeatures($request->input('features')),
        ];
        $size = $plan['size'];
        $unit = $plan['unit'];
        $typeLabel = $plan['isCommercial'] ? 'Commercial' : 'Non-Commercial';

        try {
            $range = BlockPriceRange::where('block_id', $block->id)->where('price_unit', $unit)->get()
                ->first(fn ($r) => ($r->min_size === null || $size >= (float) $r->min_size)
                  && ($r->max_size === null || $size <= (float) $r->max_size));
            if (! $range) {
                return $plan + ['ok' => false, 'message' => "Payment plan not available for this size ({$size} ".ucwords($unit).').'];
            }

            $blockPlan = $block->blockPaymentPlans()->where('is_commercial', $plan['isCommercial'])->first();
            if (! $blockPlan || (! $blockPlan->payment_template_id && empty($blockPlan->installment_template_ids))) {
                return $plan + ['ok' => false, 'message' => "No payment plan is configured for {$typeLabel} properties in this block yet."];
            }

            $inRange = ($blockPlan->min_size === null || $size >= (float) $blockPlan->min_size)
              && ($blockPlan->max_size === null || $size <= (float) $blockPlan->max_size);
            if (! $inRange) {
                return $plan + ['ok' => false, 'message' => "The configured {$typeLabel} payment plan does not cover this size ({$size} ".ucwords($unit).').'];
            }

            return $plan + ['ok' => true, 'message' => 'Plan available for this size.', 'range' => $range, 'blockPlan' => $blockPlan];
        } catch (\Exception $e) {
            return $plan + ['ok' => false, 'message' => 'Unable to check payment plan availability: '.$e->getMessage()];
        }
    }

    /**
     * Keeps only the feature keys we actually support, from either a
     * comma-separated string ("corner_premium,park_facing_premium") or an array.
     */
    private function parseFeatures($input): array
    {
        $valid = ['corner_premium', 'park_facing_premium', 'main_boulevard_premium'];
        $features = is_string($input) ? explode(',', $input) : (is_array($input) ? $input : []);

        return array_values(array_filter($features, fn ($f) => in_array($f, $valid, true)));
    }

    /**
     * Price ranges and configured payment plans for a single block (used by the details modal).
     */
    public function details($uuid)
    {
        try {
            $block = Block::where('uuid', $uuid)->firstOrFail();

            $priceRanges = $block->propertyPriceRanges->map(fn ($range) => [
                'min_size' => $range->min_size,
                'max_size' => $range->max_size,
                'price' => $range->price,
                'price_unit' => $range->price_unit,
            ]);

            $paymentPlans = $block->blockPaymentPlans()->with('paymentTemplate')->get()->map(function ($plan) {
                $installmentNames = empty($plan->installment_template_ids)
                  ? []
                  : InstallmentTemplate::whereIn('id', $plan->installment_template_ids)->pluck('name')->toArray();

                return [
                    'min_size' => $plan->min_size,
                    'max_size' => $plan->max_size,
                    'size_unit' => $plan->size_unit,
                    'is_commercial' => $plan->is_commercial,
                    'payment_template' => $plan->paymentTemplate ? [
                        'id' => $plan->paymentTemplate->id,
                        'name' => $plan->paymentTemplate->name,
                    ] : null,
                    'installment_templates' => $installmentNames,
                    'apply_to_units' => $plan->apply_to_units,
                ];
            });

            return response()->json([
                'priceRanges' => $priceRanges,
                'paymentPlans' => $paymentPlans,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'priceRanges' => [],
                'paymentPlans' => [],
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Applies a commercial and/or non-commercial payment plan to every selected block.
     */
    public function applyBlockPlan(Request $request)
    {
        $request->validate([
            'block_ids' => 'required|array|min:1',
            'block_ids.*' => 'integer',
            'commercial_enabled' => 'nullable|boolean',
            'commercial_payment_template_id' => 'nullable|exists:payment_templates,id',
            'commercial_installment_template_ids' => 'nullable|array',
            'commercial_installment_template_ids.*' => 'exists:installment_templates,id',
            'commercial_size_range' => 'nullable|string',
            'commercial_size_unit' => 'nullable|in:marla,square_yard',
            'commercial_apply_to_units' => 'nullable|boolean',
            'non_commercial_enabled' => 'nullable|boolean',
            'non_commercial_payment_template_id' => 'nullable|exists:payment_templates,id',
            'non_commercial_installment_template_ids' => 'nullable|array',
            'non_commercial_installment_template_ids.*' => 'exists:installment_templates,id',
            'non_commercial_size_range' => 'nullable|string',
            'non_commercial_size_unit' => 'nullable|in:marla,square_yard',
            'non_commercial_apply_to_units' => 'nullable|boolean',
        ]);

        if (! $request->boolean('commercial_enabled') && ! $request->boolean('non_commercial_enabled')) {
            return back()->with('error', 'Please enable at least one section (Commercial or Non-Commercial).');
        }

        try {
            DB::beginTransaction();

            $applied = 0;
            $skippedBlocks = [];

            foreach ($request->block_ids as $blockId) {
                $block = Block::find($blockId);
                if (! $block) {
                    $skippedBlocks[] = $blockId;

                    continue;
                }

                foreach (['commercial' => true, 'non_commercial' => false] as $section => $isCommercial) {
                    if (! $request->boolean("{$section}_enabled")) {
                        continue;
                    }

                    $applied += $this->applyBlockPlanForType(
                        $block,
                        $isCommercial,
                        $request->input("{$section}_payment_template_id"),
                        $request->input("{$section}_installment_template_ids", []),
                        $request->input("{$section}_size_range"),
                        $request->input("{$section}_size_unit", 'marla'),
                        $request->boolean("{$section}_apply_to_units")
                    );
                }
            }

            DB::commit();

            $msg = "Payment plan applied to {$applied} record(s) across ".count($request->block_ids).' block(s).';
            if (! empty($skippedBlocks)) {
                $msg .= ' Skipped '.count($skippedBlocks).' invalid block(s).';
            }

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return back()->with('error', 'An error occurred: '.$e->getMessage());
        }
    }

    private function applyBlockPlanForType(Block $block, bool $isCommercial, ?int $paymentTemplateId, array $installmentTemplateIds, ?string $sizeRange, string $sizeUnit, bool $applyToUnits): int
    {
        $planData = [
            'block_id' => $block->id,
            'is_commercial' => $isCommercial,
            'payment_template_id' => $paymentTemplateId,
            'installment_template_ids' => $installmentTemplateIds,
            'size_unit' => $sizeUnit,
            'apply_to_units' => $applyToUnits,
            'min_size' => null,
            'max_size' => null,
        ];

        if ($sizeRange) {
            [$planData['min_size'], $planData['max_size']] = $this->parseSizeRange($sizeRange);
        }

        BlockPaymentPlan::updateOrCreate(
            ['block_id' => $block->id, 'is_commercial' => $isCommercial],
            $planData
        );

        return 1;
    }

    /**
     * Parses a size-range string such as "5-10", "1-plus", or "1-2-kanal" into [min, max].
     * Kanal values are converted to marla (1 kanal = 20 marla).
     */
    private function parseSizeRange(string $range): array
    {
        $parts = explode('-', $range);
        $isKanal = in_array('kanal', $parts);
        $isPlus = in_array('plus', $parts);

        $min = (float) $parts[0];
        $max = $isPlus ? null : (float) ($parts[1] ?? $min);

        if ($isKanal) {
            $min *= 20;
            $max = $max !== null ? $max * 20 : null;
        }

        return [$min, $max];
    }
}
