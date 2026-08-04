<?php

namespace App\Services\Boards;

use App\Models\Board;
use App\Models\BoardList;
use App\Services\Support\PositionCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Owns every write to the board_lists table.
 */
final class BoardListService
{
    public function __construct(private readonly PositionCalculator $positions) {}

    /**
     * Append a list to the end of the board.
     */
    public function create(Board $board, string $name): BoardList
    {
        return DB::transaction(fn (): BoardList => $board->lists()->create([
            'name' => $name,
            'position' => $this->nextPosition($board),
        ]));
    }

    /**
     * Create the board's starting columns.
     *
     * @param  array<int, string>  $names
     * @return array<int, BoardList>
     */
    public function createMany(Board $board, array $names): array
    {
        return DB::transaction(function () use ($board, $names): array {
            $positions = $this->positions->sequence(count($names));

            return array_map(
                fn (string $name, int $index): BoardList => $board->lists()->create([
                    'name' => $name,
                    'position' => $positions[$index],
                ]),
                $names,
                array_keys($names),
            );
        });
    }

    /**
     * Rename the list.
     */
    public function rename(BoardList $list, string $name): BoardList
    {
        $list->fill(['name' => $name])->save();

        return $list;
    }

    /**
     * Move the list so it sits at the given index among its siblings.
     */
    public function moveToIndex(BoardList $list, int $index): BoardList
    {
        return DB::transaction(function () use ($list, $index): BoardList {
            $siblings = $list->board->lists()
                ->where('id', '!=', $list->id)
                ->lockForUpdate()
                ->get()
                ->values();

            $before = $siblings->get($index - 1)?->position;
            $after = $siblings->get($index)?->position;

            if ($this->positions->needsRebalance($before, $after)) {
                $this->rebalance($list->board);

                return $this->moveToIndex($list->fresh(), $index);
            }

            $list->forceFill(['position' => $this->positions->between($before, $after)])->save();

            return $list;
        });
    }

    /**
     * Archive the list, hiding it and its cards from the board.
     */
    public function archive(BoardList $list): BoardList
    {
        $list->forceFill(['archived_at' => now()])->save();

        return $list;
    }

    /**
     * Restore an archived list.
     */
    public function restore(BoardList $list): BoardList
    {
        $list->forceFill(['archived_at' => null])->save();

        return $list;
    }

    /**
     * Delete the list and every card in it.
     */
    public function delete(BoardList $list): void
    {
        DB::transaction(function () use ($list) {
            $list->cards()->delete();
            $list->delete();
        });
    }

    /**
     * Get the position that places a new list after all existing ones.
     */
    private function nextPosition(Board $board): float
    {
        $last = $board->lists()->max('position');

        return $this->positions->between($last === null ? null : (float) $last, null);
    }

    /**
     * Re-space every list on the board so there is room to insert again.
     */
    private function rebalance(Board $board): void
    {
        $lists = $board->lists()->lockForUpdate()->get();

        $positions = $this->positions->sequence($lists->count());

        foreach ($lists as $index => $list) {
            $list->forceFill(['position' => $positions[$index]])->save();
        }
    }
}
