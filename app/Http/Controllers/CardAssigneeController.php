<?php

namespace App\Http\Controllers;

use App\Enums\TeamPermission;
use App\Http\Requests\StoreCardAssigneeRequest;
use App\Models\Card;
use App\Models\Team;
use App\Models\User;
use App\Services\Cards\CardAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CardAssigneeController extends Controller
{
    public function __construct(private readonly CardAssignmentService $assignments) {}

    /**
     * Assign a workspace member to the card.
     */
    public function store(StoreCardAssigneeRequest $request, Team $currentTeam, Card $card): RedirectResponse
    {
        $this->authorizeAssigning($request->user(), $card);

        $assignee = User::findOrFail($request->userId());

        // Only workspace members can be assigned.
        abort_unless($assignee->belongsToTeam($currentTeam), 422);

        $this->assignments->assign($card, $assignee, $request->user());

        return back()->with('status', __('Member assigned.'));
    }

    /**
     * Remove an assignee from the card.
     */
    public function destroy(Team $currentTeam, Card $card, User $user): RedirectResponse
    {
        $this->authorizeAssigning(request()->user(), $card);

        $this->assignments->unassign($card, $user, request()->user());

        return back()->with('status', __('Member unassigned.'));
    }

    /**
     * Ensure the user may change assignees on the card.
     */
    private function authorizeAssigning(User $user, Card $card): void
    {
        Gate::authorize('view', $card);

        abort_unless($user->hasTeamPermission($card->board()->team, TeamPermission::AssignCard), 403);
    }
}
