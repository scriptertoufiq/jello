<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Attachment;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AttachmentPolicy
{
    /**
     * Determine whether the user can attach a file to the card.
     */
    public function create(User $user, Card $card): bool
    {
        return Gate::forUser($user)->allows('view', $card)
            && $user->hasTeamPermission($card->board()->team, TeamPermission::UploadAttachment);
    }

    /**
     * Determine whether the user can view or download the attachment.
     */
    public function view(User $user, Attachment $attachment): bool
    {
        return Gate::forUser($user)->allows('view', $attachment->card);
    }

    /**
     * Determine whether the user can delete the attachment.
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        return $this->view($user, $attachment)
            && $user->hasTeamPermission($attachment->card->board()->team, TeamPermission::DeleteAttachment);
    }
}
