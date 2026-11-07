<?php

namespace Tests\Feature;

use App\Http\Controllers\MovieController;
use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovieWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_lists_movies_and_searches(): void
    {
        Movie::factory()->create(['title' => 'Alpha Wolf']);
        Movie::factory()->create(['title' => 'Beta Ocean']);

        $this->get('/')->assertOk()->assertSee('Alpha Wolf')->assertSee('Beta Ocean');
        $this->get('/?q=wolf&sort=views')->assertOk()->assertSee('Alpha Wolf')->assertDontSee('Beta Ocean');
        $this->get('/?sort=bogus')->assertOk();
    }

    public function test_show_embeds_player_and_counts_one_view_per_session(): void
    {
        $movie = Movie::factory()->create(['embed_url' => 'https://vidara.to/e/8SdwOuKAAk92t']);

        $this->get("/movies/{$movie->slug}")->assertOk()
            ->assertSee('<iframe src="https://vidara.to/e/8SdwOuKAAk92t"', false)
            ->assertDontSee('sandbox=', false);
        $this->get("/movies/{$movie->slug}")->assertOk();

        $this->assertSame(1, $movie->fresh()->views);

        config(['movies.embed_sandbox' => true]);
        $this->get("/movies/{$movie->slug}")->assertSee('sandbox=', false);
    }

    public function test_vidara_watch_url_is_rewritten_to_embed_url(): void
    {
        $movie = Movie::factory()->create(['embed_url' => 'https://vidara.to/v/8SdwOuKAAk92t']);
        $this->assertSame('https://vidara.to/e/8SdwOuKAAk92t', $movie->embed_url);

        $other = Movie::factory()->create(['embed_url' => 'https://example.com/v/abc']);
        $this->assertSame('https://example.com/v/abc', $other->embed_url);
    }

    public function test_anonymous_rating_is_one_per_browser_and_updatable(): void
    {
        $movie = Movie::factory()->create();

        $res = $this->post("/movies/{$movie->slug}/rate", ['score' => 4])->assertRedirect();
        $res->assertCookie(MovieController::VOTER_COOKIE);
        $voter = $res->getCookie(MovieController::VOTER_COOKIE, false)->getValue();

        $this->withCookie(MovieController::VOTER_COOKIE, $voter)
            ->post("/movies/{$movie->slug}/rate", ['score' => 2])->assertRedirect();

        $movie->refresh();
        $this->assertSame(1, $movie->rating_count);
        $this->assertSame(2.0, $movie->rating_avg);
    }

    public function test_same_ip_new_browser_updates_instead_of_adding(): void
    {
        $movie = Movie::factory()->create();

        $this->post("/movies/{$movie->slug}/rate", ['score' => 5]);
        $this->flushSession();
        $this->withCookie(MovieController::VOTER_COOKIE, str_repeat('b', 40))
            ->post("/movies/{$movie->slug}/rate", ['score' => 3]);

        $movie->refresh();
        $this->assertSame(1, $movie->rating_count);
        $this->assertSame(3.0, $movie->rating_avg);
    }

    public function test_different_voters_are_averaged(): void
    {
        $movie = Movie::factory()->create();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->post("/movies/{$movie->slug}/rate", ['score' => 5]);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->withCookie(MovieController::VOTER_COOKIE, str_repeat('c', 40))
            ->post("/movies/{$movie->slug}/rate", ['score' => 2]);

        $movie->refresh();
        $this->assertSame(2, $movie->rating_count);
        $this->assertSame(3.5, $movie->rating_avg);
    }

    public function test_rating_score_is_validated(): void
    {
        $movie = Movie::factory()->create();

        $this->post("/movies/{$movie->slug}/rate", ['score' => 9])->assertSessionHasErrors('score');
        $this->assertSame(0, $movie->fresh()->rating_count);
    }
}
