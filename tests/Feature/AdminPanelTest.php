<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['password' => 'secret-pass']);
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_guests_are_sent_to_login_and_non_admins_are_rejected(): void
    {
        $this->get('/admin/movies')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('noindex, nofollow', false);

        $user = User::factory()->create(['password' => 'secret-pass']);
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'secret-pass'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($user)->get('/admin/movies')->assertForbidden();
    }

    public function test_admin_logs_in_and_out(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'secret-pass'])->assertRedirect('/admin/movies');
        $this->assertAuthenticatedAs($admin);

        $this->get('/admin/movies')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_create_edit_and_delete_movie_from_the_panel(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/movies', [
            '_admin_form' => '1',
            'title' => 'Panel Movie',
            'embed_url' => '<iframe src="https://vidara.to/e/8SdwOuKAAk92t" width="640" height="360" frameborder="0" allowfullscreen></iframe>',
            'categories' => 'Action, Drama',
            'thumbnail_url' => '',
            'synopsis' => 'From the admin form.',
            'release_year' => '2026',
            'duration_minutes' => '',
            'slug' => '',
            'thumbnail' => UploadedFile::fake()->image('t.jpg', 640, 360),
        ])->assertRedirect('/admin/movies/panel-movie/edit');

        $movie = Movie::where('slug', 'panel-movie')->firstOrFail();
        $this->assertSame('https://vidara.to/e/8SdwOuKAAk92t', $movie->embed_url);
        $this->assertSame(['Action', 'Drama'], $movie->categories->pluck('name')->all());
        $file = public_path(ltrim($movie->thumbnail_url, '/'));
        $this->assertFileExists($file);

        $this->get('/admin/movies/panel-movie/edit')->assertOk()->assertSee('Panel Movie')->assertSee('Action, Drama');

        // Saving with an empty URL box keeps the uploaded thumbnail.
        $this->put('/admin/movies/panel-movie', [
            '_admin_form' => '1', 'title' => 'Panel Movie 2', 'embed_url' => $movie->embed_url,
            'categories' => 'Drama', 'thumbnail_url' => '', 'slug' => 'panel-movie',
        ])->assertRedirect('/admin/movies/panel-movie/edit');
        $movie->refresh();
        $this->assertSame('Panel Movie 2', $movie->title);
        $this->assertSame(['Drama'], $movie->categories->pluck('name')->all());
        $this->assertFileExists($file);

        // Explicit removal deletes the file.
        $this->put('/admin/movies/panel-movie', [
            '_admin_form' => '1', 'title' => 'Panel Movie 2', 'embed_url' => $movie->embed_url,
            'categories' => 'Drama', 'thumbnail_url' => '', 'remove_thumbnail' => '1', 'slug' => 'panel-movie',
        ])->assertRedirect();
        $this->assertNull($movie->fresh()->thumbnail_url);
        $this->assertFileDoesNotExist($file);

        $this->get('/admin/movies?q=Panel')->assertOk()->assertSee('Panel Movie 2');

        $this->delete('/admin/movies/panel-movie')->assertRedirect('/admin/movies');
        $this->assertDatabaseMissing('movies', ['slug' => 'panel-movie']);
    }

    public function test_validation_errors_are_shown_on_the_form(): void
    {
        $this->actingAs($this->admin());

        $this->from('/admin/movies/create')
            ->post('/admin/movies', ['_admin_form' => '1', 'title' => '', 'embed_url' => 'http://insecure.example'])
            ->assertRedirect('/admin/movies/create')
            ->assertSessionHasErrors(['title', 'embed_url']);
    }

    public function test_manage_categories_from_the_panel(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/categories', ['name' => 'Science Fiction', 'slug' => '', 'description' => 'Space.'])
            ->assertRedirect('/admin/categories');
        $this->assertDatabaseHas('categories', ['slug' => 'science-fiction', 'description' => 'Space.']);

        $this->get('/admin/categories')->assertOk()->assertSee('Science Fiction');
        $this->get('/admin/categories/science-fiction/edit')->assertOk();

        $this->put('/admin/categories/science-fiction', ['name' => 'Sci-Fi', 'slug' => 'sci-fi', 'description' => ''])
            ->assertRedirect('/admin/categories');
        $this->assertDatabaseHas('categories', ['slug' => 'sci-fi', 'name' => 'Sci-Fi', 'description' => null]);

        $this->delete('/admin/categories/sci-fi')->assertRedirect('/admin/categories');
        $this->assertSame(0, Category::count());
    }

    public function test_movie_page_shows_related_movies_and_robots_blocks_admin(): void
    {
        $action = Category::create(['name' => 'Action', 'slug' => 'action']);
        $main = Movie::factory()->create(['title' => 'Main Feature']);
        $main->categories()->attach($action);
        $same = Movie::factory()->create(['title' => 'Same Category Pick']);
        $same->categories()->attach($action);
        Movie::factory()->create(['title' => 'Filler Newest']);

        $html = $this->get('/movies/main-feature')->assertOk()->assertSee('More like this')->getContent();
        $this->assertStringContainsString('Same Category Pick', $html);
        $this->assertStringContainsString('Filler Newest', $html);
        $this->assertLessThan(strpos($html, 'Filler Newest'), strpos($html, 'Same Category Pick'));

        $this->get('/robots.txt')->assertSee('Disallow: /admin');
    }
}
