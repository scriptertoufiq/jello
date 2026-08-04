<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Card;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CommentPolicy
{
    /**
     * Determine whether the user can comment on the card.
     */
    public function create(User $user, Card $card): bool
    {
        return Gate::forUser($user)->allows('view', $card)
            && $user->hasTeamPermission($card->board()->team, TeamPermission::CreateComment);
    }

    /**
     * Determine whether the user can edit the comment.
     *
     * Only the author may edit their own words.
     */
    public function update(User $user, Comment $comment): bool
    {
        return $comment->wasWrittenBy($user)
            && Gate::forUser($user)->allows('view', $comment->card)
            && $user->hasTeamPermission($comment->card->board()->team, TeamPermission::UpdateOwnComment);
    }

    /**
     * Determine whether the user can delete the comment.
     *
     * Authors can remove their own; moderators can remove anyone's.
     */
    public function delete(User $user, Comment $comment): bool
    {
        if (! Gate::forUser($user)->allows('view', $comment->card)) {
            return false;
        }

        $team = $comment->card->board()->team;

        if ($user->hasTeamPermission($team, TeamPermission::DeleteAnyComment)) {
            return true;
        }

        return $comment->wasWrittenBy($user)
            && $user->hasTeamPermission($team, TeamPermission::UpdateOwnComment);
    }
}
