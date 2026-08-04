<?php

namespace App\Services\Teams;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Resolves what a team role is allowed to do.
 *
 * Role assignment lives on the `team_members` pivot; this class answers the
 * separate question of what that role may do, reading the seeded
 * role/permission records so the mapping can be tuned in the database without
 * a code change. Registered as a singleton so the map is built at most once
 * per request.
 */
final class RolePermissionRegistry
{
    /**
     * Permission names keyed by role name.
     *
     * @var array<string, array<string, true>>|null
     */
    private ?array $map = null;

    public function __construct(private readonly PermissionRegistrar $registrar) {}

    /**
     * Determine if the given role is granted the given permission.
     *
     * Falls back to the enum's default map when the role has no seeded record,
     * so a database that has not been seeded yet is not locked down entirely.
     */
    public function allows(TeamRole $role, TeamPermission $permission): bool
    {
        $map = $this->map();

        if (! array_key_exists($role->value, $map)) {
            return $role->hasPermission($permission);
        }

        return isset($map[$role->value][$permission->value]);
    }

    /**
     * Get every permission granted to the given role.
     *
     * @return array<TeamPermission>
     */
    public function permissionsFor(TeamRole $role): array
    {
        return array_values(array_filter(
            TeamPermission::cases(),
            fn (TeamPermission $permission): bool => $this->allows($role, $permission),
        ));
    }

    /**
     * Forget the in-memory map. Call after mutating role permissions.
     */
    public function flush(): void
    {
        $this->map = null;

        $this->registrar->forgetCachedPermissions();
    }

    /**
     * Build the role name => permission names lookup from the seeded records.
     *
     * Costs a single query per request thanks to the memoization above.
     *
     * @return array<string, array<string, true>>
     */
    private function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $map = [];

        foreach (Role::with('permissions')->get() as $role) {
            $map[$role->name] = [];

            foreach ($role->permissions as $permission) {
                $map[$role->name][$permission->name] = true;
            }
        }

        return $this->map = $map;
    }
}
