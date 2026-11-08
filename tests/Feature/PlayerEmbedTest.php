<?php

namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerEmbedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Embed hosts redirect the player to rotating mirror domains (vidara.to -> as9u7bgh9.com). A bare
     * allow="fullscreen" only covers the src domain, so the player's fullscreen button silently failed
     * ("Disallowed by permissions policy"). Every permission must use `*`.
     */
    public function test_player_iframe_allows_fullscreen_on_any_domain_the_host_redirects_to(): void
    {
        $movie = Movie::factory()->create(['embed_url' => 'https://vidara.to/e/8SdwOuKAAk92t']);

        $html = $this->get('/movies/'.$movie->slug)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<iframe src="https://vidara\.to/e/8SdwOuKAAk92t"~', $html);
        $this->assertMatchesRegularExpression('~allow="fullscreen \*; encrypted-media \*; picture-in-picture \*; screen-wake-lock \*"\s+allowfullscreen~', $html);
        $this->assertDoesNotMatchRegularExpression('~allow="[^"]*\bfullscreen(;|")~', $html);
    }
}
