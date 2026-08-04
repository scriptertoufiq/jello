<?php

namespace App\Services\Cards;

use App\Models\Card;
use App\Models\Label;
use App\Models\User;
use App\Notifications\CardAssigned;
use App\Services\Activity\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Owns card assignees and label associations.
 */
final class CardAssignmentService
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Assign a user to the card, if they are not already assigned.
     */
    public function assign(Card $card, User $user, ?User $actor = null): void
    {
        DB::transaction(function () use ($card, $user, $actor) {
            if ($card->assignees()->whereKey($user->id)->exists()) {
                return;
            }

            $card->assignees()->attach($user->id);

            $this->activity->forCard($card, 'card.assigned', ['user' => $user->name], $actor);

            // Don't notify someone who assigned the card to themselves.
            if ($actor !== null && $actor->id !== $user->id) {
                $user->notify(new CardAssigned($card, $actor));
            }
        });
    }

    /**
     * Remove a user from the card.
     */
    public function unassign(Card $card, User $user, ?User $actor = null): void
    {
        DB::transaction(function () use ($card, $user, $actor) {
            if ($card->assignees()->whereKey($user->id)->doesntExist()) {
                return;
            }

            $card->assignees()->detach($user->id);

            $this->activity->forCard($card, 'card.unassigned', ['user' => $user->name], $actor);
        });
    }

    /**
     * Toggle a label on the card.
     */
    public function toggleLabel(Card $card, Label $label): void
    {
        DB::transaction(fn () => $card->labels()->toggle($label->id));
    }
}
