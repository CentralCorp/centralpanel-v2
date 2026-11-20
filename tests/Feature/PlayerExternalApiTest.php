<?php

namespace Tests\Feature;

use App\Models\MicrosoftPlayer;
use App\Models\PanelRole;
use App\Models\PlayerApiToken;
use App\Models\User;
use App\Support\PanelOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PlayerExternalApiTest extends TestCase
{
    use RefreshDatabase;

    private MicrosoftPlayer $player;
    private PanelRole $defaultRole;
    private PanelRole $vipRole;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
        PanelOptions::ensureDefaults();

        $this->defaultRole = PanelOptions::ensureRoles();
        $this->vipRole = PanelRole::create([
            'name' => 'VIP',
            'color' => '#f59e0b',
            'power' => 50,
            'is_default' => false,
            'bypass_maintenance' => false,
        ]);

        $this->player = MicrosoftPlayer::create([
            'uuid' => '069a79f444e94726a5befca90e38aaf5',
            'username' => 'Notch',
            'role_id' => $this->defaultRole->id,
            'money' => 150,
            'is_banned' => false,
        ]);
    }

    private function enableApi(): void
    {
        PanelOptions::security()->update(['player_api_enabled' => true]);
    }

    public function test_returns_503_when_external_api_is_disabled(): void
    {
        $generated = PlayerApiToken::generateToken('Disabled Test', PlayerApiToken::ALL_ABILITIES);

        $this->withToken($generated['plain_text'])
            ->postJson('/api/v1/players/Notch/ban', ['reason' => 'Test'])
            ->assertStatus(503)
            ->assertJsonPath('error', 'api_disabled');
    }

    public function test_returns_401_for_missing_invalid_and_expired_tokens(): void
    {
        $this->enableApi();

        // Missing token
        $this->postJson('/api/v1/players/Notch/ban', ['reason' => 'No token'])
            ->assertStatus(401)
            ->assertJsonPath('error', 'missing_token');

        // Invalid token
        $this->withToken('cc_pat_invalid_token_value')
            ->postJson('/api/v1/players/Notch/ban', ['reason' => 'Bad token'])
            ->assertStatus(401)
            ->assertJsonPath('error', 'invalid_token');

        // Expired token
        $expired = PlayerApiToken::generateToken(
            'Expired Token',
            PlayerApiToken::ALL_ABILITIES,
            now()->subMinute()
        );

        $this->withToken($expired['plain_text'])
            ->postJson('/api/v1/players/Notch/ban', ['reason' => 'Expired'])
            ->assertStatus(401)
            ->assertJsonPath('error', 'expired_token');
    }

    public function test_enforces_granular_token_abilities(): void
    {
        $this->enableApi();

        $pointsOnly = PlayerApiToken::generateToken('Points Only', [PlayerApiToken::ABILITY_POINTS]);

        $this->withToken($pointsOnly['plain_text'])
            ->postJson('/api/v1/players/Notch/ban', ['reason' => 'Forbidden'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'insufficient_ability');

        $this->withToken($pointsOnly['plain_text'])
            ->patchJson('/api/v1/players/Notch/points', ['action' => 'add', 'amount' => 50])
            ->assertOk()
            ->assertJsonPath('player.points', 200);

        $this->assertNotNull($pointsOnly['token']->fresh()->last_used_at);
    }

    public function test_ban_and_unban_with_reason_and_duration(): void
    {
        $this->enableApi();
        $generated = PlayerApiToken::generateToken('Moderation', [
            PlayerApiToken::ABILITY_BAN,
            PlayerApiToken::ABILITY_UNBAN,
        ]);

        // Ban with reason and duration (resolved via dashed UUID)
        $banResponse = $this->withToken($generated['plain_text'])
            ->postJson('/api/v1/players/069a79f4-44e9-4726-a5be-fca90e38aaf5/ban', [
                'reason' => 'Griefing spawn',
                'duration' => 3600,
            ]);

        $banResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('player.is_banned', true)
            ->assertJsonPath('player.ban_reason', 'Griefing spawn');

        $this->player->refresh();
        $this->assertTrue($this->player->is_banned);
        $this->assertSame('Griefing spawn', $this->player->ban_reason);
        $this->assertNotNull($this->player->banned_until);
        $this->assertTrue($this->player->banned_until->isFuture());

        // Unban (resolved via case-insensitive username)
        $unbanResponse = $this->withToken($generated['plain_text'])
            ->postJson('/api/v1/players/nOtCh/unban');

        $unbanResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('player.is_banned', false)
            ->assertJsonPath('player.ban_reason', null)
            ->assertJsonPath('player.banned_until', null);

        $this->player->refresh();
        $this->assertFalse($this->player->is_banned);
        $this->assertNull($this->player->ban_reason);
        $this->assertNull($this->player->banned_until);
    }

    public function test_update_role_by_name_by_id_and_clear_role(): void
    {
        $this->enableApi();
        $generated = PlayerApiToken::generateToken('Roles', [PlayerApiToken::ABILITY_ROLE]);

        // Assign by role name (case-insensitive) via undashed UUID
        $this->withToken($generated['plain_text'])
            ->patchJson('/api/v1/players/069a79f444e94726a5befca90e38aaf5/role', ['role' => 'vip'])
            ->assertOk()
            ->assertJsonPath('player.role.id', $this->vipRole->id)
            ->assertJsonPath('player.role.name', 'VIP');

        // Assign by role_id
        $this->withToken($generated['plain_text'])
            ->patchJson('/api/v1/players/Notch/role', ['role_id' => $this->defaultRole->id])
            ->assertOk()
            ->assertJsonPath('player.role.id', $this->defaultRole->id);

        // Remove role with role_id: null
        $this->withToken($generated['plain_text'])
            ->patchJson('/api/v1/players/Notch/role', ['role_id' => null])
            ->assertOk()
            ->assertJsonPath('player.role', null);

        $this->assertNull($this->player->fresh()->role_id);

        // Non-existent role returns 422
        $this->withToken($generated['plain_text'])
            ->patchJson('/api/v1/players/Notch/role', ['role' => 'NonExistentRank'])
            ->assertStatus(422);

        // Missing both role and role_id returns 422
        $this->withToken($generated['plain_text'])
            ->patchJson('/api/v1/players/Notch/role', [])
            ->assertStatus(422);
    }

    public function test_update_points_set_add_remove_and_validation(): void
    {
        $this->enableApi();
        $generated = PlayerApiToken::generateToken('Economy', [PlayerApiToken::ABILITY_POINTS]);

        // Set
        $this->withToken($generated['plain_text'])
            ->patchJson('/api/v1/players/Notch/points', ['action' => 'set', 'amount' => 500])
            ->assertOk()
            ->assertJsonPath('player.points', 500);

        // Add
        $this->withToken($generated['plain_text'])
            ->patchJson('/api/v1/players/Notch/points', ['action' => 'add', 'amount' => 250])
            ->assertOk()
            ->assertJsonPath('player.points', 750);

        // Remove (clamps at 0, never negative)
        $this->withToken($generated['plain_text'])
            ->patchJson('/api/v1/players/Notch/points', ['action' => 'remove', 'amount' => 1000])
            ->assertOk()
            ->assertJsonPath('player.points', 0);

        // Validation errors
        $this->withToken($generated['plain_text'])
            ->patchJson('/api/v1/players/Notch/points', ['action' => 'multiply', 'amount' => 10])
            ->assertStatus(422);

        $this->withToken($generated['plain_text'])
            ->patchJson('/api/v1/players/Notch/points', ['action' => 'add', 'amount' => -5])
            ->assertStatus(422);
    }

    public function test_returns_404_when_player_is_not_found(): void
    {
        $this->enableApi();
        $generated = PlayerApiToken::generateToken('Admin', PlayerApiToken::ALL_ABILITIES);

        $this->withToken($generated['plain_text'])
            ->postJson('/api/v1/players/UnknownPlayer999/ban', ['reason' => 'Missing'])
            ->assertStatus(404)
            ->assertJsonPath('error', 'player_not_found');
    }

    public function test_admin_can_toggle_api_create_and_revoke_tokens(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.players.api.toggle'), ['player_api_enabled' => '1'])
            ->assertRedirect(route('admin.players', ['tab' => 'api']));

        $this->assertTrue((bool) PanelOptions::security()->fresh()->player_api_enabled);

        $createResponse = $this->actingAs($admin)
            ->post(route('admin.players.api.tokens.store'), [
                'name' => 'Discord Bot',
                'abilities' => [PlayerApiToken::ABILITY_BAN, PlayerApiToken::ABILITY_POINTS],
            ]);

        $createResponse->assertRedirect(route('admin.players', ['tab' => 'api']));
        $createResponse->assertSessionHas('new_api_token');

        $token = PlayerApiToken::where('name', 'Discord Bot')->firstOrFail();
        $this->assertCount(2, $token->abilities);

        $this->actingAs($admin)
            ->delete(route('admin.players.api.tokens.destroy', $token))
            ->assertRedirect(route('admin.players', ['tab' => 'api']));

        $this->assertDatabaseMissing('player_api_tokens', ['id' => $token->id]);
    }

    public function test_azuriom_exclusive_options_are_hidden_in_microsoft_only_mode(): void
    {
        $admin = User::factory()->create();
        PanelOptions::general()->update(['auth_mode' => 'microsoft']);

        $this->actingAs($admin)
            ->get(route('admin.general'))
            ->assertOk()
            ->assertDontSee('id="email_verified"', false);

        $this->actingAs($admin)
            ->get(route('admin.config'))
            ->assertOk()
            ->assertSee('id="azuriomConfigGroup"', false)
            ->assertSee('panel-muted-surface p-3 mb-4 d-none" id="azuriomConfigGroup"', false);

        // Switch to Azuriom mode -> options become visible
        PanelOptions::general()->update(['auth_mode' => 'azuriom']);

        $this->actingAs($admin)
            ->get(route('admin.general'))
            ->assertOk()
            ->assertSee('id="email_verified"', false);
    }
}
