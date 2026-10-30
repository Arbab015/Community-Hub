<?php

namespace App\Http\Controllers;

use App\Models\FbrTaxRate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FbrTaxRateController extends Controller
{
    private const ROLES = ['buyer', 'seller'];

    private const STATUSES = ['filer', 'non_filer'];

    public function index()
    {
        $slabs = [];
        $roles = [];

        foreach (FbrTaxRate::orderBy('value_from')->get() as $rate) {
            $roles[$rate->role] = true;
            $key = $rate->role.'|'.$rate->value_from.'|'.$rate->value_to;
            $slabs[$key] ??= ['role' => $rate->role, 'value_from' => $rate->value_from, 'value_to' => $rate->value_to, 'filer' => null, 'non_filer' => null];
            $slabs[$key][$rate->filer_status] = $rate;
        }

        foreach (array_diff(self::ROLES, array_keys($roles)) as $role) {
            $slabs[$role.'|0|'] = ['role' => $role, 'value_from' => 0, 'value_to' => null, 'filer' => null, 'non_filer' => null];
        }

        foreach ($slabs as $key => $slab) {
            foreach (self::STATUSES as $status) {
                $slabs[$key][$status] ??= FbrTaxRate::create(['role' => $slab['role'], 'filer_status' => $status, 'value_from' => $slab['value_from'], 'value_to' => $slab['value_to'], 'rate_percent' => 0]);
            }
        }

        return view('content.FBR tax rates.index', [
            'buyerSlabs' => array_values(array_filter($slabs, fn ($s) => $s['role'] === 'buyer')),
            'sellerSlabs' => array_values(array_filter($slabs, fn ($s) => $s['role'] === 'seller')),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->slabInput($request, ['role' => ['required', Rule::in(self::ROLES)]]);

        if ($conflict = $this->conflict($data['role'], $data['value_from'], $data['value_to'])) {
            return back()->withErrors(['value_from' => $conflict])->withInput();
        }

        $row = ['role' => $data['role'], 'value_from' => $data['value_from'], 'value_to' => $data['value_to'], 'created_at' => now(), 'updated_at' => now()];

        FbrTaxRate::insert([
            $row + ['filer_status' => 'filer', 'rate_percent' => $data['filers_rate']],
            $row + ['filer_status' => 'non_filer', 'rate_percent' => $data['non_filers_rate']],
        ]);

        return back()->with('success', ucfirst($data['role']).' tax slab added successfully.');
    }

    public function update(Request $request)
    {
        $data = $this->slabInput($request, ['slab_id' => ['required', 'integer', 'exists:fbr_tax_rates,id']]);

        $slab = FbrTaxRate::findOrFail($data['slab_id']);

        if ($slab->filer_status !== 'filer') {
            return back()->withErrors(['value_from' => 'Invalid slab reference.'])->withInput();
        }

        $pair = $this->pair($slab)->pluck('id')->all();

        if ($conflict = $this->conflict($slab->role, $data['value_from'], $data['value_to'], $pair)) {
            return back()->withErrors(['value_from' => $conflict])->withInput();
        }

        foreach (['filer' => $data['filers_rate'], 'non_filer' => $data['non_filers_rate']] as $status => $rate) {
            FbrTaxRate::updateOrCreate(
                ['role' => $slab->role, 'filer_status' => $status, 'value_from' => $slab->value_from, 'value_to' => $slab->value_to],
                ['value_from' => $data['value_from'], 'value_to' => $data['value_to'], 'rate_percent' => $rate]
            );
        }

        return back()->with('success', 'FBR tax slab updated successfully.');
    }

    public function destroy(Request $request)
    {
        $slab = FbrTaxRate::findOrFail($request->validate(['slab_id' => ['required', 'integer', 'exists:fbr_tax_rates,id']])['slab_id']);

        if ($slab->filer_status !== 'filer') {
            return back()->withErrors(['slab_id' => 'Invalid slab reference.']);
        }

        if ($slab->value_from === 0) {
            return back()->withErrors(['slab_id' => 'The slab starting at PKR 0 is required for '.ucfirst($slab->role).'.']);
        }

        $this->pair($slab)->delete();

        return back()->with('success', 'FBR tax slab removed successfully.');
    }

    private function slabInput(Request $request, array $extra = []): array
    {
        $data = $request->validate($extra + [
            'value_from' => ['required', 'integer', 'min:0'],
            'value_to' => ['nullable', 'integer', 'min:0', 'gt:value_from'],
            'filers_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'non_filers_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        return [
            ...$data,
            'value_from' => (int) $data['value_from'],
            'value_to' => ($data['value_to'] ?? '') === '' ? null : (int) $data['value_to'],
        ];
    }

    private function conflict(string $role, int $from, ?int $to, array $ignore = []): ?string
    {
        $ranges = FbrTaxRate::where('role', $role)
            ->when($ignore, fn ($q) => $q->whereNotIn('id', $ignore))
            ->orderBy('value_from')
            ->get(['value_from', 'value_to']);

        $end = $to ?? PHP_INT_MAX;

        foreach ($ranges as $range) {
            $label = 'PKR '.number_format($range->value_from).($range->value_to === null ? ' and above' : ' - '.number_format($range->value_to));

            if ($range->value_from === $from) {
                return "A {$role} slab already starts at PKR ".number_format($from)." ({$label}).";
            }

            if ($from < ($range->value_to ?? PHP_INT_MAX) && $end > $range->value_from) {
                return 'PKR '.number_format($from).($to === null ? ' and above' : ' - '.number_format($to))." overlaps the existing {$role} slab {$label}.";
            }
        }

        return null;
    }

    private function pair(FbrTaxRate $slab): Builder
    {
        return FbrTaxRate::where(['role' => $slab->role, 'value_from' => $slab->value_from, 'value_to' => $slab->value_to])
            ->whereIn('filer_status', self::STATUSES);
    }
}
