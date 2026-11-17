<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\User;
use App\Support\PanelOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
    }

    public function test_admin_news_crud_with_cover_image_and_views(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['name' => 'CentralAdmin']);
        PanelOptions::ensureDefaults();

        $this->actingAs($admin)->get(route('admin.news'))->assertOk();
        $this->actingAs($admin)->get(route('admin.news.create'))->assertOk();

        $cover = UploadedFile::fake()->image('season4.jpg', 1280, 720);

        $this->actingAs($admin)->post(route('admin.news.store'), [
            'title' => 'Ouverture de la Saison 4 !',
            'content' => '<p>Bienvenue sur la <strong>Saison 4</strong> de CentralCorp !</p>',
            'author' => 'Riptiaz',
            'image' => $cover,
            'badge' => 'Mise à jour',
            'external_url' => 'https://centralcorp.github.io/saison-4',
            'is_pinned' => 1,
            'is_published' => 1,
            'published_at' => '2026-11-17 18:00:00',
        ])->assertRedirect(route('admin.news'));

        $article = News::firstOrFail();
        $this->assertSame('ouverture-de-la-saison-4', $article->slug);
        $this->assertNotNull($article->image_path);
        Storage::disk('public')->assertExists($article->image_path);

        $this->actingAs($admin)->get(route('admin.news.edit', $article))->assertOk();

        $this->actingAs($admin)->put(route('admin.news.update', $article), [
            'title' => 'Saison 4 Maintenant Ouverte',
            'slug' => 'saison-4-ouverte',
            'content' => '<p>Contenu mis à jour</p>',
            'author' => 'Riptiaz',
            'badge' => 'Annonce',
            'is_pinned' => 1,
            'is_published' => 1,
        ])->assertRedirect(route('admin.news'));

        $this->assertSame('Saison 4 Maintenant Ouverte', $article->fresh()->title);
        $this->assertSame('saison-4-ouverte', $article->fresh()->slug);

        $oldImagePath = $article->fresh()->image_path;
        $this->actingAs($admin)->delete(route('admin.news.destroy', $article))
            ->assertRedirect(route('admin.news'));

        $this->assertDatabaseMissing('news', ['id' => $article->id]);
        Storage::disk('public')->assertMissing($oldImagePath);
    }

    public function test_public_news_endpoint_contract_ordering_draft_filtering_and_cache_flush(): void
    {
        $olderRegular = News::create([
            'title' => 'Older Patch',
            'slug' => 'older-patch',
            'content' => '<p>Patch 1.0</p>',
            'author' => 'Admin',
            'badge' => 'Patchnote',
            'is_pinned' => false,
            'is_published' => true,
            'published_at' => now()->subDays(5),
        ]);

        $newerRegular = News::create([
            'title' => 'Newer Event',
            'slug' => 'newer-event',
            'content' => '<p>Tournoi PvP</p>',
            'author' => 'Staff',
            'image_url' => 'https://example.com/banner.png',
            'badge' => 'Événement',
            'external_url' => 'https://example.com/event',
            'is_pinned' => false,
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $pinnedOlder = News::create([
            'title' => 'Pinned Welcome',
            'slug' => 'pinned-welcome',
            'content' => '<p>Pinned info</p>',
            'author' => 'Founder',
            'badge' => 'Important',
            'is_pinned' => true,
            'is_published' => true,
            'published_at' => now()->subDays(10),
        ]);

        News::create([
            'title' => 'Secret Draft',
            'slug' => 'secret-draft',
            'content' => '<p>Not ready yet</p>',
            'author' => 'Admin',
            'is_pinned' => true,
            'is_published' => false,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/utils/news');
        $response->assertOk()
            ->assertJsonCount(3)
            ->assertJsonPath('0.id', $pinnedOlder->id)
            ->assertJsonPath('0.is_pinned', true)
            ->assertJsonPath('1.id', $newerRegular->id)
            ->assertJsonPath('1.image', 'https://example.com/banner.png')
            ->assertJsonPath('1.external_url', 'https://example.com/event')
            ->assertJsonPath('2.id', $olderRegular->id)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'title',
                    'content',
                    'author',
                    'publish_date',
                    'image',
                    'badge',
                    'is_pinned',
                    'external_url',
                ],
            ]);

        // Unpublish pinned article -> cache flushes automatically via FlushesLauncherCache!
        $pinnedOlder->update(['is_published' => false]);

        $this->getJson('/utils/news')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $newerRegular->id);
    }
}
