<?php

namespace Database\Factories;

use App\Models\Board;
use App\Models\Label;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Label>
 */
class LabelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'board_id' => Board::factory(),
            'name' => 'Label '.ucfirst(fake()->unique()->word()),
            'color' => fake()->randomElement(array_keys(config('boards.label_colors'))),
        ];
    }

    /**
     * Indicate that the label belongs to the given board.
     */
    public function forBoard(Board $board): static
    {
        return $this->state(fn (array $attributes) => [
            'board_id' => $board->id,
        ]);
    }
}
