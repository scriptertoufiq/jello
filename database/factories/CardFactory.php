<?php

namespace Database\Factories;

use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Services\Support\PositionCalculator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'board_list_id' => BoardList::factory(),
            'title' => 'Card '.ucfirst(fake()->unique()->word()),
            'description' => fake()->optional()->paragraph(),
            'position' => fake()->randomFloat(4, 1, PositionCalculator::GAP),
            'start_date' => null,
            'due_date' => null,
            'completed_at' => null,
            'cover_color' => null,
            'created_by' => User::factory(),
            'archived_at' => null,
        ];
    }

    /**
     * Indicate that the card belongs to the given list.
     */
    public function forList(BoardList $list): static
    {
        return $this->state(fn (array $attributes) => [
            'board_list_id' => $list->id,
        ]);
    }

    /**
     * Indicate that the card was created by the given user.
     */
    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }

    /**
     * Place the card at the given position.
     */
    public function atPosition(float $position): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => $position,
        ]);
    }

    /**
     * Indicate that the card is past its due date.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => now()->subDays(2),
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the card is due at the given time.
     */
    public function dueAt(string $when): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => $when,
        ]);
    }

    /**
     * Indicate that the card has been completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the card is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
