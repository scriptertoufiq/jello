<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CardPolicy
{
    /**
     * Determine whether the user can view the card.
     */
    public function view(User $user, Card $card): bool
    {
        return Gate::forUser($user)->allows('view', $card->list->board);
    }

    /**
     * Determine whether the user can add a card to the given list.
     */
    public function create(User $user, BoardList $list): bool
    {
        return Gate::forUser($user)->allows('view', $list->board)
            && $user->hasTeamPermission($list->board->team, TeamPermission::CreateCard);
    }

    /**
     * Determine whether the user can update the card.
     */
    public function update(User $user, Card $card): bool
    {
        return $this->view($user, $card)
            && $user->hasTeamPermission($card->list->board->team, TeamPermission::UpdateCard);
    }

    /**
     * Determine whether the user can move the card within or between lists.
     */
    public function move(User $user, Card $card): bool
    {
        return $this->view($user, $card)
            && $user->hasTeamPermission($card->list->board->team, TeamPermission::MoveCard);
    }

    /**
     * Determine whether the user can archive or restore the card.
     */
    public function archive(User $user, Card $card): bool
    {
        return $this->view($user, $card)
            && $user->hasTeamPermission($card->list->board->team, TeamPermission::ArchiveCard);
    }

    /**
     * Determine whether the user can delete the card.
     */
    public function delete(User $user, Card $card): bool
    {
        return $this->view($user, $card)
            && $user->hasTeamPermission($card->list->board->team, TeamPermission::DeleteCard);
    }
}
