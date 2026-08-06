<?php

namespace App\Http\Controllers;

use App\Helpers\SocietyAccessResolver;
use App\Models\PaymentTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class PaymentTemplateController extends Controller
{
    public function index(Request $request)
    {
        $login_user = auth()->user();
        $query = PaymentTemplate::query()->latest();

        $scope = SocietyAccessResolver::resolver($login_user);
        if ($scope['isSocietyScoped']) {
            $query->where('owner_id', $login_user->id);
        }

        if ($request->ajax()) {
            return DataTables::of($query->get())
                ->addColumn('checkbox', fn ($t) => '<input type="checkbox" class="form-check-input checkbox" value="'.$t->id.'">')
                ->addColumn('booking_fee', fn ($t) => $this->feeCell($t->booking_fee, $t->booking_fee_in_per))
                ->addColumn('development_fee', fn ($t) => $this->feeCell($t->development_fee, $t->development_fee_in_per, $t->development_fee_surplus))
                ->addColumn('down_payment', fn ($t) => $this->feeCell($t->down_payment, $t->down_payment_in_per))
                ->addColumn('confirmation_fee', fn ($t) => $this->feeCell($t->confirmation_fee, $t->confirmation_fee_in_per))
                ->addColumn('possession_fee', fn ($t) => $this->feeCell($t->possession_fee, $t->possession_fee_in_per, $t->possession_fee_surplus))
                ->addColumn('corner_premium', fn ($t) => $this->feeCell($t->corner_premium, true, false))
                ->addColumn('park_facing_premium', fn ($t) => $this->feeCell($t->park_facing_premium, true, false))
                ->addColumn('main_boulevard_premium', fn ($t) => $this->feeCell($t->main_boulevard_premium, true, false))
                ->addColumn('actions', function ($t) use ($login_user) {
                    $edit = '';
                    $delete = '';

                    if ($login_user->can('payment_template_create')) {
                        $data = htmlspecialchars(json_encode([
                            'id' => $t->id,
                            'name' => $t->name,
                            'booking_fee' => $t->booking_fee,
                            'booking_fee_in_per' => (int) $t->booking_fee_in_per,
                            'development_fee' => $t->development_fee,
                            'development_fee_in_per' => (int) $t->development_fee_in_per,
                            'development_fee_surplus' => (int) $t->development_fee_surplus,
                            'down_payment' => $t->down_payment,
                            'down_payment_in_per' => (int) $t->down_payment_in_per,
                            'confirmation_fee' => $t->confirmation_fee,
                            'confirmation_fee_in_per' => (int) $t->confirmation_fee_in_per,
                            'possession_fee' => $t->possession_fee,
                            'possession_fee_in_per' => (int) $t->possession_fee_in_per,
                            'possession_fee_surplus' => (int) $t->possession_fee_surplus,
                            'corner_premium' => $t->corner_premium,
                            'park_facing_premium' => $t->park_facing_premium,
                            'main_boulevard_premium' => $t->main_boulevard_premium,
                        ]), ENT_QUOTES, 'UTF-8');
                        $edit = '<button type="button" class="btn btn-sm btn-outline-primary me-1 edit-template-btn" data-template="'.$data.'">
                            <i class="ti tabler-edit"></i>
                        </button>';
                    }

                    if ($login_user->can('payment_template_delete')) {
                        $delete = '<form action="'.route('payment-templates.destroy', $t->id).'" method="POST" style="display:inline;">'.csrf_field().method_field('DELETE').'<button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(event)"><i class="ti tabler-trash"></i></button></form>';
                    }

                    return $edit.' '.$delete;
                })
                ->rawColumns(['checkbox', 'booking_fee', 'development_fee', 'down_payment', 'confirmation_fee', 'possession_fee', 'corner_premium', 'park_facing_premium', 'main_boulevard_premium', 'actions'])
                ->make(true);
        }

        $can_edit = $login_user->can('payment_template_create');
        $can_delete = $login_user->can('payment_template_delete');
        $show_actions = $can_edit || $can_delete;

        return view('content.property_management.payment_planning.index', compact('show_actions'));
    }

    public function store(Request $request)
    {
        //        dd($request->all());
        try {
            $templateId = $request->input('id');

            $validated = $request->validate([
                'id' => ['nullable', Rule::exists('payment_templates', 'id')],
                'name' => ['required', 'string', 'min:3', 'max:100', Rule::unique('payment_templates', 'name')->where('owner_id', auth()->id())->ignore($templateId)],
                'booking_fee' => ['nullable', 'numeric', 'min:0'],
                'booking_fee_in_per' => ['nullable', 'boolean'],
                'development_fee' => ['nullable', 'numeric', 'min:0'],
                'development_fee_in_per' => ['nullable', 'boolean'],
                'development_fee_surplus' => ['nullable', 'boolean'],
                'down_payment' => ['nullable', 'numeric', 'min:0'],
                'down_payment_in_per' => ['nullable', 'boolean'],
                'confirmation_fee' => ['nullable', 'numeric', 'min:0'],
                'confirmation_fee_in_per' => ['nullable', 'boolean'],
                'possession_fee' => ['nullable', 'numeric', 'min:0'],
                'possession_fee_in_per' => ['nullable', 'boolean'],
                'possession_fee_surplus' => ['nullable', 'boolean'],
                'corner_premium' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'park_facing_premium' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'main_boulevard_premium' => ['nullable', 'numeric', 'min:0', 'max:100'],
            ], [
                'name.unique' => 'This template name already exists',
                'name.min' => 'Template name must be at least 3 characters',
                'name.max' => 'Template name cannot exceed 100 characters',
            ]);

            $data = [
                'owner_id' => auth()->id(),
                'name' => $validated['name'],
                'booking_fee' => $validated['booking_fee'] ?? null,
                'booking_fee_in_per' => $request->boolean('booking_fee_in_per'),
                'development_fee' => $validated['development_fee'] ?? null,
                'development_fee_in_per' => $request->boolean('development_fee_in_per'),
                'development_fee_surplus' => $request->boolean('development_fee_surplus'),
                'down_payment' => $validated['down_payment'] ?? null,
                'down_payment_in_per' => $request->boolean('down_payment_in_per'),
                'confirmation_fee' => $validated['confirmation_fee'] ?? null,
                'confirmation_fee_in_per' => $request->boolean('confirmation_fee_in_per'),
                'possession_fee' => $validated['possession_fee'] ?? null,
                'possession_fee_in_per' => $request->boolean('possession_fee_in_per'),
                'possession_fee_surplus' => $request->boolean('possession_fee_surplus'),
                'corner_premium' => $validated['corner_premium'] ?? null,
                'corner_premium_in_per' => true,
                'park_facing_premium' => $validated['park_facing_premium'] ?? null,
                'park_facing_premium_in_per' => true,
                'main_boulevard_premium' => $validated['main_boulevard_premium'] ?? null,
                'main_boulevard_premium_in_per' => true,
            ];

            if ($validated['id']) {
                $template = PaymentTemplate::findOrFail($validated['id']);
                $template->update($data);

                return back()->with('success', 'Template updated successfully');
            }

            PaymentTemplate::create($data);

            return back()->with('success', 'Template created successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Payment template store error: '.$e->getMessage());

            return back()->with('error', 'Failed to save template')->withInput();
        }
    }

    public function destroy(PaymentTemplate $paymentTemplate)
    {
        try {
            $paymentTemplate->delete();

            return back()->with('success', 'Template deleted successfully');
        } catch (\Exception $e) {
            Log::error('Payment template delete error: '.$e->getMessage());

            return back()->with('error', 'Failed to delete template');
        }
    }

    public function bulkDelete(Request $request)
    {
        try {
            if (empty($request->ids)) {
                return response()->json(['success' => false, 'message' => 'No templates selected'], 400);
            }
            PaymentTemplate::whereIn('id', $request->ids)->delete();

            return response()->json(['success' => true, 'message' => 'Templates deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Bulk delete error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to delete templates'], 500);
        }
    }

    private function feeCell(?float $amount, bool $isPercent = false, ?bool $surplus = null): string
    {
        if ($amount === null) {
            return '<span class="text-muted"><i class="ti tabler-x text-danger"></i></span>';
        }

        $formatted = $amount == (int) $amount ? (int) $amount : round($amount, 2);
        $value = $isPercent ? $formatted.'%' : $formatted;
        $badge = ($surplus === null || $surplus) ? '<span class="badge bg-label-success ms-1">Inc</span>' : '<span class="badge bg-label-danger ms-1">Exc</span>';

        return '<span class="fw-medium">'.$value.'</span>'.$badge;
    }
}
