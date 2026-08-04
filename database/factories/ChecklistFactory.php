<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\Checklist;
use App\Services\Support\PositionCalculator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Checklist>
 */
class ChecklistFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_id' => Card::factory(),
            'name' => 'Checklist '.ucfirst(fake()->unique()->word()),
            'position' => fake()->randomFloat(4, 1, PositionCalculator::GAP),
        ];
    }

    /**
     * Indicate that the checklist belongs to the given card.
     */
    public function forCard(Card $card): static
    {
        return $this->state(fn (array $attributes) => [
            'card_id' => $card->id,
        ]);
    }
}
