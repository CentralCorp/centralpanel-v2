<?php

namespace Tests\Feature;

use Alexusmai\LaravelFileManager\Events\Deleted as FileManagerDeleted;
use Alexusmai\LaravelFileManager\Events\Rename as FileManagerRename;
use App\Models\CurseForgeMod;
use App\Models\CurseForgeModpack;
use App\Models\Instance;
use App\Models\OptionsGeneral;
use App\Models\OptionsMods;
use App\Models\OptionsRPC;
use App\Models\User;
use App\Support\ActiveInstance;
use App\Support\LauncherCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class PanelV2EnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Instance $instance;
    private string $testDataDir;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Route::has('admin.curseforge')) {
            Route::middleware('web')->group(base_path('routes/curseforge.php'));
        }

        $this->admin = User::factory()->create();
        $this->testDataDir = 'instances/v2-enh-' . uniqid();
        $this->instance = Instance::create([
            'slug' => 'v2-enh',
            'name' => 'Pixelmon Sky',
            'is_default' => true,
            'is_enabled' => true,
            'minecraft_version' => '1.20.1',
            'loader_type' => 'forge',
            'loader_activation' => true,
            'custom_data_dir' => $this->testDataDir,
        ]);
        $this->instance->ensureDirectoriesExist();

        OptionsGeneral::create([
            'curseforge_api_key' => 'test-cf-key-enh',
        ]);
    }

    protected function tearDown(): void
    {
        $absDir = Storage::disk('public')->path($this->testDataDir);
        if (File::isDirectory($absDir)) {
            File::deleteDirectory($absDir);
        }

        $pkgDir = Storage::disk('local')->path('launcher-packages/instance-v2-enh');
        if (File::isDirectory($pkgDir)) {
            File::deleteDirectory($pkgDir);
        }

        $sessionsDir = storage_path('app/cf-sessions');
        if (File::isDirectory($sessionsDir)) {
            File::deleteDirectory($sessionsDir);
        }

        parent::tearDown();
    }

    public function test_embedded_java_defaults_to_true_on_model_creation_and_loader_view(): void
    {
        // 1. Model default attribute
        $freshModel = new Instance();
        $this->assertTrue((bool) $freshModel->embedded_java);

        // 2. AdminInstanceController::store() without sending embedded_java
        $response = $this->actingAs($this->admin)->post('/admin/instances', [
            'name' => 'Survival Vanilla',
            'slug' => 'survival-vanilla',
        ]);
        $response->assertRedirect(route('admin.instances'));

        $created = Instance::where('slug', 'survival-vanilla')->firstOrFail();
        $this->assertTrue((bool) $created->embedded_java);

        // Clean up created instance dir
        $createdDir = $created->resolvedStoragePath();
        if (File::isDirectory($createdDir)) {
            File::deleteDirectory($createdDir);
        }

        // 3. Loader view renders embedded_java checked by default
        $loaderPage = $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->get(route('admin.loader'));

        $loaderPage->assertOk();
        $loaderPage->assertSee('id="embedded-java"', false);
        $loaderPage->assertSee('name="embedded_java"', false);

        // 4. Loader update can explicitly disable embedded_java
        $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->post(route('admin.loader.update'), [
                'minecraft_version' => '1.20.1',
                'loader_activation' => 1,
                'loader_type' => 'forge',
                'embedded_java' => 0,
            ])
            ->assertRedirect();

        $this->assertFalse((bool) $this->instance->fresh()->embedded_java);
    }

    public function test_chunked_modpack_prepare_batch_finalize_with_sink_streaming_and_zero_tmp_in_mods(): void
    {
        $modsDir = $this->instance->resolvedStoragePath() . DIRECTORY_SEPARATOR . 'mods';

        // Plant a residual *.jar.tmp.* file to verify automatic cleanup
        $residualTmp = $modsDir . DIRECTORY_SEPARATOR . 'Pixelmon-1.20.1.jar.tmp.deadbeef';
        file_put_contents($residualTmp, 'partial-garbage');
        $this->assertFileExists($residualTmp);

        $packZipV1 = $this->createModpackZip([
            'name' => 'Big Pixelmon Pack',
            'version' => '1.0.0',
            'overrides' => 'overrides',
            'files' => [
                ['projectID' => 3001, 'fileID' => 80001, 'required' => true],
                ['projectID' => 3002, 'fileID' => 80002, 'required' => true],
            ],
        ], ['override-helper.jar' => "PK\x03\x04OVERRIDE_BYTES"]);

        $packZipV2 = $this->createModpackZip([
            'name' => 'Big Pixelmon Pack',
            'version' => '2.0.0',
            'overrides' => 'overrides',
            'files' => [
                ['projectID' => 3001, 'fileID' => 80003, 'required' => true],
            ],
        ]);

        Http::fake([
            'https://api.curseforge.com/v1/mods/7700' => Http::response([
                'data' => [
                    'id' => 7700,
                    'name' => 'Big Pixelmon Pack',
                    'slug' => 'big-pixelmon-pack',
                    'logo' => ['thumbnailUrl' => 'https://cdn.example/pack.png'],
                    'latestFiles' => [
                        [
                            'id' => 97001,
                            'fileName' => 'pixelmon-pack-1.0.0.zip',
                            'displayName' => 'v1.0.0',
                            'gameVersions' => ['1.20.1', 'Forge'],
                            'fileDate' => '2025-01-01T00:00:00Z',
                            'downloadUrl' => 'https://cdn.example/pixelmon-pack-1.0.0.zip',
                        ],
                        [
                            'id' => 97002,
                            'fileName' => 'pixelmon-pack-2.0.0.zip',
                            'displayName' => 'v2.0.0',
                            'gameVersions' => ['1.20.1', 'Forge'],
                            'fileDate' => '2025-02-01T00:00:00Z',
                            'downloadUrl' => 'https://cdn.example/pixelmon-pack-2.0.0.zip',
                        ],
                    ],
                ],
            ]),
            'https://api.curseforge.com/v1/mods/7700/files/97001' => Http::response([
                'data' => [
                    'id' => 97001,
                    'fileName' => 'pixelmon-pack-1.0.0.zip',
                    'displayName' => 'v1.0.0',
                    'gameVersions' => ['1.20.1', 'Forge'],
                    'downloadUrl' => 'https://cdn.example/pixelmon-pack-1.0.0.zip',
                ],
            ]),
            'https://api.curseforge.com/v1/mods/7700/files/97002' => Http::response([
                'data' => [
                    'id' => 97002,
                    'fileName' => 'pixelmon-pack-2.0.0.zip',
                    'displayName' => 'v2.0.0',
                    'gameVersions' => ['1.20.1', 'Forge'],
                    'downloadUrl' => 'https://cdn.example/pixelmon-pack-2.0.0.zip',
                ],
            ]),
            'https://cdn.example/pixelmon-pack-1.0.0.zip' => Http::response($packZipV1, 200),
            'https://cdn.example/pixelmon-pack-2.0.0.zip' => Http::response($packZipV2, 200),
            'https://api.curseforge.com/v1/mods/files' => Http::response([
                'data' => [
                    [
                        'id' => 80001,
                        'modId' => 3001,
                        'fileName' => 'pixelmon-core-1.0.jar',
                        'displayName' => 'Pixelmon Core 1.0',
                        'fileFingerprint' => 111111,
                        'downloadUrl' => 'https://edge.forgecdn.net/files/8000/1/pixelmon-core-1.0.jar',
                    ],
                    [
                        'id' => 80002,
                        'modId' => 3002,
                        'fileName' => 'removed-in-v2.jar',
                        'displayName' => 'Removed Mod 1.0',
                        'fileFingerprint' => 222222,
                        'downloadUrl' => 'https://edge.forgecdn.net/files/8000/2/removed-in-v2.jar',
                    ],
                    [
                        'id' => 80003,
                        'modId' => 3001,
                        'fileName' => 'pixelmon-core-2.0.jar',
                        'displayName' => 'Pixelmon Core 2.0',
                        'fileFingerprint' => 333333,
                        'downloadUrl' => 'https://edge.forgecdn.net/files/8000/3/pixelmon-core-2.0.jar',
                    ],
                ],
            ]),
            'https://api.curseforge.com/v1/mods' => Http::response([
                'data' => [
                    ['id' => 3001, 'name' => 'Pixelmon Core', 'slug' => 'pixelmon-core', 'logo' => ['thumbnailUrl' => 'https://cdn.example/pcore.png']],
                    ['id' => 3002, 'name' => 'Removed Mod', 'slug' => 'removed-mod', 'logo' => null],
                ],
            ]),
            'https://edge.forgecdn.net/files/8000/1/pixelmon-core-1.0.jar' => Http::response("PK\x03\x04PIXELMON_V1_DATA", 200),
            'https://edge.forgecdn.net/files/8000/2/removed-in-v2.jar' => Http::response("PK\x03\x04REMOVED_MOD_DATA", 200),
            'https://edge.forgecdn.net/files/8000/3/pixelmon-core-2.0.jar' => Http::response("PK\x03\x04PIXELMON_V2_DATA", 200),
        ]);

        // Step 1: Prepare v1
        $prepRes = $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->postJson('/admin/curseforge/modpacks/prepare', [
                'modpack_id' => 7700,
                'file_id' => 97001,
            ]);

        $prepRes->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('total', 2)
            ->assertJsonPath('overrides_count', 1);

        $sessionId = $prepRes->json('session_id');
        $this->assertNotEmpty($sessionId);
        $this->assertFileExists(storage_path("app/cf-sessions/{$sessionId}.json"));
        $this->assertFileDoesNotExist($residualTmp);
        $this->assertFileExists($modsDir . DIRECTORY_SEPARATOR . 'override-helper.jar');

        // Step 2: Install batch 1 (offset=0, limit=1)
        $batch1 = $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->postJson('/admin/curseforge/modpacks/install-batch', [
                'session_id' => $sessionId,
                'offset' => 0,
                'limit' => 1,
            ]);

        $batch1->assertOk()
            ->assertJsonPath('next_offset', 1)
            ->assertJsonPath('done', false)
            ->assertJsonPath('installed_in_batch', 1)
            ->assertJsonPath('current_files.0', 'pixelmon-core-1.0.jar');

        // Verify zero *.jar.tmp.* files in mods/
        $this->assertSame([], glob($modsDir . DIRECTORY_SEPARATOR . '*.jar.tmp.*') ?: []);

        // Step 2b: Install batch 2 (offset=1, limit=1)
        $batch2 = $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->postJson('/admin/curseforge/modpacks/install-batch', [
                'session_id' => $sessionId,
                'offset' => 1,
                'limit' => 1,
            ]);

        $batch2->assertOk()
            ->assertJsonPath('next_offset', 2)
            ->assertJsonPath('done', true)
            ->assertJsonPath('installed_in_batch', 1);

        // Step 3: Finalize v1
        $finalRes = $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->postJson('/admin/curseforge/modpacks/finalize', [
                'session_id' => $sessionId,
            ]);

        $finalRes->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.installed_count', 2)
            ->assertJsonPath('data.modpack.mods_count', 2);

        $this->assertFileDoesNotExist(storage_path("app/cf-sessions/{$sessionId}.json"));
        $this->assertFileExists($modsDir . DIRECTORY_SEPARATOR . 'pixelmon-core-1.0.jar');
        $this->assertFileExists($modsDir . DIRECTORY_SEPARATOR . 'removed-in-v2.jar');

        $packRecord = CurseForgeModpack::where('instance_id', $this->instance->id)->firstOrFail();

        // Now test chunked UPDATE to v2 (removes removed-in-v2.jar, replaces pixelmon-core-1.0.jar)
        $prepV2 = $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->postJson('/admin/curseforge/modpacks/prepare', [
                'existing_modpack_id' => $packRecord->id,
                'file_id' => 97002,
            ])
            ->assertOk();

        $sessionV2 = $prepV2->json('session_id');
        $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->postJson('/admin/curseforge/modpacks/install-batch', [
                'session_id' => $sessionV2,
                'offset' => 0,
                'limit' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('done', true);

        $finalV2 = $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->postJson('/admin/curseforge/modpacks/finalize', [
                'session_id' => $sessionV2,
            ])
            ->assertOk()
            ->assertJsonPath('data.removed_count', 1)
            ->assertJsonPath('data.modpack.mods_count', 1);

        $this->assertFileExists($modsDir . DIRECTORY_SEPARATOR . 'pixelmon-core-2.0.jar');
        $this->assertFileDoesNotExist($modsDir . DIRECTORY_SEPARATOR . 'pixelmon-core-1.0.jar');
        $this->assertFileDoesNotExist($modsDir . DIRECTORY_SEPARATOR . 'removed-in-v2.jar');
        $this->assertSame([], glob($modsDir . DIRECTORY_SEPARATOR . '*.jar.tmp.*') ?: []);
    }

    public function test_optional_mods_curseforge_exclusion_prefill_sync_and_auto_deletion(): void
    {
        $modsDir = $this->instance->resolvedStoragePath() . DIRECTORY_SEPARATOR . 'mods';

        // Create a modpack + 1 modpack mod, 1 individual CF mod, and 1 external mod on disk
        $pack = CurseForgeModpack::create([
            'instance_id' => $this->instance->id,
            'curseforge_modpack_id' => 8800,
            'curseforge_file_id' => 98800,
            'name' => 'Test Pack',
            'mods_count' => 1,
        ]);

        CurseForgeMod::create([
            'instance_id' => $this->instance->id,
            'modpack_id' => $pack->id,
            'curseforge_mod_id' => 4001,
            'curseforge_file_id' => 60001,
            'name' => 'Modpack Only Mod',
            'file_name' => 'modpack-only.jar',
        ]);

        $individualCf = CurseForgeMod::create([
            'instance_id' => $this->instance->id,
            'modpack_id' => null,
            'curseforge_mod_id' => 4002,
            'curseforge_file_id' => 60002,
            'name' => 'JourneyMap',
            'logo_url' => 'https://cdn.example/journeymap.png',
            'file_name' => 'journeymap-1.0.jar',
            'version' => '1.0',
            'mc_version' => '1.20.1',
            'loader' => 'forge',
        ]);

        file_put_contents($modsDir . '/modpack-only.jar', "PK\x03\x04PACK");
        file_put_contents($modsDir . '/journeymap-1.0.jar', "PK\x03\x04JM1");
        file_put_contents($modsDir . '/custom-external.jar', "PK\x03\x04EXT");
        file_put_contents($modsDir . '/fm-deleted.jar', "PK\x03\x04FM");

        // 1. GET /admin/mods MUST exclude modpack-only.jar, include JourneyMap (with prefilled name & logo) and custom-external.jar
        $page = $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->get(route('admin.mods'));

        $page->assertOk();
        $page->assertDontSee('modpack-only.jar');
        $page->assertSee('JourneyMap');
        $page->assertSee('https://cdn.example/journeymap.png');
        $page->assertSee('custom-external.jar');

        // 2. Add JourneyMap as optional mod -> stores curseforge_mod_id and remote logo_url in icon
        $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->post(route('admin.mods.addOptional'), [
                'file' => 'journeymap-1.0.jar',
                'name' => 'JourneyMap',
                'curseforge_mod_id' => $individualCf->id,
                'icon_url' => 'https://cdn.example/journeymap.png',
            ])
            ->assertRedirect();

        $optJm = OptionsMods::where('instance_id', $this->instance->id)
            ->where('file', 'journeymap-1.0.jar')
            ->firstOrFail();

        $this->assertSame($individualCf->id, (int) $optJm->curseforge_mod_id);
        $this->assertSame('https://cdn.example/journeymap.png', $optJm->icon);

        // Launcher API exposes remote icon URL without storage/ prefix
        $apiMods = $this->getJson('/utils/instances/' . $this->instance->slug . '/mods');
        $apiMods->assertOk();
        $this->assertSame(
            'https://cdn.example/journeymap.png',
            $apiMods->json('mods')['journeymap-1.0.jar']['icon'] ?? null
        );

        // 3. Updating JourneyMap via CurseForgeService updates OptionsMods.file automatically
        Http::fake([
            'https://api.curseforge.com/v1/mods/4002' => Http::response([
                'data' => [
                    'id' => 4002,
                    'name' => 'JourneyMap',
                    'slug' => 'journeymap',
                    'logo' => ['thumbnailUrl' => 'https://cdn.example/journeymap-v2.png'],
                    'latestFiles' => [
                        [
                            'id' => 60099,
                            'fileName' => 'journeymap-2.0.jar',
                            'displayName' => 'JourneyMap 2.0',
                            'gameVersions' => ['1.20.1', 'Forge'],
                            'fileDate' => '2025-03-01T00:00:00Z',
                            'fileFingerprint' => 999999,
                            'downloadUrl' => 'https://cdn.example/journeymap-2.0.jar',
                        ],
                    ],
                ],
            ]),
            'https://cdn.example/journeymap-2.0.jar' => Http::response("PK\x03\x04JM2", 200),
        ]);

        $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->postJson("/admin/curseforge/mods/{$individualCf->id}/update")
            ->assertOk();

        $optJm->refresh();
        $this->assertSame('journeymap-2.0.jar', $optJm->file);
        $this->assertSame('https://cdn.example/journeymap-v2.png', $optJm->icon);

        // 4. Deleting JourneyMap via CurseForge deletes linked OptionsMods automatically
        $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->deleteJson("/admin/curseforge/mods/{$individualCf->id}")
            ->assertOk();

        $this->assertDatabaseMissing('mods', ['id' => $optJm->id]);

        // 5. Deleting an external mod via CurseForge deletes linked OptionsMods automatically
        $optExt = OptionsMods::create([
            'instance_id' => $this->instance->id,
            'file' => 'custom-external.jar',
            'name' => 'Custom External',
            'optional' => true,
        ]);

        $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->deleteJson('/admin/curseforge/external', ['file_name' => 'custom-external.jar'])
            ->assertOk();

        $this->assertDatabaseMissing('mods', ['id' => $optExt->id]);

        // 6. Deleting a file via File Manager Deleted event deletes linked OptionsMods & orphan CurseForgeMod + flushes cache
        $optFm = OptionsMods::create([
            'instance_id' => $this->instance->id,
            'file' => 'fm-deleted.jar',
            'name' => 'FM Deleted Mod',
            'optional' => true,
        ]);
        $cfOrphan = CurseForgeMod::create([
            'instance_id' => $this->instance->id,
            'curseforge_mod_id' => 4099,
            'curseforge_file_id' => 60099,
            'name' => 'Orphan CF',
            'file_name' => 'fm-deleted.jar',
        ]);

        ActiveInstance::set($this->instance);
        $filesVersionBefore = LauncherCache::filesVersion();

        event(new FileManagerDeleted('data', [
            ['path' => 'mods/fm-deleted.jar', 'type' => 'file'],
        ]));

        $this->assertDatabaseMissing('mods', ['id' => $optFm->id]);
        $this->assertDatabaseMissing('curseforge_mods', ['id' => $cfOrphan->id]);
        $this->assertGreaterThan($filesVersionBefore, LauncherCache::filesVersion());
    }

    public function test_per_instance_discord_rpc_with_instance_placeholder_resolution(): void
    {
        $secondInstance = Instance::create([
            'slug' => 'cobblemon',
            'name' => 'Cobblemon Adventure',
            'is_default' => false,
            'is_enabled' => true,
            'minecraft_version' => '1.21.1',
            'loader_type' => 'neoforge',
            'custom_data_dir' => 'instances/cobblemon-' . uniqid(),
        ]);

        // Save global RPC + per-instance override for active instance ($this->instance = "Pixelmon Sky")
        $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->post(route('admin.rpc.update'), [
                'rpc_activation' => 1,
                'rpc_id' => '123456789012345678',
                'rpc_details' => 'Global sur {instance}',
                'rpc_state' => 'Exploration de {instance}',
                'instance_rpc_details' => 'Chasse aux Pokémon sur {instance}',
                'instance_rpc_state' => 'Serveur {instance}',
            ])
            ->assertRedirect(route('admin.rpc'));

        $this->instance->refresh();
        $this->assertSame('Chasse aux Pokémon sur {instance}', $this->instance->rpc_details);
        $this->assertSame('Serveur {instance}', $this->instance->rpc_state);
        $this->assertNull($secondInstance->fresh()->rpc_details);

        // Verify /utils/instances/{slug} resolves {instance} for both instances
        $firstPayload = $this->getJson('/utils/instances/' . $this->instance->slug)->assertOk();
        $firstPayload
            ->assertJsonPath('rpc_details', 'Chasse aux Pokémon sur Pixelmon Sky')
            ->assertJsonPath('rpc_state', 'Serveur Pixelmon Sky');

        // Second instance falls back to global RPC with {instance} resolved to its own name
        $secondPayload = $this->getJson('/utils/instances/' . $secondInstance->slug)->assertOk();
        $secondPayload
            ->assertJsonPath('rpc_details', 'Global sur Cobblemon Adventure')
            ->assertJsonPath('rpc_state', 'Exploration de Cobblemon Adventure');

        // Verify /utils/api also exposes per-instance resolved rpc_details and rpc_state
        $optionsRes = $this->getJson('/utils/api')->assertOk();
        $instances = collect($optionsRes->json('instances'));
        $p1 = $instances->firstWhere('slug', $this->instance->slug);
        $p2 = $instances->firstWhere('slug', $secondInstance->slug);

        $this->assertSame('Chasse aux Pokémon sur Pixelmon Sky', $p1['rpc_details']);
        $this->assertSame('Global sur Cobblemon Adventure', $p2['rpc_details']);

        // Clean up second instance dir if created
        $secondDir = $secondInstance->resolvedStoragePath();
        if (File::isDirectory($secondDir)) {
            File::deleteDirectory($secondDir);
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, string>  $overridesMods
     */
    private function createModpackZip(array $manifest, array $overridesMods = []): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_pack_');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', json_encode($manifest));
        foreach ($overridesMods as $name => $bytes) {
            $zip->addFromString('overrides/mods/' . $name, $bytes);
        }
        $zip->close();

        $contents = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $contents;
    }
}
