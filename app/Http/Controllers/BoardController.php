<?php

namespace App\Http\Controllers;

use App\Data\BoardAttributes;
use App\Http\Requests\StoreBoardRequest;
use App\Http\Requests\UpdateBoardRequest;
use App\Models\Board;
use App\Models\Team;
use App\Services\Boards\BoardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BoardController extends Controller
{
    public function __construct(private readonly BoardService $boards) {}

    /**
     * List the boards in the current workspace.
     */
    public function index(Request $request, Team $currentTeam): View
    {
        Gate::authorize('viewAny', [Board::class, $currentTeam]);

        $archived = $request->boolean('archived');

        return view('pages.boards.index', [
            'team' => $currentTeam,
            'boards' => $this->boards->listFor($currentTeam, $request->user(), $archived),
            'showingArchived' => $archived,
        ]);
    }

    /**
     * Store a new board in the current workspace.
     */
    public function store(StoreBoardRequest $request, Team $currentTeam): RedirectResponse
    {
        Gate::authorize('create', [Board::class, $currentTeam]);

        $board = $this->boards->create($currentTeam, $request->user(), BoardAttributes::fromRequest($request));

        if ($thumbnail = $request->thumbnail()) {
            $this->boards->setThumbnail($board, $thumbnail);
        }

        return to_route('boards.show', ['board' => $board])
            ->with('status', __('Board created.'));
    }

    /**
     * Show a single board.
     */
    public function show(Team $currentTeam, Board $board): View
    {
        Gate::authorize('view', $board);

        $board->load(['members', 'team.members']);

        return view('pages.boards.show', [
            'team' => $currentTeam,
            'board' => $board,
        ]);
    }

    /**
     * Update the board's settings.
     */
    public function update(UpdateBoardRequest $request, Team $currentTeam, Board $board): RedirectResponse
    {
        Gate::authorize('update', $board);

        $board = $this->boards->update($board, BoardAttributes::fromRequest($request, $board));

        if ($thumbnail = $request->thumbnail()) {
            $this->boards->setThumbnail($board, $thumbnail);
        } elseif ($request->wantsThumbnailRemoved()) {
            $this->boards->removeThumbnail($board);
        }

        return to_route('boards.show', ['board' => $board])
            ->with('status', __('Board updated.'));
    }

    /**
     * Duplicate the board's structure into a new board.
     */
    public function duplicate(Request $request, Team $currentTeam, Board $board): RedirectResponse
    {
        Gate::authorize('view', $board);
        Gate::authorize('create', [Board::class, $currentTeam]);

        $board->load(['labels', 'lists.cards.labels']);

        $copy = $this->boards->duplicate($board, $request->user());

        return to_route('boards.show', ['board' => $copy])
            ->with('status', __('Board duplicated.'));
    }

    /**
     * Archive the board.
     */
    public function archive(Team $currentTeam, Board $board): RedirectResponse
    {
        Gate::authorize('archive', $board);

        $this->boards->archive($board);

        return to_route('boards.index')
            ->with('status', __('Board archived.'));
    }

    /**
     * Restore an archived board.
     */
    public function restore(Team $currentTeam, Board $board): RedirectResponse
    {
        Gate::authorize('archive', $board);

        $this->boards->restore($board);

        return to_route('boards.show', ['board' => $board])
            ->with('status', __('Board restored.'));
    }

    /**
     * Delete the board.
     */
    public function destroy(Team $currentTeam, Board $board): RedirectResponse
    {
        Gate::authorize('delete', $board);

        $this->boards->delete($board);

        return to_route('boards.index')
            ->with('status', __('Board deleted.'));
    }
}
