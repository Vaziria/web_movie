<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Movie;
use App\Models\PlayerReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocaleServersReportsTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();

        return $u;
    }

    // ---- Indonesian / English ------------------------------------------------

    public function test_indonesian_is_default_and_english_lives_under_en(): void
    {
        $movie = Movie::factory()->create(['title' => 'Night Train', 'views' => 1234]);
        $cat = Category::create(['name' => 'Drama', 'slug' => 'drama']);
        $movie->categories()->attach($cat);

        $id = $this->get('/')->assertOk()->assertHeader('Content-Language', 'id')->getContent();
        $this->assertStringContainsString('<html lang="id"', $id);
        $this->assertStringContainsString('Film terbaru', $id);
        $this->assertStringContainsString('1.234 kali ditonton', $id);
        $this->assertStringContainsString('<meta property="og:locale" content="id_ID">', $id);

        $en = $this->get('/en')->assertOk()->assertHeader('Content-Language', 'en')->getContent();
        $this->assertStringContainsString('<html lang="en"', $en);
        $this->assertStringContainsString('Latest movies', $en);
        $this->assertStringContainsString('1,234 views', $en);
        $this->assertStringContainsString('href="'.url('/en/movies/night-train').'"', $en);

        $this->get('/en/category/drama')->assertOk()->assertSee('Drama movies');
        $this->get('/category/drama')->assertOk()->assertSee('Film Drama');
        $this->get('/en/movies/night-train')->assertOk()->assertSee('Rate this movie');
        $this->get('/movies/night-train')->assertOk()->assertSee('Beri rating film ini');
    }

    public function test_hreflang_alternates_and_canonical_per_language(): void
    {
        Movie::factory()->create(['title' => 'Night Train']);

        foreach (['/movies/night-train' => url('/movies/night-train'), '/en/movies/night-train' => url('/en/movies/night-train')] as $path => $canonical) {
            $html = $this->get($path)->getContent();
            $this->assertStringContainsString('<link rel="canonical" href="'.$canonical.'">', $html);
            $this->assertStringContainsString('<link rel="alternate" hreflang="id" href="'.url('/movies/night-train').'">', $html);
            $this->assertStringContainsString('<link rel="alternate" hreflang="en" href="'.url('/en/movies/night-train').'">', $html);
            $this->assertStringContainsString('<link rel="alternate" hreflang="x-default" href="'.url('/movies/night-train').'">', $html);
        }

        // Search pages are noindex and have no alternates.
        $this->assertStringNotContainsString('<link rel="alternate" hreflang', $this->get('/?q=night')->getContent());
    }

    public function test_language_switch_keeps_the_page_and_query(): void
    {
        Movie::factory()->create(['title' => 'Night Train']);

        $html = $this->get('/movies/night-train?server=1')->getContent();
        $this->assertStringContainsString('href="'.url('/en/movies/night-train').'?server=1" hreflang="en"', $html);
        $this->assertStringContainsString('href="'.url('/').'?sort=views" hreflang="id"', $this->get('/en?sort=views')->getContent());
    }

    public function test_rating_from_english_page_redirects_back_to_english(): void
    {
        $movie = Movie::factory()->create();

        $this->post("/en/movies/{$movie->slug}/rate", ['score' => 4])
            ->assertRedirect(url("/en/movies/{$movie->slug}"))
            ->assertSessionHas('status', 'Thanks for rating!');
        $this->post("/movies/{$movie->slug}/rate", ['score' => 9])
            ->assertSessionHasErrors(['score' => 'Pilih rating 1 sampai 5 bintang.']);
    }

    public function test_sitemap_lists_both_languages_with_alternates(): void
    {
        Movie::factory()->create(['title' => 'Night Train']);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>'.url('/movies/night-train').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.url('/en/movies/night-train').'</loc>', $xml);
        $this->assertStringContainsString('<xhtml:link rel="alternate" hreflang="en" href="'.url('/en/movies/night-train').'"/>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    // ---- Backup players ------------------------------------------------------

    public function test_api_accepts_backup_players_and_normalises_them(): void
    {
        Sanctum::actingAs($this->adminUser());

        $this->postJson('/api/movies', [
            'title' => 'Multi',
            'embed_url' => 'https://vidara.to/e/main',
            'backup_embed_urls' => [
                '<iframe src="https://vidara.to/e/b1" width="640" height="360" frameborder="0" allowfullscreen></iframe>',
                'https://vidara.to/v/b2',
                'https://vidara.to/e/main',
            ],
        ])->assertCreated()
            ->assertJsonPath('data.backup_embed_urls', ['https://vidara.to/e/b1', 'https://vidara.to/e/b2']);

        $this->postJson('/api/movies', ['title' => 'Bad', 'embed_url' => 'https://a.b/c', 'backup_embed_urls' => ['http://insecure.example/x']])
            ->assertStatus(422)->assertJsonValidationErrors('backup_embed_urls.0');
        $this->postJson('/api/movies', ['title' => 'Many', 'embed_url' => 'https://a.b/c', 'backup_embed_urls' => array_fill(0, 6, 'https://a.b/d')])
            ->assertStatus(422)->assertJsonValidationErrors('backup_embed_urls');

        $this->patchJson('/api/movies/multi', ['backup_embed_urls' => []])->assertOk()->assertJsonPath('data.backup_embed_urls', []);
    }

    public function test_movie_page_switches_servers(): void
    {
        $movie = Movie::factory()->create(['embed_url' => 'https://vidara.to/e/main', 'backup_embed_urls' => ['https://vidara.to/e/b1']]);

        $this->get("/movies/{$movie->slug}")->assertOk()
            ->assertSee('<iframe src="https://vidara.to/e/main"', false)
            ->assertSee('href="'.url("/movies/{$movie->slug}").'?server=2"', false);
        $this->get("/movies/{$movie->slug}?server=2")->assertSee('<iframe src="https://vidara.to/e/b1"', false);
        $this->get("/movies/{$movie->slug}?server=9")->assertSee('<iframe src="https://vidara.to/e/main"', false);

        $single = Movie::factory()->create();
        $this->get("/movies/{$single->slug}")->assertDontSee('?server=2', false);
    }

    public function test_admin_form_takes_one_backup_per_line(): void
    {
        $this->actingAs($this->adminUser());

        $this->post('/admin/movies', [
            '_admin_form' => '1', 'title' => 'Lines', 'embed_url' => 'https://vidara.to/e/main',
            'backup_embed_urls' => "https://vidara.to/e/b1\r\n\r\n<iframe src=\"https://vidara.to/e/b2\"></iframe>\n",
        ])->assertRedirect('/admin/movies/lines/edit');
        $this->assertSame(['https://vidara.to/e/b1', 'https://vidara.to/e/b2'], Movie::where('slug', 'lines')->first()->backup_embed_urls);

        $this->get('/admin/movies/lines/edit')->assertSee("https://vidara.to/e/b1\nhttps://vidara.to/e/b2", false);

        $this->put('/admin/movies/lines', ['_admin_form' => '1', 'title' => 'Lines', 'embed_url' => 'https://vidara.to/e/main', 'slug' => 'lines', 'backup_embed_urls' => ''])
            ->assertRedirect();
        $this->assertNull(Movie::where('slug', 'lines')->first()->backup_embed_urls);
    }

    // ---- Broken video reports ------------------------------------------------

    public function test_visitor_reports_a_broken_player_once_per_day(): void
    {
        $movie = Movie::factory()->create(['backup_embed_urls' => ['https://vidara.to/e/b1']]);

        $this->post("/movies/{$movie->slug}/report", ['server' => 2, 'reason' => 'wrong_video'])
            ->assertRedirect(url("/movies/{$movie->slug}").'?server=2')
            ->assertSessionHas('status');
        $this->post("/movies/{$movie->slug}/report", ['server' => 2]);
        $this->post("/en/movies/{$movie->slug}/report", ['server' => 1])->assertRedirect(url("/en/movies/{$movie->slug}"));

        $this->assertSame(2, PlayerReport::count());
        $this->assertSame('wrong_video', PlayerReport::where('server', 2)->value('reason'));

        $this->post("/movies/{$movie->slug}/report", ['server' => 3])->assertSessionHasErrors('server');
        $this->post("/movies/{$movie->slug}/report", ['server' => 1, 'reason' => 'nonsense'])->assertSessionHasErrors('reason');
    }

    public function test_admin_sees_and_resolves_reports(): void
    {
        $movie = Movie::factory()->create(['title' => 'Dead Link']);
        PlayerReport::create(['movie_id' => $movie->id, 'server' => 1, 'reason' => 'not_loading', 'ip_hash' => str_repeat('a', 64)]);
        PlayerReport::create(['movie_id' => $movie->id, 'server' => 1, 'reason' => 'not_loading', 'ip_hash' => str_repeat('b', 64)]);

        $this->actingAs($this->adminUser());
        $this->get('/admin/movies')->assertSee('2 broken-video reports');
        $this->get('/admin/reports')->assertOk()->assertSee('Dead Link')->assertSee('2 reports')->assertSee('does not load ×2');

        $this->post('/admin/reports/dead-link/resolve')->assertRedirect('/admin/reports');
        $this->assertSame(0, PlayerReport::open()->count());
        $this->get('/admin/reports')->assertSee('No open reports');
    }

    public function test_reports_api_is_admin_only(): void
    {
        $movie = Movie::factory()->create(['title' => 'Dead Link']);
        PlayerReport::create(['movie_id' => $movie->id, 'server' => 1, 'reason' => 'other', 'ip_hash' => str_repeat('a', 64)]);

        $this->getJson('/api/reports')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/reports')->assertForbidden();

        Sanctum::actingAs($this->adminUser());
        $this->getJson('/api/reports')->assertOk()
            ->assertJsonPath('data.0.movie.slug', 'dead-link')
            ->assertJsonPath('data.0.open_reports', 1);
        $this->postJson('/api/reports/dead-link/resolve')->assertOk()->assertJsonPath('resolved', 1);
        $this->getJson('/api/reports')->assertJsonCount(0, 'data');
    }
}
