<?php

namespace App\Http\Middleware;

use App\Models\OptionsSecurity;
use App\Models\PlayerApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePlayerApiToken
{
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $security = OptionsSecurity::first();
        if (!$security || !$security->player_api_enabled) {
            return response()->json([
                'status' => 'error',
                'error' => 'api_disabled',
                'message' => 'External player API is disabled.',
            ], 503);
        }

        $plainToken = $request->bearerToken();
        if (!$plainToken) {
            return response()->json([
                'status' => 'error',
                'error' => 'missing_token',
                'message' => 'Authorization Bearer token is required.',
            ], 401);
        }

        $token = PlayerApiToken::findByPlainText($plainToken);
        if (!$token) {
            return response()->json([
                'status' => 'error',
                'error' => 'invalid_token',
                'message' => 'The provided API token is invalid.',
            ], 401);
        }

        if ($token->isExpired()) {
            return response()->json([
                'status' => 'error',
                'error' => 'expired_token',
                'message' => 'The provided API token has expired.',
            ], 401);
        }

        if ($ability !== null && $ability !== '' && !$token->hasAbility($ability)) {
            return response()->json([
                'status' => 'error',
                'error' => 'insufficient_ability',
                'message' => "This API token lacks the required '{$ability}' permission.",
            ], 403);
        }

        $token->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('player_api_token', $token);

        return $next($request);
    }
}
