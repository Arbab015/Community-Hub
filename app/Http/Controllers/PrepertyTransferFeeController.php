<?php

namespace App\Http\Controllers;

use App\Helpers\SocietyAccessResolver;
use App\Models\PrepertyTransferFee;
use App\Models\Society;
use App\Services\PropertyRangeService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class PrepertyTransferFeeController extends Controller
{
    /**
     * Property size dropdown options, mirrored from the "Apply Listing" and
     * "Apply Payment Plan" modals on the blocks page.
     */
    private const SIZE_RANGES = [
        '0-5-marla',
        '5-10-marla',
        '10-20-marla',
        '0-2-kanal',
        '2-4-kanal',
        '4-8-kanal',
        '8-kanal-plus',
    ];

    public function index(Request $request)
    {
        $login_user = auth()->user();
        $societies_ids = SocietyAccessResolver::resolver($login_user)['ownedSocietyIds'] ?? [];

        if ($request->ajax()) {
            return DataTables::of(
                PrepertyTransferFee::with('society')
                    ->whereIn('society_id', $societies_ids)
                    ->orderBy('id', 'desc')
            )
                ->addColumn('checkbox', fn ($fee) => '<input type="checkbox" class="form-check-input checkbox" value="'.$fee->id.'">')
                ->addColumn('society', fn ($fee) => $fee->society?->name ?? '—')
                ->addColumn('min_size', fn ($fee) => is_null($fee->min_size) ? '—' : $fee->min_size)
                ->addColumn('max_size', fn ($fee) => is_null($fee->max_size) ? '∞' : $fee->max_size)
                ->addColumn('size_unit', fn ($fee) => '<span class="badge bg-label-secondary">'.str_replace('_', ' ', $fee->size_unit).'</span>')
                ->addColumn('fee', fn ($fee) => $fee->is_percentage
                  ? rtrim(rtrim(number_format((float) $fee->fee, 2, '.', ''), '0'), '.').'%'
                  : number_format((float) $fee->fee))
                ->addColumn('actions', function ($fee) use ($login_user) {
                    $edit = '';
                    $delete = '';

                    if ($login_user->can('transfer_fee_edit')) {
                        $data = htmlspecialchars(json_encode([
                            'id' => $fee->id,
                            'society_id' => $fee->society_id,
                            'min_size' => $fee->min_size,
                            'max_size' => $fee->max_size,
                            'size_unit' => $fee->size_unit,
                            'fee' => $fee->fee,
                            'is_percentage' => (bool) $fee->is_percentage,
                        ]), ENT_QUOTES, 'UTF-8');

                        $edit = '<button type="button" class="btn btn-sm btn-outline-primary me-1 edit-transfer-fee-btn" data-fee="'.$data.'" title="Edit">
                            <i class="ti tabler-edit"></i>
                        </button>';
                    }

                    if ($login_user->can('transfer_fee_delete')) {
                        $delete = '<form action="'.route('transfer_fee.destroy', $fee->id).'" method="POST" style="display:inline;">'
                          .csrf_field().method_field('DELETE')
                          .'<button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(event)" title="Delete">
                                <i class="ti tabler-trash"></i>
                            </button>'
                          .'</form>';
                    }

                    return $edit.' '.$delete;
                })
                ->rawColumns(['checkbox', 'size_unit', 'actions'])
                ->make(true);
        }

        $can_edit = $login_user->can('transfer_fee_edit');
        $can_delete = $login_user->can('transfer_fee_delete');

        $societies = Society::whereIn('id', $societies_ids)->get();
        $show_actions = $can_edit || $can_delete;
        $can_create = $login_user->can('transfer_fee_create');

        return view('content.property_management.transfer_fee.index', compact('societies', 'show_actions', 'can_create'));
    }

    public function store(Request $request)
    {
        $login_user = auth()->user();
        $societies_ids = SocietyAccessResolver::resolver($login_user)['ownedSocietyIds'] ?? [];
        $isPercentage = $request->boolean('is_percentage');

        abort_unless(
            $login_user->can($request->filled('id') ? 'transfer_fee_edit' : 'transfer_fee_create'),
            403,
            'Unauthorized.'
        );

        $validated = $request->validate([
            'id' => ['nullable', 'integer', Rule::exists('preperty_transfer_fees', 'id')],
            'society_id' => ['required', 'integer', Rule::in($societies_ids)],
            'size_range' => ['required', Rule::in(self::SIZE_RANGES)],
            'size_unit' => ['required', 'in:marla,square_yard'],
            'fee' => ['required', 'numeric', 'min:0', $isPercentage ? 'max:100' : ''],
            'is_percentage' => ['nullable', 'boolean'],
        ], [
            'fee.max' => 'Percentage must be between 0-100',
        ]);

        if (! empty($validated['id'])) {
            $existing = PrepertyTransferFee::findOrFail($validated['id']);
            abort_unless(in_array($existing->society_id, $societies_ids), 403, 'Unauthorized.');
        }

        [$min, $max] = app(PropertyRangeService::class)->parseSizeRange($validated['size_range']);

        try {
            $transferFee = PrepertyTransferFee::updateOrCreate(
                ['id' => $validated['id'] ?? null],
                [
                    'society_id' => $validated['society_id'],
                    'min_size' => is_null($min) ? null : (int) $min,
                    'max_size' => is_null($max) ? null : (int) $max,
                    'size_unit' => $validated['size_unit'],
                    'fee' => $validated['fee'],
                    'is_percentage' => $isPercentage,
                ]
            );

            return back()->with('success', $transferFee->wasRecentlyCreated
              ? 'Transfer fee created successfully.'
              : 'Transfer fee updated successfully.');
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) == 1062) {
                return back()->withInput()->with('error', 'A transfer fee for this size range already exists in the selected society.');
            }

            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $login_user = auth()->user();
        $societies_ids = SocietyAccessResolver::resolver($login_user)['ownedSocietyIds'] ?? [];

        $transferFee = PrepertyTransferFee::findOrFail($id);
        abort_unless(in_array($transferFee->society_id, $societies_ids), 403, 'Unauthorized.');

        $transferFee->delete();

        return back()->with('success', 'Transfer fee deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        try {
            PrepertyTransferFee::whereIn('id', $request->ids)->delete();

            return response()->json(['success' => true, 'message' => 'Selected transfer fees deleted successfully.']);
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}
