<?php

namespace App\Http\Controllers;

use App\Helpers\SocietyAccessResolver;
use App\Models\Society;
use App\Models\SocietySetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SystemSettingController extends Controller
{
    public function index(Request $request)
    {
        $login_user = auth()->user();
        $societyIds = SocietyAccessResolver::resolver($login_user)['ownedSocietyIds'] ?? collect();

        $societies = Society::whereIn('id', $societyIds)->orderBy('name')->get();

        $selectedId = (int) ($request->query('society_id') ?? session('active_society_id') ?? 0);

        if (! $societies->contains('id', $selectedId)) {
            $selectedId = (int) ($societies->first()->id ?? 0);
        }

        return view('content.system_settings.index', [
            'societies' => $societies,
            'selectedSociety' => $societies->firstWhere('id', $selectedId),
            'settings' => $selectedId ? SocietySetting::settingsFor($selectedId) : [],
            'definitions' => SocietySetting::DEFINITIONS,
        ]);
    }

    public function update(Request $request)
    {
        $login_user = auth()->user();
        $societyIds = (SocietyAccessResolver::resolver($login_user)['ownedSocietyIds'] ?? collect())->all();

        $validated = $request->validate([
            'society_id' => ['required', 'integer', Rule::in($societyIds)],
            'settings' => ['nullable', 'array'],
            'settings.*' => ['boolean'],
        ]);

        $societyId = (int) $validated['society_id'];

        foreach (SocietySetting::DEFINITIONS as $key => $definition) {
            SocietySetting::set($societyId, $key, (bool) ($validated['settings'][$key] ?? false));
        }

        return redirect()
            ->route('system-settings.index', ['society_id' => $societyId])
            ->with('success', 'System settings updated successfully.');
    }
}
