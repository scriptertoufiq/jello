<?php

namespace App\Services\Boards;

use App\Enums\BoardVisibility;
use App\Enums\TeamRole;
use App\Models\Board;
use App\Models\Team;
use App\Models\User;
use App\Services\Support\PositionCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Owns every write to the boards table.
 *
 * Callers (controllers and Livewire components) are responsible for
 * authorization and validation; this service receives values it can trust.
 */
final class BoardService
{
    public function __construct(private readonly PositionCalculator $positions) {}

    /**
     * Get the boards in a workspace that the given user may see.
     *
     * @return Collection<int, Board>
     */
    public function listFor(Team $team, User $user, bool $archived = false): Collection
    {
        return $team->boards()
            ->visibleTo($user)
            ->when($archived, fn ($query) => $query->archived(), fn ($query) => $query->active())
            ->withCount('boardMemberships')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * Create a board in the given workspace.
     *
     * @param  array{name: string, description?: string|null, background?: string, visibility?: string}  $attributes
     */
    public function create(Team $team, User $creator, array $attributes): Board
    {
        return DB::transaction(function () use ($team, $creator, $attributes): Board {
            $visibility = BoardVisibility::from($attributes['visibility'] ?? BoardVisibility::Workspace->value);

            $board = $team->boards()->create([
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'background' => $attributes['background'] ?? 'slate',
                'visibility' => $visibility,
                'created_by' => $creator->id,
                'position' => $this->nextPosition($team),
            ]);

            // The creator must stay able to reach a private board they just made.
            if ($visibility === BoardVisibility::Private) {
                $board->boardMemberships()->create([
                    'user_id' => $creator->id,
                    'role' => TeamRole::Admin,
                ]);
            }

            return $board;
        });
    }

    /**
     * Update the board's attributes.
     *
     * @param  array{name?: string, description?: string|null, background?: string, visibility?: string}  $attributes
     */
    public function update(Board $board, array $attributes): Board
    {
        return DB::transaction(function () use ($board, $attributes): Board {
            if (array_key_exists('visibility', $attributes)) {
                $attributes['visibility'] = BoardVisibility::from($attributes['visibility']);
            }

            $board->fill($attributes)->save();

            // Becoming private would otherwise lock out the creator.
            if ($board->isPrivate() && $board->boardMemberships()->doesntExist()) {
                $board->boardMemberships()->create([
                    'user_id' => $board->created_by,
                    'role' => TeamRole::Admin,
                ]);
            }

            return $board;
        });
    }

    /**
     * Archive the board, hiding it from the default board list.
     */
    public function archive(Board $board): Board
    {
        $board->forceFill(['archived_at' => now()])->save();

        return $board;
    }

    /**
     * Restore an archived board.
     */
    public function restore(Board $board): Board
    {
        $board->forceFill(['archived_at' => null])->save();

        return $board;
    }

    /**
     * Soft delete the board.
     */
    public function delete(Board $board): void
    {
        DB::transaction(fn () => $board->delete());
    }

    /**
     * Move the board to sit between the given sibling positions.
     */
    public function reorder(Board $board, ?float $before, ?float $after): Board
    {
        return DB::transaction(function () use ($board, $before, $after): Board {
            if ($this->positions->needsRebalance($before, $after)) {
                $this->rebalance($board->team);

                return $this->reorder($board->fresh(), $before, $after);
            }

            $board->forceFill(['position' => $this->positions->between($before, $after)])->save();

            return $board;
        });
    }

    /**
     * Get the position that places a new board after all existing ones.
     */
    private function nextPosition(Team $team): float
    {
        $last = $team->boards()->max('position');

        return $this->positions->between($last === null ? null : (float) $last, null);
    }

    /**
     * Re-space every board in the workspace so there is room to insert again.
     */
    private function rebalance(Team $team): void
    {
        $boards = $team->boards()->orderBy('position')->orderBy('id')->lockForUpdate()->get();

        $positions = $this->positions->sequence($boards->count());

        foreach ($boards as $index => $board) {
            $board->forceFill(['position' => $positions[$index]])->save();
        }
    }
}
