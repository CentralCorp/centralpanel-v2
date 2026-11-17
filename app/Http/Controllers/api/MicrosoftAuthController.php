<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MicrosoftPlayer;
use App\Models\OptionsSecurity;
use App\Support\PanelOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MicrosoftAuthController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $accessToken = trim((string) $request->input('access_token', ''));

        if ($accessToken === '') {
            return response()->json([
                'status' => 'error',
                'reason' => 'invalid_token',
                'message' => 'Invalid Minecraft token',
            ], 401, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->get('https://api.minecraftservices.com/minecraft/profile');
        } catch (\Throwable) {
            return response()->json([
                'status' => 'error',
                'reason' => 'invalid_token',
                'message' => 'Invalid Minecraft token',
            ], 401, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $profile = $response->successful() ? $response->json() : null;
        if (!is_array($profile) || empty($profile['id']) || empty($profile['name'])) {
            return response()->json([
                'status' => 'error',
                'reason' => 'invalid_token',
                'message' => 'Invalid Minecraft token',
            ], 401, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $uuid = MicrosoftPlayer::normalizeUuid((string) $profile['id']);
        $username = trim((string) $profile['name']);

        $activeSkinUrl = $request->input('skin_url');
        if (!$activeSkinUrl && !empty($profile['skins']) && is_array($profile['skins'])) {
            foreach ($profile['skins'] as $skin) {
                if (($skin['state'] ?? '') === 'ACTIVE' && !empty($skin['url'])) {
                    $activeSkinUrl = $skin['url'];
                    break;
                }
            }
        }

        $activeCapeUrl = $request->input('cape_url');
        if (!$activeCapeUrl && !empty($profile['capes']) && is_array($profile['capes'])) {
            foreach ($profile['capes'] as $cape) {
                if (($cape['state'] ?? '') === 'ACTIVE' && !empty($cape['url'])) {
                    $activeCapeUrl = $cape['url'];
                    break;
                }
            }
        }

        $defaultRole = PanelOptions::ensureRoles();

        $player = MicrosoftPlayer::where('uuid', $uuid)->first();
        if (!$player) {
            // Match pre-registered player by case-insensitive username where uuid was null
            $player = MicrosoftPlayer::whereNull('uuid')
                ->whereRaw('LOWER(username) = ?', [strtolower($username)])
                ->first();
        }

        // Prevent unique username collision if another player previously held this username
        $colliding = MicrosoftPlayer::whereRaw('LOWER(username) = ?', [strtolower($username)])
            ->when($player, fn ($q) => $q->where('id', '!=', $player->id))
            ->first();

        if ($colliding) {
            $suffix = $colliding->uuid ? substr($colliding->uuid, 0, 6) : (string) $colliding->id;
            $colliding->update(['username' => $colliding->username . '_' . $suffix]);
        }

        if (!$player) {
            $player = new MicrosoftPlayer([
                'uuid' => $uuid,
                'username' => $username,
                'role_id' => $defaultRole->id,
                'money' => 0,
                'is_banned' => false,
            ]);
        } else {
            $player->uuid = $uuid;
            $player->username = $username;
            if (!$player->role_id) {
                $player->role_id = $defaultRole->id;
            }
        }

        if ($request->filled('xuid')) {
            $player->xuid = (string) $request->input('xuid');
        }
        if ($activeSkinUrl) {
            $player->skin_url = (string) $activeSkinUrl;
        }
        if ($activeCapeUrl || $request->has('cape_url')) {
            $player->cape_url = $activeCapeUrl ? (string) $activeCapeUrl : null;
        }
        $player->last_seen_at = now();
        $player->save();

        if ($player->is_banned) {
            return response()->json([
                'status' => 'error',
                'reason' => 'user_banned',
                'message' => $player->ban_reason ?: 'Account banned',
            ], 403, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $role = $player->role()->first() ?? $defaultRole;
        $security = OptionsSecurity::first();

        $globalBypassRoles = array_map('strtolower', array_filter($security?->maintenance_bypass_roles ?? []));
        $globalBypassUsers = array_map('strtolower', array_filter($security?->maintenance_bypass_users ?? []));

        $maintenanceBypass = (bool) ($role?->bypass_maintenance)
            || ($role && in_array(strtolower($role->name), $globalBypassRoles, true))
            || in_array(strtolower($player->username), $globalBypassUsers, true)
            || ($player->uuid && in_array(strtolower($player->uuid), $globalBypassUsers, true));

        $domain = request()->getHost();
        $protocol = request()->isSecure() ? 'https' : 'http';
        $port = request()->getPort();
        $baseURL = $protocol . '://' . $domain . (($port && !in_array($port, [80, 443], true)) ? ":$port" : '');

        return response()->json([
            'status' => 'success',
            'uuid' => $player->uuid,
            'username' => $player->username,
            'money' => (int) $player->money,
            'banned' => false,
            'ban_reason' => null,
            'verified' => true,
            'maintenance_bypass' => $maintenanceBypass,
            'role' => [
                'id' => (int) $role->id,
                'name' => $role->name,
                'color' => $role->color ?: '#00bd7a',
                'background' => $role->background_path
                    ? ($baseURL . '/storage/' . ltrim($role->background_path, '/'))
                    : null,
            ],
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
