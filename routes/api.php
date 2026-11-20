<?php

use App\Http\Controllers\api\PlayerExternalApiController;
use Illuminate\Support\Facades\Route;

// External REST API for Microsoft player management (/api/v1/players/...)
Route::prefix('v1/players')->group(function () {
    Route::post('/{uuid_or_username}/ban', [PlayerExternalApiController::class, 'ban'])
        ->middleware('player.api:players:ban')
        ->name('api.v1.players.ban');

    Route::post('/{uuid_or_username}/unban', [PlayerExternalApiController::class, 'unban'])
        ->middleware('player.api:players:unban')
        ->name('api.v1.players.unban');

    Route::patch('/{uuid_or_username}/role', [PlayerExternalApiController::class, 'updateRole'])
        ->middleware('player.api:players:role')
        ->name('api.v1.players.role');

    Route::patch('/{uuid_or_username}/points', [PlayerExternalApiController::class, 'updatePoints'])
        ->middleware('player.api:players:points')
        ->name('api.v1.players.points');
});
