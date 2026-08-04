<?php

namespace Database\Factories;

use App\Enums\BoardVisibility;
use App\Models\Board;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Board>
 */
class BoardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->catchPhrase(),
            'slug' => null,
            'description' => fake()->optional()->sentence(),
            'background' => fake()->randomElement(['slate', 'blue', 'green', 'amber', 'rose', 'violet']),
            'visibility' => BoardVisibility::Workspace,
            'created_by' => User::factory(),
            'position' => fake()->randomFloat(4, 0, 1000),
            'archived_at' => null,
        ];
    }

    /**
     * Indicate that the board belongs to the given workspace.
     */
    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
        ]);
    }

    /**
     * Indicate that the board was created by the given user.
     */
    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }

    /**
     * Indicate that the board is private to its explicit members.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => BoardVisibility::Private,
        ]);
    }

    /**
     * Indicate that the board is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }

    /**
     * Indicate that the board has been soft deleted.
     */
    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
