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
        $blocks = collect($m[1])->map(fn ($j) => json_decode($j, true));
        $this->assertSame(['Movie', 'VideoObject', 'BreadcrumbList'], $blocks->pluck('@type')->all());

        // The video is its own top-level item (Google video results), linked from the Movie by @id.
        [$schema, $video] = [$blocks[0], $blocks[1]];
        $this->assertSame(['@id' => url('/movies/night-train').'#video'], $schema['video']);
        $this->assertSame('https://schema.org', $video['@context']);
        $this->assertSame(url('/movies/night-train').'#video', $video['@id']);
        $this->assertSame('Night Train', $video['name']);
        $this->assertSame('A long ride.', $video['description']);
        $this->assertSame('https://img.example.com/n.jpg', $video['thumbnailUrl']);
        $this->assertSame($movie->embed_url, $video['embedUrl']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d[+-]\d\d:\d\d$/', $video['uploadDate']);
    }

    public function test_video_schema_always_has_googles_required_fields(): void
    {
        // No thumbnail, no synopsis, no duration: name, description, thumbnailUrl and uploadDate must still be set.
        $movie = Movie::factory()->create(['title' => 'Bare Film', 'synopsis' => null, 'thumbnail_url' => null, 'duration_minutes' => null]);

        preg_match_all('~<script type="application/ld\+json">(.+?)</script>~s', $this->get('/movies/'.$movie->slug)->getContent(), $m);
        $video = collect($m[1])->map(fn ($j) => json_decode($j, true))->firstWhere('@type', 'VideoObject');

        $this->assertSame('Bare Film', $video['name']);
        $this->assertSame('Nonton Bare Film online di '.config('app.name').'.', $video['description']);
        $this->assertSame(asset('og-default.png'), $video['thumbnailUrl']);
        $this->assertNotEmpty($video['uploadDate']);
        $this->assertArrayNotHasKey('duration', $video);

        // English page: same video, English description.
        preg_match_all('~<script type="application/ld\+json">(.+?)</script>~s', $this->get('/en/movies/'.$movie->slug)->getContent(), $m);
        $en = collect($m[1])->map(fn ($j) => json_decode($j, true))->firstWhere('@type', 'VideoObject');
        $this->assertSame(url('/en/movies/'.$movie->slug).'#video', $en['@id']);
        $this->assertSame('en', $en['inLanguage']);
    }

    public function test_search_pages_are_noindex_and_sorted_lists_canonicalise(): void
    {
        $this->get('/?q=abc')->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->get('/?sort=views')->assertOk()
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<link rel="canonical" href="'.url('/').'">', false);
    }
}
