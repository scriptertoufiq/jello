<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Board;
use App\Models\Team;
use App\Models\User;

class BoardPolicy
{
    /**
     * Determine whether the user can list the workspace's boards.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ViewBoard);
    }

    /**
     * Determine whether the user can view the board.
     *
     * Beyond the workspace permission, a private board is only visible to the
     * users explicitly added to it.
     */
    public function view(User $user, Board $board): bool
    {
        if (! $user->hasTeamPermission($board->team, TeamPermission::ViewBoard)) {
            return false;
        }

        return ! $board->isPrivate() || $this->isBoardMember($user, $board);
    }

    /**
     * Determine whether the user can create boards in the workspace.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::CreateBoard);
    }

    /**
     * Determine whether the user can update the board.
     */
    public function update(User $user, Board $board): bool
    {
        return $this->view($user, $board)
            && $user->hasTeamPermission($board->team, TeamPermission::UpdateBoard);
    }

    /**
     * Determine whether the user can archive or restore the board.
     */
    public function archive(User $user, Board $board): bool
    {
        return $this->view($user, $board)
            && $user->hasTeamPermission($board->team, TeamPermission::ArchiveBoard);
    }

    /**
     * Determine whether the user can delete the board.
     */
    public function delete(User $user, Board $board): bool
    {
        return $this->view($user, $board)
            && $user->hasTeamPermission($board->team, TeamPermission::DeleteBoard);
    }

    /**
     * Determine whether the user can manage the board's explicit members.
     */
    public function manageMembers(User $user, Board $board): bool
    {
        return $this->view($user, $board)
            && $user->hasTeamPermission($board->team, TeamPermission::ManageBoardMembers);
    }

    /**
     * Determine if the user is an explicit member of the board.
     */
    protected function isBoardMember(User $user, Board $board): bool
    {
        if ($board->relationLoaded('boardMemberships')) {
            return $board->boardMemberships->contains('user_id', $user->id);
        }

        return $board->boardMemberships()->where('user_id', $user->id)->exists();
    }
}
