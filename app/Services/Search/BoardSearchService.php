<?php

namespace App\Services\Search;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads cards across a workspace for search, filtering, and "my tasks".
 *
 * Every query is constrained to boards the given user may actually see, so
 * private boards never leak into results.
 */
final class BoardSearchService
{
    /**
     * Search cards across every board in the workspace the user can see.
     *
     * @param  array{term?: string|null, assignee?: int|null, label?: int|null, due?: string|null, board?: int|null}  $filters
     * @return LengthAwarePaginator<int, Card>
     */
    public function search(Team $team, User $user, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->baseQuery($team, $user)
            ->when($filters['term'] ?? null, fn (Builder $query, string $term) => $query->where(
                fn (Builder $query) => $query
                    ->where('cards.title', 'like', '%'.$term.'%')
                    ->orWhere('cards.description', 'like', '%'.$term.'%'),
            ))
            ->when($filters['board'] ?? null, fn (Builder $query, int $boardId) => $query->whereHas(
                'list',
                fn (Builder $query) => $query->where('board_id', $boardId),
            ))
            ->when($filters['assignee'] ?? null, fn (Builder $query, int $userId) => $query->whereHas(
                'assignees',
                fn (Builder $query) => $query->where('users.id', $userId),
            ))
            ->when($filters['label'] ?? null, fn (Builder $query, int $labelId) => $query->whereHas(
                'labels',
                fn (Builder $query) => $query->where('labels.id', $labelId),
            ))
            ->when($filters['due'] ?? null, fn (Builder $query, string $due) => $this->applyDueFilter($query, $due))
            ->latest('cards.updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get the open cards assigned to the user, soonest due first.
     *
     * @return Collection<int, Card>
     */
    public function assignedTo(Team $team, User $user): Collection
    {
        return $this->baseQuery($team, $user)
            ->whereHas('assignees', fn (Builder $query) => $query->where('users.id', $user->id))
            ->whereNull('cards.completed_at')
            ->orderByRaw('CASE WHEN cards.due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('cards.due_date')
            ->orderBy('cards.id')
            ->get();
    }

    /**
     * Get the cards on a single board for the list view.
     *
     * `manual` keeps the board's own ordering so the list mirrors the columns;
     * the other sorts flatten across lists.
     *
     * @param  'manual'|'due'|'title'  $sort
     * @return Collection<int, Card>
     */
    public function forBoard(Board $board, string $sort = 'manual', bool $hideCompleted = false): Collection
    {
        return Card::query()
            ->active()
            ->with(['list', 'assignees', 'labels'])
            ->whereHas('list', fn (Builder $query) => $query
                ->where('board_id', $board->id)
                ->whereNull('board_lists.archived_at'))
            ->when($hideCompleted, fn (Builder $query) => $query->whereNull('cards.completed_at'))
            ->when($sort === 'due', fn (Builder $query) => $query
                ->orderByRaw('CASE WHEN cards.due_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('cards.due_date'))
            ->when($sort === 'title', fn (Builder $query) => $query->orderBy('cards.title'))
            ->when($sort === 'manual', fn (Builder $query) => $query
                ->orderBy(
                    BoardList::query()
                        ->select('position')
                        ->whereColumn('board_lists.id', 'cards.board_list_id'),
                )
                ->orderBy('cards.position'))
            ->orderBy('cards.id')
            ->get();
    }

    /**
     * Get the boards visible to the user, for filter dropdowns.
     *
     * @return Collection<int, Board>
     */
    public function visibleBoards(Team $team, User $user): Collection
    {
        return $team->boards()->visibleTo($user)->active()->orderBy('name')->get();
    }

    /**
     * Build the query of cards the user may see in this workspace.
     *
     * @return Builder<Card>
     */
    private function baseQuery(Team $team, User $user): Builder
    {
        $visibleBoardIds = $team->boards()
            ->visibleTo($user)
            ->active()
            ->select('boards.id');

        return Card::query()
            ->active()
            ->with(['list.board', 'assignees', 'labels'])
            ->whereHas(
                'list',
                fn (Builder $query) => $query
                    ->whereNull('board_lists.archived_at')
                    ->whereIn('board_lists.board_id', $visibleBoardIds),
            );
    }

    /**
     * Narrow the query by a due-date bucket.
     *
     * @param  Builder<Card>  $query
     * @return Builder<Card>
     */
    private function applyDueFilter(Builder $query, string $due): Builder
    {
        return match ($due) {
            'overdue' => $query->whereNotNull('cards.due_date')
                ->where('cards.due_date', '<', now())
                ->whereNull('cards.completed_at'),
            'today' => $query->whereBetween('cards.due_date', [now()->startOfDay(), now()->endOfDay()]),
            'week' => $query->whereBetween('cards.due_date', [now(), now()->addWeek()]),
            'none' => $query->whereNull('cards.due_date'),
            default => $query,
        };
    }
}
