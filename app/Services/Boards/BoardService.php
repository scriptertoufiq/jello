<?php

namespace App\Services\Boards;

use App\Data\BoardAttributes;
use App\Enums\TeamRole;
use App\Models\Board;
use App\Models\Team;
use App\Models\User;
use App\Services\Support\PositionCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Owns every write to the boards table.
 *
 * Callers (controllers and Livewire components) are responsible for
 * authorization and validation; this service receives values it can trust.
 */
final class BoardService
{
    public function __construct(
        private readonly PositionCalculator $positions,
        private readonly BoardListService $lists,
        private readonly LabelService $labels,
    ) {}

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
     */
    public function create(Team $team, User $creator, BoardAttributes $attributes): Board
    {
        return DB::transaction(function () use ($team, $creator, $attributes): Board {
            $board = $team->boards()->create([
                ...$attributes->toArray(),
                'created_by' => $creator->id,
                'position' => $this->nextPosition($team),
            ]);

            // The creator must stay able to reach a private board they just made.
            if ($board->isPrivate()) {
                $board->boardMemberships()->create([
                    'user_id' => $creator->id,
                    'role' => TeamRole::Admin,
                ]);
            }

            // Start with usable columns and labels rather than an empty board.
            $this->lists->createMany($board, config('boards.default_lists', []));
            $this->labels->createDefaults($board);

            return $board;
        });
    }

    /**
     * Update the board's attributes.
     */
    public function update(Board $board, BoardAttributes $attributes): Board
    {
        return DB::transaction(function () use ($board, $attributes): Board {
            $board->fill($attributes->toArray())->save();

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
     * Copy a board, including its lists, labels, and open cards.
     *
     * Comments, attachments, and activity are intentionally not copied: they
     * belong to the original board's history.
     */
    public function duplicate(Board $board, User $creator): Board
    {
        return DB::transaction(function () use ($board, $creator): Board {
            $copy = $board->team->boards()->create([
                'name' => $board->name.' '.__('(copy)'),
                'description' => $board->description,
                'background' => $board->background,
                'visibility' => $board->visibility,
                'created_by' => $creator->id,
                'position' => $this->nextPosition($board->team),
            ]);

            $this->copyThumbnail($board, $copy);

            if ($copy->isPrivate()) {
                $copy->boardMemberships()->create([
                    'user_id' => $creator->id,
                    'role' => TeamRole::Admin,
                ]);
            }

            // Map old label ids to new ones so cards keep their labels.
            $labelMap = [];

            foreach ($board->labels as $label) {
                $labelMap[$label->id] = $copy->labels()->create([
                    'name' => $label->name,
                    'color' => $label->color,
                ])->id;
            }

            foreach ($board->lists()->whereNull('archived_at')->get() as $list) {
                $listCopy = $copy->lists()->create([
                    'name' => $list->name,
                    'position' => $list->position,
                ]);

                foreach ($list->cards()->whereNull('archived_at')->get() as $card) {
                    $cardCopy = $listCopy->cards()->create([
                        'title' => $card->title,
                        'description' => $card->description,
                        'position' => $card->position,
                        'start_date' => $card->start_date,
                        'due_date' => $card->due_date,
                        'cover_color' => $card->cover_color,
                        'created_by' => $creator->id,
                    ]);

                    $newLabelIds = $card->labels
                        ->map(fn ($label) => $labelMap[$label->id] ?? null)
                        ->filter()
                        ->all();

                    if ($newLabelIds !== []) {
                        $cardCopy->labels()->sync($newLabelIds);
                    }
                }
            }

            return $copy;
        });
    }

    /**
     * Store a cover image for the board, replacing any existing one.
     */
    public function setThumbnail(Board $board, UploadedFile $file): Board
    {
        $disk = config('boards.thumbnails.disk');

        $path = $file->store("boards/{$board->id}/thumbnails", $disk);

        return DB::transaction(function () use ($board, $disk, $path): Board {
            $previous = $board->hasThumbnail()
                ? ['disk' => $board->thumbnail_disk, 'path' => $board->thumbnail_path]
                : null;

            $board->forceFill([
                'thumbnail_disk' => $disk,
                'thumbnail_path' => $path,
            ])->save();

            // Only bin the old file once the new one is safely recorded.
            if ($previous !== null) {
                Storage::disk($previous['disk'])->delete($previous['path']);
            }

            return $board;
        });
    }

    /**
     * Remove the board's cover image, falling back to its gradient.
     */
    public function removeThumbnail(Board $board): Board
    {
        if (! $board->hasThumbnail()) {
            return $board;
        }

        return DB::transaction(function () use ($board): Board {
            Storage::disk($board->thumbnail_disk)->delete($board->thumbnail_path);

            $board->forceFill([
                'thumbnail_disk' => null,
                'thumbnail_path' => null,
            ])->save();

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
     * Copy a board's cover image onto its duplicate.
     *
     * The copy gets its own file so deleting either board's thumbnail cannot
     * blank out the other.
     */
    private function copyThumbnail(Board $source, Board $copy): void
    {
        if (! $source->hasThumbnail()) {
            return;
        }

        $disk = Storage::disk($source->thumbnail_disk);

        if (! $disk->exists($source->thumbnail_path)) {
            return;
        }

        $path = "boards/{$copy->id}/thumbnails/".basename($source->thumbnail_path);

        $disk->copy($source->thumbnail_path, $path);

        $copy->forceFill([
            'thumbnail_disk' => $source->thumbnail_disk,
            'thumbnail_path' => $path,
        ])->save();
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
