<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MicrosoftPlayer;
use App\Models\PanelRole;
use App\Support\LauncherCache;
use App\Support\LauncherResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerExternalApiController extends Controller
{
    public function ban(Request $request, string $uuid_or_username): JsonResponse
    {
        $player = MicrosoftPlayer::resolveByIdentifier($uuid_or_username);
        if (!$player) {
            return $this->playerNotFoundResponse($uuid_or_username);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
            'duration' => 'nullable|integer|min:1|max:315360000',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $reason = isset($validated['reason']) && trim($validated['reason']) !== ''
            ? trim($validated['reason'])
            : 'Banned via API';

        $bannedUntil = null;
        if (isset($validated['duration'])) {
            $bannedUntil = now()->addSeconds((int) $validated['duration']);
        } elseif (!empty($validated['expires_at'])) {
            $bannedUntil = Carbon::parse($validated['expires_at']);
        }

        $player->update([
            'is_banned' => true,
            'ban_reason' => $reason,
            'banned_until' => $bannedUntil,
        ]);

        LauncherCache::flushMetadata();

        return $this->playerResponse($player->fresh('role'));
    }

    public function unban(string $uuid_or_username): JsonResponse
    {
        $player = MicrosoftPlayer::resolveByIdentifier($uuid_or_username);
        if (!$player) {
            return $this->playerNotFoundResponse($uuid_or_username);
        }

        $player->update([
            'is_banned' => false,
            'ban_reason' => null,
            'banned_until' => null,
        ]);

        LauncherCache::flushMetadata();

        return $this->playerResponse($player->fresh('role'));
    }

    public function updateRole(Request $request, string $uuid_or_username): JsonResponse
    {
        $player = MicrosoftPlayer::resolveByIdentifier($uuid_or_username);
        if (!$player) {
            return $this->playerNotFoundResponse($uuid_or_username);
        }

        if (!$request->exists('role_id') && !$request->exists('role')) {
            return response()->json([
                'status' => 'error',
                'error' => 'validation_error',
                'message' => 'Either role_id or role must be provided.',
                'errors' => [
                    'role_id' => ['Either role_id or role must be provided.'],
                ],
            ], 422, [], LauncherResponse::JSON_FLAGS);
        }

        $validated = $request->validate([
            'role_id' => 'nullable|integer',
            'role' => 'nullable|string|max:255',
        ]);

        $roleId = null;

        if ($request->exists('role_id') && $validated['role_id'] !== null) {
            $role = PanelRole::find((int) $validated['role_id']);
            if (!$role) {
                return response()->json([
                    'status' => 'error',
                    'error' => 'role_not_found',
                    'message' => 'The specified role_id does not exist.',
                    'errors' => [
                        'role_id' => ['The specified role_id does not exist.'],
                    ],
                ], 422, [], LauncherResponse::JSON_FLAGS);
            }
            $roleId = $role->id;
        } elseif ($request->exists('role') && $validated['role'] !== null && trim($validated['role']) !== '') {
            $roleName = trim($validated['role']);
            $role = PanelRole::whereRaw('LOWER(name) = ?', [strtolower($roleName)])->first();
            if (!$role) {
                return response()->json([
                    'status' => 'error',
                    'error' => 'role_not_found',
                    'message' => 'The specified role does not exist.',
                    'errors' => [
                        'role' => ['The specified role does not exist.'],
                    ],
                ], 422, [], LauncherResponse::JSON_FLAGS);
            }
            $roleId = $role->id;
        }

        $player->update(['role_id' => $roleId]);
        LauncherCache::flushMetadata();

        return $this->playerResponse($player->fresh('role'));
    }

    public function updatePoints(Request $request, string $uuid_or_username): JsonResponse
    {
        $player = MicrosoftPlayer::resolveByIdentifier($uuid_or_username);
        if (!$player) {
            return $this->playerNotFoundResponse($uuid_or_username);
        }

        $validated = $request->validate([
            'action' => 'required|string|in:set,add,remove',
            'amount' => 'required|integer|min:0|max:1000000000',
        ]);

        $current = (int) $player->money;
        $amount = (int) $validated['amount'];

        $newAmount = match ($validated['action']) {
            'set' => $amount,
            'add' => min(1000000000, $current + $amount),
            'remove' => max(0, $current - $amount),
        };

        $player->update(['money' => $newAmount]);
        LauncherCache::flushMetadata();

        return $this->playerResponse($player->fresh('role'));
    }

    private function playerNotFoundResponse(string $identifier): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'error' => 'player_not_found',
            'message' => "Player '{$identifier}' not found.",
        ], 404, [], LauncherResponse::JSON_FLAGS);
    }

    private function playerResponse(MicrosoftPlayer $player): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'player' => [
                'id' => (int) $player->id,
                'uuid' => $player->uuid,
                'username' => $player->username,
                'is_banned' => (bool) $player->is_banned,
                'ban_reason' => $player->ban_reason,
                'banned_until' => $player->banned_until?->toIso8601String(),
                'points' => (int) $player->money,
                'money' => (int) $player->money,
                'role' => $player->role ? [
                    'id' => (int) $player->role->id,
                    'name' => $player->role->name,
                    'color' => $player->role->color,
                ] : null,
            ],
        ], 200, [], LauncherResponse::JSON_FLAGS);
    }
}
