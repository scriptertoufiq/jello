<?php

namespace App\Services\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns every write to a workspace's membership roles.
 *
 * Keeping these in one place means the "exactly one owner" rule is enforced
 * in a single spot rather than in each caller.
 */
final class TeamMemberService
{
    /**
     * Change a member's role.
     *
     * The owner's role cannot be changed here; use transferOwnership().
     */
    public function changeRole(Team $team, User $member, TeamRole $role): void
    {
        if ($role === TeamRole::Owner) {
            throw ValidationException::withMessages([
                'role' => __('Use ownership transfer to make someone the owner.'),
            ]);
        }

        DB::transaction(function () use ($team, $member, $role) {
            $membership = $team->memberships()
                ->where('user_id', $member->id)
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                throw ValidationException::withMessages([
                    'role' => __('That user is not a member of this workspace.'),
                ]);
            }

            if ($membership->role === TeamRole::Owner) {
                throw ValidationException::withMessages([
                    'role' => __('The workspace owner\'s role cannot be changed.'),
                ]);
            }

            $membership->update(['role' => $role]);

            $member->flushTeamRoleCache();
        });
    }

    /**
     * Remove a member from the workspace.
     *
     * The owner cannot be removed while they still own the workspace.
     */
    public function remove(Team $team, User $member): void
    {
        DB::transaction(function () use ($team, $member) {
            $membership = $team->memberships()
                ->where('user_id', $member->id)
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                return;
            }

            if ($membership->role === TeamRole::Owner) {
                throw ValidationException::withMessages([
                    'member' => __('Transfer ownership before removing the owner.'),
                ]);
            }

            $membership->delete();

            $member->flushTeamRoleCache();

            // Move them off a workspace they can no longer reach.
            if ($member->isCurrentTeam($team)) {
                $fallback = $member->fallbackTeam($team) ?? $member->personalTeam();

                if ($fallback !== null) {
                    $member->switchTeam($fallback);
                }
            }
        });
    }

    /**
     * Hand ownership of the workspace to another member.
     *
     * The outgoing owner becomes an admin so they keep working access.
     */
    public function transferOwnership(Team $team, User $currentOwner, User $newOwner): void
    {
        DB::transaction(function () use ($team, $currentOwner, $newOwner) {
            $memberships = $team->memberships()
                ->whereIn('user_id', [$currentOwner->id, $newOwner->id])
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

            $outgoing = $memberships->get($currentOwner->id);
            $incoming = $memberships->get($newOwner->id);

            if ($outgoing === null || $outgoing->role !== TeamRole::Owner) {
                throw ValidationException::withMessages([
                    'user_id' => __('Only the current owner can transfer ownership.'),
                ]);
            }

            if ($incoming === null) {
                throw ValidationException::withMessages([
                    'user_id' => __('That user is not a member of this workspace.'),
                ]);
            }

            $incoming->update(['role' => TeamRole::Owner]);
            $outgoing->update(['role' => TeamRole::Admin]);

            $currentOwner->flushTeamRoleCache();
            $newOwner->flushTeamRoleCache();
        });
    }
}
