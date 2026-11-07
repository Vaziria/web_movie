<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Sanctum::actingAs($admin);
    }

    public function test_create_with_categories_reuses_existing_by_slug(): void
    {
        $this->actAsAdmin();

        $this->postJson('/api/movies', [
            'title' => 'One', 'embed_url' => 'https://vidara.to/e/a1', 'categories' => ['Action', 'Sci-Fi'],
        ])->assertCreated()
            ->assertJsonPath('data.categories.0.slug', 'action')
            ->assertJsonPath('data.categories.1.slug', 'sci-fi');

        $this->postJson('/api/movies', [
            'title' => 'Two', 'embed_url' => 'https://vidara.to/e/a2', 'categories' => ['action', 'SCI FI', 'Drama'],
        ])->assertCreated();

        $this->assertSame(3, Category::count());
        $this->getJson('/api/categories')->assertOk()
            ->assertJsonPath('data.0.slug', 'action')
            ->assertJsonPath('data.0.movies_count', 2);
    }

    public function test_update_replaces_categories_only_when_sent(): void
    {
        $this->actAsAdmin();
        $slug = $this->postJson('/api/movies', [
            'title' => 'X', 'embed_url' => 'https://vidara.to/e/x', 'categories' => ['Action'],
        ])->json('data.slug');

        $this->patchJson("/api/movies/{$slug}", ['title' => 'X2'])
            ->assertJsonPath('data.categories.0.slug', 'action');
        $this->patchJson("/api/movies/{$slug}", ['categories' => ['Horror']])
            ->assertJsonCount(1, 'data.categories')->assertJsonPath('data.categories.0.slug', 'horror');
        $this->patchJson("/api/movies/{$slug}", ['categories' => []])
            ->assertJsonCount(0, 'data.categories');
    }

    public function test_filter_by_category_on_api_and_web(): void
    {
        $action = Category::create(['name' => 'Action', 'slug' => 'action']);
        $a = Movie::factory()->create(['title' => 'Explosions']);
        $a->categories()->attach($action);
        Movie::factory()->create(['title' => 'Quiet Drama']);

        $this->assertSame(['Explosions'], collect($this->getJson('/api/movies?category=action')->json('data'))->pluck('title')->all());

        $this->get('/category/action')->assertOk()->assertSee('Explosions')->assertDontSee('Quiet Drama')
            ->assertSee('<link rel="canonical" href="'.url('/category/action').'">', false);
        $this->get('/category/missing')->assertNotFound();
    }

    public function test_admin_manages_categories(): void
    {
        $this->postJson('/api/categories', ['name' => 'Action'])->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/categories', ['name' => 'Action'])->assertForbidden();

        $this->actAsAdmin();

        $this->postJson('/api/categories', ['name' => 'Science Fiction', 'description' => 'Space and robots.'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'science-fiction')
            ->assertJsonPath('data.movies_count', 0);
        $this->postJson('/api/categories', ['name' => 'Science Fiction'])->assertStatus(422)->assertJsonValidationErrors('slug');
        $this->postJson('/api/categories', ['name' => 'X', 'slug' => 'Bad Slug'])->assertStatus(422)->assertJsonValidationErrors('slug');

        $this->patchJson('/api/categories/science-fiction', ['name' => 'Sci-Fi', 'slug' => 'sci-fi'])
            ->assertOk()->assertJsonPath('data.name', 'Sci-Fi')->assertJsonPath('data.url', url('/category/sci-fi'));
        $this->getJson('/api/categories/science-fiction')->assertNotFound();

        $movie = Movie::factory()->create(['title' => 'Robots']);
        $movie->categories()->attach(Category::where('slug', 'sci-fi')->first());

        $this->get('/category/sci-fi')->assertOk()
            ->assertSee('Space and robots.')
            ->assertSee('<meta name="description" content="Space and robots.">', false);

        $this->deleteJson('/api/categories/sci-fi')->assertNoContent();
        $this->assertDatabaseHas('movies', ['id' => $movie->id]);
        $this->assertSame(0, $movie->categories()->count());
    }

    public function test_thumbnail_url_and_file_upload(): void
    {
        $this->actAsAdmin();

        $this->postJson('/api/movies', [
            'title' => 'Remote', 'embed_url' => 'https://vidara.to/e/r', 'thumbnail_url' => 'https://img.example.com/t.jpg',
        ])->assertCreated()->assertJsonPath('data.thumbnail_url', 'https://img.example.com/t.jpg');

        $res = $this->post('/api/movies', [
            'title' => 'Uploaded', 'embed_url' => 'https://vidara.to/e/u', 'categories' => 'Action, Drama',
            'thumbnail' => UploadedFile::fake()->image('thumb.png', 640, 360),
        ], ['Accept' => 'application/json'])->assertCreated();

        $url = $res->json('data.thumbnail_url');
        $this->assertStringStartsWith(url('/uploads/thumbnails/'), $url);
        $this->assertCount(2, $res->json('data.categories'));
        $file = public_path(parse_url($url, PHP_URL_PATH));
        $this->assertFileExists($file);

        // Replacing the thumbnail via multipart POST deletes the old file.
        $slug = $res->json('data.slug');
        $this->post("/api/movies/{$slug}", ['thumbnail' => UploadedFile::fake()->image('new.jpg', 640, 360)], ['Accept' => 'application/json'])
            ->assertOk();
        $this->assertFileDoesNotExist($file);
        $newFile = public_path(parse_url(Movie::where('slug', $slug)->first()->thumbnailSrc(), PHP_URL_PATH));
        $this->assertFileExists($newFile);

        // Deleting the movie removes its uploaded file.
        $this->deleteJson("/api/movies/{$slug}")->assertNoContent();
        $this->assertFileDoesNotExist($newFile);
    }

    public function test_thumbnail_must_be_an_image(): void
    {
        $this->actAsAdmin();

        $this->post('/api/movies', [
            'title' => 'Bad', 'embed_url' => 'https://vidara.to/e/b',
            'thumbnail' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('thumbnail');
    }
}
