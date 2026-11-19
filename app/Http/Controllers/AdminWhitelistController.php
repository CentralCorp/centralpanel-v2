<?php

namespace App\Http\Controllers;

use App\Models\MicrosoftPlayer;
use App\Models\OptionsWhitelist;
use App\Models\OptionsWhitelistRole;
use App\Models\PanelRole;
use App\Request\AzuriomApi;
use App\Support\ActiveInstance;
use App\Support\LauncherCache;
use App\Support\PanelOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminWhitelistController extends Controller
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
        $activeInstance = ActiveInstance::current();
        $securityOptions = PanelOptions::security();
        $generalOptions = PanelOptions::general();
        PanelOptions::ensureRoles();

        $users = OptionsWhitelist::query()
            ->where(function ($q) use ($activeInstance) {
                $q->where('instance_id', $activeInstance->id)
                    ->orWhereNull('instance_id');
            })
            ->orderByRaw('CASE WHEN instance_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('users')
            ->get();

        $roles = OptionsWhitelistRole::query()
            ->where(function ($q) use ($activeInstance) {
                $q->where('instance_id', $activeInstance->id)
                    ->orWhereNull('instance_id');
            })
            ->orderByRaw('CASE WHEN instance_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('role')
            ->get();

        $whitelistedUsernames = $users->pluck('users')->all();
        $whitelistedRoleNames = $roles->pluck('role')->all();

        $panelRoles = PanelRole::orderByDesc('power')->orderBy('name')->get();
        $microsoftPlayers = MicrosoftPlayer::with('role')
            ->where('is_banned', false)
            ->orderBy('username')
            ->get();

        $hasAzuriomApi = $this->azuriomApi !== null;

        return view('admin.whitelist', compact(
            'users',
            'roles',
            'activeInstance',
            'securityOptions',
            'generalOptions',
            'panelRoles',
            'microsoftPlayers',
            'whitelistedUsernames',
            'whitelistedRoleNames',
            'hasAzuriomApi'
        ));
    }

    public function fetchUsers(Request $request)
    {
        if (!$this->azuriomApi) {
            return response()->json(['error' => __('messages.flash.azuriom_api_error')], 503);
        }

        $activeInstance = ActiveInstance::current();
        $allUsers = Cache::remember('azuriom.users', now()->addMinutes(5), fn () => $this->azuriomApi->getUsers());
        $whitelistedUsers = OptionsWhitelist::query()
            ->where(function ($q) use ($activeInstance) {
                $q->where('instance_id', $activeInstance->id)->orWhereNull('instance_id');
            })
            ->pluck('users')
            ->toArray();

        $results = collect($allUsers)
            ->filter(function ($user) use ($whitelistedUsers) {
                if (in_array($user['name'], $whitelistedUsers, true)) return false;
                if ($user['is_banned'] ?? false) return false;
                if (str_starts_with($user['name'], 'Deleted #')) return false;
                return true;
            })
            ->sortBy(fn ($user) => [($user['role']['is_admin'] ?? false) ? 0 : 1, strtolower($user['name'])])
            ->map(fn ($user) => [
                'id' => $user['id'],
                'name' => $user['name'],
                'role' => $user['role']['name'] ?? 'Unknown',
                'role_color' => $user['role']['color'] ?? '#666',
                'is_admin' => $user['role']['is_admin'] ?? false,
            ])
            ->values();

        return response()->json($results);
    }

    public function fetchRoles(Request $request)
    {
        if (!$this->azuriomApi) {
            return response()->json(['error' => __('messages.flash.azuriom_api_error')], 503);
        }

        $activeInstance = ActiveInstance::current();
        $allRoles = Cache::remember('azuriom.roles', now()->addMinutes(5), fn () => $this->azuriomApi->getRoles());
        $whitelistedRoles = OptionsWhitelistRole::query()
            ->where(function ($q) use ($activeInstance) {
                $q->where('instance_id', $activeInstance->id)->orWhereNull('instance_id');
            })
            ->pluck('role')
            ->toArray();

        $results = collect($allRoles)
            ->filter(fn ($role) => !in_array($role['name'], $whitelistedRoles, true))
            ->sortBy(fn ($role) => [($role['is_admin'] ?? false) ? 0 : 1, strtolower($role['name'])])
            ->map(fn ($role) => [
                'id' => $role['id'],
                'name' => $role['name'],
                'color' => $role['color'] ?? '#666',
                'power' => $role['power'] ?? 0,
                'is_admin' => $role['is_admin'] ?? false,
            ])
            ->values();

        return response()->json($results);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'whitelist' => 'required|boolean',
            'scope' => 'nullable|in:instance,global',
            'manual_users' => 'nullable|string|max:2000',
            'whitelist_users' => 'nullable|array',
            'whitelist_users.*' => 'string|max:255',
            'panel_roles' => 'nullable|array',
            'panel_roles.*' => 'string|max:255',
            'azuriom_roles' => 'nullable|array',
            'azuriom_roles.*' => 'string|max:255',
        ]);

        $activeInstance = ActiveInstance::current();
        $activeInstance->update([
            'whitelist_activate' => (bool) $validated['whitelist'],
        ]);

        if ($activeInstance->is_default) {
            $securityOptions = PanelOptions::security();
            $securityOptions->whitelist = (bool) $validated['whitelist'];
            $securityOptions->save();
        }

        $instanceId = ($validated['scope'] ?? 'instance') === 'global' ? null : $activeInstance->id;

        $manualEntries = preg_split('/[\r\n,]+/', (string) ($validated['manual_users'] ?? '')) ?: [];
        $allUsernames = array_merge($validated['whitelist_users'] ?? [], $manualEntries);

        foreach ($allUsernames as $username) {
            $username = trim((string) $username);
            if ($username !== '') {
                OptionsWhitelist::firstOrCreate([
                    'instance_id' => $instanceId,
                    'users' => $username,
                ]);
            }
        }

        $allRoles = array_merge($validated['azuriom_roles'] ?? [], $validated['panel_roles'] ?? []);
        foreach ($allRoles as $role) {
            $role = trim((string) $role);
            if ($role !== '') {
                OptionsWhitelistRole::firstOrCreate([
                    'instance_id' => $instanceId,
                    'role' => $role,
                ]);
            }
        }

        LauncherCache::flushMetadata();

        return redirect()->route('admin.whitelist')->with('success', __('messages.flash.whitelist_updated'));
    }

    public function destroyUser($id)
    {
        OptionsWhitelist::findOrFail($id)->delete();
        LauncherCache::flushMetadata();

        return redirect()->route('admin.whitelist')->with('success', __('messages.flash.whitelist_user_deleted'));
    }

    public function destroyRole($id)
    {
        OptionsWhitelistRole::findOrFail($id)->delete();
        LauncherCache::flushMetadata();

        return redirect()->route('admin.whitelist')->with('success', __('messages.flash.whitelist_role_deleted'));
    }
}
