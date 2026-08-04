<?php

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Services\Teams\RolePermissionRegistry;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('every permission case is seeded', function () {
    foreach (TeamPermission::cases() as $permission) {
        expect(Permission::where('name', $permission->value)->exists())
            ->toBeTrue("Permission [{$permission->value}] was not seeded.");
    }
});

test('every role is seeded with its default permissions', function () {
    foreach (TeamRole::cases() as $role) {
        $seeded = Role::findByName($role->value)->permissions->pluck('name')->sort()->values()->all();

        $expected = collect($role->permissions())
            ->map(fn (TeamPermission $permission) => $permission->value)
            ->sort()
            ->values()
            ->all();

        expect($seeded)->toEqual($expected, "Role [{$role->value}] permissions do not match its defaults.");
    }
});

test('the seeder is idempotent', function () {
    $this->seed(PermissionSeeder::class);

    expect(Permission::count())->toEqual(count(TeamPermission::cases()))
        ->and(Role::count())->toEqual(count(TeamRole::cases()));
});

test('role permission matrix is enforced', function (TeamRole $role, TeamPermission $permission, bool $allowed) {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => $role->value]);

    expect($user->hasTeamPermission($team, $permission))->toEqual($allowed);
})->with([
    'viewer can view boards' => [TeamRole::Viewer, TeamPermission::ViewBoard, true],
    'viewer cannot create boards' => [TeamRole::Viewer, TeamPermission::CreateBoard, false],
    'viewer cannot create cards' => [TeamRole::Viewer, TeamPermission::CreateCard, false],
    'viewer cannot comment' => [TeamRole::Viewer, TeamPermission::CreateComment, false],

    'member can create boards' => [TeamRole::Member, TeamPermission::CreateBoard, true],
    'member can move cards' => [TeamRole::Member, TeamPermission::MoveCard, true],
    'member can comment' => [TeamRole::Member, TeamPermission::CreateComment, true],
    'member cannot delete boards' => [TeamRole::Member, TeamPermission::DeleteBoard, false],
    'member cannot update the team' => [TeamRole::Member, TeamPermission::UpdateTeam, false],
    'member cannot invite' => [TeamRole::Member, TeamPermission::CreateInvitation, false],

    'admin can delete boards' => [TeamRole::Admin, TeamPermission::DeleteBoard, true],
    'admin can manage board members' => [TeamRole::Admin, TeamPermission::ManageBoardMembers, true],
    'admin can invite' => [TeamRole::Admin, TeamPermission::CreateInvitation, true],
    'admin can update the team' => [TeamRole::Admin, TeamPermission::UpdateTeam, true],
    'admin cannot remove members' => [TeamRole::Admin, TeamPermission::RemoveMember, false],
    'admin cannot update member roles' => [TeamRole::Admin, TeamPermission::UpdateMember, false],
    'admin cannot delete the team' => [TeamRole::Admin, TeamPermission::DeleteTeam, false],

    'owner can remove members' => [TeamRole::Owner, TeamPermission::RemoveMember, true],
    'owner can delete the team' => [TeamRole::Owner, TeamPermission::DeleteTeam, true],
    'owner can delete boards' => [TeamRole::Owner, TeamPermission::DeleteBoard, true],
]);

test('non members have no permissions on a team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    foreach (TeamPermission::cases() as $permission) {
        expect($user->hasTeamPermission($team, $permission))->toBeFalse();
    }
});

test('permissions can be tuned in the database without a code change', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Viewer->value]);

    expect($user->hasTeamPermission($team, TeamPermission::CreateComment))->toBeFalse();

    Role::findByName(TeamRole::Viewer->value)
        ->givePermissionTo(TeamPermission::CreateComment->value);

    app(RolePermissionRegistry::class)->flush();

    expect($user->hasTeamPermission($team, TeamPermission::CreateComment))->toBeTrue();
});

test('the registry falls back to enum defaults when a role is not seeded', function () {
    Role::findByName(TeamRole::Member->value)->delete();

    app(RolePermissionRegistry::class)->flush();

    $registry = app(RolePermissionRegistry::class);

    expect($registry->allows(TeamRole::Member, TeamPermission::CreateCard))->toBeTrue()
        ->and($registry->allows(TeamRole::Member, TeamPermission::DeleteBoard))->toBeFalse();
});

test('team role is memoized and can be flushed', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    expect($user->teamRole($team))->toEqual(TeamRole::Member);

    $team->members()->updateExistingPivot($user->id, ['role' => TeamRole::Admin->value]);

    expect($user->teamRole($team))->toEqual(TeamRole::Member);

    $user->flushTeamRoleCache();

    expect($user->teamRole($team))->toEqual(TeamRole::Admin);
});

test('role hierarchy places viewer below member', function () {
    expect(TeamRole::Viewer->isAtLeast(TeamRole::Member))->toBeFalse()
        ->and(TeamRole::Member->isAtLeast(TeamRole::Viewer))->toBeTrue()
        ->and(TeamRole::Owner->isAtLeast(TeamRole::Admin))->toBeTrue();
});

test('viewer is assignable to members', function () {
    expect(collect(TeamRole::assignable())->pluck('value')->all())
        ->toEqual([TeamRole::Admin->value, TeamRole::Member->value, TeamRole::Viewer->value]);
});
