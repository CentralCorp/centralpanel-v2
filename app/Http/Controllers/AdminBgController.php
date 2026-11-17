<?php

namespace App\Http\Controllers;

use App\Models\OptionsBg;
use App\Models\PanelRole;
use App\Request\AzuriomApi;
use App\Support\LauncherCache;
use App\Support\PanelOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminBgController extends Controller
{
    private ?AzuriomApi $azuriomApi = null;

    public function __construct()
    {
        try {
            $this->azuriomApi = new AzuriomApi();
        } catch (\RuntimeException) {
            $this->azuriomApi = null;
        }
    }

    public function index()
    {
        $generalOptions = PanelOptions::general();
        PanelOptions::ensureRoles();

        $authMode = $generalOptions->auth_mode ?: 'azuriom';
        $showAzuriomRoles = in_array($authMode, ['azuriom', 'both'], true);
        $showPanelRoles = in_array($authMode, ['microsoft', 'both'], true);

        $hasAzuriomApi = $this->azuriomApi !== null;
        $roles = ($showAzuriomRoles && $hasAzuriomApi) ? $this->azuriomApi->getRoles() : [];
        $backgrounds = OptionsBg::all()->keyBy('role_id');
        $panelRoles = PanelRole::orderByDesc('power')->orderBy('name')->get();

        return view('admin.bg', compact(
            'roles',
            'backgrounds',
            'panelRoles',
            'hasAzuriomApi',
            'authMode',
            'showAzuriomRoles',
            'showPanelRoles'
        ));
    }

    public function update(Request $request)
    {
        $source = $request->input('source', 'azuriom');

        if ($source === 'panel') {
            $validated = $request->validate([
                'panel_role_id' => 'required|integer|exists:panel_roles,id',
                'bg_image' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            ]);

            $panelRole = PanelRole::findOrFail($validated['panel_role_id']);
            if ($panelRole->background_path) {
                Storage::disk('public')->delete($panelRole->background_path);
            }

            $path = $request->file('bg_image')->store('backgrounds', 'public');
            $panelRole->update(['background_path' => $path]);
            LauncherCache::flush();

            return back()->with('success', __('messages.flash.bg_updated'));
        }

        if (!$this->azuriomApi) {
            return back()->with('error', __('messages.flash.bg_api_error'));
        }

        $request->validate([
            'role_id' => 'required',
            'bg_image' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
        ]);

        $role = collect($this->azuriomApi->getRoles())->firstWhere('id', $request->role_id);

        if (!$role) {
            return back()->with('error', __('messages.flash.role_not_found'));
        }

        $oldBg = OptionsBg::where('role_id', $request->role_id)->first();
        if ($oldBg) {
            Storage::disk('public')->delete($oldBg->image_path);
        }

        $path = $request->file('bg_image')->store('backgrounds', 'public');

        OptionsBg::updateOrCreate(
            ['role_id' => $request->role_id],
            [
                'image_path' => $path,
                'role_name' => $role['name'],
            ]
        );

        LauncherCache::flush();

        return back()->with('success', __('messages.flash.bg_updated'));
    }

    public function destroy($role_id)
    {
        if (str_starts_with((string) $role_id, 'panel:')) {
            $panelId = (int) substr((string) $role_id, 6);
            $panelRole = PanelRole::find($panelId);

            if ($panelRole && $panelRole->background_path) {
                Storage::disk('public')->delete($panelRole->background_path);
                $panelRole->update(['background_path' => null]);
                LauncherCache::flush();

                return back()->with('success', __('messages.flash.bg_deleted'));
            }

            return back()->with('error', __('messages.flash.bg_not_found'));
        }

        $background = OptionsBg::where('role_id', $role_id)->first();

        if ($background) {
            Storage::disk('public')->delete($background->image_path);
            $background->delete();
            LauncherCache::flush();

            return back()->with('success', __('messages.flash.bg_deleted'));
        }

        return back()->with('error', __('messages.flash.bg_not_found'));
    }
}
