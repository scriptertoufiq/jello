<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class BoardListPolicy
{
    /**
     * Determine whether the user can view the list.
     */
    public function view(User $user, BoardList $list): bool
    {
        return Gate::forUser($user)->allows('view', $list->board);
    }

    /**
     * Determine whether the user can add a list to the board.
     */
    public function create(User $user, Board $board): bool
    {
        return Gate::forUser($user)->allows('view', $board)
            && $user->hasTeamPermission($board->team, TeamPermission::CreateList);
    }

    /**
     * Determine whether the user can rename or archive the list.
     */
    public function update(User $user, BoardList $list): bool
    {
        return $this->view($user, $list)
            && $user->hasTeamPermission($list->board->team, TeamPermission::UpdateList);
    }

    /**
     * Determine whether the user can reorder lists on the board.
     */
    public function reorder(User $user, BoardList $list): bool
    {
        return $this->view($user, $list)
            && $user->hasTeamPermission($list->board->team, TeamPermission::ReorderList);
    }

    /**
     * Determine whether the user can delete the list.
     */
    public function delete(User $user, BoardList $list): bool
    {
        return $this->view($user, $list)
            && $user->hasTeamPermission($list->board->team, TeamPermission::DeleteList);
    }
}
