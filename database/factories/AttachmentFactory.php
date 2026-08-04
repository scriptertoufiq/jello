<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Card;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().'.pdf';

        return [
            'card_id' => Card::factory(),
            'user_id' => User::factory(),
            'disk' => config('boards.attachments.disk'),
            'path' => 'cards/1/'.$name,
            'name' => $name,
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 1024 * 512),
        ];
    }

    /**
     * Indicate that the attachment belongs to the given card.
     */
    public function forCard(Card $card): static
    {
        return $this->state(fn (array $attributes) => [
            'card_id' => $card->id,
        ]);
    }

    /**
     * Indicate that the attachment is an image.
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->unique()->word().'.png',
            'mime_type' => 'image/png',
        ]);
    }
}
