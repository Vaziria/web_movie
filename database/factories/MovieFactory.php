<?php

namespace Database\Factories;

use App\Models\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movie>
 */
class MovieFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(3),
            'synopsis' => fake()->paragraph(),
            'thumbnail_url' => null,
            'embed_url' => 'https://player.example.com/e/'.fake()->unique()->bothify('????####'),
            'release_year' => fake()->numberBetween(1970, 2026),
            'duration_minutes' => fake()->numberBetween(70, 180),
        ];
    }
}
