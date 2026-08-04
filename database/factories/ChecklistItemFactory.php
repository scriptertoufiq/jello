<?php

namespace Database\Factories;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Services\Support\PositionCalculator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_id' => Checklist::factory(),
            'content' => 'Item '.ucfirst(fake()->unique()->word()),
            'is_completed' => false,
            'position' => fake()->randomFloat(4, 1, PositionCalculator::GAP),
        ];
    }

    /**
     * Indicate that the item belongs to the given checklist.
     */
    public function forChecklist(Checklist $checklist): static
    {
        return $this->state(fn (array $attributes) => [
            'checklist_id' => $checklist->id,
        ]);
    }

    /**
     * Indicate that the item is done.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_completed' => true,
        ]);
    }
}
