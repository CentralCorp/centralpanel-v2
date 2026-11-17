<?php

namespace App\Http\Controllers;

use App\Models\MicrosoftPlayer;
use App\Models\PanelRole;
use App\Support\LauncherCache;
use App\Support\PanelOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminRoleController extends Controller
{
    public function index()
    {
        PanelOptions::ensureRoles();
        $roles = PanelRole::withCount('players')
            ->orderByDesc('power')
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', compact('roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:panel_roles,name',
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'power' => 'required|integer|min:0|max:10000',
            'is_default' => 'boolean',
            'bypass_maintenance' => 'boolean',
        ]);

        $makeDefault = $request->boolean('is_default');

        $role = DB::transaction(function () use ($validated, $makeDefault, $request) {
            if ($makeDefault) {
                PanelRole::where('is_default', true)->update(['is_default' => false]);
            }

            return PanelRole::create([
                'name' => trim($validated['name']),
                'color' => $validated['color'],
                'power' => (int) $validated['power'],
                'is_default' => $makeDefault,
                'bypass_maintenance' => $request->boolean('bypass_maintenance'),
            ]);
        });

        PanelOptions::ensureRoles();
        LauncherCache::flush();

        return redirect()->route('admin.roles')->with('success', __('messages.flash.role_created', ['name' => $role->name]));
    }

    public function update(Request $request, PanelRole $role)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('panel_roles', 'name')->ignore($role->id)],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'power' => 'required|integer|min:0|max:10000',
            'is_default' => 'boolean',
            'bypass_maintenance' => 'boolean',
        ]);

        $makeDefault = $request->boolean('is_default');

        DB::transaction(function () use ($role, $validated, $makeDefault, $request) {
            if ($makeDefault) {
                PanelRole::where('id', '!=', $role->id)->where('is_default', true)->update(['is_default' => false]);
            }

            $role->update([
                'name' => trim($validated['name']),
                'color' => $validated['color'],
                'power' => (int) $validated['power'],
                'is_default' => $makeDefault,
                'bypass_maintenance' => $request->boolean('bypass_maintenance'),
            ]);
        });

        PanelOptions::ensureRoles();
        LauncherCache::flush();

        return redirect()->route('admin.roles')->with('success', __('messages.flash.role_updated', ['name' => $role->name]));
    }

    public function destroy(PanelRole $role)
    {
        if (PanelRole::count() <= 1) {
            return redirect()->route('admin.roles')->with('error', __('messages.flash.role_cannot_delete_last'));
        }

        $name = $role->name;
        if ($role->background_path) {
            Storage::disk('public')->delete($role->background_path);
        }

        $role->delete();
        $defaultRole = PanelOptions::ensureRoles();

        MicrosoftPlayer::whereNull('role_id')->update(['role_id' => $defaultRole->id]);
        LauncherCache::flush();

        return redirect()->route('admin.roles')->with('success', __('messages.flash.role_deleted', ['name' => $name]));
    }
}
