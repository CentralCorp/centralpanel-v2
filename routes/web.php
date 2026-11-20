<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SettingsExportController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\AdminBgController;
use App\Http\Controllers\AdminConfigController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminIgnoreController;
use App\Http\Controllers\AdminInstanceController;
use App\Http\Controllers\AdminLoaderController;
use App\Http\Controllers\AdminModController;
use App\Http\Controllers\AdminNewsController;
use App\Http\Controllers\AdminPlayerController;
use App\Http\Controllers\AdminRoleController;
use App\Http\Controllers\AdminRpcController;
use App\Http\Controllers\AdminSecurityController;
use App\Http\Controllers\AdminServerController;
use App\Http\Controllers\AdminUIController;
use App\Http\Controllers\AdminWhitelistController;
use App\Http\Controllers\api\ApiController;
use App\Http\Controllers\api\FileController;
use App\Http\Controllers\api\MicrosoftAuthController;
use App\Http\Controllers\api\ModController;
use App\Http\Controllers\api\NewsController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\users\AdminUserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Auth::routes(['register' => false]);

Route::get('/', [PanelController::class, 'root']);

Route::get('/install', [InstallController::class, 'showDatabase'])->name('install.database');
Route::post('/install', [InstallController::class, 'install'])->name('install.store');
Route::get('/install/finish', [InstallController::class, 'finish'])->name('install.finish');

Route::prefix('admin')->middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('admin.index');

    Route::get('/config', [AdminConfigController::class, 'show'])->name('admin.config');
    Route::post('/config', [AdminConfigController::class, 'update'])->name('admin.config.update');

    Route::get('/instances', [AdminInstanceController::class, 'index'])->name('admin.instances');
    Route::get('/instances/create', [AdminInstanceController::class, 'create'])->name('admin.instances.create');
    Route::post('/instances', [AdminInstanceController::class, 'store'])->name('admin.instances.store');
    Route::post('/instances/switch', [AdminInstanceController::class, 'switchInstance'])->name('admin.instances.switch');
    Route::post('/instances/{instance}/default', [AdminInstanceController::class, 'setDefault'])->name('admin.instances.default');
    Route::get('/instances/{instance}/edit', [AdminInstanceController::class, 'edit'])->name('admin.instances.edit');
    Route::put('/instances/{instance}', [AdminInstanceController::class, 'update'])->name('admin.instances.update');
    Route::delete('/instances/{instance}', [AdminInstanceController::class, 'destroy'])->name('admin.instances.destroy');

    Route::get('/roles', [AdminRoleController::class, 'index'])->name('admin.roles');
    Route::post('/roles', [AdminRoleController::class, 'store'])->name('admin.roles.store');
    Route::put('/roles/{role}', [AdminRoleController::class, 'update'])->name('admin.roles.update');
    Route::delete('/roles/{role}', [AdminRoleController::class, 'destroy'])->name('admin.roles.destroy');

    Route::get('/players', [AdminPlayerController::class, 'index'])->name('admin.players');
    Route::post('/players', [AdminPlayerController::class, 'store'])->name('admin.players.store');
    Route::post('/players/api/toggle', [AdminPlayerController::class, 'toggleApi'])->name('admin.players.api.toggle');
    Route::post('/players/api/tokens', [AdminPlayerController::class, 'storeToken'])->name('admin.players.api.tokens.store');
    Route::delete('/players/api/tokens/{token}', [AdminPlayerController::class, 'destroyToken'])->name('admin.players.api.tokens.destroy');
    Route::put('/players/{player}', [AdminPlayerController::class, 'update'])->name('admin.players.update');
    Route::post('/players/{player}/ban', [AdminPlayerController::class, 'toggleBan'])->name('admin.players.toggleBan');
    Route::delete('/players/{player}', [AdminPlayerController::class, 'destroy'])->name('admin.players.destroy');

    Route::get('/news', [AdminNewsController::class, 'index'])->name('admin.news');
    Route::get('/news/create', [AdminNewsController::class, 'create'])->name('admin.news.create');
    Route::post('/news', [AdminNewsController::class, 'store'])->name('admin.news.store');
    Route::get('/news/{news}/edit', [AdminNewsController::class, 'edit'])->name('admin.news.edit');
    Route::put('/news/{news}', [AdminNewsController::class, 'update'])->name('admin.news.update');
    Route::delete('/news/{news}', [AdminNewsController::class, 'destroy'])->name('admin.news.destroy');

    Route::get('/general', [AdminController::class, 'general'])->name('admin.general');
    Route::post('/general/update', [AdminController::class, 'updateGeneral'])->name('admin.general.update');

    Route::get('/security', [AdminSecurityController::class, 'show'])->name('admin.security');
    Route::post('/security/update', [AdminSecurityController::class, 'update'])->name('admin.security.update');

    Route::get('/server', [AdminServerController::class, 'show'])->name('admin.server');
    Route::post('/server/store', [AdminServerController::class, 'store'])->name('admin.server.store');
    Route::post('/server/update', [AdminServerController::class, 'update'])->name('admin.server.update');
    Route::put('/server/{id}', [AdminServerController::class, 'updateServer'])->name('admin.server.updateServer')->whereNumber('id');
    Route::delete('/server/{id}', [AdminServerController::class, 'destroy'])->name('admin.server.destroy')->whereNumber('id');
    Route::post('/server/set-default', [AdminServerController::class, 'setDefaultServer'])->name('admin.server.set-default');
    Route::post('/server/sync', [AdminServerController::class, 'sync'])->name('admin.server.sync');
    Route::post('/server/{serverId}/icon', [AdminServerController::class, 'updateIcon'])->name('admin.server.updateIcon');
    Route::delete('/server/{serverId}/icon', [AdminServerController::class, 'deleteIcon'])->name('admin.server.deleteIcon');

    Route::get('/ui', [AdminUIController::class, 'show'])->name('admin.ui');
    Route::post('/ui/update', [AdminUIController::class, 'update'])->name('admin.ui.update');

    Route::get('/whitelist', [AdminWhitelistController::class, 'index'])->name('admin.whitelist');
    Route::post('/whitelist', [AdminWhitelistController::class, 'store'])->name('admin.whitelist.store');
    Route::get('/whitelist/fetch-users', [AdminWhitelistController::class, 'fetchUsers'])->name('admin.whitelist.fetchUsers');
    Route::get('/whitelist/fetch-roles', [AdminWhitelistController::class, 'fetchRoles'])->name('admin.whitelist.fetchRoles');
    Route::delete('/whitelist/user/{id}', [AdminWhitelistController::class, 'destroyUser'])->name('admin.whitelist.destroyUser');
    Route::delete('/whitelist/role/{id}', [AdminWhitelistController::class, 'destroyRole'])->name('admin.whitelist.destroyRole');

    Route::get('/ignore', [AdminIgnoreController::class, 'index'])->name('admin.ignore');
    Route::post('/ignore', [AdminIgnoreController::class, 'store'])->name('admin.ignore.store');
    Route::delete('/ignore/folder/{id}', [AdminIgnoreController::class, 'destroyFolder'])->name('admin.ignore.destroyFolder');

    Route::get('/mods', [AdminModController::class, 'index'])->name('admin.mods');
    Route::post('/mods/add', [AdminModController::class, 'addOptionalMod'])->name('admin.mods.addOptional');
    Route::post('/mods/update', [AdminModController::class, 'updateOptionalMod'])->name('admin.mods.updateOptional');
    Route::post('/mods/delete/{id}', [AdminModController::class, 'deleteOptionalMod'])->name('admin.mods.delete');
    Route::get('/mods/{id}', [AdminModController::class, 'getOptionalModDetails'])->name('admin.mods.getOptionalModDetails');

    Route::get('/loader', [AdminLoaderController::class, 'index'])->name('admin.loader');
    Route::post('/loader/update', [AdminLoaderController::class, 'update'])->name('admin.loader.update');
    Route::get('/loader/builds', [AdminLoaderController::class, 'getForgeBuilds'])->name('admin.loader.builds');
    Route::get('/loader/fabric-versions', [AdminLoaderController::class, 'getFabricVersions'])->name('admin.loader.fabric-versions');
    Route::get('/loader/neoforge-versions', [AdminLoaderController::class, 'getNeoForgeVersions'])->name('admin.loader.neoforge-versions');
    Route::get('/loader/quilt-versions', [AdminLoaderController::class, 'getQuiltVersions'])->name('admin.loader.quilt-versions');
    Route::get('/loader/legacyfabric-versions', [AdminLoaderController::class, 'getLegacyFabricVersions'])->name('admin.loader.legacyfabric-versions');

    Route::get('/rpc', [AdminRpcController::class, 'show'])->name('admin.rpc');
    Route::post('/rpc/update', [AdminRpcController::class, 'update'])->name('admin.rpc.update');

    Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users');
    Route::get('/users/create', [AdminUserController::class, 'create'])->name('admin.users.create');
    Route::post('/users/add', [AdminUserController::class, 'add'])->name('admin.users.add');
    Route::delete('/users/delete/{id}', [AdminUserController::class, 'delete'])->name('admin.users.delete');
    Route::get('/users/edit/{id}', [AdminUserController::class, 'edit'])->name('admin.users.edit');
    Route::put('/users/update/{id}', [AdminUserController::class, 'update'])->name('admin.users.update');

    Route::get('/settings/export', [SettingsExportController::class, 'export'])->name('admin.settings.export');
    Route::post('/settings/import', [SettingsExportController::class, 'import'])->name('admin.settings.import');

    Route::get('/bg', [AdminBgController::class, 'index'])->name('admin.bg');
    Route::post('/bg/update', [AdminBgController::class, 'update'])->name('admin.bg.update');
    Route::delete('/bg/destroy/{role_id}', [AdminBgController::class, 'destroy'])->name('admin.bg.destroy');

    Route::get('/update', [UpdateController::class, 'index'])->name('admin.update');
    Route::post('/update', [UpdateController::class, 'update'])->name('admin.update.run');
    Route::post('/update/cache', [UpdateController::class, 'clearCache'])->name('admin.update.cache');
});

Route::get('/file-manager', [PanelController::class, 'fileManager'])->name('admin.file-manager')->middleware('auth');

Route::prefix('utils')->group(function () {
    Route::get('/api', [ApiController::class, 'getOptions']);
    Route::get('/mods', [ModController::class, 'getMods']);
    Route::get('/news', [NewsController::class, 'getNews']);
    Route::get('/instances', [ApiController::class, 'getInstances']);
    Route::get('/instances/{slug}', [ApiController::class, 'getInstance']);
    Route::get('/instances/{slug}/files', [FileController::class, 'getInstanceFiles']);
    Route::get('/instances/{slug}/mods', [ModController::class, 'getInstanceMods']);
    Route::get('/packages/{scope}/{filename}', [FileController::class, 'downloadPackage'])
        ->where([
            'scope' => '[a-zA-Z0-9_-]+',
            'filename' => 'pkg-[a-zA-Z0-9_-]+\.zip',
        ]);
    Route::post('/auth/microsoft/sync', [MicrosoftAuthController::class, 'sync']);
});

Route::get('/data', [FileController::class, 'getFiles']);
Route::get('lang/{locale}', [App\Http\Controllers\LanguageController::class, 'switch'])->name('lang.switch');

require __DIR__ . '/curseforge.php';

