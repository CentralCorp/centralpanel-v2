<?php

namespace App\Http\Controllers;

use App\Exceptions\CurseForgeException;
use App\Models\CurseForgeMod;
use App\Services\CurseForgeService;
use App\Support\ActiveInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminCurseForgeController extends Controller
{
    public function __construct(
        private readonly CurseForgeService $curseForge
    ) {
    }

    public function index()
    {
        $activeInstance = ActiveInstance::current();
        $activeInstance->ensureDirectoriesExist();

        $installed = $this->curseForge->getInstalledMods($activeInstance);
        $hasApiKey = $this->curseForge->hasApiKey();
        $mcVersion = $this->curseForge->resolveInstanceMcVersion($activeInstance);
        $resolvedLoader = $this->curseForge->resolveInstanceLoader($activeInstance);
        $rawLoader = strtolower(trim((string) ($activeInstance->loader_type ?: 'vanilla')));
        $loaderSupported = $resolvedLoader !== null;

        return view('admin.curseforge', compact(
            'activeInstance',
            'installed',
            'hasApiKey',
            'mcVersion',
            'resolvedLoader',
            'rawLoader',
            'loaderSupported'
        ));
    }

    public function saveApiKey(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'curseforge_api_key' => 'nullable|string|max:255',
        ]);

        $this->curseForge->saveApiKey($validated['curseforge_api_key'] ?? null);
        $configured = $this->curseForge->hasApiKey();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'has_api_key' => $configured,
                'message' => $configured
                    ? 'Clé API CurseForge enregistrée avec succès.'
                    : 'Clé API CurseForge effacée.',
            ]);
        }

        return redirect()->back()->with(
            'success',
            $configured ? 'Clé API CurseForge enregistrée avec succès.' : 'Clé API CurseForge effacée.'
        );
    }

    public function installed(): JsonResponse
    {
        $activeInstance = ActiveInstance::current();

        return response()->json([
            'status' => 'ok',
            'instance_id' => $activeInstance->id,
            'data' => $this->curseForge->getInstalledMods($activeInstance),
        ]);
    }

    public function searchMods(Request $request): JsonResponse
    {
        $activeInstance = ActiveInstance::current();
        $query = (string) $request->query('q', '');
        $page = max(0, (int) $request->query('page', 0));
        $pageSize = max(1, min(50, (int) $request->query('page_size', 20)));

        $mcVersion = $this->curseForge->resolveInstanceMcVersion($activeInstance);
        $loader = $this->curseForge->resolveInstanceLoader($activeInstance);

        try {
            $result = $this->curseForge->searchMods($query, $mcVersion, $loader, $page, $pageSize);

            return response()->json([
                'status' => 'ok',
                'mc_version' => $mcVersion,
                'loader' => $loader,
                'items' => $result['items'],
                'pagination' => $result['pagination'],
            ]);
        } catch (CurseForgeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->statusCode());
        }
    }

    public function searchModpacks(Request $request): JsonResponse
    {
        $activeInstance = ActiveInstance::current();
        $query = (string) $request->query('q', '');
        $page = max(0, (int) $request->query('page', 0));
        $pageSize = max(1, min(50, (int) $request->query('page_size', 20)));

        $mcVersion = $this->curseForge->resolveInstanceMcVersion($activeInstance);
        $loader = $this->curseForge->resolveInstanceLoader($activeInstance);

        try {
            $result = $this->curseForge->searchModpacks($query, $mcVersion, $loader, $page, $pageSize);

            return response()->json([
                'status' => 'ok',
                'mc_version' => $mcVersion,
                'loader' => $loader,
                'items' => $result['items'],
                'pagination' => $result['pagination'],
            ]);
        } catch (CurseForgeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->statusCode());
        }
    }

    public function installMod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mod_id' => 'required|integer|min:1',
            'file_id' => 'nullable|integer|min:1',
        ]);

        $activeInstance = ActiveInstance::current();

        try {
            $result = $this->curseForge->installMod(
                $activeInstance,
                (int) $validated['mod_id'],
                isset($validated['file_id']) ? (int) $validated['file_id'] : null
            );

            return response()->json([
                'status' => 'ok',
                'message' => "Mod '{$result['mod']['name']}' installé avec succès.",
                'data' => $result,
            ]);
        } catch (CurseForgeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->statusCode());
        }
    }

    public function installModpack(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'modpack_id' => 'required|integer|min:1',
            'file_id' => 'nullable|integer|min:1',
        ]);

        $activeInstance = ActiveInstance::current();

        try {
            $summary = $this->curseForge->installModpack(
                $activeInstance,
                (int) $validated['modpack_id'],
                isset($validated['file_id']) ? (int) $validated['file_id'] : null
            );

            return response()->json([
                'status' => 'ok',
                'message' => "Modpack '{$summary['modpack_name']}' installé ({$summary['installed_count']} mod(s) installés).",
                'data' => $summary,
            ]);
        } catch (CurseForgeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->statusCode());
        }
    }

    public function checkUpdates(): JsonResponse
    {
        $activeInstance = ActiveInstance::current();

        try {
            $updates = $this->curseForge->checkUpdates($activeInstance);

            return response()->json([
                'status' => 'ok',
                'updates' => $updates,
            ]);
        } catch (CurseForgeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->statusCode());
        }
    }

    public function updateMod(Request $request, int $mod): JsonResponse
    {
        $validated = $request->validate([
            'file_id' => 'nullable|integer|min:1',
        ]);

        $activeInstance = ActiveInstance::current();
        $record = CurseForgeMod::findOrFail($mod);

        try {
            $result = $this->curseForge->updateMod(
                $activeInstance,
                $record,
                isset($validated['file_id']) ? (int) $validated['file_id'] : null
            );

            return response()->json([
                'status' => 'ok',
                'message' => "Mod '{$result['mod']['name']}' mis à jour avec succès.",
                'data' => $result,
            ]);
        } catch (CurseForgeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->statusCode());
        }
    }

    public function deleteMod(int $mod): JsonResponse
    {
        $activeInstance = ActiveInstance::current();
        $record = CurseForgeMod::findOrFail($mod);

        try {
            $name = $record->name;
            $this->curseForge->deleteCurseForgeMod($activeInstance, $record);

            return response()->json([
                'status' => 'ok',
                'message' => "Mod '{$name}' supprimé.",
            ]);
        } catch (CurseForgeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->statusCode());
        }
    }

    public function deleteExternalMod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_name' => 'required|string|max:255',
        ]);

        $activeInstance = ActiveInstance::current();

        try {
            $this->curseForge->deleteExternalMod($activeInstance, (string) $validated['file_name']);

            return response()->json([
                'status' => 'ok',
                'message' => "Mod externe '{$validated['file_name']}' supprimé.",
            ]);
        } catch (CurseForgeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->statusCode());
        }
    }

    public function scanExternalMods(): JsonResponse
    {
        $activeInstance = ActiveInstance::current();

        try {
            $matches = $this->curseForge->scanExternalMods($activeInstance);

            return response()->json([
                'status' => 'ok',
                'matches' => $matches,
                'matched_count' => count($matches),
            ]);
        } catch (CurseForgeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->statusCode());
        }
    }

    public function reconcileExternalMod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_name' => 'required|string|max:255',
            'mod_id' => 'required|integer|min:1',
            'file_id' => 'required|integer|min:1',
        ]);

        $activeInstance = ActiveInstance::current();

        try {
            $record = $this->curseForge->reconcileExternalMod(
                $activeInstance,
                (string) $validated['file_name'],
                (int) $validated['mod_id'],
                (int) $validated['file_id']
            );

            return response()->json([
                'status' => 'ok',
                'message' => "Mod '{$record['name']}' converti vers CurseForge avec succès.",
                'data' => $record,
            ]);
        } catch (CurseForgeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->statusCode());
        }
    }
}
