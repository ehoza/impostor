<?php

namespace Database\Factories;

use App\Models\Word;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Word>
 */
class WordFactory extends Factory
{
    protected $model = Word::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'word' => fake()->word(),
            'category' => fake()->optional()->randomElement(['Romanian', 'Balkan', 'European', 'English']),
            'definition' => fake()->optional()->sentence(),
            'impostor_word_id' => null,
            'is_impostor_word' => false,
            'difficulty' => fake()->numberBetween(1, 5),
        ];
    }
}
