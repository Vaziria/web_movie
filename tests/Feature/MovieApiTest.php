<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MovieApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['password' => 'secret-pass']);
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_admin_can_log_in_and_get_a_token(): void
    {
        $admin = $this->admin();

        $res = $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'secret-pass'])
            ->assertOk()->assertJsonStructure(['token', 'token_type']);

        $this->withToken($res->json('token'))->getJson('/api/me')
            ->assertOk()->assertJsonPath('email', $admin->email);
    }

    public function test_non_admin_and_wrong_password_cannot_log_in(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret-pass'])->assertStatus(422);

        $admin = $this->admin();
        $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'wrong'])->assertStatus(422);
    }

    public function test_admin_can_create_a_movie(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/movies', [
            'title' => 'Test Movie',
            'embed_url' => 'https://vidara.to/v/8SdwOuKAAk92t',
            'synopsis' => 'A test.',
            'release_year' => 2024,
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'test-movie')
            ->assertJsonPath('data.views', 0)
            ->assertJsonPath('data.rating_count', 0);

        $this->assertDatabaseHas('movies', ['title' => 'Test Movie']);
    }

    public function test_duplicate_titles_get_unique_slugs(): void
    {
        Sanctum::actingAs($this->admin());
        $payload = ['title' => 'Same', 'embed_url' => 'https://example.com/e/1'];

        $this->postJson('/api/movies', $payload)->assertJsonPath('data.slug', 'same');
        $this->postJson('/api/movies', $payload)->assertJsonPath('data.slug', 'same-2');
    }

    public function test_create_requires_auth_and_admin(): void
    {
        $payload = ['title' => 'X', 'embed_url' => 'https://example.com/e/1'];
        $this->postJson('/api/movies', $payload)->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/movies', $payload)->assertForbidden();
    }

    public function test_embed_url_must_be_https(): void
    {
        Sanctum::actingAs($this->admin());

        foreach (['http://example.com/e/1', 'javascript:alert(1)', 'not a url'] as $bad) {
            $this->postJson('/api/movies', ['title' => 'X', 'embed_url' => $bad])
                ->assertStatus(422)->assertJsonValidationErrors('embed_url');
        }
    }

    public function test_admin_can_update_and_delete(): void
    {
        Sanctum::actingAs($this->admin());
        $movie = Movie::factory()->create();

        $this->patchJson("/api/movies/{$movie->slug}", ['title' => 'Renamed'])
            ->assertOk()->assertJsonPath('data.title', 'Renamed');

        $this->deleteJson("/api/movies/{$movie->slug}")->assertNoContent();
        $this->assertDatabaseMissing('movies', ['id' => $movie->id]);
    }

    public function test_list_sorts_and_searches(): void
    {
        $a = Movie::factory()->create(['title' => 'Alpha Wolf', 'views' => 10, 'rating_avg' => 2.0]);
        $b = Movie::factory()->create(['title' => 'Beta Ocean', 'views' => 99, 'rating_avg' => 4.5]);
        $c = Movie::factory()->create(['title' => 'Gamma Wolf', 'views' => 50, 'rating_avg' => 3.0]);
        $a->forceFill(['created_at' => now()->subDays(3)])->save();
        $b->forceFill(['created_at' => now()->subDays(2)])->save();
        $c->forceFill(['created_at' => now()->subDay()])->save();

        $ids = fn (string $qs) => collect($this->getJson('/api/movies'.$qs)->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$b->id, $c->id, $a->id], $ids('?sort=views'));
        $this->assertSame([$b->id, $c->id, $a->id], $ids('?sort=rating'));
        $this->assertSame([$c->id, $b->id, $a->id], $ids('?sort=created'));
        $this->assertSame([$c->id, $a->id], $ids('?q=wolf'));
        $this->getJson('/api/movies?sort=bogus')->assertStatus(422);
    }
}
