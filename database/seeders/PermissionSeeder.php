<?php

namespace Database\Seeders;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Seed the role and permission records from the TeamRole/TeamPermission enums.
     *
     * Idempotent: safe to re-run after adding an enum case. Existing roles are
     * re-synced to the enum defaults, so any manual database tuning of a role's
     * permissions is intentionally reset by running this seeder again.
     */
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $registrar->forgetCachedPermissions();

        foreach (TeamPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, config('auth.defaults.guard'));
        }

        foreach (TeamRole::cases() as $role) {
            Role::findOrCreate($role->value, config('auth.defaults.guard'))
                ->syncPermissions(array_map(
                    fn (TeamPermission $permission): string => $permission->value,
                    $role->permissions(),
                ));
        }

        $registrar->forgetCachedPermissions();
    }
}
