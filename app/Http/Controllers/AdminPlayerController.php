<?php

namespace App\Http\Controllers;

use App\Models\MicrosoftPlayer;
use App\Models\PanelRole;
use App\Support\LauncherCache;
use App\Support\PanelOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminPlayerController extends Controller
{
    public function index(Request $request)
    {
        $defaultRole = PanelOptions::ensureRoles();
        $roles = PanelRole::orderByDesc('power')->orderBy('name')->get();

        $search = trim((string) $request->query('q', ''));
        $roleFilter = $request->query('role_id');
        $statusFilter = $request->query('status');

        $players = MicrosoftPlayer::with('role')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('username', 'like', "%{$search}%")
                        ->orWhere('uuid', 'like', '%' . str_replace('-', '', $search) . '%');
                });
            })
            ->when($roleFilter !== null && $roleFilter !== '', fn ($q) => $q->where('role_id', (int) $roleFilter))
            ->when($statusFilter === 'banned', fn ($q) => $q->where('is_banned', true))
            ->when($statusFilter === 'active', fn ($q) => $q->where('is_banned', false))
            ->orderByDesc('last_seen_at')
            ->orderBy('username')
            ->get();

        return view('admin.players.index', compact('players', 'roles', 'defaultRole', 'search', 'roleFilter', 'statusFilter'));
    }

    public function store(Request $request)
    {
        $normalizedUuid = MicrosoftPlayer::normalizeUuid($request->input('uuid'));
        $request->merge(['uuid' => $normalizedUuid]);

        $validated = $request->validate([
            'username' => 'required|string|max:64|unique:microsoft_players,username',
            'uuid' => 'nullable|string|regex:/^[0-9a-f]{32}$/|unique:microsoft_players,uuid',
            'role_id' => 'nullable|integer|exists:panel_roles,id',
            'money' => 'required|integer|min:0|max:1000000000',
            'is_banned' => 'boolean',
            'ban_reason' => 'nullable|string|max:255',
        ]);

        $defaultRole = PanelOptions::ensureRoles();

        $player = MicrosoftPlayer::create([
            'username' => trim($validated['username']),
            'uuid' => $validated['uuid'] ?? null,
            'role_id' => ($validated['role_id'] ?? null) ?: $defaultRole->id,
            'money' => (int) $validated['money'],
            'is_banned' => $request->boolean('is_banned'),
            'ban_reason' => $request->boolean('is_banned') ? (($validated['ban_reason'] ?? null) ?: null) : null,
        ]);

        LauncherCache::flush();

        return redirect()->route('admin.players')->with('success', __('messages.flash.player_created', ['name' => $player->username]));
    }

    public function update(Request $request, MicrosoftPlayer $player)
    {
        $normalizedUuid = MicrosoftPlayer::normalizeUuid($request->input('uuid'));
        $request->merge(['uuid' => $normalizedUuid]);

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:64', Rule::unique('microsoft_players', 'username')->ignore($player->id)],
            'uuid' => ['nullable', 'string', 'regex:/^[0-9a-f]{32}$/', Rule::unique('microsoft_players', 'uuid')->ignore($player->id)],
            'role_id' => 'nullable|integer|exists:panel_roles,id',
            'money' => 'required|integer|min:0|max:1000000000',
            'is_banned' => 'boolean',
            'ban_reason' => 'nullable|string|max:255',
        ]);

        $defaultRole = PanelOptions::ensureRoles();
        $isBanned = $request->boolean('is_banned');

        $player->update([
            'username' => trim($validated['username']),
            'uuid' => $validated['uuid'] ?? null,
            'role_id' => ($validated['role_id'] ?? null) ?: $defaultRole->id,
            'money' => (int) $validated['money'],
            'is_banned' => $isBanned,
            'ban_reason' => $isBanned ? (($validated['ban_reason'] ?? null) ?: null) : null,
        ]);

        LauncherCache::flush();

        return redirect()->route('admin.players')->with('success', __('messages.flash.player_updated', ['name' => $player->username]));
    }

    public function toggleBan(Request $request, MicrosoftPlayer $player)
    {
        $validated = $request->validate([
            'ban_reason' => 'nullable|string|max:255',
        ]);

        $newBanState = !$player->is_banned;
        $player->update([
            'is_banned' => $newBanState,
            'ban_reason' => $newBanState ? (($validated['ban_reason'] ?? null) ?: $player->ban_reason) : null,
        ]);

        LauncherCache::flush();

        $msgKey = $newBanState ? 'messages.flash.player_banned' : 'messages.flash.player_unbanned';

        return redirect()->route('admin.players')->with('success', __($msgKey, ['name' => $player->username]));
    }

    public function destroy(MicrosoftPlayer $player)
    {
        $name = $player->username;
        $player->delete();
        LauncherCache::flush();

        return redirect()->route('admin.players')->with('success', __('messages.flash.player_deleted', ['name' => $name]));
    }
}
