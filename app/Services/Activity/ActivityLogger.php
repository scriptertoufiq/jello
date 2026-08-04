<?php

namespace App\Services\Activity;

use App\Models\Activity;
use App\Models\Board;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Records entries in the board activity feed.
 *
 * Called by the write services so history is captured next to the change that
 * produced it, rather than being reconstructed later.
 */
final class ActivityLogger
{
    /**
     * Record an action against a card.
     *
     * @param  array<string, mixed>  $properties
     */
    public function forCard(Card $card, string $action, array $properties = [], ?User $actor = null): Activity
    {
        $board = $card->board();

        return $this->record($board, $action, $properties, $actor, $card);
    }

    /**
     * Record an action against a board.
     *
     * @param  array<string, mixed>  $properties
     */
    public function forBoard(Board $board, string $action, array $properties = [], ?User $actor = null): Activity
    {
        return $this->record($board, $action, $properties, $actor);
    }

    /**
     * Persist the activity row.
     *
     * @param  array<string, mixed>  $properties
     */
    private function record(Board $board, string $action, array $properties, ?User $actor, ?Card $card = null): Activity
    {
        return Activity::create([
            'team_id' => $board->team_id,
            'board_id' => $board->id,
            'card_id' => $card?->id,
            'user_id' => ($actor ?? Auth::user())?->id,
            'action' => $action,
            'properties' => $properties === [] ? null : $properties,
        ]);
    }
}
