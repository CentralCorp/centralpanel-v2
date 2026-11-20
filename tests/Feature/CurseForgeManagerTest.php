<?php

namespace Tests\Feature;

use App\Models\CurseForgeMod;
use App\Models\Instance;
use App\Models\OptionsGeneral;
use App\Models\User;
use App\Services\CurseForgeService;
use App\Support\ActiveInstance;
use App\Support\LauncherCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class CurseForgeManagerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Instance $instance;
    private string $testDataDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Register dedicated CurseForge routes for testing (since routes/web.php is not modified by Agent 2)
        if (!Route::has('admin.curseforge')) {
            Route::middleware('web')->group(base_path('routes/curseforge.php'));
        }

        $this->admin = User::factory()->create();

        $this->testDataDir = 'instances/cf-test-' . uniqid();
        $this->instance = Instance::create([
            'slug' => 'cf-test',
            'name' => 'CF Test Instance',
            'is_default' => true,
            'is_enabled' => true,
            'minecraft_version' => '1.20.1',
            'loader_type' => 'forge',
            'loader_activation' => true,
            'custom_data_dir' => $this->testDataDir,
        ]);
        $this->instance->ensureDirectoriesExist();

        OptionsGeneral::create([
            'curseforge_api_key' => 'test-cf-api-key-123',
        ]);
    }

    protected function tearDown(): void
    {
        $absDir = Storage::disk('public')->path($this->testDataDir);
        if (File::isDirectory($absDir)) {
            File::deleteDirectory($absDir);
        }

        $pkgDir = Storage::disk('local')->path('launcher-packages/instance-cf-test');
        if (File::isDirectory($pkgDir)) {
            File::deleteDirectory($pkgDir);
        }

        parent::tearDown();
    }

    public function test_murmur2_fingerprint_vectors_and_whitespace_stripping(): void
    {
        // Empty input (and whitespace-only input): seed=1, len=0 -> 1540447798 (0x5bd15e36)
        $this->assertSame(1540447798, CurseForgeService::computeBytesFingerprint(''));
        $this->assertSame(1540447798, CurseForgeService::computeBytesFingerprint(" \t\r\n  \x09\x0A\x0D\x20"));

        // Whitespace bytes (0x09, 0x0A, 0x0D, 0x20) must be completely ignored
        $fpPlain = CurseForgeService::computeBytesFingerprint('CentralCorpModJarPayload123456789');
        $fpWithWs = CurseForgeService::computeBytesFingerprint("Central\tCorp\nMod\rJar Payload\x09\x0A\x0D\x20123456789");
        $this->assertSame($fpPlain, $fpWithWs);

        // Tail lengths 1, 2, 3 and exact 4-byte boundary should all produce valid unsigned 32-bit ints
        foreach (['A', 'AB', 'ABC', 'ABCD', 'ABCDE', 'ABCDEF', 'ABCDEFG', 'ABCDEFGH'] as $sample) {
            $fp = CurseForgeService::computeBytesFingerprint($sample);
            $this->assertGreaterThanOrEqual(0, $fp);
            $this->assertLessThanOrEqual(0xFFFFFFFF, $fp);
        }

        // File fingerprint matches bytes fingerprint
        $tmpFile = tempnam(sys_get_temp_dir(), 'cf_fp_');
        $payload = "PK\x03\x04\x14\x00 test \t\r\n jar bytes";
        file_put_contents($tmpFile, $payload);
        try {
            $this->assertSame(
                CurseForgeService::computeBytesFingerprint($payload),
                CurseForgeService::computeFileFingerprint($tmpFile)
            );
        } finally {
            @unlink($tmpFile);
        }
    }

    public function test_strict_mc_version_and_loader_filtering_rejects_incompatible_with_422_and_accepts_compatible(): void
    {
        $service = app(CurseForgeService::class);

        // Loader mapping & unsupported loader checks
        $this->assertSame(1, $service->getModLoaderType('forge'));
        $this->assertSame(4, $service->getModLoaderType('fabric'));
        $this->assertSame(5, $service->getModLoaderType('quilt'));
        $this->assertSame(6, $service->getModLoaderType('neoforge'));
        $this->assertNull($service->getModLoaderType('vanilla'));
        $this->assertNull($service->getModLoaderType('legacyfabric'));

        // Exact MC version + loader check
        $this->assertTrue($service->isFileCompatible(['gameVersions' => ['1.20.1', 'Forge']], '1.20.1', 'forge'));
        $this->assertFalse($service->isFileCompatible(['gameVersions' => ['1.20.1', 'Fabric']], '1.20.1', 'forge'));
        $this->assertFalse($service->isFileCompatible(['gameVersions' => ['1.20', 'Forge']], '1.20.1', 'forge'));

        Http::fake([
            'https://api.curseforge.com/v1/mods/9001' => Http::response([
                'data' => [
                    'id' => 9001,
                    'name' => 'Fabric Only Mod',
                    'slug' => 'fabric-only-mod',
                    'logo' => ['thumbnailUrl' => 'https://cdn.example/logo-9001.png'],
                    'latestFiles' => [
                        [
                            'id' => 50001,
                            'fileName' => 'fabric-only-1.20.1.jar',
                            'displayName' => 'Fabric Only 1.0',
                            'gameVersions' => ['1.20.1', 'Fabric'],
                            'fileDate' => '2025-01-10T00:00:00Z',
                            'downloadUrl' => 'https://cdn.example/fabric-only-1.20.1.jar',
                        ],
                    ],
                ],
            ]),
            'https://api.curseforge.com/v1/mods/9001/files*' => Http::response(['data' => []]),
            'https://api.curseforge.com/v1/mods/9002' => Http::response([
                'data' => [
                    'id' => 9002,
                    'name' => 'JEI Forge',
                    'slug' => 'jei',
                    'logo' => ['thumbnailUrl' => 'https://cdn.example/jei.png'],
                    'latestFiles' => [
                        [
                            'id' => 50002,
                            'fileName' => 'jei-1.20.1-forge-15.2.0.jar',
                            'displayName' => 'JEI 15.2.0',
                            'gameVersions' => ['1.20.1', 'Forge'],
                            'fileDate' => '2025-02-01T00:00:00Z',
                            'fileFingerprint' => 123456789,
                            'downloadUrl' => 'https://cdn.example/jei-1.20.1-forge-15.2.0.jar',
                        ],
                    ],
                ],
            ]),
            'https://cdn.example/jei-1.20.1-forge-15.2.0.jar' => Http::response('FAKE_JAR_BINARY_CONTENT_JEI', 200),
        ]);

        // Create a dummy cached package zip to verify invalidation
        $pkgDir = Storage::disk('local')->path('launcher-packages/instance-cf-test');
        File::makeDirectory($pkgDir, 0755, true, true);
        $staleZip = $pkgDir . DIRECTORY_SEPARATOR . 'pkg-mods-p1-abcdef123456.zip';
        file_put_contents($staleZip, 'stale-zip');
        $filesVersionBefore = LauncherCache::filesVersion();

        // 1. Incompatible mod -> MUST return 422
        $resIncompat = $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->postJson('/admin/curseforge/mods/install', ['mod_id' => 9001]);

        $resIncompat->assertStatus(422);
        $this->assertDatabaseMissing('curseforge_mods', ['curseforge_mod_id' => 9001]);

        // 2. Compatible mod -> MUST return 200, write .jar, save DB, invalidate zip cache
        $resCompat = $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->postJson('/admin/curseforge/mods/install', ['mod_id' => 9002]);

        $resCompat->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.file_name', 'jei-1.20.1-forge-15.2.0.jar');

        $expectedJar = $this->instance->resolvedStoragePath() . '/mods/jei-1.20.1-forge-15.2.0.jar';
        $this->assertFileExists($expectedJar);
        $this->assertSame('FAKE_JAR_BINARY_CONTENT_JEI', file_get_contents($expectedJar));

        $this->assertDatabaseHas('curseforge_mods', [
            'instance_id' => $this->instance->id,
            'curseforge_mod_id' => 9002,
            'curseforge_file_id' => 50002,
            'file_name' => 'jei-1.20.1-forge-15.2.0.jar',
            'logo_url' => 'https://cdn.example/jei.png',
        ]);

        // Verify zip package cache was purged and LauncherCache filesVersion bumped
        $this->assertFileDoesNotExist($staleZip);
        $this->assertGreaterThan($filesVersionBefore, LauncherCache::filesVersion());
    }

    public function test_modpack_manifest_parsing_and_installation_with_edge_cdn_fallback_and_overrides(): void
    {
        // Build a real modpack .zip archive in memory/temp
        $tmpZip = tempnam(sys_get_temp_dir(), 'cf_pack_test_');
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString('manifest.json', json_encode([
            'minecraft' => ['version' => '1.20.1', 'modLoaders' => [['id' => 'forge-47.3.0', 'primary' => true]]],
            'manifestType' => 'minecraftModpack',
            'manifestVersion' => 1,
            'name' => 'CentralCorp Adventure Pack',
            'version' => '2.0.0',
            'overrides' => 'overrides',
            'files' => [
                ['projectID' => 3001, 'fileID' => 4567890, 'required' => true],
                ['projectID' => 3002, 'fileID' => 4567005, 'required' => true], // downloadUrl=null -> edge.forgecdn.net fallback
            ],
        ]));
        $zip->addFromString('overrides/mods/custom-override.jar', 'OVERRIDE_JAR_BYTES');
        $zip->close();
        $zipBinary = (string) file_get_contents($tmpZip);
        @unlink($tmpZip);

        Http::fake([
            'https://api.curseforge.com/v1/mods/8888' => Http::response([
                'data' => [
                    'id' => 8888,
                    'name' => 'CentralCorp Adventure Pack',
                    'slug' => 'centralcorp-adventure',
                    'logo' => ['thumbnailUrl' => 'https://cdn.example/pack.png'],
                    'latestFiles' => [
                        [
                            'id' => 888801,
                            'fileName' => 'adventure-pack-2.0.0.zip',
                            'displayName' => '2.0.0',
                            'gameVersions' => ['1.20.1', 'Forge'],
                            'fileDate' => '2025-03-01T00:00:00Z',
                            'downloadUrl' => 'https://cdn.example/adventure-pack-2.0.0.zip',
                        ],
                    ],
                ],
            ]),
            'https://cdn.example/adventure-pack-2.0.0.zip' => Http::response($zipBinary, 200),
            'https://api.curseforge.com/v1/mods/files' => Http::response([
                'data' => [
                    [
                        'id' => 4567890,
                        'modId' => 3001,
                        'fileName' => 'create-1.20.1.jar',
                        'displayName' => 'Create 0.5.1',
                        'fileFingerprint' => 111222333,
                        'downloadUrl' => 'https://cdn.example/create-1.20.1.jar',
                    ],
                    [
                        'id' => 4567005,
                        'modId' => 3002,
                        'fileName' => 'opt-lib.jar',
                        'displayName' => 'OptLib 1.2',
                        'fileFingerprint' => 444555666,
                        'downloadUrl' => null,
                    ],
                ],
            ]),
            'https://api.curseforge.com/v1/mods' => Http::response([
                'data' => [
                    ['id' => 3001, 'name' => 'Create', 'slug' => 'create', 'logo' => ['thumbnailUrl' => 'https://cdn.example/create.png']],
                    ['id' => 3002, 'name' => 'OptLib', 'slug' => 'optlib', 'logo' => ['url' => 'https://cdn.example/optlib.png']],
                ],
            ]),
            'https://cdn.example/create-1.20.1.jar' => Http::response('CREATE_JAR_BYTES', 200),
            'https://edge.forgecdn.net/files/4567/5/opt-lib.jar' => Http::response('OPTLIB_JAR_BYTES', 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->postJson('/admin/curseforge/modpacks/install', ['modpack_id' => 8888]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.modpack_name', 'CentralCorp Adventure Pack')
            ->assertJsonPath('data.installed_count', 2)
            ->assertJsonPath('data.overrides_count', 1)
            ->assertJsonPath('data.skipped_count', 0);

        $modsDir = $this->instance->resolvedStoragePath() . '/mods';
        $this->assertFileExists($modsDir . '/create-1.20.1.jar');
        $this->assertFileExists($modsDir . '/opt-lib.jar');
        $this->assertFileExists($modsDir . '/custom-override.jar');

        $this->assertDatabaseHas('curseforge_mods', [
            'instance_id' => $this->instance->id,
            'curseforge_mod_id' => 3001,
            'file_name' => 'create-1.20.1.jar',
        ]);
        $this->assertDatabaseHas('curseforge_mods', [
            'instance_id' => $this->instance->id,
            'curseforge_mod_id' => 3002,
            'file_name' => 'opt-lib.jar',
        ]);
    }

    public function test_separation_of_curseforge_vs_external_mods_and_fingerprint_reconciliation(): void
    {
        $modsDir = $this->instance->resolvedStoragePath() . '/mods';
        file_put_contents($modsDir . '/tracked-mod.jar', 'TRACKED_BYTES');
        $externalContent = "EXTERNAL_MOD_JAR_CONTENT_FOR_MURMUR2 \r\n\t ";
        file_put_contents($modsDir . '/unknown-external.jar', $externalContent);
        $expectedFp = CurseForgeService::computeBytesFingerprint($externalContent);

        CurseForgeMod::create([
            'instance_id' => $this->instance->id,
            'curseforge_mod_id' => 101,
            'curseforge_file_id' => 201,
            'name' => 'Tracked Mod',
            'file_name' => 'tracked-mod.jar',
            'version' => '1.0.0',
            'mc_version' => '1.20.1',
            'loader' => 'forge',
        ]);

        // 1. Installed listing separates CurseForge vs External
        $listRes = $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->getJson('/admin/curseforge/installed');

        $listRes->assertStatus(200)
            ->assertJsonCount(1, 'data.curseforge')
            ->assertJsonCount(1, 'data.external')
            ->assertJsonPath('data.curseforge.0.file_name', 'tracked-mod.jar')
            ->assertJsonPath('data.external.0.file_name', 'unknown-external.jar');

        // 2. Fingerprint scan identifies the external mod
        Http::fake([
            'https://api.curseforge.com/v1/fingerprints/432' => Http::response([
                'data' => [
                    'exactMatches' => [
                        [
                            'id' => 777,
                            'file' => [
                                'id' => 77701,
                                'modId' => 777,
                                'fileName' => 'unknown-external.jar',
                                'displayName' => 'Waystones 14.1.0',
                                'fileFingerprint' => $expectedFp,
                                'gameVersions' => ['1.20.1', 'Forge'],
                                'downloadUrl' => 'https://cdn.example/waystones.jar',
                            ],
                        ],
                    ],
                ],
            ]),
            'https://api.curseforge.com/v1/mods' => Http::response([
                'data' => [
                    [
                        'id' => 777,
                        'name' => 'Waystones',
                        'slug' => 'waystones',
                        'logo' => ['thumbnailUrl' => 'https://cdn.example/waystones.png'],
                    ],
                ],
            ]),
            'https://api.curseforge.com/v1/mods/777' => Http::response([
                'data' => [
                    'id' => 777,
                    'name' => 'Waystones',
                    'slug' => 'waystones',
                    'logo' => ['thumbnailUrl' => 'https://cdn.example/waystones.png'],
                ],
            ]),
            'https://api.curseforge.com/v1/mods/777/files/77701' => Http::response([
                'data' => [
                    'id' => 77701,
                    'modId' => 777,
                    'fileName' => 'unknown-external.jar',
                    'displayName' => 'Waystones 14.1.0',
                    'fileFingerprint' => $expectedFp,
                    'downloadUrl' => 'https://cdn.example/waystones.jar',
                ],
            ]),
        ]);

        $scanRes = $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->postJson('/admin/curseforge/external/scan');

        $scanRes->assertStatus(200)
            ->assertJsonPath('matched_count', 1);

        $matches = $scanRes->json('matches');
        $this->assertArrayHasKey('unknown-external.jar', $matches);
        $this->assertSame(777, $matches['unknown-external.jar']['curseforge_mod_id']);
        $this->assertSame('Waystones', $matches['unknown-external.jar']['name']);

        // 3. Reconcile in 1 click -> external mod becomes CurseForge tracked mod
        $reconcileRes = $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->postJson('/admin/curseforge/external/reconcile', [
                'file_name' => 'unknown-external.jar',
                'mod_id' => 777,
                'file_id' => 77701,
            ]);

        $reconcileRes->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.name', 'Waystones')
            ->assertJsonPath('data.logo_url', 'https://cdn.example/waystones.png');

        $afterList = $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->getJson('/admin/curseforge/installed');

        $afterList->assertStatus(200)
            ->assertJsonCount(2, 'data.curseforge')
            ->assertJsonCount(0, 'data.external');
    }

    public function test_one_click_update_replaces_old_jar_and_updates_database(): void
    {
        $modsDir = $this->instance->resolvedStoragePath() . '/mods';
        file_put_contents($modsDir . '/sodium-old-1.0.jar', 'OLD_JAR_BYTES');

        $mod = CurseForgeMod::create([
            'instance_id' => $this->instance->id,
            'curseforge_mod_id' => 555,
            'curseforge_file_id' => 55501,
            'name' => 'Embeddium',
            'slug' => 'embeddium',
            'file_name' => 'sodium-old-1.0.jar',
            'version' => '1.0.0',
            'mc_version' => '1.20.1',
            'loader' => 'forge',
        ]);

        Http::fake([
            'https://api.curseforge.com/v1/mods' => Http::response([
                'data' => [
                    [
                        'id' => 555,
                        'name' => 'Embeddium',
                        'slug' => 'embeddium',
                        'logo' => ['thumbnailUrl' => 'https://cdn.example/embeddium.png'],
                        'latestFiles' => [
                            [
                                'id' => 55502,
                                'fileName' => 'embeddium-1.20.1-2.0.jar',
                                'displayName' => 'Embeddium 2.0.0',
                                'gameVersions' => ['1.20.1', 'Forge'],
                                'fileDate' => '2025-05-01T00:00:00Z',
                                'downloadUrl' => 'https://cdn.example/embeddium-1.20.1-2.0.jar',
                            ],
                        ],
                    ],
                ],
            ]),
            'https://api.curseforge.com/v1/mods/555' => Http::response([
                'data' => [
                    'id' => 555,
                    'name' => 'Embeddium',
                    'slug' => 'embeddium',
                    'logo' => ['thumbnailUrl' => 'https://cdn.example/embeddium.png'],
                    'latestFiles' => [
                        [
                            'id' => 55502,
                            'fileName' => 'embeddium-1.20.1-2.0.jar',
                            'displayName' => 'Embeddium 2.0.0',
                            'gameVersions' => ['1.20.1', 'Forge'],
                            'fileDate' => '2025-05-01T00:00:00Z',
                            'downloadUrl' => 'https://cdn.example/embeddium-1.20.1-2.0.jar',
                        ],
                    ],
                ],
            ]),
            'https://api.curseforge.com/v1/mods/555/files/55502' => Http::response([
                'data' => [
                    'id' => 55502,
                    'fileName' => 'embeddium-1.20.1-2.0.jar',
                    'displayName' => 'Embeddium 2.0.0',
                    'gameVersions' => ['1.20.1', 'Forge'],
                    'fileDate' => '2025-05-01T00:00:00Z',
                    'downloadUrl' => 'https://cdn.example/embeddium-1.20.1-2.0.jar',
                ],
            ]),
            'https://cdn.example/embeddium-1.20.1-2.0.jar' => Http::response('NEW_UPDATED_JAR_BYTES', 200),
        ]);

        // 1. Check updates
        $checkRes = $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->postJson('/admin/curseforge/updates/check');

        $checkRes->assertStatus(200)
            ->assertJsonPath("updates.{$mod->id}.has_update", true)
            ->assertJsonPath("updates.{$mod->id}.latest_file_id", 55502);

        // 2. Apply 1-click update
        $updateRes = $this->actingAs($this->admin)
            ->withSession([ActiveInstance::SESSION_KEY => $this->instance->id])
            ->postJson("/admin/curseforge/mods/{$mod->id}/update", ['file_id' => 55502]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.file_name', 'embeddium-1.20.1-2.0.jar');

        $this->assertFileDoesNotExist($modsDir . '/sodium-old-1.0.jar');
        $this->assertFileExists($modsDir . '/embeddium-1.20.1-2.0.jar');
        $this->assertSame('NEW_UPDATED_JAR_BYTES', file_get_contents($modsDir . '/embeddium-1.20.1-2.0.jar'));

        $mod->refresh();
        $this->assertSame(55502, $mod->curseforge_file_id);
        $this->assertSame('embeddium-1.20.1-2.0.jar', $mod->file_name);
        $this->assertSame('Embeddium 2.0.0', $mod->version);
    }
}
