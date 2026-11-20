<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Instance;
use App\Models\OptionsMods;
use App\Support\LauncherCache;
use App\Support\LauncherResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ModController extends Controller
{
    public function getMods(Request $request): Response
    {
        $slug = $request->query('instance');
        if (is_string($slug) && $slug !== '') {
            return $this->getInstanceMods($request, $slug);
        }

        $output = LauncherCache::remember('launcher_optional_mods', 'default_legacy', now()->addMinutes(30), function () {
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

            return self::formatModsPayload($query->get(), false);
        });

        return LauncherResponse::json($request, $output);
    }

    public function getInstanceMods(Request $request, string $slug): Response
    {
        $instance = Instance::where('slug', $slug)->where('is_enabled', true)->first();

        if (!$instance) {
            if ($slug === 'default' && Instance::count() === 0) {
                $output = LauncherCache::remember('launcher_optional_mods', 'instance:default_fallback', now()->addMinutes(30), function () {
                    $mods = OptionsMods::query()->whereNull('instance_id')->orderBy('name')->get();

                    return self::formatModsPayload($mods, true);
                });

                return LauncherResponse::json($request, $output);
            }

            return LauncherResponse::json($request, [
                'status' => 'error',
                'message' => 'Instance not found',
            ], 404);
        }

        $output = LauncherCache::remember('launcher_optional_mods', 'instance:' . $instance->slug, now()->addMinutes(30), function () use ($instance) {
            $query = OptionsMods::query()->orderBy('name');
            if ($instance->is_default) {
                $query->where(function ($q) use ($instance) {
                    $q->where('instance_id', $instance->id)
                        ->orWhereNull('instance_id');
                });
            } else {
                $query->where('instance_id', $instance->id);
            }

            return self::formatModsPayload($query->get(), true);
        });

        return LauncherResponse::json($request, $output);
    }

    public static function getCachedModsByInstance(string $baseURL): array
    {
        $baseURL = rtrim($baseURL, '/');

        return LauncherCache::remember('launcher_mods_by_instance', sha1($baseURL), now()->addMinutes(30), static function () use ($baseURL): array {
            $instances = Instance::where('is_enabled', true)
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'slug', 'is_default']);

            $allOptionalMods = OptionsMods::query()
                ->where('optional', true)
                ->orderBy('name')
                ->get();

            if ($instances->isEmpty()) {
                $legacyMods = $allOptionalMods->filter(static fn (OptionsMods $mod): bool => $mod->instance_id === null);

                return [
                    'default' => self::formatModsPayload($legacyMods, true, $baseURL),
                ];
            }

            $result = [];
            foreach ($instances as $instance) {
                $scopedMods = $allOptionalMods->filter(static function (OptionsMods $mod) use ($instance): bool {
                    if ($instance->is_default) {
                        return $mod->instance_id === (int) $instance->id || $mod->instance_id === null;
                    }

                    return $mod->instance_id === (int) $instance->id;
                });

                $result[(string) $instance->slug] = self::formatModsPayload($scopedMods, true, $baseURL);
            }

            return $result;
        });
    }

    public static function formatModsPayload($mods, bool $includeRecommendedAlias, ?string $baseURL = null): array
    {
        $modsData = [];
        $optionalMods = [];

        foreach ($mods as $mod) {
            $modsFile = basename($mod->file);
            $iconUrl = '';
            if ($mod->icon) {
                if (str_starts_with($mod->icon, 'http://') || str_starts_with($mod->icon, 'https://')) {
                    $iconUrl = $mod->icon;
                } else {
                    $rel = 'storage/' . ltrim($mod->icon, '/');
                    $iconUrl = $baseURL ? (rtrim($baseURL, '/') . '/' . $rel) : asset($rel);
                }
            }

            $entry = [
                'name' => $mod->name,
                'description' => $mod->description,
                'icon' => $iconUrl,
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
