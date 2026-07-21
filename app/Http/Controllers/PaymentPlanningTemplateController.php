<?php

namespace App\Http\Controllers;

use App\Helpers\SocietyAccessResolver;
use App\Models\PaymentPlanningTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class PaymentPlanningTemplateController extends Controller
{
    public function index(Request $request)
    {
        $login_user = auth()->user();

        $query = PaymentPlanningTemplate::query()->latest();

        $scope = SocietyAccessResolver::resolver($login_user);
        if ($scope['isSocietyScoped']) {
            $query->where('owner_id', $login_user->id);
        }

        if ($request->ajax()) {
            return DataTables::of($query->get())
                ->addColumn('checkbox', function ($template) {
                    return '<input type="checkbox" class="form-check-input checkbox" title="Select Record" value="'.$template->id.'">';
                })
                ->addColumn('booking_fee', fn ($t) => $this->feeCell($t->booking_fee, $t->booking_fee_in_per))
                ->addColumn('development_fee', fn ($t) => $this->feeCell($t->development_fee, $t->development_fee_in_per, $t->development_fee_surplus))
                ->addColumn('down_payment', fn ($t) => $this->feeCell($t->down_payment, $t->down_payment_in_per))
                ->addColumn('transfer_fee', fn ($t) => $this->feeCell($t->transfer_fee, $t->transfer_fee_in_per, $t->transfer_fee_surplus))
                ->addColumn('confirmation_fee', fn ($t) => $this->feeCell($t->confirmation_fee, $t->confirmation_fee_in_per))
                ->addColumn('possession_fee', fn ($t) => $this->feeCell($t->possession_fee, $t->possession_fee_in_per, $t->possession_fee_surplus))
                ->addColumn('actions', function ($template) {
                    $edit = '<button type="button" class="btn btn-sm btn-outline-primary me-1 edit-template-btn" data-id="'.$template->id.'">
                        <i class="ti tabler-edit"></i>
                    </button>';

                    $delete = '<form action="'.route('payment-planning-templates.destroy', $template->id).'" method="POST" style="display:inline;">'
                      .csrf_field().method_field('DELETE')
                      .'<button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(event)">
                            <i class="ti tabler-trash"></i>
                        </button>'
                      .'</form>';

                    return $edit.$delete;
                })
                ->rawColumns([
                    'checkbox', 'booking_fee', 'development_fee', 'down_payment',
                    'transfer_fee', 'confirmation_fee', 'possession_fee', 'actions',
                ])
                ->make(true);
        }

        $show_actions = true;

        return view('content.property_management.payment_planning.templates.index', compact('show_actions'));
    }

    public function store(Request $request)
    {

        //    dd($request->all());
        $login_user = auth()->user();

        $validated = $request->validate([
            'id' => ['nullable', 'integer', Rule::exists('payment_planning_templates', 'id')],
            'name' => ['required', 'string', 'max:255'],

            'booking_fee' => ['nullable', 'numeric', 'min:0'],
            'booking_fee_in_per' => ['nullable', 'boolean'],

            'development_fee' => ['nullable', 'numeric', 'min:0'],
            'development_fee_in_per' => ['nullable', 'boolean'],
            'development_fee_surplus' => ['nullable', 'boolean'],

            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'down_payment_in_per' => ['nullable', 'boolean'],

            'transfer_fee' => ['nullable', 'numeric', 'min:0'],
            'transfer_fee_in_per' => ['nullable', 'boolean'],
            'transfer_fee_surplus' => ['nullable', 'boolean'],

            'confirmation_fee' => ['nullable', 'numeric', 'min:0'],
            'confirmation_fee_in_per' => ['nullable', 'boolean'],

            'possession_fee' => ['nullable', 'numeric', 'min:0'],
            'possession_fee_in_per' => ['nullable', 'boolean'],
            'possession_fee_surplus' => ['nullable', 'boolean'],
        ]);

        try {
            $template = null;

            if (! empty($validated['id'])) {
                $template = PaymentPlanningTemplate::where('id', $validated['id'])
                    ->where('owner_id', $login_user->id)
                    ->first();

                if (! $template) {
                    return redirect()->back()
                        ->with('error', 'Template not found or you do not have permission to edit it.')
                        ->withInput();
                }
            }

            $data = [
                'owner_id' => $login_user->id,
                'name' => $validated['name'],

                'booking_fee' => $validated['booking_fee'] ?? null,
                'booking_fee_in_per' => $request->boolean('booking_fee_in_per'),

                'development_fee' => $validated['development_fee'] ?? null,
                'development_fee_in_per' => $request->boolean('development_fee_in_per'),
                'development_fee_surplus' => $request->boolean('development_fee_surplus'),

                'down_payment' => $validated['down_payment'] ?? null,
                'down_payment_in_per' => $request->boolean('down_payment_in_per'),

                'transfer_fee' => $validated['transfer_fee'] ?? null,
                'transfer_fee_in_per' => $request->boolean('transfer_fee_in_per'),
                'transfer_fee_surplus' => $request->boolean('transfer_fee_surplus'),

                'confirmation_fee' => $validated['confirmation_fee'] ?? null,
                'confirmation_fee_in_per' => $request->boolean('confirmation_fee_in_per'),

                'possession_fee' => $validated['possession_fee'] ?? null,
                'possession_fee_in_per' => $request->boolean('possession_fee_in_per'),
                'possession_fee_surplus' => $request->boolean('possession_fee_surplus'),
            ];

            if ($template) {
                $template->update($data);
                $message = 'Template updated successfully.';
            } else {
                PaymentPlanningTemplate::create($data);
                $message = 'Template created successfully.';
            }

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function destroy(PaymentPlanningTemplate $paymentPlanningTemplate)
    {
        $login_user = auth()->user();

        if ($paymentPlanningTemplate->owner_id !== $login_user->id) {
            return redirect()->back()->with('error', 'You do not have permission to delete this template.');
        }

        try {
            $paymentPlanningTemplate->delete();

            return redirect()->back()->with('success', 'Template deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('Payment planning template delete failed: '.$e->getMessage());

            return redirect()->back()->with('error', 'Something went wrong while deleting the template.');
        }
    }

    private function feeCell(?float $amount, bool $isPercent = false, ?bool $surplus = null): string
    {
        if ($amount === null) {
            return '<span class="text-muted ti tabler-x text-danger"></span>';
        }

        $formatted = $amount == (int) $amount
          ? number_format($amount, 0)
          : rtrim(rtrim(number_format($amount, 2), '0'), '.');

        $value = $isPercent ? $formatted.'%' : $formatted;

        $included = $surplus === null ? true : $surplus;

        $badge = $included
          ? '<span class="badge bg-label-success ms-1">Inc</span>'
          : '<span class="badge bg-label-danger ms-1">Exc</span>';

        return '<span class="fw-medium">'.$value.'</span>'.$badge;
    }

    public function bulkDelete(Request $request)
    {
        try {
            PaymentPlanningTemplate::whereIn('id', $request->ids)->delete();

            return response()->json(['success' => true, 'message' => 'Selected messages deleted successfully.']);
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}
