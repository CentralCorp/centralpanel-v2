<?php

namespace App\Http\Controllers;

use App\Models\CurseForgeMod;
use App\Models\OptionsMods;
use App\Support\ActiveInstance;
use App\Support\LauncherCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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

        $modpackFileSet = [];
        $individualCfMods = collect();

        if (Schema::hasTable('curseforge_mods')) {
            $modpackFileSet = CurseForgeMod::query()
                ->where('instance_id', $activeInstance->id)
                ->whereNotNull('modpack_id')
                ->pluck('file_name')
                ->map(static fn ($f): string => basename((string) $f))
                ->flip()
                ->all();

            $individualCfMods = CurseForgeMod::query()
                ->where('instance_id', $activeInstance->id)
                ->whereNull('modpack_id')
                ->get()
                ->keyBy(static fn (CurseForgeMod $m): string => basename((string) $m->file_name));
        }

        $seenFiles = [];
        $modsData = [];

        foreach ($jarFiles as $jarFile) {
            $baseName = preg_replace('/\.jar-disable$/', '.jar', basename($jarFile));
            if (isset($seenFiles[$baseName]) || isset($modpackFileSet[$baseName])) {
                continue;
            }
            $seenFiles[$baseName] = true;

            /** @var CurseForgeMod|null $cfMod */
            $cfMod = $individualCfMods->get($baseName);

            $modsData[] = [
                'file' => $baseName,
                'name' => $cfMod ? (string) $cfMod->name : $baseName,
                'description' => '',
                'icon' => $cfMod && $cfMod->logo_url ? (string) $cfMod->logo_url : '',
                'logo_url' => $cfMod ? $cfMod->logo_url : null,
                'curseforge_mod_id' => $cfMod ? (int) $cfMod->id : null,
                'is_curseforge' => $cfMod !== null,
                'version' => $cfMod ? $cfMod->version : null,
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
            if ($mod->icon && !self::isRemoteUrl($mod->icon) && Storage::disk('public')->exists($mod->icon)) {
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
            if ($mod->icon && !self::isRemoteUrl($mod->icon) && Storage::disk('public')->exists($mod->icon)) {
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
            'curseforge_mod_id' => 'nullable|integer|exists:curseforge_mods,id',
            'icon_url' => 'nullable|url|max:512',
            'optional_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $activeInstance = ActiveInstance::current();
        $fileName = basename((string) $validated['file']);

        $linkedCfMod = null;
        if (Schema::hasTable('curseforge_mods')) {
            if (!empty($validated['curseforge_mod_id'])) {
                $linkedCfMod = CurseForgeMod::query()
                    ->where('id', (int) $validated['curseforge_mod_id'])
                    ->where('instance_id', $activeInstance->id)
                    ->whereNull('modpack_id')
                    ->first();
            }

            if ($linkedCfMod === null) {
                $linkedCfMod = CurseForgeMod::query()
                    ->where('instance_id', $activeInstance->id)
                    ->where('file_name', $fileName)
                    ->whereNull('modpack_id')
                    ->first();
            }
        }

        $payload = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'optional' => true,
            'curseforge_mod_id' => $linkedCfMod?->id,
        ];

        if ($request->hasFile('optional_image')) {
            $payload['icon'] = $request->file('optional_image')->store('mod_icon', 'public');
        } elseif (!empty($validated['icon_url'])) {
            $payload['icon'] = (string) $validated['icon_url'];
        } elseif ($linkedCfMod && !empty($linkedCfMod->logo_url)) {
            $payload['icon'] = (string) $linkedCfMod->logo_url;
        }

        OptionsMods::updateOrCreate(
            [
                'instance_id' => $activeInstance->id,
                'file' => $fileName,
            ],
            $payload
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

    private static function isRemoteUrl(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }
}
