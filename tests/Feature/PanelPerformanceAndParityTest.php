<?php

namespace Tests\Feature;

use App\Models\Instance;
use App\Models\MicrosoftPlayer;
use App\Models\News;
use App\Models\OptionsIgnore;
use App\Models\OptionsMods;
use App\Models\OptionsServer;
use App\Models\PanelRole;
use App\Models\User;
use App\Support\LauncherCache;
use App\Support\PanelOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PanelPerformanceAndParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
        File::put(storage_path('installed'), 'installed');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(Storage::disk('public')->path('instances/creative-opti'));
        File::deleteDirectory(Storage::disk('public')->path('instances/neoforge-upper'));
        File::deleteDirectory(Storage::disk('public')->path('instances/neoforge-lower'));
        parent::tearDown();
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'email' => 'opti-admin@centralcorp.test',
        ]);
    }

    public function test_consolidated_bootstrap_include_news_and_mods_and_skin_api(): void
    {
        PanelOptions::ensureDefaults();
        $defaultInstance = PanelOptions::defaultInstance();

        $creative = Instance::create([
            'slug' => 'creative-opti',
            'name' => 'Creative Opti',
            'is_default' => false,
            'is_enabled' => true,
            'position' => 1,
            'minecraft_version' => '1.21.1',
            'loader_type' => 'fabric',
            'loader_build_version' => '0.16.5',
            'loader_activation' => true,
            'mods_enabled' => true,
            'file_verification' => true,
            'embedded_java' => true,
            'min_ram' => 2048,
            'max_ram' => 4096,
            'custom_data_dir' => 'instances/creative-opti',
        ]);

        Instance::create([
            'slug' => 'disabled-opti',
            'name' => 'Disabled Opti',
            'is_default' => false,
            'is_enabled' => false,
            'position' => 2,
            'minecraft_version' => '1.20.1',
            'loader_type' => 'forge',
            'loader_activation' => false,
            'min_ram' => 2048,
            'max_ram' => 4096,
            'custom_data_dir' => 'instances/disabled-opti',
        ]);

        OptionsMods::create([
            'instance_id' => $defaultInstance->id,
            'file' => 'sodium.jar',
            'name' => 'Sodium',
            'description' => 'FPS boost',
            'optional' => true,
            'recommended' => true,
        ]);

        OptionsMods::create([
            'instance_id' => $defaultInstance->id,
            'file' => 'core-required.jar',
            'name' => 'Core Required',
            'description' => 'Mandatory mod',
            'optional' => false,
            'recommended' => false,
        ]);

        News::create([
            'title' => 'Season 4 Launch',
            'slug' => 'season-4-launch',
            'content' => 'Welcome to Season 4!',
            'author' => 'Riptiaz',
            'badge' => 'Update',
            'is_published' => true,
            'is_pinned' => true,
            'published_at' => now()->subHour(),
        ]);

        News::create([
            'title' => 'Secret Draft',
            'slug' => 'secret-draft',
            'content' => 'Not public yet',
            'author' => 'Riptiaz',
            'is_published' => false,
        ]);

        // 1. Default /utils/api without ?include= omits included_news & mods_by_instance, emits skin_api=true, compact JSON
        $plainResponse = $this->getJson('/utils/api')->assertOk();
        $plainRaw = $plainResponse->getContent();
        $this->assertStringNotContainsString("\n", $plainRaw);

        $plainJson = $plainResponse->json();
        $this->assertTrue($plainJson['skin_api']);
        $this->assertArrayNotHasKey('mods_by_instance', $plainJson);
        $this->assertArrayNotHasKey('included_news', $plainJson);

        // 2. With news_source = azuriom, ?include=news,mods includes mods_by_instance but NOT included_news
        PanelOptions::general()->update(['news_source' => 'azuriom']);
        $azuriomBootstrap = $this->getJson('/utils/api?include=news,mods')->assertOk();
        $azuriomData = $azuriomBootstrap->json();
        $this->assertArrayHasKey('mods_by_instance', $azuriomData);
        $this->assertArrayNotHasKey('included_news', $azuriomData);
        $this->assertArrayHasKey('default', $azuriomData['mods_by_instance']);
        $this->assertArrayHasKey('creative-opti', $azuriomData['mods_by_instance']);
        $this->assertArrayNotHasKey('disabled-opti', $azuriomData['mods_by_instance']);

        // Only optional=true mods are included, with BOTH recommanded and recommended boolean keys
        $this->assertSame(['sodium.jar'], $azuriomData['mods_by_instance']['default']['optionalMods']);
        $this->assertArrayNotHasKey('core-required.jar', $azuriomData['mods_by_instance']['default']['mods']);
        $this->assertTrue($azuriomData['mods_by_instance']['default']['mods']['sodium.jar']['recommanded']);
        $this->assertTrue($azuriomData['mods_by_instance']['default']['mods']['sodium.jar']['recommended']);

        // Creative has 0 optional mods -> mods serializes as JSON {}
        $this->assertStringContainsString('"creative-opti":{"optionalMods":[],"mods":{}}', $azuriomBootstrap->getContent());

        // 3. With news_source = panel or both, included_news matches /utils/news exactly
        foreach (['panel', 'both'] as $source) {
            PanelOptions::general()->update(['news_source' => $source]);
            $expectedNews = $this->getJson('/utils/news')->assertOk()->json();
            $bootstrap = $this->getJson('/utils/api?include=news,mods')->assertOk()->json();

            $this->assertCount(1, $expectedNews);
            $this->assertSame($expectedNews, $bootstrap['included_news']);
            $this->assertSame('Season 4 Launch', $bootstrap['included_news'][0]['title']);
        }
    }

    public function test_etag_and_if_none_match_return_304_across_launcher_json_endpoints(): void
    {
        PanelOptions::ensureDefaults();
        PanelOptions::general()->update(['news_source' => 'panel']);
        $defaultInstance = PanelOptions::defaultInstance();

        File::put($defaultInstance->resolvedStoragePath() . '/mods/test-mod.jar', 'jar-bytes');
        News::create([
            'title' => 'ETag News',
            'slug' => 'etag-news',
            'content' => 'Testing ETag',
            'author' => 'Admin',
            'is_published' => true,
            'published_at' => now(),
        ]);
        OptionsMods::create([
            'instance_id' => $defaultInstance->id,
            'file' => 'test-mod.jar',
            'name' => 'Test Mod',
            'description' => 'Desc',
            'optional' => true,
            'recommended' => false,
        ]);

        $endpoints = [
            '/utils/api',
            '/utils/api?include=news,mods',
            '/data',
            '/utils/instances/default/files',
            '/utils/mods',
            '/utils/instances/default/mods',
            '/utils/news',
        ];

        try {
            foreach ($endpoints as $uri) {
                $first = $this->get($uri);
                $first->assertOk();

                $body = $first->getContent();
                $this->assertNotEmpty($body);
                $this->assertStringNotContainsString("\n", $body, "Expected compact JSON on {$uri}");

                $etag = $first->headers->get('ETag');
                $this->assertNotNull($etag, "Missing ETag on {$uri}");
                $this->assertMatchesRegularExpression('/^"[0-9a-f]{40}"$/', $etag);
                $this->assertSame('"' . sha1($body) . '"', $etag);
                $this->assertStringContainsString('no-cache', (string) $first->headers->get('Cache-Control'));

                // Exact match -> 304 Not Modified
                $exact304 = $this->withHeaders(['If-None-Match' => $etag])->get($uri);
                $exact304->assertStatus(304);
                $this->assertSame('', $exact304->getContent());
                $this->assertSame($etag, $exact304->headers->get('ETag'));

                // Weak ETag prefix -> 304 Not Modified
                $weak304 = $this->withHeaders(['If-None-Match' => 'W/' . $etag])->get($uri);
                $weak304->assertStatus(304);

                // Comma-separated list -> 304 Not Modified
                $list304 = $this->withHeaders(['If-None-Match' => '"0000000000000000000000000000000000000000", ' . $etag])->get($uri);
                $list304->assertStatus(304);

                // Mismatched ETag -> 200 OK
                $miss200 = $this->withHeaders(['If-None-Match' => '"ffffffffffffffffffffffffffffffffffffffff"'])->get($uri);
                $miss200->assertOk();
            }
        } finally {
            File::delete($defaultInstance->resolvedStoragePath() . '/mods/test-mod.jar');
        }
    }

    public function test_microsoft_sync_never_flushes_launcher_cache_and_skips_redundant_updates(): void
    {
        PanelOptions::ensureDefaults();

        // Warm metadata and file caches
        $initialApi = $this->get('/utils/api')->assertOk();
        $this->get('/data')->assertOk();

        $etagBefore = $initialApi->headers->get('ETag');
        $metaVersionBefore = LauncherCache::version();
        $filesVersionBefore = LauncherCache::filesVersion();

        Http::fake([
            'api.minecraftservices.com/minecraft/profile' => Http::response([
                'id' => '069a79f444e94726a5befca90e38aaf5',
                'name' => 'Notch',
                'skins' => [
                    ['state' => 'ACTIVE', 'url' => 'https://textures.minecraft.net/texture/skin1'],
                ],
                'capes' => [],
            ], 200),
        ]);

        // 1. First login creates MicrosoftPlayer without flushing LauncherCache
        $this->postJson('/utils/auth/microsoft/sync', [
            'access_token' => 'valid-ms-token',
        ])->assertOk()->assertJsonPath('username', 'Notch');

        $this->assertSame($metaVersionBefore, LauncherCache::version());
        $this->assertSame($filesVersionBefore, LauncherCache::filesVersion());
        $this->withHeaders(['If-None-Match' => $etagBefore])->get('/utils/api')->assertStatus(304);

        // 2. Immediate repeat login (< 5 min, unchanged profile) skips redundant UPDATE on microsoft_players
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->postJson('/utils/auth/microsoft/sync', [
            'access_token' => 'valid-ms-token',
        ])->assertOk();

        $queries = array_map(static fn (array $entry): string => strtolower($entry['query']), DB::getQueryLog());
        $playerUpdates = array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'update') && str_contains($sql, 'microsoft_players'));
        $this->assertCount(0, $playerUpdates, 'Expected no redundant UPDATE on microsoft_players within 5 minutes');
        $this->assertSame($metaVersionBefore, LauncherCache::version());
        $this->assertSame($filesVersionBefore, LauncherCache::filesVersion());

        // 3. After > 5 minutes, last_seen_at updates in DB, STILL without flushing LauncherCache
        $this->travel(6)->minutes();
        DB::flushQueryLog();

        $this->postJson('/utils/auth/microsoft/sync', [
            'access_token' => 'valid-ms-token',
        ])->assertOk();

        $queriesAfter6m = array_map(static fn (array $entry): string => strtolower($entry['query']), DB::getQueryLog());
        $playerUpdatesAfter6m = array_filter($queriesAfter6m, static fn (string $sql): bool => str_contains($sql, 'update') && str_contains($sql, 'microsoft_players'));
        $this->assertCount(1, $playerUpdatesAfter6m);
        $this->assertSame($metaVersionBefore, LauncherCache::version());
        $this->assertSame($filesVersionBefore, LauncherCache::filesVersion());
        DB::disableQueryLog();

        // 4. Admin editing a player does not flush LauncherCache either
        $admin = $this->createAdmin();
        $player = MicrosoftPlayer::firstOrFail();
        $this->actingAs($admin)->put(route('admin.players.update', $player), [
            'username' => 'Notch',
            'role_id' => $player->role_id,
            'money' => 500,
        ])->assertRedirect();

        $this->assertSame($metaVersionBefore, LauncherCache::version());
        $this->assertSame($filesVersionBefore, LauncherCache::filesVersion());
    }

    public function test_metadata_vs_files_cache_split_isolation(): void
    {
        PanelOptions::ensureDefaults();

        $metaV0 = LauncherCache::version();
        $filesV0 = LauncherCache::filesVersion();

        // Updating metadata models bumps metadata version ONLY
        News::create([
            'title' => 'Metadata Only',
            'slug' => 'metadata-only',
            'content' => 'Does not bust file manifest',
            'author' => 'Admin',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $metaV1 = LauncherCache::version();
        $this->assertGreaterThan($metaV0, $metaV1);
        $this->assertSame($filesV0, LauncherCache::filesVersion());

        // Flushing files (as File Manager mutations do) bumps files version ONLY
        LauncherCache::flushFiles();
        $filesV1 = LauncherCache::filesVersion();
        $this->assertGreaterThan($filesV0, $filesV1);
        $this->assertSame($metaV1, LauncherCache::version());

        // Changing OptionsIgnore invalidates both metadata (ignored list in /utils/api) and files (/data manifest)
        OptionsIgnore::create(['folder_name' => 'logs']);
        $this->assertGreaterThan($metaV1, LauncherCache::version());
        $this->assertGreaterThan($filesV1, LauncherCache::filesVersion());
    }

    public function test_no_n_plus_one_queries_for_multiple_instances_with_server_icons_and_bypass_roles(): void
    {
        PanelOptions::ensureDefaults();
        PanelOptions::general()->update(['azuriom_url' => 'https://azuriom.centralcorp.test']);

        PanelRole::create([
            'name' => 'Moderator',
            'color' => '#3b82f6',
            'power' => 50,
            'is_default' => false,
            'bypass_maintenance' => true,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $server = OptionsServer::create([
                'server_id' => 100 + $i,
                'server_name' => "Server {$i}",
                'server_ip' => "10.0.0.{$i}",
                'server_port' => (string) (25565 + $i),
                'icon' => "servers/icon-{$i}.png",
                'type' => 'minecraft',
                'is_default' => $i === 1,
            ]);

            Instance::create([
                'slug' => "inst-{$i}",
                'name' => "Instance {$i}",
                'is_default' => false,
                'is_enabled' => true,
                'position' => $i,
                'minecraft_version' => '1.20.1',
                'loader_type' => 'forge',
                'loader_activation' => true,
                'min_ram' => 2048,
                'max_ram' => 4096,
                'server_id' => $server->id,
                'custom_data_dir' => 'instances/default',
            ]);
        }

        Cache::clear();
        DB::enableQueryLog();
        DB::flushQueryLog();

        $res = $this->getJson('/utils/api')->assertOk();
        $queries = array_map(static fn (array $entry): string => strtolower($entry['query']), DB::getQueryLog());
        DB::disableQueryLog();

        $generalQueries = array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'options_general'));
        $bypassQueries = array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'panel_roles') && str_contains($sql, 'bypass_maintenance'));

        $this->assertCount(1, $generalQueries, 'options_general must only be queried once per cache fill');
        $this->assertCount(1, $bypassQueries, 'panel_roles bypass_maintenance must only be queried once per cache fill');

        $inst1 = collect($res->json('instances'))->firstWhere('slug', 'inst-1');
        $this->assertSame('https://azuriom.centralcorp.test/storage/servers/icon-1.png', $inst1['server_icon']);
        $this->assertContains('Moderator', $inst1['maintenance_bypass_roles']);
    }

    public function test_rpc_image_keys_persistence_defaults_and_unswapped_api_output(): void
    {
        PanelOptions::ensureDefaults();
        $admin = $this->createAdmin();

        // 1. Default values are no longer swapped ('large' for large_image, 'small' for small_image)
        $initial = $this->getJson('/utils/api')->assertOk()->json();
        $this->assertSame('large', $initial['rpc_large_image']);
        $this->assertSame('small', $initial['rpc_small_image']);

        // 2. Admin view renders new inputs and documents dynamic placeholders
        $this->actingAs($admin)
            ->get(route('admin.rpc'))
            ->assertOk()
            ->assertSee('name="rpc_large_image"', false)
            ->assertSee('name="rpc_small_image"', false)
            ->assertSee('{player}')
            ->assertSee('{instance}')
            ->assertSee('{version}')
            ->assertSee('{server}');

        // 3. Updating custom image keys persists and reflects in /utils/api
        $this->actingAs($admin)
            ->post(route('admin.rpc.update'), [
                'rpc_activation' => '1',
                'rpc_id' => '9876543210',
                'rpc_details' => 'Playing {instance} ({version})',
                'rpc_state' => '{player} on {server}',
                'rpc_large_image' => 'logo_season4',
                'rpc_large_text' => 'CentralCorp',
                'rpc_small_image' => 'badge_vip',
                'rpc_small_text' => 'Online',
                'rpc_button1' => 'Discord',
                'rpc_button1_url' => 'https://discord.gg/centralcorp',
                'rpc_button2' => '',
                'rpc_button2_url' => '',
            ])
            ->assertRedirect(route('admin.rpc'))
            ->assertSessionHasNoErrors();

        $updated = $this->getJson('/utils/api')->assertOk()->json();
        $this->assertSame('logo_season4', $updated['rpc_large_image']);
        $this->assertSame('badge_vip', $updated['rpc_small_image']);
        $this->assertSame('Playing {instance} ({version})', $updated['rpc_details']);
        $this->assertNull($updated['rpc_button2']);
    }

    public function test_disabled_rpc_and_ui_forms_save_with_empty_optional_fields_and_youtube_formats(): void
    {
        PanelOptions::ensureDefaults();
        $admin = $this->createAdmin();

        // 1. Disabled RPC with all optional fields empty saves cleanly
        $this->actingAs($admin)
            ->post(route('admin.rpc.update'), [
                'rpc_activation' => '0',
                'rpc_id' => '',
                'rpc_details' => '',
                'rpc_state' => '',
                'rpc_large_image' => '',
                'rpc_large_text' => '',
                'rpc_small_image' => '',
                'rpc_small_text' => '',
                'rpc_button1' => '',
                'rpc_button1_url' => '',
                'rpc_button2' => '',
                'rpc_button2_url' => '',
            ])
            ->assertRedirect(route('admin.rpc'))
            ->assertSessionHasNoErrors();

        $this->assertFalse($this->getJson('/utils/api')->assertOk()->json('rpc_activation'));

        // 2. Disabled UI alert & video with empty splash, splash_author, alert_msg, video_url saves cleanly
        $this->actingAs($admin)
            ->post(route('admin.ui.update'), [
                'alert_activation' => '0',
                'alert_scroll' => '0',
                'alert_msg' => '',
                'video_activation' => '0',
                'video_url' => '',
                'splash' => '',
                'splash_author' => '',
                'accent_color' => '#123456',
            ])
            ->assertRedirect(route('admin.ui'))
            ->assertSessionHasNoErrors();

        $uiDisabledApi = $this->getJson('/utils/api')->assertOk()->json();
        $this->assertFalse($uiDisabledApi['alert_activate']);
        $this->assertFalse($uiDisabledApi['video_activate']);
        $this->assertSame('', $uiDisabledApi['splash']);
        $this->assertSame('', $uiDisabledApi['splash_author']);
        $this->assertSame('', $uiDisabledApi['alert_msg']);
        $this->assertSame('', $uiDisabledApi['video_url']);

        // 3. Supported YouTube formats (watch, shorts, embed, youtu.be, live, raw 11-char ID)
        $validInputs = [
            ['input' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'id' => 'dQw4w9WgXcQ', 'type' => 'video'],
            ['input' => 'https://www.youtube.com/shorts/a336KPLjsZU?feature=share', 'id' => 'a336KPLjsZU', 'type' => 'short'],
            ['input' => 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'id' => 'dQw4w9WgXcQ', 'type' => 'video'],
            ['input' => 'https://youtu.be/dQw4w9WgXcQ?t=42', 'id' => 'dQw4w9WgXcQ', 'type' => 'video'],
            ['input' => 'https://www.youtube.com/live/dQw4w9WgXcQ?si=abc', 'id' => 'dQw4w9WgXcQ', 'type' => 'video'],
            ['input' => 'dQw4w9WgXcQ', 'id' => 'dQw4w9WgXcQ', 'type' => 'video'],
        ];

        foreach ($validInputs as $case) {
            $this->actingAs($admin)
                ->post(route('admin.ui.update'), [
                    'alert_activation' => '1',
                    'alert_scroll' => '0',
                    'alert_msg' => 'Alert',
                    'video_activation' => '1',
                    'video_url' => $case['input'],
                    'splash' => 'Splash',
                    'splash_author' => 'Author',
                    'accent_color' => '#FFA500',
                ])
                ->assertRedirect(route('admin.ui'))
                ->assertSessionHasNoErrors();

            $api = $this->getJson('/utils/api')->assertOk()->json();
            $this->assertSame($case['id'], $api['video_url'], "Failed extracting ID for {$case['input']}");
            $this->assertSame($case['type'], $api['video_type'], "Failed detecting type for {$case['input']}");
        }

        // 4. Invalid non-YouTube URL is rejected
        $this->actingAs($admin)
            ->from(route('admin.ui'))
            ->post(route('admin.ui.update'), [
                'video_activation' => '1',
                'video_url' => 'https://vimeo.com/123456789',
                'accent_color' => '#FFA500',
            ])
            ->assertRedirect(route('admin.ui'))
            ->assertSessionHasErrors('video_url');
    }

    public function test_instance_parity_empty_ip_without_server_merged_bypass_roles_and_neoforge_casing(): void
    {
        PanelOptions::ensureDefaults();
        $admin = $this->createAdmin();

        // Ensure zero servers exist and default instance has server_id = null
        OptionsServer::query()->delete();
        $defaultInstance = PanelOptions::defaultInstance();
        $defaultInstance->update([
            'server_id' => null,
            'maintenance_bypass_roles' => ['Builder'],
        ]);

        PanelRole::create([
            'name' => 'Fondateur',
            'color' => '#eab308',
            'power' => 99,
            'is_default' => false,
            'bypass_maintenance' => true,
        ]);

        LauncherCache::flush();

        // 1. When $server === null, status.ip is '' (not '127.0.0.1') and PanelRole bypass_maintenance is merged
        $api = $this->getJson('/utils/api')->assertOk()->json();
        $this->assertSame('', $api['status']['ip']);
        $this->assertSame('', $api['instances'][0]['status']['ip']);
        $this->assertContains('Builder', $api['instances'][0]['maintenance_bypass_roles']);
        $this->assertContains('Fondateur', $api['instances'][0]['maintenance_bypass_roles']);

        // 2. Both neoForge and neoforge are accepted cleanly in AdminInstanceController & AdminLoaderController
        $this->actingAs($admin)
            ->post(route('admin.instances.store'), [
                'name' => 'NeoForge Upper',
                'slug' => 'neoforge-upper',
                'is_enabled' => '1',
                'minecraft_version' => '1.21.1',
                'loader_activation' => '1',
                'loader_type' => 'neoForge',
                'loader_build_version' => '21.1.72',
                'min_ram' => 2048,
                'max_ram' => 4096,
            ])
            ->assertRedirect(route('admin.instances'))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.instances.store'), [
                'name' => 'NeoForge Lower',
                'slug' => 'neoforge-lower',
                'is_enabled' => '1',
                'minecraft_version' => '1.21.1',
                'loader_activation' => '1',
                'loader_type' => 'neoforge',
                'loader_build_version' => '21.1.72',
                'min_ram' => 2048,
                'max_ram' => 4096,
            ])
            ->assertRedirect(route('admin.instances'))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.loader.update'), [
                'minecraft_version' => '1.21.1',
                'loader_activation' => '1',
                'loader_type' => 'neoforge',
                'loader_build_version' => '21.1.72',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->get(route('admin.loader'))
            ->assertOk();
    }
}
