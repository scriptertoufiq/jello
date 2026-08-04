<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
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
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }

    /**
     * Indicate that the comment belongs to the given card.
     */
    public function forCard(Card $card): static
    {
        return $this->state(fn (array $attributes) => [
            'card_id' => $card->id,
        ]);
    }

    /**
     * Indicate that the comment was written by the given user.
     */
    public function writtenBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
