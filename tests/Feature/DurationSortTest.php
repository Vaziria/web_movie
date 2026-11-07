<?php

namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DurationSortTest extends TestCase
{
    use RefreshDatabase;

    private function seedMovies(): void
    {
        Movie::factory()->create(['title' => 'Short', 'duration_minutes' => 45, 'views' => 300, 'rating_avg' => 2.5]);
        Movie::factory()->create(['title' => 'Epic', 'duration_minutes' => 180, 'views' => 10, 'rating_avg' => 4.8]);
        Movie::factory()->create(['title' => 'Unknown', 'duration_minutes' => null, 'views' => 50, 'rating_avg' => 0]);
        Movie::factory()->create(['title' => 'Medium', 'duration_minutes' => 105, 'views' => 99, 'rating_avg' => 3.9]);
    }

    private function titles(string $query): array
    {
        return array_column($this->getJson('/api/movies'.$query)->assertOk()->json('data'), 'title');
    }

    public function test_api_sorts_by_duration_both_ways_with_unknown_last(): void
    {
        $this->seedMovies();

        $this->assertSame(['Epic', 'Medium', 'Short', 'Unknown'], $this->titles('?sort=duration'));
        $this->assertSame(['Short', 'Medium', 'Epic', 'Unknown'], $this->titles('?sort=duration&dir=asc'));
    }

    public function test_api_sorts_by_rating_and_views_both_ways(): void
    {
        $this->seedMovies();

        $this->assertSame(['Epic', 'Medium', 'Short', 'Unknown'], $this->titles('?sort=rating'));
        $this->assertSame(['Unknown', 'Short', 'Medium', 'Epic'], $this->titles('?sort=rating&dir=asc'));
        $this->assertSame(['Short', 'Medium', 'Unknown', 'Epic'], $this->titles('?sort=views'));
        $this->assertSame(['Epic', 'Unknown', 'Medium', 'Short'], $this->titles('?sort=views&dir=asc'));

        $this->getJson('/api/movies?dir=sideways')->assertStatus(422)->assertJsonValidationErrors('dir');
        $this->getJson('/api/movies?sort=length')->assertStatus(422)->assertJsonValidationErrors('sort');
    }

    public function test_web_duration_tab_toggles_direction_and_paginates_with_it(): void
    {
        $this->seedMovies();

        $html = $this->get('/?sort=duration')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Epic.*Medium.*Short.*Unknown/s', $html);
        // Active tab links to the reversed order; inactive tabs to their default.
        $this->assertStringContainsString('href="'.url('/').'?sort=duration&amp;dir=asc"', $html);
        $this->assertStringContainsString('href="'.url('/').'?sort=rating"', $html);
        $this->assertStringContainsString('Durasi', $html);

        $asc = $this->get('/?sort=duration&dir=asc')->getContent();
        $this->assertMatchesRegularExpression('/Short.*Medium.*Epic.*Unknown/s', $asc);
        $this->assertStringContainsString('href="'.url('/').'?sort=duration"', $asc);

        $this->assertStringContainsString('Duration', $this->get('/en?sort=duration')->getContent());

        Movie::factory()->count(30)->create(['duration_minutes' => 90]);
        $this->assertStringContainsString('sort=duration&amp;dir=asc&amp;page=2', $this->get('/?sort=duration&dir=asc')->getContent());
    }

    public function test_duration_is_shown_in_hours_and_minutes_per_language(): void
    {
        $movie = Movie::factory()->create(['title' => 'Medium', 'duration_minutes' => 105]);
        Movie::factory()->create(['title' => 'Hour', 'duration_minutes' => 60]);

        $this->get('/')->assertSee('1j 45m')->assertSee('1j');
        $this->get('/en')->assertSee('1h 45m');
        $this->get("/movies/{$movie->slug}")->assertSee('1 jam 45 menit');
        $this->get("/en/movies/{$movie->slug}")->assertSee('1 hour 45 minutes');
        $this->get('/en/movies/hour')->assertSee('1 hour')->assertDontSee('0 minutes');
    }
}
