<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Movie;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();

        return $u;
    }

    /** A: korea+zombie (Drama), B: korea, C: zombie, D: korea (draft). */
    private function seedMovies(): void
    {
        $drama = Category::create(['name' => 'Drama', 'slug' => 'drama']);
        $korea = Tag::create(['name' => 'Korea', 'slug' => 'korea']);
        $zombie = Tag::create(['name' => 'Zombie', 'slug' => 'zombie']);

        $a = Movie::factory()->create(['title' => 'Alpha Train', 'duration_minutes' => 120]);
        $a->tags()->attach([$korea->id, $zombie->id]);
        $a->categories()->attach($drama);
        Movie::factory()->create(['title' => 'Bravo Seoul', 'duration_minutes' => 90])->tags()->attach($korea);
        Movie::factory()->create(['title' => 'Charlie Dead', 'duration_minutes' => 100])->tags()->attach($zombie);
        Movie::factory()->create(['title' => 'Delta Draft', 'published_at' => null])->tags()->attach($korea);
    }

    /** A link to $href followed (after any whitespace) by $after, e.g. rel="nofollow". */
    private function assertLink(string $html, string $href, string $after = 'rel="nofollow"'): void
    {
        $this->assertMatchesRegularExpression('~href="'.preg_quote($href, '~').'"\s+'.preg_quote($after, '~').'~', $html);
    }

    private function slugs(string $query): array
    {
        return array_column($this->getJson('/api/movies'.$query)->assertOk()->json('data'), 'slug');
    }

    // ---- posting ------------------------------------------------------------------

    public function test_api_posts_tags_as_json_or_comma_list_and_replaces_them_on_update(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/movies', ['title' => 'Night Train', 'embed_url' => 'https://a.b/e/1', 'tags' => ['Korea', 'Tom Hanks', 'tom hanks', ' ']])
            ->assertCreated()
            ->assertJsonPath('data.tags', [['name' => 'Korea', 'slug' => 'korea'], ['name' => 'Tom Hanks', 'slug' => 'tom-hanks']]);

        $this->post('/api/movies', [
            'title' => 'Upload', 'embed_url' => 'https://a.b/e/2', 'tags' => 'Korea, Zombie',
            'thumbnail' => UploadedFile::fake()->image('t.jpg', 640, 360),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.tags.1.slug', 'zombie');
        $this->assertSame(3, Tag::count());   // "Korea" was reused

        $this->patchJson('/api/movies/night-train', ['synopsis' => 'x'])->assertJsonCount(2, 'data.tags');   // left out = kept
        $this->patchJson('/api/movies/night-train', ['tags' => ['Zombie']])->assertJsonPath('data.tags', [['name' => 'Zombie', 'slug' => 'zombie']]);
        $this->patchJson('/api/movies/night-train', ['tags' => []])->assertJsonPath('data.tags', []);

        $this->postJson('/api/movies', ['title' => 'Too many', 'embed_url' => 'https://a.b/e/3', 'tags' => array_map(fn ($i) => "t{$i}", range(1, 21))])
            ->assertStatus(422)->assertJsonValidationErrors('tags');
        $this->postJson('/api/movies', ['title' => 'Too long', 'embed_url' => 'https://a.b/e/3', 'tags' => [str_repeat('x', 51)]])
            ->assertStatus(422)->assertJsonValidationErrors('tags.0');
    }

    public function test_admin_form_takes_tags_and_tags_page_manages_them(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/movies', ['_admin_form' => '1', 'title' => 'Form Film', 'embed_url' => 'https://a.b/e/1', 'tags' => 'Korea, Zombie'])
            ->assertRedirect('/admin/movies/form-film/edit');
        $this->get('/admin/movies/form-film/edit')->assertSee('value="Korea, Zombie"', false)->assertSee('Used: Korea, Zombie');
        $this->get('/admin/movies')->assertSee('#Korea #Zombie');

        $this->get('/admin/tags')->assertOk()->assertSee('#Korea')->assertSee('/tag/korea · 1 movie');
        $this->put('/admin/tags/korea', ['name' => 'Korean', 'slug' => 'korean'])->assertRedirect('/admin/tags');
        $this->get('/admin/movies?tag=korean')->assertSee('Form Film')->assertSee('#Korean &times;', false);
        $this->get('/admin/movies?tag=zombie&q=nothing')->assertSee('No movies match this filter');

        $this->delete('/admin/tags/korean')->assertRedirect('/admin/tags');
        $this->assertSame(['zombie'], Movie::where('slug', 'form-film')->first()->tags->pluck('slug')->all());
    }

    // ---- filtering ----------------------------------------------------------------

    public function test_several_tags_must_all_match_on_the_site_and_in_the_api(): void
    {
        $this->seedMovies();

        $this->assertEqualsCanonicalizing(['alpha-train', 'bravo-seoul'], $this->slugs('?tags=korea'));
        $this->assertSame(['alpha-train'], $this->slugs('?tags=korea,zombie'));
        $this->assertSame(['alpha-train'], $this->slugs('?tags=zombie,korea&category=drama'));
        $this->assertSame([], $this->slugs('?tags=korea,unknown'));

        $both = $this->get('/?tags=korea,zombie')->assertOk();
        $both->assertSee('Alpha Train')->assertDontSee('Bravo Seoul')->assertDontSee('Charlie Dead')
            ->assertSee('Film dengan tag Korea + Zombie')
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->get('/tag/korea?tags=zombie')->assertSee('Alpha Train')->assertDontSee('Bravo Seoul');
        $this->get('/category/drama?tags=korea')->assertSee('Alpha Train')->assertDontSee('Bravo Seoul')
            ->assertSee('<meta name="robots" content="noindex, follow">', false);

        // Unknown slugs in ?tags are ignored; an unknown /tag page is a 404; drafts never show.
        $this->get('/?tags=korea,nope')->assertSee('Bravo Seoul');
        $this->get('/tag/nope')->assertNotFound();
        $this->get('/tag/korea')->assertDontSee('Delta Draft');

        // Combine with sort: zombie movies, shortest first.
        $this->assertMatchesRegularExpression('/Charlie Dead.*Alpha Train/s', $this->get('/tag/zombie?sort=duration&dir=asc')->getContent());
    }

    public function test_tag_page_is_an_indexed_landing_page_in_both_languages(): void
    {
        $this->seedMovies();

        $html = $this->get('/tag/korea')->assertOk()->getContent();
        $this->assertStringContainsString('<title>Film Korea - Nonton Online | MovieMov</title>', $html);
        $this->assertStringContainsString('<h1 class="mb-4 text-xl font-semibold text-white md:text-2xl">Film dengan tag Korea</h1>', $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/tag/korea').'">', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="en" href="'.url('/en/tag/korea').'">', $html);
        $this->assertStringContainsString('Nonton film Korea online di MovieMov', $html);

        $this->get('/en/tag/korea')->assertOk()->assertSee('Movies tagged Korea')->assertSee('<title>Korea Movies - Watch Online | MovieMov</title>', false);
        // ?tags=korea alone points Google at the clean /tag/korea page.
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/tag/korea').'">', $this->get('/?tags=korea')->getContent());
    }

    public function test_tag_chips_add_and_remove_tags_and_keep_other_filters(): void
    {
        $this->seedMovies();

        // Home: popular tags link to the clean (crawlable) tag pages.
        $home = $this->get('/')->getContent();
        $this->assertMatchesRegularExpression('~<a href="'.preg_quote(url('/tag/korea'), '~').'"\s+class="[^"]*">#Korea</a>~', $home);

        // Tag page: the active chip removes it, the others add to it (nofollow, combined URL), categories keep it.
        $page = $this->get('/tag/korea')->getContent();
        $this->assertMatchesRegularExpression('~href="'.preg_quote(url('/'), '~').'"\s+aria-current="true" aria-label="Hapus tag Korea"~', $page);
        $this->assertLink($page, url('/').'?tags=korea,zombie');
        $this->assertLink($page, url('/category/drama').'?tags=korea');

        // Two tags: removing one goes back to the other's clean page; sort is kept everywhere.
        $both = $this->get('/?tags=korea,zombie&sort=rating')->getContent();
        $this->assertLink($both, url('/tag/zombie').'?sort=rating');
        $this->assertLink($both, url('/').'?sort=rating');   // "Clear tags": the only link without tags
        $this->assertStringContainsString('Hapus semua tag', $both);
    }

    public function test_search_inside_tags_and_tag_links_on_the_movie_page(): void
    {
        $this->seedMovies();

        $page = $this->get('/tag/korea')->getContent();
        $this->assertStringContainsString('action="'.url('/tag/korea').'" role="search"', $page);
        $this->assertStringContainsString('placeholder="Cari di #Korea"', $page);

        $both = $this->get('/?tags=korea,zombie&sort=views')->getContent();
        $this->assertStringContainsString('action="'.url('/').'" role="search"', $both);
        $this->assertStringContainsString('<input type="hidden" name="tags" value="korea,zombie">', $both);
        $this->assertStringContainsString('<input type="hidden" name="sort" value="views">', $both);
        $this->get('/tag/korea?q=seoul')->assertSee('Bravo Seoul')->assertDontSee('Alpha Train');

        $this->get('/movies/alpha-train')->assertSee('href="'.url('/tag/korea').'"', false)->assertSee('#Zombie');
        $this->get('/en/movies/alpha-train')->assertSee('href="'.url('/en/tag/zombie').'"', false);
    }

    public function test_sitemap_lists_tags_with_published_movies(): void
    {
        $this->seedMovies();
        Tag::create(['name' => 'Unused', 'slug' => 'unused']);
        Tag::create(['name' => 'Only Draft', 'slug' => 'only-draft'])->movies()->attach(Movie::where('slug', 'delta-draft')->first());

        $xml = $this->get('/sitemap.xml')->getContent();
        $this->assertStringContainsString('<loc>'.url('/tag/korea').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.url('/en/tag/zombie').'</loc>', $xml);
        $this->assertStringNotContainsString('/tag/unused', $xml);
        $this->assertStringNotContainsString('/tag/only-draft', $xml);
    }

    // ---- tags API -------------------------------------------------------------------

    public function test_tags_api_lists_used_tags_and_is_admin_only_for_changes(): void
    {
        $this->seedMovies();
        Tag::create(['name' => 'Unused', 'slug' => 'unused']);

        $list = $this->getJson('/api/tags')->assertOk()->json('data');
        $this->assertSame(['korea', 'zombie'], array_column($list, 'slug'));   // most used first; drafts not counted
        $this->assertSame([2, 2], array_column($list, 'movies_count'));
        $this->getJson('/api/tags?all=1')->assertJsonCount(2, 'data');          // all=1 needs an admin token
        $this->getJson('/api/tags/korea')->assertOk()->assertJsonPath('data.url', url('/tag/korea'));

        $this->postJson('/api/tags', ['name' => 'X'])->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/tags', ['name' => 'X'])->assertForbidden();

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/tags?all=1')->assertJsonCount(3, 'data');
        $this->postJson('/api/tags', ['name' => 'Tom Hanks'])->assertCreated()->assertJsonPath('data.slug', 'tom-hanks');
        $this->postJson('/api/tags', ['name' => 'tom hanks'])->assertStatus(422)->assertJsonValidationErrors('slug');
        $this->patchJson('/api/tags/tom-hanks', ['name' => 'Tom H.'])->assertOk()->assertJsonPath('data.slug', 'tom-hanks');
        $this->deleteJson('/api/tags/zombie')->assertNoContent();
        $this->assertSame(1, Movie::where('slug', 'alpha-train')->first()->tags()->count());
    }

    public function test_filter_takes_at_most_five_tags(): void
    {
        $this->assertSame(['a', 'b', 'c', 'd', 'e'], Tag::parseFilter('A, b,,c,d,e,f,a'));
        $this->assertSame(['x'], Tag::parseFilter(['x', ['nested'], ' ']));
    }
}
