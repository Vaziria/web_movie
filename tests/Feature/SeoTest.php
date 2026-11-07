<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_lists_home_categories_and_movies(): void
    {
        $cat = Category::create(['name' => 'Action', 'slug' => 'action']);
        $movie = Movie::factory()->create(['title' => 'Big Bang']);
        $movie->categories()->attach($cat);
        Category::create(['name' => 'Empty', 'slug' => 'empty']);

        $res = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($res->getContent());
        $this->assertNotFalse($xml, 'sitemap is valid XML');

        $locs = [];
        foreach ($xml->url as $u) {
            $locs[] = (string) $u->loc;
        }
        $this->assertContains(url('/'), $locs);
        $this->assertContains(url('/category/action'), $locs);
        $this->assertContains(url('/movies/big-bang'), $locs);
        $this->assertNotContains(url('/category/empty'), $locs);
    }

    public function test_robots_points_to_sitemap(): void
    {
        $this->get('/robots.txt')->assertOk()
            ->assertSee('Sitemap: '.url('/sitemap.xml'))
            ->assertSee('Disallow: /api/');
    }

    public function test_movie_page_has_meta_og_and_json_ld(): void
    {
        $movie = Movie::factory()->create([
            'title' => 'Night Train', 'synopsis' => 'A long ride.', 'release_year' => 2024,
            'thumbnail_url' => 'https://img.example.com/n.jpg',
        ]);

        $html = $this->get('/movies/'.$movie->slug)->assertOk()->getContent();

        $this->assertStringContainsString('<title>Night Train (2024) - Nonton Online | '.config('app.name').'</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="A long ride.">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/movies/night-train').'">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="https://img.example.com/n.jpg">', $html);

        preg_match_all('~<script type="application/ld\+json">(.+?)</script>~s', $html, $m);
        $types = collect($m[1])->map(fn ($j) => json_decode($j, true)['@type'] ?? null)->all();
        $this->assertSame(['Movie', 'BreadcrumbList'], $types);
        $schema = json_decode($m[1][0], true);
        $this->assertSame('VideoObject', $schema['video']['@type']);
        $this->assertSame($movie->embed_url, $schema['video']['embedUrl']);
    }

    public function test_search_pages_are_noindex_and_sorted_lists_canonicalise(): void
    {
        $this->get('/?q=abc')->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->get('/?sort=views')->assertOk()
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<link rel="canonical" href="'.url('/').'">', false);
    }
}
