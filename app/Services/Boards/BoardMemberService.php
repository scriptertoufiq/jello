<?php

namespace App\Services\Boards;

use App\Enums\TeamRole;
use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the explicit membership list used by private boards.
 */
final class BoardMemberService
{
    /**
     * Add a workspace member to the board.
     */
    public function add(Board $board, User $user, TeamRole $role = TeamRole::Member): void
    {
        if (! $user->belongsToTeam($board->team)) {
            throw ValidationException::withMessages([
                'user_id' => __('Only workspace members can be added to a board.'),
            ]);
        }

        DB::transaction(function () use ($board, $user, $role) {
            $board->boardMemberships()->updateOrCreate(
                ['user_id' => $user->id],
                ['role' => $role],
            );
        });
    }

    /**
     * Change a board member's role.
     */
    public function changeRole(Board $board, User $user, TeamRole $role): void
    {
        $membership = $board->boardMemberships()->where('user_id', $user->id)->first();

        if ($membership === null) {
            throw ValidationException::withMessages([
                'user_id' => __('That user is not a member of this board.'),
            ]);
        }

        $membership->update(['role' => $role]);
    }

    /**
     * Remove a member from the board.
     *
     * A private board must keep at least one member, otherwise nobody could
     * reach it again.
     */
    public function remove(Board $board, User $user): void
    {
        DB::transaction(function () use ($board, $user) {
            if ($board->isPrivate() && $board->boardMemberships()->lockForUpdate()->count() <= 1) {
                throw ValidationException::withMessages([
                    'user_id' => __('A private board must keep at least one member.'),
                ]);
            }

            $board->boardMemberships()->where('user_id', $user->id)->delete();
        });
    }
}
