<?php

namespace App\Services\Cards;

use App\Models\Card;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Services\Support\PositionCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Owns every write to the checklists and checklist_items tables.
 */
final class ChecklistService
{
    public function __construct(private readonly PositionCalculator $positions) {}

    /**
     * Add a checklist to the card.
     */
    public function create(Card $card, string $name): Checklist
    {
        return DB::transaction(fn (): Checklist => $card->checklists()->create([
            'name' => $name,
            'position' => $this->nextPosition($card->checklists()->max('position')),
        ]));
    }

    /**
     * Rename a checklist.
     */
    public function rename(Checklist $checklist, string $name): Checklist
    {
        $checklist->fill(['name' => $name])->save();

        return $checklist;
    }

    /**
     * Delete a checklist and its items.
     */
    public function delete(Checklist $checklist): void
    {
        DB::transaction(function () use ($checklist) {
            $checklist->items()->delete();
            $checklist->delete();
        });
    }

    /**
     * Append an item to a checklist.
     */
    public function addItem(Checklist $checklist, string $content): ChecklistItem
    {
        return DB::transaction(fn (): ChecklistItem => $checklist->items()->create([
            'content' => $content,
            'position' => $this->nextPosition($checklist->items()->max('position')),
        ]));
    }

    /**
     * Update an item's text.
     */
    public function updateItem(ChecklistItem $item, string $content): ChecklistItem
    {
        $item->fill(['content' => $content])->save();

        return $item;
    }

    /**
     * Toggle an item's completed state.
     */
    public function toggleItem(ChecklistItem $item): ChecklistItem
    {
        $item->forceFill(['is_completed' => ! $item->is_completed])->save();

        return $item;
    }

    /**
     * Delete a checklist item.
     */
    public function deleteItem(ChecklistItem $item): void
    {
        $item->delete();
    }

    /**
     * Get the position that appends after the given highest position.
     */
    private function nextPosition(mixed $highest): float
    {
        return $this->positions->between($highest === null ? null : (float) $highest, null);
    }
}
