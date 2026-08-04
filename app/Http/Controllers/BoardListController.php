<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBoardListRequest;
use App\Http\Requests\UpdateBoardListRequest;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Team;
use App\Services\Boards\BoardListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class BoardListController extends Controller
{
    public function __construct(private readonly BoardListService $lists) {}

    /**
     * Append a list to the board.
     */
    public function store(StoreBoardListRequest $request, Team $currentTeam, Board $board): RedirectResponse
    {
        Gate::authorize('create', [BoardList::class, $board]);

        $this->lists->create($board, $request->name());

        return back()->with('status', __('List added.'));
    }

    /**
     * Rename the list.
     */
    public function update(UpdateBoardListRequest $request, Team $currentTeam, BoardList $list): RedirectResponse
    {
        Gate::authorize('update', $list);

        $this->lists->rename($list, $request->name());

        return back()->with('status', __('List renamed.'));
    }

    /**
     * Archive the list.
     */
    public function archive(Team $currentTeam, BoardList $list): RedirectResponse
    {
        Gate::authorize('update', $list);

        $this->lists->archive($list);

        return back()->with('status', __('List archived.'));
    }

    /**
     * Restore an archived list.
     */
    public function restore(Team $currentTeam, BoardList $list): RedirectResponse
    {
        Gate::authorize('update', $list);

        $this->lists->restore($list);

        return back()->with('status', __('List restored.'));
    }

    /**
     * Delete the list and its cards.
     */
    public function destroy(Team $currentTeam, BoardList $list): RedirectResponse
    {
        Gate::authorize('delete', $list);

        $this->lists->delete($list);

        return back()->with('status', __('List deleted.'));
    }
}
