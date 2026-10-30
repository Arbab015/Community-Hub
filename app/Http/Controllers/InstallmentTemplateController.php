<?php

namespace App\Http\Controllers;

use App\Models\InstallmentTemplate;
use App\Support\InstallmentPlanCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class InstallmentTemplateController extends Controller
{
    public function index(Request $request)
    {
        try {
            $login_user = auth()->user();
            $query = InstallmentTemplate::where('owner_id', auth()->id())->latest();

            if ($request->ajax()) {
                return DataTables::of($query->get())
                    ->addColumn('checkbox', fn ($t) => '<input type="checkbox" class="form-check-input checkbox" value="'.$t->id.'">')
                    ->addColumn('is_commercial', fn ($t) => $t->is_commercial
                      ? '<span class="badge bg-label-warning">Commercial</span>'
                      : '<span class="badge bg-label-success">Non Commercial</span>')
                    ->addColumn('is_constructed', fn ($t) => $t->is_constructed
                      ? '<span class="badge bg-label-dark">Constructed</span>'
                      : '<span class="badge bg-label-info">Non-Constructed</span>')
                    ->addColumn('balloon_payment', fn ($t) => ! $t->balloon_payment ? '<i class="ti tabler-x text-danger"></i>' : ($t->balloon_payment_in_per ? $t->balloon_payment.'%' : $t->balloon_payment))
                    ->addColumn('balloon_payment_frequency', fn ($t) => ! $t->balloon_payment ? '<i class="ti tabler-x text-danger"></i>' : ucfirst($t->balloon_payment_frequency))
                    ->addColumn('additional_amount', fn ($t) => $t->additional_installment_amount_in_per ? $t->additional_installment_amount.'%' : $t->additional_installment_amount)
                    ->addColumn('actions', function ($t) use ($login_user) {
                        $info = '';
                        $edit = '';
                        $delete = '';

                        $infoData = htmlspecialchars(json_encode([
                            'name' => $t->name,
                            'is_commercial' => (bool) $t->is_commercial,
                            'is_constructed' => (bool) $t->is_constructed,
                            'number_of_installments' => $t->number_of_installments,
                            'installment_frequency' => $t->installment_frequency,
                            'additional_installment_amount' => $t->additional_installment_amount,
                            'additional_installment_amount_in_per' => (int) $t->additional_installment_amount_in_per,
                            'balloon_payment' => $t->balloon_payment,
                            'balloon_payment_in_per' => (int) $t->balloon_payment_in_per,
                            'balloon_payment_frequency' => $t->balloon_payment_frequency,
                        ]), ENT_QUOTES, 'UTF-8');
                        $info = '<button type="button" class="btn btn-sm btn-outline-info me-1 info-template-btn" data-template="'.$infoData.'">
                            <i class="ti tabler-info-circle"></i>
                        </button>';

                        if ($login_user->can('installment_template_create')) {
                            $data = htmlspecialchars(json_encode([
                                'id' => $t->id,
                                'name' => $t->name,
                                'is_commercial' => (int) $t->is_commercial,
                                'is_constructed' => (int) $t->is_constructed,
                                'number_of_installments' => $t->number_of_installments,
                                'installment_frequency' => $t->installment_frequency,
                                'additional_installment_amount' => $t->additional_installment_amount,
                                'additional_installment_amount_in_per' => (int) $t->additional_installment_amount_in_per,
                                'balloon_payment' => $t->balloon_payment,
                                'balloon_payment_in_per' => (int) $t->balloon_payment_in_per,
                                'balloon_payment_frequency' => $t->balloon_payment_frequency,
                            ]), ENT_QUOTES, 'UTF-8');
                            $edit = '<button type="button" class="btn btn-sm btn-outline-primary me-1 edit-template-btn" data-template="'.$data.'">
                            <i class="ti tabler-edit"></i>
                        </button>';
                        }

                        if ($login_user->can('installment_template_delete')) {
                            $delete = '<form action="'.route('installment-templates.destroy', $t->uuid).'" method="POST" style="display:inline;">'
                              .csrf_field().method_field('DELETE')
                              .'<button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(event)">
                            <i class="ti tabler-trash"></i>
                        </button>'
                              .'</form>';
                        }

                        return $info.' '.$edit.' '.$delete;
                    })
                    ->rawColumns(['checkbox', 'is_commercial', 'is_constructed', 'balloon_payment', 'balloon_payment_frequency', 'additional_amount', 'actions'])
                    ->make(true);
            }
            $can_edit = $login_user->can('installment_template_create');
            $can_delete = $login_user->can('installment_template_delete');
            $show_actions = $can_edit || $can_delete;

            return view('content.property_management.installment_template.index', compact('show_actions'));
        } catch (\Exception $e) {
            Log::error('Installment templates index error: '.$e->getMessage());

            return back()->with('error', 'Failed to load templates');
        }
    }

    public function show(InstallmentTemplate $installmentTemplate)
    {
        try {
            if ($installmentTemplate->owner_id !== auth()->id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            return response()->json([
                'id' => $installmentTemplate->id,
                'name' => $installmentTemplate->name,
                'is_commercial' => (bool) $installmentTemplate->is_commercial,
                'is_constructed' => (bool) $installmentTemplate->is_constructed,
                'number_of_installments' => $installmentTemplate->number_of_installments,
                'installment_frequency' => $installmentTemplate->installment_frequency,
                'additional_installment_amount' => $installmentTemplate->additional_installment_amount,
                'additional_installment_amount_in_per' => (int) $installmentTemplate->additional_installment_amount_in_per,
                'balloon_payment' => $installmentTemplate->balloon_payment,
                'balloon_payment_in_per' => (int) $installmentTemplate->balloon_payment_in_per,
                'balloon_payment_frequency' => $installmentTemplate->balloon_payment_frequency,
            ]);
        } catch (\Exception $e) {
            Log::error('Template fetch error: '.$e->getMessage());

            return response()->json(['error' => 'Failed to load template'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $templateId = $request->input('id');
            $isAdditionalPercentage = $request->boolean('additional_installment_amount_in_per');
            $isBalloonPercentage = $request->boolean('balloon_payment_in_per');

            $validated = $request->validate([
                'id' => ['nullable', Rule::exists('installment_templates', 'id')],
                'name' => [
                    'required',
                    'string',
                    'min:3',
                    'max:100',
                    Rule::unique('installment_templates', 'name')
                        ->where('owner_id', auth()->id())
                        ->ignore($templateId),
                ],
                'is_commercial' => ['nullable', 'boolean'],
                'is_constructed' => ['nullable', 'boolean'],
                'number_of_installments' => ['required', 'integer', 'min:1', 'max:360'],
                'installment_frequency' => ['required', 'in:monthly,quarterly,biannually,annually'],
                'additional_installment_amount' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:1000000',
                    $isAdditionalPercentage ? 'max:100' : '',
                ],
                'additional_installment_amount_in_per' => ['required', 'boolean'],
                'balloon_payment' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:1000000',
                    $isBalloonPercentage ? 'max:100' : '',
                ],
                'balloon_payment_in_per' => ['required', 'boolean'],
                'balloon_payment_frequency' => ['nullable', 'in:quarterly,biannually,annually'],
            ], [
                'name.required' => 'Template name is required',
                'name.min' => 'Template name must be at least 3 characters',
                'name.max' => 'Template name cannot exceed 100 characters',
                'name.unique' => 'This template name already exists',
                'number_of_installments.required' => 'Number of installments is required',
                'number_of_installments.min' => 'Number of installments must be at least 1',
                'number_of_installments.max' => 'Number of installments cannot exceed 360',
                'installment_frequency.required' => 'Installment frequency is required',
                'additional_installment_amount.required' => 'Additional installment amount is required',
                'additional_installment_amount.max' => $isAdditionalPercentage ? 'Percentage must be between 0-100' : 'Amount cannot exceed 1,000,000',
                'additional_installment_amount_in_per.required' => 'Percentage toggle is required',
                'balloon_payment.max' => $isBalloonPercentage ? 'Percentage must be between 0-100' : 'Amount cannot exceed 1,000,000',
                'balloon_payment_in_per.required' => 'Percentage toggle is required',
            ]);

            if ($validated['balloon_payment'] && ! $validated['balloon_payment_frequency']) {
                return back()->withErrors(['balloon_payment_frequency' => 'Balloon frequency is required when balloon payment is set'])->withInput();
            }

            if ($validated['balloon_payment'] && $validated['balloon_payment_frequency']) {
                if (! InstallmentPlanCalculator::balloonFrequencyValid($validated['installment_frequency'], $validated['balloon_payment_frequency'])) {
                    return back()->withErrors(['balloon_payment_frequency' => 'Balloon frequency must be less frequent than the installment frequency (e.g. quarterly balloons with monthly installments)'])->withInput();
                }
            }

            $data = [
                'owner_id' => auth()->id(),
                'name' => $validated['name'],
                'is_commercial' => $request->boolean('is_commercial'),
                'is_constructed' => $request->boolean('is_constructed'),
                'number_of_installments' => $validated['number_of_installments'],
                'installment_frequency' => $validated['installment_frequency'],
                'additional_installment_amount' => $validated['additional_installment_amount'],
                'additional_installment_amount_in_per' => $isAdditionalPercentage,
                'balloon_payment' => $validated['balloon_payment'] ?? null,
                'balloon_payment_in_per' => $isBalloonPercentage,
                'balloon_payment_frequency' => $validated['balloon_payment_frequency'] ?? null,
            ];

            if ($validated['id']) {
                $template = InstallmentTemplate::findOrFail($validated['id']);
                $template->update($data);

                return back()->with('success', 'Template updated successfully');
            }

            InstallmentTemplate::create($data);

            return back()->with('success', 'Template created successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Installment template store error: '.$e->getMessage());

            return back()->with('error', 'Failed to save template')->withInput();
        }
    }

    public function destroy(InstallmentTemplate $installmentTemplate)
    {
        try {
            $installmentTemplate->delete();

            return back()->with('success', 'Template deleted successfully');
        } catch (\Exception $e) {
            Log::error('Installment template delete error: '.$e->getMessage());

            return back()->with('error', 'Failed to delete template');
        }
    }

    public function bulkDelete(Request $request)
    {
        try {
            if (empty($request->ids)) {
                return response()->json(['success' => false, 'message' => 'No templates selected'], 400);
            }

            InstallmentTemplate::whereIn('id', $request->ids)->delete();

            return response()->json(['success' => true, 'message' => 'Templates deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Bulk delete error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to delete templates'], 500);
        }
    }
}
