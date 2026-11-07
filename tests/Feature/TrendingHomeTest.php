<?php

namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrendingHomeTest extends TestCase
{
    use RefreshDatabase;

    private function views(Movie $movie, int $views, int $daysAgo = 0): void
    {
        DB::table('movie_view_days')->insert(['movie_id' => $movie->id, 'day' => now()->subDays($daysAgo)->toDateString(), 'views' => $views]);
    }

    public function test_opening_a_movie_counts_a_daily_view_once_per_session(): void
    {
        $movie = Movie::factory()->create(['title' => 'Counted']);

        $this->get('/movies/counted')->assertOk();
        $this->get('/en/movies/counted')->assertOk();   // same session: not counted again
        $this->assertSame(1, (int) DB::table('movie_view_days')->where('movie_id', $movie->id)->value('views'));

        $this->flushSession();
        $this->get('/movies/counted');
        $this->assertSame(2, (int) DB::table('movie_view_days')->where('movie_id', $movie->id)->where('day', now()->toDateString())->value('views'));
        $this->assertSame(2, $movie->fresh()->views);
    }

    public function test_trending_sort_uses_the_last_seven_days_only(): void
    {
        $old = Movie::factory()->create(['title' => 'Old Hit', 'views' => 1000]);
        $hot = Movie::factory()->create(['title' => 'Hot Now', 'views' => 40]);
        $warm = Movie::factory()->create(['title' => 'Warm', 'views' => 30]);
        $this->views($old, 900, 8);    // outside the window
        $this->views($old, 2, 1);
        $this->views($hot, 25, 0);
        $this->views($hot, 10, 6);     // first day of the window
        $this->views($warm, 12, 3);

        $res = $this->getJson('/api/movies?sort=trending')->assertOk();
        $this->assertSame(['hot-now', 'warm', 'old-hit'], array_column($res->json('data'), 'slug'));
        $this->assertSame([35, 12, 2], array_column($res->json('data'), 'views_week'));
        $this->assertArrayNotHasKey('views_week', $this->getJson('/api/movies')->json('data.0'));

        $this->assertMatchesRegularExpression('/Hot Now.*Warm.*Old Hit/s', $this->get('/?sort=trending')->assertOk()->getContent());
    }

    public function test_home_shows_trending_and_top_rated_rows(): void
    {
        $a = Movie::factory()->create(['title' => 'Alpha', 'rating_avg' => 3.5, 'rating_count' => 4]);
        $b = Movie::factory()->create(['title' => 'Bravo', 'rating_avg' => 4.9, 'rating_count' => 2]);
        Movie::factory()->create(['title' => 'Charlie']);   // unrated, no views
        $draft = Movie::factory()->create(['title' => 'Draft Star', 'published_at' => null, 'rating_avg' => 5, 'rating_count' => 9]);
        $this->views($a, 50);
        $this->views($b, 7);
        $this->views($draft, 999);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<h1 class="sr-only">Nonton film online di MovieMov</h1>', $html);
        $this->assertStringContainsString('Trending minggu ini', $html);
        $this->assertStringContainsString('Rating tertinggi', $html);
        $this->assertStringContainsString('50 kali ditonton minggu ini', $html);
        $this->assertStringContainsString('href="'.url('/').'?sort=trending"', $html);
        $this->assertStringContainsString('Lihat semua', $html);
        $this->assertStringNotContainsString('Draft Star', $html);

        $trending = $this->section($html, 'row-trending');
        $this->assertMatchesRegularExpression('/#1<\/span>.*Alpha.*#2<\/span>.*Bravo/s', $trending);
        $this->assertStringNotContainsString('Charlie', $trending);
        $this->assertMatchesRegularExpression('/Bravo.*Alpha/s', $this->section($html, 'row-rating'));
        $this->assertStringNotContainsString('Charlie', $this->section($html, 'row-rating'));

        $this->assertStringContainsString('Trending this week', $this->get('/en')->getContent());
    }

    public function test_rows_only_on_the_plain_home_page(): void
    {
        $m = Movie::factory()->create(['title' => 'Solo', 'rating_avg' => 4, 'rating_count' => 1]);
        $this->views($m, 3);

        foreach (['/?sort=views', '/?q=solo', '/?page=2', '/?dir=asc'] as $url) {
            $this->assertStringNotContainsString('row-trending', $this->get($url)->getContent(), $url);
        }

        // No views and no ratings yet: no rows, the plain list keeps a visible h1.
        DB::table('movie_view_days')->delete();
        $m->forceFill(['rating_count' => 0, 'rating_avg' => 0])->save();
        $html = $this->get('/')->getContent();
        $this->assertStringNotContainsString('row-', $html);
        $this->assertStringContainsString('<h1 class="mb-4 text-xl font-semibold text-white md:text-2xl">Film terbaru</h1>', $html);
    }

    public function test_trending_tab_is_listed(): void
    {
        Movie::factory()->create();
        $nav = explode('</nav>', explode('aria-label="Urutkan"', $this->get('/')->getContent())[1])[0];
        $this->assertMatchesRegularExpression('/Terbaru.*Trending.*Rating.*Populer.*Durasi/s', $nav);
    }

    private function section(string $html, string $id): string
    {
        $start = strpos($html, 'aria-labelledby="'.$id.'"');
        $this->assertNotFalse($start, "section {$id} missing");

        return substr($html, $start, strpos($html, '</section>', $start) - $start);
    }
}
