<?php

namespace Tests\Feature;

use App\Models\Instance;
use App\Models\OptionsGeneral;
use App\Models\OptionsIgnore;
use App\Models\OptionsMods;
use App\Models\OptionsServer;
use App\Models\OptionsWhitelist;
use App\Models\OptionsWhitelistRole;
use App\Models\User;
use App\Support\ActiveInstance;
use App\Support\PanelOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MultiInstanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
    }

    public function test_file_manager_routes_require_authentication(): void
    {
        $this->get('/file-manager')->assertRedirect('/login');
        $this->get('/file-manager/initialize')->assertRedirect('/login');
        $this->getJson('/file-manager/initialize')->assertUnauthorized();

        Storage::fake('public');
        $admin = User::factory()->create();
        PanelOptions::ensureDefaults();

        $this->actingAs($admin)->get('/file-manager')->assertOk();
        $this->actingAs($admin)->getJson('/file-manager/initialize')->assertOk();
    }

    public function test_multi_instance_api_contract_and_endpoints(): void
    {
        Storage::fake('public');
        PanelOptions::general()->update([
            'auth_mode' => 'both',
            'news_source' => 'both',
            'game_folder_name' => 'centralcorp',
        ]);

        $lobbyServer = OptionsServer::create([
            'server_id' => -1,
            'server_name' => 'Survie Server',
            'server_ip' => 'survie.centralcorp.fr',
            'server_port' => '25565',
            'type' => 'minecraft',
            'is_default' => true,
        ]);

        $skyServer = OptionsServer::create([
            'server_id' => -2,
            'server_name' => 'Skyblock Server',
            'server_ip' => 'sky.centralcorp.fr',
            'server_port' => '25566',
            'type' => 'minecraft',
            'is_default' => false,
        ]);

        $survival = Instance::create([
            'slug' => 'survival',
            'name' => 'Survie Moddée',
            'description' => 'Serveur Survie 1.20.1',
            'is_default' => true,
            'is_enabled' => true,
            'position' => 1,
            'maintenance' => false,
            'maintenance_message' => 'Maintenance Survie',
            'maintenance_bypass_roles' => ['Admin'],
            'maintenance_bypass_users' => ['Riptiaz'],
            'minecraft_version' => '1.20.1',
            'loader_type' => 'forge',
            'loader_build_version' => '47.2.0',
            'loader_forge_version' => '47.2.0',
            'loader_activation' => true,
            'mods_enabled' => true,
            'file_verification' => true,
            'embedded_java' => true,
            'java_version' => 17,
            'jvm_args' => ['-XX:+UseG1GC'],
            'game_args' => ['--width', '1280'],
            'auto_connect' => true,
            'min_ram' => 2048,
            'max_ram' => 6144,
            'server_id' => $lobbyServer->id,
            'whitelist_activate' => true,
            'custom_data_dir' => 'instances/survival',
        ]);

        Instance::create([
            'slug' => 'skyblock',
            'name' => 'Skyblock Fabric',
            'description' => 'Skyblock 1.21.1',
            'is_default' => false,
            'is_enabled' => true,
            'position' => 2,
            'minecraft_version' => '1.21.1',
            'loader_type' => 'fabric',
            'loader_build_version' => '0.16.0',
            'loader_activation' => true,
            'mods_enabled' => true,
            'file_verification' => false,
            'embedded_java' => true,
            'java_version' => 21,
            'min_ram' => 2048,
            'max_ram' => 4096,
            'server_id' => $skyServer->id,
            'whitelist_activate' => false,
            'custom_data_dir' => 'instances/skyblock',
        ]);

        Instance::create([
            'slug' => 'disabled-secret',
            'name' => 'Secret Dev',
            'is_default' => false,
            'is_enabled' => false,
            'position' => 3,
            'minecraft_version' => '1.20.4',
            'custom_data_dir' => 'instances/disabled-secret',
        ]);

        OptionsWhitelist::create(['instance_id' => null, 'users' => 'GlobalAdmin']);
        OptionsWhitelist::create(['instance_id' => $survival->id, 'users' => 'SurvivalPlayer']);
        OptionsWhitelistRole::create(['instance_id' => null, 'role' => 'Staff']);

        $apiResp = $this->getJson('/utils/api');
        $apiResp->assertOk()
            ->assertJsonPath('auth_mode', 'both')
            ->assertJsonPath('azuriom_auth', true)
            ->assertJsonPath('microsoft_auth', true)
            ->assertJsonPath('client_id', '00000000402b5328')
            ->assertJsonPath('news_source', 'both')
            ->assertJsonPath('game_version', '1.20.1')
            ->assertJsonPath('ram_min', 2)
            ->assertJsonPath('ram_max', 6)
            ->assertJsonPath('status.nameServer', 'Survie Server')
            ->assertJsonCount(2, 'instances')
            ->assertJsonPath('instances.0.slug', 'survival')
            ->assertJsonPath('instances.0.java_version', 17)
            ->assertJsonPath('instances.0.jvm_args.0', '-XX:+UseG1GC')
            ->assertJsonPath('instances.0.auto_connect', true)
            ->assertJsonPath('instances.1.slug', 'skyblock')
            ->assertJsonPath('instances.1.loader.type', 'fabric')
            ->assertJsonPath('instances.1.status.port', 25566);

        $survivalWhitelist = $apiResp->json('instances.0.whitelist');
        $this->assertContains('GlobalAdmin', $survivalWhitelist);
        $this->assertContains('SurvivalPlayer', $survivalWhitelist);

        $this->getJson('/utils/instances')
            ->assertOk()
            ->assertJsonCount(2);

        $this->getJson('/utils/instances/skyblock')
            ->assertOk()
            ->assertJsonPath('slug', 'skyblock')
            ->assertJsonPath('game_version', '1.21.1');

        $this->getJson('/utils/instances/disabled-secret')->assertNotFound();
        $this->getJson('/utils/instances/non-existent')->assertNotFound();
    }

    public function test_instance_file_and_mod_isolation_with_global_and_scoped_rules(): void
    {
        Storage::fake('public');

        $survival = Instance::create([
            'slug' => 'survival',
            'name' => 'Survival',
            'is_default' => true,
            'is_enabled' => true,
            'minecraft_version' => '1.20.1',
            'custom_data_dir' => 'instances/survival',
        ]);

        $skyblock = Instance::create([
            'slug' => 'skyblock',
            'name' => 'Skyblock',
            'is_default' => false,
            'is_enabled' => true,
            'minecraft_version' => '1.21.1',
            'custom_data_dir' => 'instances/skyblock',
        ]);

        Storage::disk('public')->put('instances/survival/mods/jei.jar', 'survival-jei-bytes');
        Storage::disk('public')->put('instances/survival/logs/latest.log', 'global-ignored');
        Storage::disk('public')->put('instances/survival/secret/local.cfg', 'instance-ignored');

        Storage::disk('public')->put('instances/skyblock/mods/sodium.jar', 'skyblock-sodium-bytes');
        Storage::disk('public')->put('instances/skyblock/secret/local.cfg', 'allowed-in-skyblock');

        OptionsIgnore::create(['instance_id' => null, 'folder_name' => 'logs']);
        OptionsIgnore::create(['instance_id' => $survival->id, 'folder_name' => 'secret']);

        OptionsMods::create([
            'instance_id' => $survival->id,
            'file' => 'jei.jar',
            'name' => 'JEI',
            'description' => 'Recipe viewer',
            'optional' => true,
            'recommended' => true,
        ]);

        OptionsMods::create([
            'instance_id' => $skyblock->id,
            'file' => 'sodium.jar',
            'name' => 'Sodium',
            'description' => 'FPS boost',
            'optional' => true,
            'recommended' => false,
        ]);

        $survivalFiles = $this->getJson('/utils/instances/survival/files');
        $survivalFiles->assertOk()
            ->assertJsonFragment([
                'path' => 'mods/jei.jar',
                'hash' => sha1('survival-jei-bytes'),
            ])
            ->assertJsonMissing(['path' => 'logs/latest.log'])
            ->assertJsonMissing(['path' => 'secret/local.cfg'])
            ->assertJsonMissing(['path' => 'mods/sodium.jar']);

        $skyblockFiles = $this->getJson('/data?instance=skyblock');
        $skyblockFiles->assertOk()
            ->assertJsonFragment([
                'path' => 'mods/sodium.jar',
                'hash' => sha1('skyblock-sodium-bytes'),
            ])
            ->assertJsonFragment([
                'path' => 'secret/local.cfg',
            ])
            ->assertJsonMissing(['path' => 'mods/jei.jar']);

        $survivalMods = $this->getJson('/utils/instances/survival/mods');
        $survivalMods->assertOk()
            ->assertJsonPath('optionalMods', ['jei.jar']);
        $this->assertTrue($survivalMods->json('mods')['jei.jar']['recommanded']);
        $this->assertTrue($survivalMods->json('mods')['jei.jar']['recommended']);

        $skyblockMods = $this->getJson('/utils/mods?instance=skyblock');
        $skyblockMods->assertOk()
            ->assertJsonPath('optionalMods', ['sodium.jar']);
        $this->assertFalse($skyblockMods->json('mods')['sodium.jar']['recommanded']);
        $this->assertFalse($skyblockMods->json('mods')['sodium.jar']['recommended']);
    }

    public function test_admin_instance_crud_active_switcher_and_cache_invalidation(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        PanelOptions::ensureDefaults();

        // Warm cache
        $this->getJson('/utils/api')->assertOk()->assertJsonCount(1, 'instances');

        // Create second instance via Admin CRUD
        $this->actingAs($admin)->post(route('admin.instances.store'), [
            'name' => 'Faction PvP',
            'slug' => 'faction-pvp',
            'description' => 'Instance Faction',
            'is_enabled' => 1,
            'is_default' => 0,
            'position' => 2,
            'minecraft_version' => '1.19.4',
            'loader_activation' => 1,
            'loader_type' => 'fabric',
            'loader_build_version' => '0.15.11',
            'mods_enabled' => 1,
            'file_verification' => 1,
            'embedded_java' => 1,
            'java_version' => 17,
            'jvm_args_text' => "-XX:+UseG1GC\n-Dsome.flag=true",
            'min_ram' => 2048,
            'max_ram' => 4096,
        ])->assertRedirect(route('admin.instances'));

        $faction = Instance::where('slug', 'faction-pvp')->firstOrFail();
        $this->assertSame(['-XX:+UseG1GC', '-Dsome.flag=true'], $faction->jvm_args);

        // Cache was automatically invalidated on store!
        $this->getJson('/utils/api')->assertOk()->assertJsonCount(2, 'instances');

        // Switch active instance and update loader for that active instance
        $this->actingAs($admin)->post(route('admin.instances.switch'), [
            'instance_id' => $faction->id,
        ])->assertRedirect();

        $this->assertEquals($faction->id, session(ActiveInstance::SESSION_KEY));

        $this->actingAs($admin)->post(route('admin.loader.update'), [
            'minecraft_version' => '1.20.4',
            'loader_activation' => 1,
            'loader_type' => 'fabric',
            'loader_build_version' => '0.16.2',
        ])->assertRedirect();

        $this->assertSame('1.20.4', $faction->fresh()->minecraft_version);

        // Render admin pages to ensure zero Blade view errors
        $this->actingAs($admin)->get(route('admin.instances'))->assertOk();
        $this->actingAs($admin)->get(route('admin.instances.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.instances.edit', $faction))->assertOk();
        $this->actingAs($admin)->get(route('admin.loader'))->assertOk();
        $this->actingAs($admin)->get(route('admin.mods'))->assertOk();
        $this->actingAs($admin)->get(route('admin.ignore'))->assertOk();
    }

    public function test_settings_export_v2_and_import_v1_and_v2(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        PanelOptions::ensureDefaults();

        Instance::create([
            'slug' => 'creative',
            'name' => 'Creative',
            'is_default' => false,
            'is_enabled' => true,
            'minecraft_version' => '1.20.6',
            'min_ram' => 2048,
            'max_ram' => 4096,
            'custom_data_dir' => 'instances/creative',
        ]);

        $exportResp = $this->actingAs($admin)->get(route('admin.settings.export'));
        $exportResp->assertOk();
        $exportedJson = $exportResp->getContent();
        $decoded = json_decode($exportedJson, true);

        $this->assertSame('2.0', $decoded['version']);
        $this->assertArrayHasKey('instances', $decoded['data']);
        $this->assertArrayHasKey('panel_roles', $decoded['data']);
        $this->assertArrayHasKey('news', $decoded['data']);
        $this->assertArrayHasKey('microsoft_players', $decoded['data']);
        $this->assertCount(2, $decoded['data']['instances']);

        // Import legacy v1.0 archive
        $v1Payload = json_encode([
            'version' => '1.0',
            'export_date' => '2026-01-01 00:00:00',
            'data' => [
                'options_general' => [[
                    'id' => 1,
                    'mods_enabled' => true,
                    'file_verification' => true,
                    'embedded_java' => true,
                    'game_folder_name' => 'legacycraft',
                    'min_ram' => 2048,
                    'max_ram' => 8192,
                ]],
                'options_loader' => [[
                    'id' => 1,
                    'minecraft_version' => '1.16.5',
                    'loader_type' => 'forge',
                    'loader_build_version' => '36.2.39',
                    'loader_activation' => true,
                ]],
            ],
        ]);

        $v1File = UploadedFile::fake()->createWithContent('legacy.centralcorp', $v1Payload);
        $this->actingAs($admin)->post(route('admin.settings.import'), [
            'settings_file' => $v1File,
        ])->assertRedirect();

        $this->assertSame('legacycraft', OptionsGeneral::first()->game_folder_name);
        $this->assertSame('1.16.5', PanelOptions::defaultInstance()->minecraft_version);

        // Re-import v2.0 archive
        $v2File = UploadedFile::fake()->createWithContent('backup_v2.centralcorp', $exportedJson);
        $this->actingAs($admin)->post(route('admin.settings.import'), [
            'settings_file' => $v2File,
        ])->assertRedirect();

        $this->assertEquals(2, Instance::count());
        $this->assertNotNull(Instance::where('slug', 'creative')->first());
    }

    public function test_empty_role_data_and_optional_mods_serialize_as_json_objects(): void
    {
        PanelOptions::ensureDefaults();
        \Illuminate\Support\Facades\DB::table('options_bgs')->delete();
        \Illuminate\Support\Facades\DB::table('panel_roles')->update(['background_path' => null]);
        \Illuminate\Support\Facades\DB::table('mods')->delete();
        Cache::clear();

        $apiRaw = $this->getJson('/utils/api')->assertOk()->getContent();
        $this->assertStringContainsString('"role_data":{}', $apiRaw);

        $modsRaw = $this->getJson('/utils/instances/default/mods')->assertOk()->getContent();
        $this->assertStringContainsString('"mods":{}', $modsRaw);
    }

    public function test_upgrade_from_v1_0_8_schema_migrates_legacy_data_cleanly(): void
    {
        // Roll back the 4 Phase-2+ v2.0 migrations so DB matches exact v1.0.8 schema
        \Illuminate\Support\Facades\Artisan::call('migrate:rollback', ['--step' => 4]);

        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('instances'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('panel_roles'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('news'));

        // Seed legacy v1.0.8 singleton tables and unscoped lists
        $now = now();
        \Illuminate\Support\Facades\DB::table('options_general')->insert([
            'email_verified' => 1,
            'azuriom_url' => 'https://legacy.centralcorp.fr',
            'azuriom_api_key' => 'legacy-key',
            'mods_enabled' => 1,
            'file_verification' => 1,
            'embedded_java' => 0,
            'game_folder_name' => 'legacyv1',
            'role_display' => 1,
            'money_display' => 1,
            'min_ram' => 2560,
            'max_ram' => 6144,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        \Illuminate\Support\Facades\DB::table('options_loader')->insert([
            'minecraft_version' => '1.16.5',
            'loader_type' => 'forge',
            'loader_build_version' => '36.2.39',
            'loader_forge_version' => '36.2.39',
            'loader_activation' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        \Illuminate\Support\Facades\DB::table('options_security')->insert([
            'maintenance' => 1,
            'whitelist' => 1,
            'maintenance_message' => 'Upgrade en cours',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $legacyServerId = \Illuminate\Support\Facades\DB::table('options_server')->insertGetId([
            'server_id' => 42,
            'server_name' => 'Legacy Faction',
            'server_ip' => 'faction.centralcorp.fr',
            'server_port' => '25565',
            'type' => 'minecraft',
            'is_default' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        \Illuminate\Support\Facades\DB::table('mods')->insert([
            'file' => 'optifine.jar',
            'name' => 'OptiFine',
            'description' => 'HD FPS',
            'optional' => 1,
            'recommended' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        \Illuminate\Support\Facades\DB::table('ignored_folders')->insert([
            'folder_name' => 'shaderpacks',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        \Illuminate\Support\Facades\DB::table('whitelist')->insert([
            'users' => 'Riptiaz',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        \Illuminate\Support\Facades\DB::table('whitelist_roles')->insert([
            'role' => 'Fondateur',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Run v2.0 upgrade migrations
        \Illuminate\Support\Facades\Artisan::call('migrate');
        Cache::clear();

        $defaultInstance = Instance::where('slug', 'default')->firstOrFail();
        $this->assertTrue($defaultInstance->is_default);
        $this->assertSame('1.16.5', $defaultInstance->minecraft_version);
        $this->assertSame('forge', $defaultInstance->loader_type);
        $this->assertSame('36.2.39', $defaultInstance->loader_build_version);
        $this->assertTrue($defaultInstance->loader_activation);
        $this->assertFalse($defaultInstance->embedded_java);
        $this->assertSame(2560, $defaultInstance->min_ram);
        $this->assertSame(6144, $defaultInstance->max_ram);
        $this->assertTrue($defaultInstance->maintenance);
        $this->assertTrue($defaultInstance->whitelist_activate);
        $this->assertEquals($legacyServerId, $defaultInstance->server_id);

        // Verify legacy child rows were bound to the migrated default instance
        $this->assertEquals($defaultInstance->id, OptionsMods::where('file', 'optifine.jar')->value('instance_id'));
        $this->assertEquals($defaultInstance->id, OptionsIgnore::where('folder_name', 'shaderpacks')->value('instance_id'));
        $this->assertEquals($defaultInstance->id, OptionsWhitelist::where('users', 'Riptiaz')->value('instance_id'));
        $this->assertEquals($defaultInstance->id, OptionsWhitelistRole::where('role', 'Fondateur')->value('instance_id'));

        $this->getJson('/utils/api')
            ->assertOk()
            ->assertJsonPath('game_version', '1.16.5')
            ->assertJsonPath('ram_min', 2.5)
            ->assertJsonPath('ram_max', 6)
            ->assertJsonPath('java', false)
            ->assertJsonPath('instances.0.slug', 'default')
            ->assertJsonPath('instances.0.whitelist.0', 'Riptiaz');
    }

    public function test_folder_partitioned_zip_packages_and_delta_updates(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $survival = Instance::create([
            'slug' => 'survival',
            'name' => 'Survival',
            'is_default' => true,
            'is_enabled' => true,
            'minecraft_version' => '1.20.1',
            'custom_data_dir' => 'instances/survival',
        ]);

        Storage::disk('public')->put('instances/survival/config/forge-client.toml', 'renderDistance = 12');
        Storage::disk('public')->put('instances/survival/mods/jei.jar', 'required-jei-bytes');
        Storage::disk('public')->put('instances/survival/mods/minimap.jar', 'optional-minimap-bytes');
        Storage::disk('public')->put('instances/survival/servers.dat', 'servers-v1');

        OptionsMods::create([
            'instance_id' => $survival->id,
            'file' => 'minimap.jar',
            'name' => 'Xaero Minimap',
            'description' => 'HUD',
            'optional' => true,
            'recommended' => false,
        ]);

        // 1. Legacy endpoint without header/query still returns flat array for v1 clients
        $legacyResp = $this->getJson('/utils/instances/survival/files');
        $legacyResp->assertOk();
        $this->assertTrue(array_is_list($legacyResp->json()));
        $this->assertCount(4, $legacyResp->json());

        // 2. Packaged endpoint via X-Accept-Packages: 1 header (or ?packages=1)
        $pkgResp = $this->withHeader('X-Accept-Packages', '1')
            ->getJson('/utils/instances/survival/files');
        $pkgResp->assertOk()
            ->assertJsonPath('version', 2)
            ->assertJsonCount(4, 'packages') // config, mods, mods-optional, root
            ->assertJsonCount(4, 'files');

        // ZIP generation MUST be 100% invisible inside public storage & the instance File Manager folder!
        $this->assertFalse(Storage::disk('public')->exists('launcher-packages'));
        $instanceVisibleFiles = Storage::disk('public')->allFiles('instances/survival');
        foreach ($instanceVisibleFiles as $visFile) {
            $this->assertStringEndsNotWith('.zip', $visFile);
        }
        $this->assertNotEmpty(Storage::disk('local')->allFiles('launcher-packages/instance-survival'));

        $packages = collect($pkgResp->json('packages'))->keyBy('folder');
        $this->assertTrue($packages->has('config'));
        $this->assertTrue($packages->has('mods'));
        $this->assertTrue($packages->has('mods-optional'));
        $this->assertTrue($packages->has('root'));

        // Download the generated config ZIP package and verify its SHA-1 and internal entry
        $configPkg = $packages->get('config');
        $configUrlPath = parse_url($configPkg['url'], PHP_URL_PATH);
        $dlResp = $this->get($configUrlPath);
        $dlResp->assertOk();
        $zipRealPath = $dlResp->baseResponse->getFile()->getPathname();
        $this->assertSame($configPkg['hash'], hash_file('sha1', $zipRealPath));

        $za = new \ZipArchive();
        $this->assertTrue($za->open($zipRealPath) === true);
        $this->assertSame('renderDistance = 12', $za->getFromName('config/forge-client.toml'));
        $za->close();

        // 3. Modify ONLY config/forge-client.toml -> config package ID updates, mods/mods-optional/root IDs remain identical!
        sleep(1);
        Storage::disk('public')->put('instances/survival/config/forge-client.toml', 'renderDistance = 16');

        $pkgResp2 = $this->getJson('/utils/instances/survival/files?packages=1');
        $pkgResp2->assertOk();
        $packages2 = collect($pkgResp2->json('packages'))->keyBy('folder');

        $this->assertNotSame($configPkg['id'], $packages2->get('config')['id']);
        $this->assertSame($packages->get('mods')['id'], $packages2->get('mods')['id']);
        $this->assertSame($packages->get('mods-optional')['id'], $packages2->get('mods-optional')['id']);
        $this->assertSame($packages->get('root')['id'], $packages2->get('root')['id']);

        // Old stale config ZIP must be pruned automatically
        $this->get($configUrlPath)->assertNotFound();

        // 4. Verify small-chunk splitting when maxChunkFiles = 2 on a folder with 5 files
        foreach (range(1, 5) as $n) {
            Storage::disk('public')->put("instances/survival/kubejs/s{$n}.js", (string) $n);
        }
        $smallChunks = \App\Support\InstancePackageBuilder::build(
            $survival->resolvedStoragePath(),
            [
                ['path' => 'kubejs/s1.js', 'size' => 1, 'hash' => sha1('1'), 'url' => 'http://test/1'],
                ['path' => 'kubejs/s2.js', 'size' => 1, 'hash' => sha1('2'), 'url' => 'http://test/2'],
                ['path' => 'kubejs/s3.js', 'size' => 1, 'hash' => sha1('3'), 'url' => 'http://test/3'],
                ['path' => 'kubejs/s4.js', 'size' => 1, 'hash' => sha1('4'), 'url' => 'http://test/4'],
                ['path' => 'kubejs/s5.js', 'size' => 1, 'hash' => sha1('5'), 'url' => 'http://test/5'],
            ],
            Storage::disk('local')->path('launcher-packages/chunk-test'),
            static fn (string $z): string => 'http://test/' . $z,
            [],
            1024,
            2
        );
        $this->assertCount(3, $smallChunks['packages']);
        $this->assertSame(1, $smallChunks['packages'][0]['part']);
        $this->assertSame(2, $smallChunks['packages'][1]['part']);
        $this->assertSame(3, $smallChunks['packages'][2]['part']);
    }
}

