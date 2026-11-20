<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PanelOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LoaderVersionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
        PanelOptions::ensureDefaults();
        $this->admin = User::factory()->create();
    }

    public function test_fetches_and_filters_neoforge_versions_for_mc_version(): void
    {
        Http::fake([
            'https://maven.neoforged.net/api/maven/versions/releases/net/neoforged/neoforge' => Http::response([
                'isSnapshot' => false,
                'versions' => [
                    '20.4.190',
                    '21.1.1',
                    '21.1.72',
                    '21.1.12',
                    '21.0.5',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.loader.neoforge-versions', ['mc_version' => '1.21.1']));

        $response->assertOk()
            ->assertExactJson([
                'builds' => ['21.1.72', '21.1.12', '21.1.1'],
                'versions' => ['21.1.72', '21.1.12', '21.1.1'],
            ]);
    }

    public function test_fetches_neoforge_legacy_forge_versions_for_mc_1_20_1(): void
    {
        Http::fake([
            'https://maven.neoforged.net/api/maven/versions/releases/net/neoforged/neoforge' => Http::response([
                'isSnapshot' => false,
                'versions' => ['20.2.3-beta', '21.1.10'],
            ], 200),
            'https://maven.neoforged.net/api/maven/versions/releases/net/neoforged/forge' => Http::response([
                'isSnapshot' => false,
                'versions' => [
                    '1.20.1-47.1.76',
                    '1.20.1-47.1.106',
                    '1.20.1-47.1.99',
                    '1.20.2-48.0.1',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.loader.neoforge-versions', ['mc_version' => '1.20.1']));

        $response->assertOk()
            ->assertExactJson([
                'builds' => ['1.20.1-47.1.106', '1.20.1-47.1.99', '1.20.1-47.1.76'],
                'versions' => ['1.20.1-47.1.106', '1.20.1-47.1.99', '1.20.1-47.1.76'],
            ]);
    }

    public function test_fetches_quilt_versions_for_mc_version(): void
    {
        Http::fake([
            'https://meta.quiltmc.org/v3/versions/loader/1.20.1' => Http::response([
                ['loader' => ['version' => '0.26.4', 'build' => 10]],
                ['loader' => ['version' => '0.26.3', 'build' => 9]],
                ['loader' => ['version' => '0.26.4', 'build' => 10]],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.loader.quilt-versions', ['mc_version' => '1.20.1']));

        $response->assertOk()
            ->assertExactJson([
                'builds' => ['0.26.4', '0.26.3'],
                'versions' => ['0.26.4', '0.26.3'],
            ]);
    }

    public function test_fetches_legacyfabric_versions_for_mc_version(): void
    {
        Http::fake([
            'https://meta.legacyfabric.net/v2/versions/loader/1.8.9' => Http::response([
                ['loader' => ['version' => '0.16.5']],
                ['loader' => ['version' => '0.15.11']],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.loader.legacyfabric-versions', ['mc_version' => '1.8.9']));

        $response->assertOk()
            ->assertExactJson([
                'builds' => ['0.16.5', '0.15.11'],
                'versions' => ['0.16.5', '0.15.11'],
            ]);
    }

    public function test_returns_empty_list_gracefully_on_upstream_error(): void
    {
        Http::fake([
            'https://meta.quiltmc.org/v3/versions/loader/1.21.1' => Http::response([], 500),
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.loader.quilt-versions', ['mc_version' => '1.21.1']))
            ->assertOk()
            ->assertExactJson([
                'builds' => [],
                'versions' => [],
            ]);
    }

    public function test_requires_mc_version_query_parameter(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.loader.neoforge-versions'))
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->getJson(route('admin.loader.quilt-versions'))
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->getJson(route('admin.loader.legacyfabric-versions'))
            ->assertStatus(422);
    }
}
