<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Instance;
use App\Models\OptionsMods;
use App\Support\LauncherCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModController extends Controller
{
    public function getMods(Request $request): JsonResponse
    {
        $slug = $request->query('instance');
        if (is_string($slug) && $slug !== '') {
            return $this->getInstanceMods($slug);
        }

        $output = LauncherCache::remember('launcher_optional_mods', 'default_legacy', now()->addMinutes(5), function () {
            $defaultInstance = Instance::where('is_default', true)->first()
                ?? Instance::orderBy('position')->orderBy('id')->first();

            $query = OptionsMods::query()->orderBy('name');
            if ($defaultInstance) {
                $query->where(function ($q) use ($defaultInstance) {
                    $q->where('instance_id', $defaultInstance->id)
                        ->orWhereNull('instance_id');
                });
            } else {
                $query->whereNull('instance_id');
            }

            return $this->formatModsPayload($query->get(), false);
        });

        return response()->json($output, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function getInstanceMods(string $slug): JsonResponse
    {
        $instance = Instance::where('slug', $slug)->where('is_enabled', true)->first();

        if (!$instance) {
            if ($slug === 'default' && Instance::count() === 0) {
                $output = LauncherCache::remember('launcher_optional_mods', 'instance:default_fallback', now()->addMinutes(5), function () {
                    $mods = OptionsMods::query()->whereNull('instance_id')->orderBy('name')->get();

                    return $this->formatModsPayload($mods, true);
                });

                return response()->json($output, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Instance not found',
            ], 404, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $output = LauncherCache::remember('launcher_optional_mods', 'instance:' . $instance->slug, now()->addMinutes(5), function () use ($instance) {
            $query = OptionsMods::query()->orderBy('name');
            if ($instance->is_default) {
                $query->where(function ($q) use ($instance) {
                    $q->where('instance_id', $instance->id)
                        ->orWhereNull('instance_id');
                });
            } else {
                $query->where('instance_id', $instance->id);
            }

            return $this->formatModsPayload($query->get(), true);
        });

        return response()->json($output, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function formatModsPayload($mods, bool $includeRecommendedAlias): array
    {
        $modsData = [];
        $optionalMods = [];

        foreach ($mods as $mod) {
            $modsFile = basename($mod->file);
            $entry = [
                'name' => $mod->name,
                'description' => $mod->description,
                'icon' => $mod->icon ? asset('storage/' . $mod->icon) : '',
                'recommanded' => (bool) $mod->recommended,
            ];

            if ($includeRecommendedAlias) {
                $entry['recommended'] = (bool) $mod->recommended;
            }

            $modsData[$modsFile] = $entry;

            if ($mod->optional) {
                $optionalMods[] = $modsFile;
            }
        }

        return [
            'optionalMods' => array_values(array_unique($optionalMods)),
            'mods' => (object) $modsData,
        ];
    }
}
