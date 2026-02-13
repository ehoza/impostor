<?php

namespace Database\Factories;

use App\Models\Lobby;
use App\Models\Word;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lobby>
 */
class LobbyFactory extends Factory
{
    protected $model = Lobby::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('??????')),
            'name' => fake()->optional()->word(),
            'status' => 'waiting',
            'settings' => [
                'impostor_count' => 1,
                'discussion_time' => 60,
                'voting_time' => 30,
                'word_difficulty' => 3,
            ],
            'word_id' => Word::inRandomOrder()->first()?->id,
        ];
    }
}
