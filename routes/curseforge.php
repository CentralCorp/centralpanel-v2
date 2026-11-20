<?php

use App\Http\Controllers\AdminCurseForgeController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/curseforge')->middleware('auth')->name('admin.curseforge')->group(function () {
    Route::get('/', [AdminCurseForgeController::class, 'index']);
    Route::post('/api-key', [AdminCurseForgeController::class, 'saveApiKey'])->name('.api-key');
    Route::get('/installed', [AdminCurseForgeController::class, 'installed'])->name('.installed');
    Route::get('/search/mods', [AdminCurseForgeController::class, 'searchMods'])->name('.search.mods');
    Route::get('/search/modpacks', [AdminCurseForgeController::class, 'searchModpacks'])->name('.search.modpacks');
    Route::post('/mods/install', [AdminCurseForgeController::class, 'installMod'])->name('.mods.install');
    Route::post('/modpacks/prepare', [AdminCurseForgeController::class, 'prepareModpack'])->name('.modpacks.prepare');
    Route::post('/modpacks/install-batch', [AdminCurseForgeController::class, 'installModpackBatch'])->name('.modpacks.install-batch');
    Route::post('/modpacks/finalize', [AdminCurseForgeController::class, 'finalizeModpack'])->name('.modpacks.finalize');
    Route::post('/modpacks/install', [AdminCurseForgeController::class, 'installModpack'])->name('.modpacks.install');
    Route::post('/modpacks/{modpack}/update', [AdminCurseForgeController::class, 'updateModpack'])->name('.modpacks.update')->whereNumber('modpack');
    Route::delete('/modpacks/{modpack}', [AdminCurseForgeController::class, 'deleteModpack'])->name('.modpacks.delete')->whereNumber('modpack');
    Route::post('/updates/check', [AdminCurseForgeController::class, 'checkUpdates'])->name('.updates.check');
    Route::post('/mods/{mod}/update', [AdminCurseForgeController::class, 'updateMod'])->name('.mods.update')->whereNumber('mod');
    Route::delete('/mods/{mod}', [AdminCurseForgeController::class, 'deleteMod'])->name('.mods.delete')->whereNumber('mod');
    Route::delete('/external', [AdminCurseForgeController::class, 'deleteExternalMod'])->name('.external.delete');
    Route::post('/external/scan', [AdminCurseForgeController::class, 'scanExternalMods'])->name('.external.scan');
    Route::post('/external/reconcile', [AdminCurseForgeController::class, 'reconcileExternalMod'])->name('.external.reconcile');
});
