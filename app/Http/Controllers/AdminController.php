<?php

namespace App\Http\Controllers;

use App\Support\ActiveInstance;
use App\Support\LauncherCache;
use App\Support\PanelOptions;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function updateGeneral(Request $request)
    {
        $validated = $request->validate([
            'mods_enabled' => 'boolean',
            'file_verification' => 'boolean',
            'embedded_java' => 'boolean',
            'game_folder_name' => 'required|string|max:100',
            'email_verified' => 'boolean',
            'role_display' => 'nullable|integer',
            'money_display' => 'nullable|integer',
            'min_ram' => 'required|integer|min:512|max:65536',
            'max_ram' => 'required|integer|min:512|max:65536|gte:min_ram',
        ]);

        PanelOptions::general()->update($validated);

        // Sync active instance (and default instance if active is default)
        $activeInstance = ActiveInstance::current();
        $activeInstance->update([
            'mods_enabled' => (bool) ($validated['mods_enabled'] ?? $activeInstance->mods_enabled),
            'file_verification' => (bool) ($validated['file_verification'] ?? $activeInstance->file_verification),
            'embedded_java' => (bool) ($validated['embedded_java'] ?? $activeInstance->embedded_java),
            'min_ram' => (int) $validated['min_ram'],
            'max_ram' => (int) $validated['max_ram'],
        ]);

        LauncherCache::flush();

        return redirect()->route('admin.general')->with('success', __('messages.flash.options_updated'));
    }

    public function general()
    {
        $options = PanelOptions::general();
        $activeInstance = ActiveInstance::current();

        return view('admin.general', compact('options', 'activeInstance'));
    }
}
