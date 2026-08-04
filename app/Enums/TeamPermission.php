<?php

namespace App\Enums;

enum TeamPermission: string
{
    case UpdateTeam = 'team:update';
    case DeleteTeam = 'team:delete';

    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';

    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';

    case ViewBoard = 'board:view';
    case CreateBoard = 'board:create';
    case UpdateBoard = 'board:update';
    case DeleteBoard = 'board:delete';
    case ArchiveBoard = 'board:archive';
    case ManageBoardMembers = 'board:manage-members';

    case CreateList = 'list:create';
    case UpdateList = 'list:update';
    case DeleteList = 'list:delete';
    case ReorderList = 'list:reorder';

    case CreateCard = 'card:create';
    case UpdateCard = 'card:update';
    case DeleteCard = 'card:delete';
    case MoveCard = 'card:move';
    case AssignCard = 'card:assign';
    case ArchiveCard = 'card:archive';

    case CreateComment = 'comment:create';
    case UpdateOwnComment = 'comment:update-own';
    case DeleteAnyComment = 'comment:delete-any';

    case ManageLabel = 'label:manage';
    case ManageChecklist = 'checklist:manage';
    case UploadAttachment = 'attachment:upload';
    case DeleteAttachment = 'attachment:delete';

    /**
     * Get the display label for the permission.
     */
    public function label(): string
    {
        return str($this->value)
            ->after(':')
            ->replace('-', ' ')
            ->headline()
            ->value();
    }

    /**
     * Get the resource group this permission belongs to.
     */
    public function group(): string
    {
        return str($this->value)->before(':')->value();
    }
}
