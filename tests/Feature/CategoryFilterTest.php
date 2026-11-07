<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    private function seedMovies(): void
    {
        $drama = Category::create(['name' => 'Drama', 'slug' => 'drama']);
        $action = Category::create(['name' => 'Action', 'slug' => 'action']);

        Movie::factory()->create(['title' => 'Long Drama', 'duration_minutes' => 150])->categories()->attach($drama);
        Movie::factory()->create(['title' => 'Short Drama', 'duration_minutes' => 80])->categories()->attach($drama);
        Movie::factory()->create(['title' => 'Night Chase', 'duration_minutes' => 100])->categories()->attach($action);
        Movie::factory()->create(['title' => 'Loose Film', 'duration_minutes' => 90]);
    }

    public function test_category_chips_keep_search_and_sort(): void
    {
        $this->seedMovies();

        $html = $this->get('/?sort=duration&dir=asc')->getContent();
        $this->assertMatchesRegularExpression('~href="'.preg_quote(url('/category/drama'), '~').'\?sort=duration&amp;dir=asc"\s+rel="nofollow"~', $html);

        // Plain chips (no search/sort) stay crawlable links.
        $chips = fn (string $page) => explode('aria-label="Urutkan"', $this->get($page)->getContent())[0];
        $this->assertStringContainsString('href="'.url('/category/action').'"', $chips('/category/drama'));
        $this->assertStringNotContainsString('rel="nofollow"', $chips('/category/drama'));
        $this->assertStringNotContainsString('rel="nofollow"', $chips('/'));

        $this->assertStringContainsString('href="'.url('/en/category/action').'?q=night"', $this->get('/en?q=night')->getContent());
        $this->assertStringContainsString('href="'.url('/').'?sort=views"', $this->get('/category/drama?sort=views')->getContent());
    }

    public function test_category_plus_sort_and_direction(): void
    {
        $this->seedMovies();

        $this->assertMatchesRegularExpression('/Short Drama.*Long Drama/s', $this->get('/category/drama?sort=duration&dir=asc')->getContent());
        $html = $this->get('/category/drama?sort=duration')->getContent();
        $this->assertMatchesRegularExpression('/Long Drama.*Short Drama/s', $html);
        $this->assertStringNotContainsString('Night Chase', $html);

        $titles = array_column($this->getJson('/api/movies?category=drama&sort=duration&dir=asc')->json('data'), 'title');
        $this->assertSame(['Short Drama', 'Long Drama'], $titles);
    }

    public function test_search_on_a_category_page_stays_in_the_category(): void
    {
        $this->seedMovies();

        $html = $this->get('/category/drama?sort=duration')->getContent();
        $this->assertStringContainsString('action="'.url('/category/drama').'" role="search"', $html);
        $this->assertStringContainsString('<input type="hidden" name="sort" value="duration">', $html);
        $this->assertStringContainsString('placeholder="Cari di Drama"', $html);
        $this->assertStringContainsString('placeholder="Search in Drama"', $this->get('/en/category/drama')->getContent());

        $this->get('/category/drama?q=short')->assertSee('Short Drama')->assertDontSee('Long Drama');

        // Nothing in this category: offer the same search in all categories.
        $this->get('/category/drama?q=chase')->assertSee('Film tidak ditemukan')
            ->assertSee('href="'.url('/').'?q=chase"', false)
            ->assertSee('Cari “chase” di semua kategori');

        // Movie pages and the home page search everything.
        $this->assertStringContainsString('action="'.url('/').'" role="search"', $this->get('/movies/long-drama')->getContent());
        $this->assertStringContainsString('placeholder="Cari film"', $this->get('/')->getContent());
    }

    public function test_admin_filters_movies_by_category(): void
    {
        $this->seedMovies();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);

        $this->get('/admin/movies')->assertSee('Drama (2)')->assertSee('Action (1)')->assertSee('Loose Film');
        $this->get('/admin/movies?category=drama')->assertSee('Long Drama')->assertSee('Short Drama')->assertDontSee('Night Chase')->assertDontSee('Loose Film');
        $this->get('/admin/movies?category=none')->assertSee('Loose Film')->assertDontSee('Long Drama');
        $this->get('/admin/movies?category=drama&q=short')->assertSee('Short Drama')->assertDontSee('Long Drama');
        $this->get('/admin/movies?category=action&q=drama')->assertSee('No movies match this filter');
    }
}
