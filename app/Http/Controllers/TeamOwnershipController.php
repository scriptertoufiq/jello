<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransferOwnershipRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\Teams\TeamMemberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TeamOwnershipController extends Controller
{
    public function __construct(private readonly TeamMemberService $members) {}

    /**
     * Hand ownership of the workspace to another member.
     */
    public function update(TransferOwnershipRequest $request, Team $team): RedirectResponse
    {
        // Only the current owner may transfer, and never on a personal workspace.
        Gate::authorize('delete', $team);

        $newOwner = User::findOrFail($request->userId());

        $this->members->transferOwnership($team, $request->user(), $newOwner);

        return back()->with('status', __('Ownership transferred.'));
    }
}
