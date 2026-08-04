<?php

namespace Database\Factories;

use App\Models\Board;
use App\Models\BoardList;
use App\Services\Support\PositionCalculator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardList>
 */
class BoardListFactory extends Factory
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
            'name' => fake()->randomElement(['To Do', 'In Progress', 'In Review', 'Blocked', 'Done']),
            'position' => fake()->randomFloat(4, 1, PositionCalculator::GAP),
            'archived_at' => null,
        ];
    }

    /**
     * Indicate that the list belongs to the given board.
     */
    public function forBoard(Board $board): static
    {
        return $this->state(fn (array $attributes) => [
            'board_id' => $board->id,
        ]);
    }

    /**
     * Place the list at the given position.
     */
    public function atPosition(float $position): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => $position,
        ]);
    }

    /**
     * Indicate that the list is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
