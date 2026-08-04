<?php

namespace App\Enums;

enum TeamRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Get the default permissions for this role.
     *
     * This map is the source of truth seeded into the database by the
     * PermissionSeeder. Runtime checks read the seeded records so that
     * permissions can be tuned without a code change.
     *
     * @return array<TeamPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => TeamPermission::cases(),
            self::Admin => [
                ...self::Member->permissions(),
                TeamPermission::UpdateTeam,
                TeamPermission::CreateInvitation,
                TeamPermission::CancelInvitation,
                TeamPermission::DeleteBoard,
                TeamPermission::ManageBoardMembers,
                TeamPermission::DeleteAnyComment,
            ],
            self::Member => [
                TeamPermission::ViewBoard,
                TeamPermission::CreateBoard,
                TeamPermission::UpdateBoard,
                TeamPermission::ArchiveBoard,
                TeamPermission::CreateList,
                TeamPermission::UpdateList,
                TeamPermission::DeleteList,
                TeamPermission::ReorderList,
                TeamPermission::CreateCard,
                TeamPermission::UpdateCard,
                TeamPermission::DeleteCard,
                TeamPermission::MoveCard,
                TeamPermission::AssignCard,
                TeamPermission::ArchiveCard,
                TeamPermission::CreateComment,
                TeamPermission::UpdateOwnComment,
                TeamPermission::ManageLabel,
                TeamPermission::ManageChecklist,
                TeamPermission::UploadAttachment,
                TeamPermission::DeleteAttachment,
            ],
            self::Viewer => [
                TeamPermission::ViewBoard,
            ],
        };
    }

    /**
     * Determine if the role has the given permission by default.
     *
     * Prefer User::hasTeamPermission() for runtime authorization; it reads the
     * seeded records rather than this hard-coded default map.
     */
    public function hasPermission(TeamPermission $permission): bool
    {
        return in_array($permission, $this->permissions());
    }

    /**
     * Get the hierarchy level for this role.
     * Higher numbers indicate higher privileges.
     */
    public function level(): int
    {
        return match ($this) {
            self::Owner => 4,
            self::Admin => 3,
            self::Member => 2,
            self::Viewer => 1,
        };
    }

    /**
     * Check if this role is at least as privileged as another role.
     */
    public function isAtLeast(TeamRole $role): bool
    {
        return $this->level() >= $role->level();
    }

    /**
     * Get the roles that can be assigned to team members (excludes Owner).
     *
     * @return array<array{value: string, label: string}>
     */
    public static function assignable(): array
    {
        return collect(self::cases())
            ->filter(fn (self $role) => $role !== self::Owner)
            ->map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()])
            ->values()
            ->toArray();
    }
}
