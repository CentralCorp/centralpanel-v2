<?php

namespace App\Http\Controllers;

use App\Models\OptionsMods;
use App\Support\ActiveInstance;
use App\Support\LauncherCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminModController extends Controller
{
    public function index(Request $request)
    {
        $activeInstance = ActiveInstance::current();
        $activeInstance->ensureDirectoriesExist();

        $modsDir = $activeInstance->resolvedStoragePath() . DIRECTORY_SEPARATOR . 'mods';
        $jarFiles = is_dir($modsDir) ? array_merge(
            glob($modsDir . '/*.jar') ?: [],
            glob($modsDir . '/*.jar-disable') ?: []
        ) : [];

        $seenFiles = [];
        $modsData = [];

        foreach ($jarFiles as $jarFile) {
            $baseName = preg_replace('/\.jar-disable$/', '.jar', basename($jarFile));
            if (isset($seenFiles[$baseName])) {
                continue;
            }
            $seenFiles[$baseName] = true;

            $modsData[] = [
                'file' => $baseName,
                'name' => $baseName,
                'description' => '',
                'icon' => '',
                'optional' => 0,
            ];
        }

        $optionalMods = OptionsMods::query()
            ->where('optional', true)
            ->where(function ($q) use ($activeInstance) {
                $q->where('instance_id', $activeInstance->id);
                if ($activeInstance->is_default) {
                    $q->orWhereNull('instance_id');
                }
            })
            ->orderBy('name')
            ->get();

        $selectedModId = $request->input('selectedMod');

        return view('admin.mods', compact('modsData', 'optionalMods', 'selectedModId', 'activeInstance'));
    }

    public function updateOptionalMod(Request $request)
    {
        $validated = $request->validate([
            'mod_id' => 'required|integer|exists:mods,id',
            'optional_name' => 'required|string|max:255',
            'optional_description' => 'nullable|string|max:2000',
            'optional_recommended' => 'nullable|boolean',
            'optional_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $activeInstance = ActiveInstance::current();
        $mod = OptionsMods::findOrFail($validated['mod_id']);

        if (!$mod->instance_id) {
            $mod->instance_id = $activeInstance->id;
        }

        $mod->name = $validated['optional_name'];
        $mod->description = $validated['optional_description'] ?? '';
        $mod->recommended = $request->boolean('optional_recommended');

        if ($request->hasFile('optional_image')) {
            if ($mod->icon && Storage::disk('public')->exists($mod->icon)) {
                Storage::disk('public')->delete($mod->icon);
            }
            $mod->icon = $request->file('optional_image')->store('mod_icon', 'public');
        }

        $mod->save();
        LauncherCache::flush();

        return redirect()->back()->with('success', __('messages.flash.mod_updated'));
    }

    public function deleteOptionalMod($id)
    {
        try {
            $mod = OptionsMods::findOrFail($id);
            if ($mod->icon && Storage::disk('public')->exists($mod->icon)) {
                Storage::disk('public')->delete($mod->icon);
            }
            $mod->delete();
            LauncherCache::flush();

            return redirect()->back()->with('success', __('messages.flash.mod_deleted'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('messages.flash.mod_delete_error') . ' ' . $e->getMessage());
        }
    }

    public function addOptionalMod(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);

        $activeInstance = ActiveInstance::current();

        OptionsMods::updateOrCreate(
            [
                'instance_id' => $activeInstance->id,
                'file' => $validated['file'],
            ],
            [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? '',
                'optional' => true,
            ]
        );

        LauncherCache::flush();

        return redirect()->back()->with('success', __('messages.flash.mod_added'));
    }

    public function getOptionalModDetails($id)
    {
        $mod = OptionsMods::find($id);
        if (!$mod) {
            return response()->json(['error' => __('messages.flash.mod_not_found')], 404);
        }

        return response()->json($mod);
    }
}
