<?php

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Services\Teams\TeamMemberService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('the owner can transfer ownership and becomes an admin', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($owner)
        ->patch(route('teams.ownership.update', ['team' => $team->slug]), ['user_id' => $member->id])
        ->assertRedirect();

    expect($member->fresh()->teamRole($team))->toEqual(TeamRole::Owner)
        ->and($owner->fresh()->teamRole($team))->toEqual(TeamRole::Admin);
});

test('a non owner cannot transfer ownership', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($admin)
        ->patch(route('teams.ownership.update', ['team' => $team->slug]), ['user_id' => $member->id])
        ->assertForbidden();

    expect($owner->fresh()->teamRole($team))->toEqual(TeamRole::Owner)
        ->and($member->fresh()->teamRole($team))->toEqual(TeamRole::Member);
});

test('ownership cannot be transferred to a non member', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $this->actingAs($owner)
        ->patch(route('teams.ownership.update', ['team' => $team->slug]), ['user_id' => $stranger->id])
        ->assertSessionHasErrors('user_id');

    expect($owner->fresh()->teamRole($team))->toEqual(TeamRole::Owner);
});

test('ownership cannot be transferred to yourself', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($owner)
        ->patch(route('teams.ownership.update', ['team' => $team->slug]), ['user_id' => $owner->id])
        ->assertSessionHasErrors('user_id');
});

test('a member can be promoted to viewer', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($owner);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->call('updateMember', $member->id, TeamRole::Viewer->value)
        ->assertHasNoErrors();

    expect($member->fresh()->teamRole($team))->toEqual(TeamRole::Viewer);
});

test('the viewer role is offered in the member role picker', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($owner);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->assertSee('Viewer');
});

test('the owners role cannot be changed through the member service', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    expect(fn () => app(TeamMemberService::class)->changeRole($team, $owner, TeamRole::Member))
        ->toThrow(ValidationException::class);

    expect($owner->fresh()->teamRole($team))->toEqual(TeamRole::Owner);
});

test('nobody can be promoted to owner through a role change', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    expect(fn () => app(TeamMemberService::class)->changeRole($team, $member, TeamRole::Owner))
        ->toThrow(ValidationException::class);

    expect($member->fresh()->teamRole($team))->toEqual(TeamRole::Member);
});

test('the owner cannot be removed from the workspace', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    expect(fn () => app(TeamMemberService::class)->remove($team, $owner))
        ->toThrow(ValidationException::class);

    expect($owner->fresh()->belongsToTeam($team))->toBeTrue();
});

test('a role change takes effect immediately for permission checks', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Viewer->value]);

    expect($member->hasTeamPermission($team, TeamPermission::CreateBoard))->toBeFalse();

    app(TeamMemberService::class)->changeRole($team, $member, TeamRole::Member);

    expect($member->hasTeamPermission($team, TeamPermission::CreateBoard))->toBeTrue();
});
