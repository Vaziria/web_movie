<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DraftScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();

        return $u;
    }

    private function slugs(string $query = ''): array
    {
        return array_column($this->getJson('/api/movies'.$query)->assertOk()->json('data'), 'slug');
    }

    public function test_existing_and_default_movies_are_published(): void
    {
        $movie = Movie::factory()->create();
        $this->assertSame('published', $movie->status());

        Sanctum::actingAs($this->admin());
        $this->postJson('/api/movies', ['title' => 'Live', 'embed_url' => 'https://a.b/e/1'])
            ->assertCreated()->assertJsonPath('data.status', 'published');
    }

    public function test_draft_is_hidden_from_visitors_but_previewable_by_admin(): void
    {
        $admin = $this->admin();
        $cat = Category::create(['name' => 'Secret', 'slug' => 'secret']);
        $draft = Movie::factory()->create(['title' => 'Hidden Gem', 'published_at' => null]);
        $draft->categories()->attach($cat);
        Movie::factory()->create(['title' => 'Public One']);

        $this->assertSame('draft', $draft->fresh()->status());
        $this->get('/')->assertDontSee('Hidden Gem')->assertSee('Public One')->assertDontSee('/category/secret');
        $this->get('/?q=hidden')->assertDontSee('Hidden Gem');
        $this->get('/movies/hidden-gem')->assertNotFound();
        $this->get('/en/movies/hidden-gem')->assertNotFound();
        $this->post('/movies/hidden-gem/rate', ['score' => 5])->assertNotFound();
        $this->post('/movies/hidden-gem/report', ['server' => 1])->assertNotFound();
        $this->getJson('/api/movies/hidden-gem')->assertNotFound();
        $this->assertSame(['public-one'], $this->slugs());
        $this->getJson('/api/movies?status=draft')->assertForbidden();
        $this->assertStringNotContainsString('hidden-gem', $this->get('/sitemap.xml')->getContent());
        $this->assertSame(0, collect($this->getJson('/api/categories')->json('data'))->firstWhere('slug', 'secret')['movies_count']);

        // Admin (web session): noindex preview with a banner, no view counted.
        $this->actingAs($admin);
        $this->get('/movies/hidden-gem')->assertOk()
            ->assertSee('Admin preview')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->assertSame(0, $draft->fresh()->views);

        // Admin (token): can list and read drafts.
        Sanctum::actingAs($admin);
        $this->getJson('/api/movies/hidden-gem')->assertOk()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.published_at', null);
        $this->assertSame(['hidden-gem'], $this->slugs('?status=draft'));
        $this->assertEqualsCanonicalizing(['hidden-gem', 'public-one'], $this->slugs('?status=all'));
    }

    public function test_api_schedules_in_admin_time_zone_and_goes_live_by_itself(): void
    {
        Carbon::setTestNow('2030-01-01 00:00:00');   // UTC
        Sanctum::actingAs($this->admin());

        // No offset = WIB (UTC+7): 20:00 WIB = 13:00 UTC.
        $this->postJson('/api/movies', ['title' => 'Premiere', 'embed_url' => 'https://a.b/e/1', 'status' => 'scheduled', 'published_at' => '2030-01-01 20:00'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.published_at', '2030-01-01T13:00:00+00:00');
        // Explicit offsets are respected.
        $this->postJson('/api/movies', ['title' => 'Utc Show', 'embed_url' => 'https://a.b/e/2', 'status' => 'scheduled', 'published_at' => '2030-01-02T09:00:00Z'])
            ->assertJsonPath('data.published_at', '2030-01-02T09:00:00+00:00');

        Sanctum::actingAs(User::factory()->create());   // a non-admin user = visitor view of the API
        $this->assertSame([], $this->slugs());
        $this->get('/movies/premiere')->assertNotFound();

        Carbon::setTestNow('2030-01-01 13:00:01');
        $this->assertSame(['premiere'], $this->slugs());
        $this->get('/movies/premiere')->assertOk()->assertDontSee('Admin preview');
        $this->assertStringContainsString('/movies/premiere', $this->get('/sitemap.xml')->getContent());
        Carbon::setTestNow();
    }

    public function test_publish_status_validation(): void
    {
        Sanctum::actingAs($this->admin());
        $base = ['title' => 'X', 'embed_url' => 'https://a.b/e/1'];

        $this->postJson('/api/movies', $base + ['status' => 'scheduled'])->assertStatus(422)->assertJsonValidationErrors('published_at');
        $this->postJson('/api/movies', $base + ['status' => 'scheduled', 'published_at' => now()->subHour()->toIso8601String()])
            ->assertStatus(422)->assertJsonValidationErrors(['published_at' => 'future']);
        $this->postJson('/api/movies', $base + ['status' => 'published', 'published_at' => now()->addDay()->toIso8601String()])
            ->assertStatus(422)->assertJsonValidationErrors(['published_at' => 'scheduled']);
        $this->postJson('/api/movies', $base + ['status' => 'live'])->assertStatus(422)->assertJsonValidationErrors('status');
        $this->postJson('/api/movies', $base + ['published_at' => 'not a date'])->assertStatus(422)->assertJsonValidationErrors('published_at');
    }

    public function test_api_status_changes_keep_or_reset_the_publish_time(): void
    {
        Sanctum::actingAs($this->admin());
        $movie = Movie::factory()->create(['title' => 'Old One', 'published_at' => now()->subMonth()]);
        $original = $movie->published_at->toIso8601String();

        // Editing other fields, or re-saving as "published", keeps the original date.
        $this->patchJson('/api/movies/old-one', ['synopsis' => 'New text'])->assertJsonPath('data.published_at', $original);
        $this->patchJson('/api/movies/old-one', ['status' => 'published'])->assertJsonPath('data.published_at', $original);

        $this->patchJson('/api/movies/old-one', ['status' => 'draft'])->assertJsonPath('data.status', 'draft');
        $this->patchJson('/api/movies/old-one', ['status' => 'published'])->assertJsonPath('data.status', 'published');
        $this->assertTrue(Movie::where('slug', 'old-one')->first()->published_at->isAfter(now()->subMinute()));

        // Raw published_at without status: null = draft.
        $this->patchJson('/api/movies/old-one', ['published_at' => null])->assertJsonPath('data.status', 'draft');
    }

    public function test_admin_form_drafts_and_schedules(): void
    {
        $this->actingAs($this->admin());
        $form = ['_admin_form' => '1', 'embed_url' => 'https://a.b/e/1'];

        $this->post('/admin/movies', $form + ['title' => 'Form Draft', 'status' => 'draft', 'published_at' => '2031-01-01T10:00'])
            ->assertRedirect('/admin/movies/form-draft/edit');
        $this->assertNull(Movie::where('slug', 'form-draft')->value('published_at'));   // date box ignored for drafts

        $when = now()->addDays(2)->setTimezone('Asia/Jakarta')->setTime(20, 0);
        $this->post('/admin/movies', $form + ['title' => 'Form Later', 'status' => 'scheduled', 'published_at' => $when->format('Y-m-d\TH:i')])
            ->assertRedirect();
        $later = Movie::where('slug', 'form-later')->first();
        $this->assertSame('scheduled', $later->status());
        $this->assertSame($when->copy()->utc()->toDateTimeString(), $later->published_at->toDateTimeString());

        $this->get('/admin/movies/form-later/edit')->assertOk()
            ->assertSee('value="'.$when->format('Y-m-d\TH:i').'"', false)
            ->assertSee('value="scheduled" checked', false)
            ->assertSee('Preview');

        $this->post('/admin/movies', $form + ['title' => 'Bad Time', 'status' => 'scheduled', 'published_at' => '2001-01-01T10:00'])
            ->assertSessionHasErrors('published_at');

        $this->get('/admin/movies')->assertSee('● Draft')->assertSee('◷ Scheduled · '.$when->format('j M Y').' 20:00 WIB')
            ->assertSee('Drafts (1)')->assertSee('Scheduled (1)');
        $this->get('/admin/movies?status=draft')->assertSee('Form Draft')->assertDontSee('Form Later');
        $this->get('/admin/movies?status=scheduled')->assertSee('Form Later')->assertDontSee('Form Draft');

        // Publish the draft from the form.
        $this->put('/admin/movies/form-draft', $form + ['title' => 'Form Draft', 'slug' => 'form-draft', 'status' => 'published'])->assertRedirect();
        $this->assertSame('published', Movie::where('slug', 'form-draft')->first()->status());
        $this->get('/admin/movies/form-draft/edit')->assertSee('Published')->assertSee('since');
    }

    public function test_newest_uses_publish_time(): void
    {
        $early = Movie::factory()->create(['title' => 'Written Early', 'created_at' => now()->subDays(10), 'published_at' => now()->subMinute()]);
        Movie::factory()->create(['title' => 'Written Late', 'created_at' => now()->subDay(), 'published_at' => now()->subDay()]);

        $this->assertSame(['written-early', 'written-late'], $this->slugs('?sort=created'));
        $this->assertSame('written-early', $early->slug);
    }
}
