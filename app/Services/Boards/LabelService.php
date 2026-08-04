<?php

namespace App\Services\Boards;

use App\Models\Board;
use App\Models\Label;

/**
 * Owns every write to the labels table.
 */
final class LabelService
{
    /**
     * Create a label on the board.
     */
    public function create(Board $board, string $name, string $color): Label
    {
        return $board->labels()->create([
            'name' => $name,
            'color' => $color,
        ]);
    }

    /**
     * Update a label's name or colour.
     */
    public function update(Label $label, string $name, string $color): Label
    {
        $label->fill(['name' => $name, 'color' => $color])->save();

        return $label;
    }

    /**
     * Delete a label, removing it from every card that carries it.
     */
    public function delete(Label $label): void
    {
        $label->delete();
    }

    /**
     * Create the default label set for a new board.
     *
     * @return array<int, Label>
     */
    public function createDefaults(Board $board): array
    {
        return array_map(
            fn (array $label): Label => $this->create($board, $label['name'], $label['color']),
            config('boards.default_labels', []),
        );
    }
}
