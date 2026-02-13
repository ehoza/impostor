<?php

namespace Database\Factories;

use App\Models\Lobby;
use App\Models\Player;
use App\Models\Word;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Player>
 */
class PlayerFactory extends Factory
{
    protected $model = Player::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'lobby_id' => Lobby::factory(),
            'is_host' => false,
            'is_impostor' => false,
            'is_eliminated' => false,
            'word_id' => Word::inRandomOrder()->first(),
            'session_id' => fake()->uuid(),
            'votes_received' => 0,
        ];
    }
}
