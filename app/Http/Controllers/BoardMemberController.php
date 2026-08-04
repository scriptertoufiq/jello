<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBoardMemberRequest;
use App\Models\Board;
use App\Models\Team;
use App\Models\User;
use App\Services\Boards\BoardMemberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class BoardMemberController extends Controller
{
    public function __construct(private readonly BoardMemberService $members) {}

    /**
     * Add a workspace member to the board.
     */
    public function store(StoreBoardMemberRequest $request, Team $currentTeam, Board $board): RedirectResponse
    {
        Gate::authorize('manageMembers', $board);

        $this->members->add($board, User::findOrFail($request->userId()), $request->role());

        return back()->with('status', __('Board member added.'));
    }

    /**
     * Change a board member's role.
     */
    public function update(StoreBoardMemberRequest $request, Team $currentTeam, Board $board): RedirectResponse
    {
        Gate::authorize('manageMembers', $board);

        $this->members->changeRole($board, User::findOrFail($request->userId()), $request->role());

        return back()->with('status', __('Board member updated.'));
    }

    /**
     * Remove a member from the board.
     */
    public function destroy(Team $currentTeam, Board $board, User $member): RedirectResponse
    {
        Gate::authorize('manageMembers', $board);

        $this->members->remove($board, $member);

        return back()->with('status', __('Board member removed.'));
    }
}
