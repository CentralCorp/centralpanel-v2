<?php

namespace App\Http\Controllers;

use App\Support\ActiveInstance;
use App\Support\LauncherCache;
use App\Support\PanelOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AdminLoaderController extends Controller
{
    public function index()
    {
        $activeInstance = ActiveInstance::current();
        $row = $activeInstance;

        return view('admin.loader', compact('row', 'activeInstance'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'minecraft_version' => 'required|string|max:50',
            'loader_activation' => 'required|boolean',
            'loader_type' => 'required|string|in:forge,fabric,legacyfabric,neoForge,neoforge,quilt',
            'loader_forge_version' => 'nullable|string|max:100',
            'loader_build_version' => 'nullable|string|max:100',
            'java_version' => 'nullable|integer|min:8|max:30',
            'jvm_args_text' => 'nullable|string|max:4000',
            'game_args_text' => 'nullable|string|max:4000',
        ]);

        $activeInstance = ActiveInstance::current();

        $updatePayload = [
            'minecraft_version' => $validated['minecraft_version'],
            'loader_activation' => (bool) $validated['loader_activation'],
            'loader_type' => $validated['loader_type'],
            'loader_forge_version' => $validated['loader_forge_version'] ?? $validated['loader_build_version'] ?? null,
            'loader_build_version' => $validated['loader_build_version'] ?? $validated['loader_forge_version'] ?? null,
        ];

        if ($request->has('java_version')) {
            $updatePayload['java_version'] = $validated['java_version'] ?? null;
        }
        if ($request->has('jvm_args_text')) {
            $lines = preg_split('/[\r\n]+/', (string) ($validated['jvm_args_text'] ?? '')) ?: [];
            $updatePayload['jvm_args'] = array_values(array_filter(array_map('trim', $lines), fn ($v) => $v !== ''));
        }
        if ($request->has('game_args_text')) {
            $lines = preg_split('/[\r\n]+/', (string) ($validated['game_args_text'] ?? '')) ?: [];
            $updatePayload['game_args'] = array_values(array_filter(array_map('trim', $lines), fn ($v) => $v !== ''));
        }

        $activeInstance->update($updatePayload);

        if ($activeInstance->is_default) {
            PanelOptions::loader()->update([
                'minecraft_version' => $activeInstance->minecraft_version,
                'loader_activation' => $activeInstance->loader_activation,
                'loader_type' => $activeInstance->loader_type,
                'loader_forge_version' => $activeInstance->loader_forge_version,
                'loader_build_version' => $activeInstance->loader_build_version,
            ]);
        }

        LauncherCache::flushMetadata();

        return redirect()->back()->with('success', __('messages.flash.loader_updated'));
    }

    public function getForgeBuilds(Request $request)
    {
        $validated = $request->validate([
            'mc_version' => 'required|string|max:50',
        ]);

        $mcVersion = $validated['mc_version'];
        $url = "https://files.minecraftforge.net/net/minecraftforge/forge/index_{$mcVersion}.html";

        $builds = Cache::remember("forge_builds:{$mcVersion}", now()->addHours(6), function () use ($url) {
            $response = Http::timeout(10)->get($url);
            if (!$response->successful()) {
                return [];
            }

            $dom = new \DOMDocument;
            libxml_use_internal_errors(true);
            $dom->loadHTML($response->body());
            libxml_clear_errors();

            $xpath = new \DOMXPath($dom);
            $links = $xpath->query('//a[contains(@href, "maven.minecraftforge.net/net/minecraftforge/forge/")]');
            $builds = [];

            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                if (preg_match('/forge\/([\d\.\-]+)\/forge-\1-/', $href, $matches)) {
                    $builds[] = $matches[1];
                }
            }

            return array_values(array_unique($builds));
        });

        return response()->json(['builds' => $builds]);
    }

    public function getFabricVersions()
    {
        $versions = Cache::remember('fabric_loader_versions', now()->addHours(6), function () {
            $response = Http::timeout(10)->get('https://meta.fabricmc.net/v2/versions/loader');

            return $response->successful() ? $response->json() : [];
        });

        return response()->json(['versions' => $versions]);
    }
}
