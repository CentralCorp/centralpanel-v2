<?php

namespace Tests\Feature;

use App\Models\MicrosoftPlayer;
use App\Models\OptionsServer;
use App\Models\OptionsWhitelist;
use App\Models\OptionsWhitelistRole;
use App\Models\PanelRole;
use App\Models\User;
use App\Support\PanelOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MicrosoftAuthParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
    }

    public function test_microsoft_only_config_saves_without_azuriom_and_exposes_default_client_id(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        PanelOptions::ensureDefaults();

        $this->actingAs($admin)->post(route('admin.config.update'), [
            'app_name' => config('app.name'),
            'auth_mode' => 'microsoft',
            'microsoft_client_id' => '',
            'news_source' => 'panel',
            'azuriom_url' => '',
            'azuriom_api_key' => '',
        ])->assertRedirect(route('admin.config'));

        $this->getJson('/utils/api')
            ->assertOk()
            ->assertJsonPath('auth_mode', 'microsoft')
            ->assertJsonPath('microsoft_auth', true)
            ->assertJsonPath('azuriom_auth', false)
            ->assertJsonPath('client_id', '00000000402b5328')
            ->assertJsonPath('news_source', 'panel');

        // Custom Azure Client ID
        $this->actingAs($admin)->post(route('admin.config.update'), [
            'app_name' => config('app.name'),
            'auth_mode' => 'microsoft',
            'microsoft_client_id' => '11111111-2222-3333-4444-555555555555',
            'news_source' => 'panel',
        ])->assertRedirect(route('admin.config'));

        $this->getJson('/utils/api')
            ->assertOk()
            ->assertJsonPath('client_id', '11111111-2222-3333-4444-555555555555');
    }

    public function test_microsoft_sync_rejects_missing_or_invalid_token_with_401(): void
    {
        $this->postJson('/utils/auth/microsoft/sync', [])
            ->assertStatus(401)
            ->assertExactJson([
                'status' => 'error',
                'reason' => 'invalid_token',
                'message' => 'Invalid Minecraft token',
            ]);

        Http::fake([
            'https://api.minecraftservices.com/minecraft/profile' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $this->postJson('/utils/auth/microsoft/sync', [
            'access_token' => 'expired-mc-token',
        ])->assertStatus(401)
            ->assertJsonPath('reason', 'invalid_token');
    }

    public function test_microsoft_sync_creates_new_player_with_default_role(): void
    {
        Http::fake([
            'https://api.minecraftservices.com/minecraft/profile' => Http::response([
                'id' => '069a79f444e94726a5befca90e38aaf5',
                'name' => 'Notch',
                'skins' => [
                    ['state' => 'ACTIVE', 'url' => 'https://textures.minecraft.net/texture/notchskin'],
                ],
                'capes' => [],
            ], 200),
        ]);

        $response = $this->postJson('/utils/auth/microsoft/sync', [
            'access_token' => 'valid-mc-jwt',
            'xuid' => '2535412345678901',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('uuid', '069a79f444e94726a5befca90e38aaf5')
            ->assertJsonPath('username', 'Notch')
            ->assertJsonPath('money', 0)
            ->assertJsonPath('banned', false)
            ->assertJsonPath('ban_reason', null)
            ->assertJsonPath('verified', true)
            ->assertJsonPath('maintenance_bypass', false)
            ->assertJsonPath('role.name', 'Membre');

        $player = MicrosoftPlayer::where('uuid', '069a79f444e94726a5befca90e38aaf5')->firstOrFail();
        $this->assertSame('2535412345678901', $player->xuid);
        $this->assertSame('https://textures.minecraft.net/texture/notchskin', $player->skin_url);
        $this->assertNotNull($player->last_seen_at);
    }

    public function test_microsoft_sync_matches_preregistered_player_preserves_role_money_and_background(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        PanelOptions::ensureDefaults();

        $adminRole = PanelRole::where('name', 'Admin')->firstOrFail();

        // Upload background for PanelRole via AdminBgController
        $bgFile = UploadedFile::fake()->image('admin_bg.png', 1920, 1080);
        $this->actingAs($admin)->post(route('admin.bg.update'), [
            'source' => 'panel',
            'panel_role_id' => $adminRole->id,
            'bg_image' => $bgFile,
        ])->assertRedirect();

        $adminRole->refresh();
        $this->assertNotNull($adminRole->background_path);

        // Pre-register player without UUID
        $this->actingAs($admin)->post(route('admin.players.store'), [
            'username' => 'Riptiaz',
            'uuid' => '',
            'role_id' => $adminRole->id,
            'money' => 1250,
            'is_banned' => 0,
        ])->assertRedirect(route('admin.players'));

        Http::fake([
            'https://api.minecraftservices.com/minecraft/profile' => Http::response([
                'id' => 'abcdef01-2345-6789-abcd-ef0123456789',
                'name' => 'riptiaz',
            ], 200),
        ]);

        $syncResp = $this->postJson('/utils/auth/microsoft/sync', [
            'access_token' => 'riptiaz-mc-token',
        ]);

        $syncResp->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('uuid', 'abcdef0123456789abcdef0123456789')
            ->assertJsonPath('username', 'riptiaz')
            ->assertJsonPath('money', 1250)
            ->assertJsonPath('maintenance_bypass', true)
            ->assertJsonPath('role.name', 'Admin');

        $this->assertStringContainsString('/storage/backgrounds/', (string) $syncResp->json('role.background'));

        // Verify role_data in /utils/api includes PanelRole background
        $apiRoleData = $this->getJson('/utils/api')->assertOk()->json('role_data');
        $this->assertArrayHasKey('panel_role' . $adminRole->id, $apiRoleData);
        $this->assertSame('Admin', $apiRoleData['panel_role' . $adminRole->id]['name']);
    }

    public function test_microsoft_sync_blocks_banned_player_with_403(): void
    {
        $admin = User::factory()->create();
        $defaultRole = PanelOptions::ensureRoles();

        $player = MicrosoftPlayer::create([
            'uuid' => 'deadbeefdeadbeefdeadbeefdeadbeef',
            'username' => 'Cheater123',
            'role_id' => $defaultRole->id,
            'money' => 0,
            'is_banned' => false,
        ]);

        $this->actingAs($admin)->post(route('admin.players.toggleBan', $player), [
            'ban_reason' => 'X-Ray Cheat Detected',
        ])->assertRedirect(route('admin.players'));

        Http::fake([
            'https://api.minecraftservices.com/minecraft/profile' => Http::response([
                'id' => 'deadbeefdeadbeefdeadbeefdeadbeef',
                'name' => 'Cheater123',
            ], 200),
        ]);

        $this->postJson('/utils/auth/microsoft/sync', [
            'access_token' => 'cheater-token',
        ])->assertStatus(403)
            ->assertExactJson([
                'status' => 'error',
                'reason' => 'user_banned',
                'message' => 'X-Ray Cheat Detected',
            ]);
    }

    public function test_manual_server_crud_and_azuriom_free_whitelist_and_roles_crud(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        PanelOptions::ensureDefaults();
        PanelOptions::general()->update([
            'auth_mode' => 'microsoft',
            'azuriom_url' => null,
            'azuriom_api_key' => null,
        ]);

        // Roles CRUD
        $this->actingAs($admin)->post(route('admin.roles.store'), [
            'name' => 'VIP+',
            'color' => '#ffaa00',
            'power' => 50,
            'is_default' => 0,
            'bypass_maintenance' => 1,
        ])->assertRedirect(route('admin.roles'));

        $vipRole = PanelRole::where('name', 'VIP+')->firstOrFail();

        // Manual Server CRUD without Azuriom
        $this->actingAs($admin)->post(route('admin.server.store'), [
            'server_name' => 'Manual Survie',
            'server_ip' => 'play.manual.test',
            'server_port' => '25565',
            'is_default' => 1,
        ])->assertRedirect(route('admin.server'));

        $manualServer = OptionsServer::where('server_name', 'Manual Survie')->firstOrFail();
        $this->assertTrue($manualServer->is_default);
        $this->assertLessThan(0, $manualServer->server_id);

        $this->actingAs($admin)->put(route('admin.server.updateServer', $manualServer->id), [
            'server_name' => 'Manual Survie Updated',
            'server_ip' => 'mc.manual.test',
            'server_port' => '25577',
        ])->assertRedirect(route('admin.server'));

        $this->assertSame('25577', $manualServer->fresh()->server_port);

        // Whitelist without Azuriom (manual usernames + PanelRole)
        $this->actingAs($admin)->post(route('admin.whitelist.store'), [
            'whitelist' => 1,
            'scope' => 'global',
            'manual_users' => "Alex, Dinnerbone\nJeb_",
            'panel_roles' => ['VIP+'],
        ])->assertRedirect(route('admin.whitelist'));

        $this->assertTrue(OptionsWhitelist::where('users', 'Dinnerbone')->whereNull('instance_id')->exists());
        $this->assertTrue(OptionsWhitelistRole::where('role', 'VIP+')->whereNull('instance_id')->exists());

        // Render all Microsoft parity admin pages without Azuriom configured
        $this->actingAs($admin)->get(route('admin.config'))->assertOk();
        $this->actingAs($admin)->get(route('admin.roles'))->assertOk();
        $this->actingAs($admin)->get(route('admin.players'))->assertOk();
        $this->actingAs($admin)->get(route('admin.server'))->assertOk();
        $this->actingAs($admin)->get(route('admin.whitelist'))->assertOk();
        $this->actingAs($admin)->get(route('admin.security'))->assertOk();
        $this->actingAs($admin)->get(route('admin.bg'))->assertOk();

        // Delete manual server
        $this->actingAs($admin)->delete(route('admin.server.destroy', $manualServer->id))
            ->assertRedirect(route('admin.server'));
        $this->assertDatabaseMissing('options_server', ['id' => $manualServer->id]);
    }
}
