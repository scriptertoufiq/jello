<?php

namespace App\Services\Cards;

use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use App\Services\Support\PositionCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Owns every write to the cards table.
 */
final class CardService
{
    public function __construct(
        private readonly PositionCalculator $positions,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Append a card to the end of the given list.
     */
    public function create(BoardList $list, User $creator, string $title): Card
    {
        return DB::transaction(function () use ($list, $creator, $title): Card {
            $card = $list->cards()->create([
                'title' => $title,
                'created_by' => $creator->id,
                'position' => $this->nextPosition($list),
            ]);

            $this->activity->forCard($card, 'card.created', actor: $creator);

            return $card;
        });
    }

    /**
     * Update the card's editable fields.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Card $card, array $attributes): Card
    {
        return DB::transaction(function () use ($card, $attributes): Card {
            $card->fill($attributes)->save();

            if ($card->wasChanged()) {
                $this->activity->forCard($card, 'card.updated');
            }

            return $card;
        });
    }

    /**
     * Move the card into the given list at the given index.
     *
     * Index is the destination position among the cards already in that list,
     * excluding the card being moved.
     */
    public function move(Card $card, BoardList $list, int $index): Card
    {
        return DB::transaction(function () use ($card, $list, $index): Card {
            $siblings = $list->cards()
                ->where('id', '!=', $card->id)
                ->lockForUpdate()
                ->get()
                ->values();

            $before = $siblings->get($index - 1)?->position;
            $after = $siblings->get($index)?->position;

            if ($this->positions->needsRebalance($before, $after)) {
                $this->rebalance($list);

                return $this->move($card->fresh(), $list->fresh(), $index);
            }

            $movedList = $card->board_list_id !== $list->id;

            $card->forceFill([
                'board_list_id' => $list->id,
                'position' => $this->positions->between($before, $after),
            ])->save();

            if ($movedList) {
                $this->activity->forCard($card, 'card.moved', ['list' => $list->name]);
            }

            return $card;
        });
    }

    /**
     * Mark the card complete or reopen it.
     */
    public function toggleComplete(Card $card): Card
    {
        $completing = ! $card->isComplete();

        $card->forceFill([
            'completed_at' => $completing ? now() : null,
        ])->save();

        $this->activity->forCard($card, $completing ? 'card.completed' : 'card.reopened');

        return $card;
    }

    /**
     * Archive the card, hiding it from the board.
     */
    public function archive(Card $card): Card
    {
        $card->forceFill(['archived_at' => now()])->save();

        $this->activity->forCard($card, 'card.archived');

        return $card;
    }

    /**
     * Restore an archived card.
     */
    public function restore(Card $card): Card
    {
        $card->forceFill(['archived_at' => null])->save();

        $this->activity->forCard($card, 'card.restored');

        return $card;
    }

    /**
     * Soft delete the card.
     */
    public function delete(Card $card): void
    {
        DB::transaction(fn () => $card->delete());
    }

    /**
     * Get the position that places a new card after all existing ones.
     */
    private function nextPosition(BoardList $list): float
    {
        $last = $list->cards()->max('position');

        return $this->positions->between($last === null ? null : (float) $last, null);
    }

    /**
     * Re-space every card in the list so there is room to insert again.
     */
    private function rebalance(BoardList $list): void
    {
        $cards = $list->cards()->lockForUpdate()->get();

        $positions = $this->positions->sequence($cards->count());

        foreach ($cards as $index => $card) {
            $card->forceFill(['position' => $positions[$index]])->save();
        }
    }
}
