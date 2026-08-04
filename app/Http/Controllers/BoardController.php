<?php

namespace App\Http\Controllers;

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

        $board = $this->boards->create($currentTeam, $request->user(), $request->validated());

        return to_route('boards.show', ['board' => $board])
            ->with('status', __('Board created.'));
    }

    /**
     * Show a single board.
     */
    public function show(Team $currentTeam, Board $board): View
    {
        Gate::authorize('view', $board);

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

        $board = $this->boards->update($board, $request->validated());

        return to_route('boards.show', ['board' => $board])
            ->with('status', __('Board updated.'));
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
