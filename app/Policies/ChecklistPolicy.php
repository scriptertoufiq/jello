<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Card;
use App\Models\Checklist;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ChecklistPolicy
{
    /**
     * Determine whether the user can add a checklist to the card.
     */
    public function create(User $user, Card $card): bool
    {
        return Gate::forUser($user)->allows('view', $card)
            && $user->hasTeamPermission($card->board()->team, TeamPermission::ManageChecklist);
    }

    /**
     * Determine whether the user can change the checklist.
     */
    public function update(User $user, Checklist $checklist): bool
    {
        return $this->create($user, $checklist->card);
    }

    /**
     * Determine whether the user can delete the checklist.
     */
    public function delete(User $user, Checklist $checklist): bool
    {
        return $this->create($user, $checklist->card);
    }
}
