<?php

namespace App\Http\Controllers;

use App\Enums\TeamPermission;
use App\Http\Requests\StoreLabelRequest;
use App\Models\Board;
use App\Models\Label;
use App\Models\Team;
use App\Models\User;
use App\Services\Boards\LabelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class LabelController extends Controller
{
    public function __construct(private readonly LabelService $labels) {}

    /**
     * Create a label on the board.
     */
    public function store(StoreLabelRequest $request, Team $currentTeam, Board $board): RedirectResponse
    {
        $this->authorizeManaging($request->user(), $board);

        $this->labels->create($board, $request->name(), $request->color());

        return back()->with('status', __('Label created.'));
    }

    /**
     * Update a label.
     */
    public function update(StoreLabelRequest $request, Team $currentTeam, Label $label): RedirectResponse
    {
        $this->authorizeManaging($request->user(), $label->board);

        $this->labels->update($label, $request->name(), $request->color());

        return back()->with('status', __('Label updated.'));
    }

    /**
     * Delete a label.
     */
    public function destroy(Team $currentTeam, Label $label): RedirectResponse
    {
        $this->authorizeManaging(request()->user(), $label->board);

        $this->labels->delete($label);

        return back()->with('status', __('Label deleted.'));
    }

    /**
     * Ensure the user may manage labels on the board.
     */
    private function authorizeManaging(User $user, Board $board): void
    {
        Gate::authorize('view', $board);

        abort_unless($user->hasTeamPermission($board->team, TeamPermission::ManageLabel), 403);
    }
}
