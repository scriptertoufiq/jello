<?php

use App\Enums\TeamRole;
use App\Models\Board;
use App\Models\Team;
use App\Models\User;

/**
 * Build a workspace with a private board owned by the given user.
 *
 * @return array{0: User, 1: Team, 2: Board}
 */
function privateBoardFixture(TeamRole $role = TeamRole::Admin): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => $role->value]);
    $user->update(['current_team_id' => $team->id]);

    $board = Board::factory()->forTeam($team)->createdBy($user)->private()->create();
    $board->boardMemberships()->create(['user_id' => $user->id, 'role' => TeamRole::Admin]);

    return [$user, $team, $board];
}

test('admins can add a workspace member to a private board', function () {
    [$admin, $team, $board] = privateBoardFixture();

    $teammate = User::factory()->create();
    $team->members()->attach($teammate, ['role' => TeamRole::Member->value]);

    $this->actingAs($admin)
        ->post(route('board-members.store', ['current_team' => $team->slug, 'board' => $board]), [
            'user_id' => $teammate->id,
            'role' => TeamRole::Member->value,
        ])
        ->assertRedirect();

    expect($board->boardMemberships()->where('user_id', $teammate->id)->exists())->toBeTrue();

    // Adding them makes the private board reachable.
    $this->actingAs($teammate)
        ->get(route('boards.show', ['current_team' => $team->slug, 'board' => $board]))
        ->assertOk();
});

test('members cannot manage board members', function () {
    [$admin, $team, $board] = privateBoardFixture();

    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $board->boardMemberships()->create(['user_id' => $member->id, 'role' => TeamRole::Member]);

    $other = User::factory()->create();
    $team->members()->attach($other, ['role' => TeamRole::Member->value]);

    $this->actingAs($member)
        ->post(route('board-members.store', ['current_team' => $team->slug, 'board' => $board]), [
            'user_id' => $other->id,
        ])
        ->assertForbidden();
});

test('a user outside the workspace cannot be added to a board', function () {
    [$admin, $team, $board] = privateBoardFixture();

    $stranger = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('board-members.store', ['current_team' => $team->slug, 'board' => $board]), [
            'user_id' => $stranger->id,
        ])
        ->assertSessionHasErrors('user_id');

    expect($board->boardMemberships()->where('user_id', $stranger->id)->exists())->toBeFalse();
});

test('board members can be removed', function () {
    [$admin, $team, $board] = privateBoardFixture();

    $teammate = User::factory()->create();
    $team->members()->attach($teammate, ['role' => TeamRole::Member->value]);
    $board->boardMemberships()->create(['user_id' => $teammate->id, 'role' => TeamRole::Member]);

    $this->actingAs($admin)
        ->delete(route('board-members.destroy', ['current_team' => $team->slug, 'board' => $board, 'member' => $teammate]))
        ->assertRedirect();

    expect($board->boardMemberships()->where('user_id', $teammate->id)->exists())->toBeFalse();

    // And they lose access again.
    $this->actingAs($teammate)
        ->get(route('boards.show', ['current_team' => $team->slug, 'board' => $board]))
        ->assertForbidden();
});

test('the last member of a private board cannot be removed', function () {
    [$admin, $team, $board] = privateBoardFixture();

    expect($board->boardMemberships()->count())->toEqual(1);

    $this->actingAs($admin)
        ->delete(route('board-members.destroy', ['current_team' => $team->slug, 'board' => $board, 'member' => $admin]))
        ->assertSessionHasErrors('user_id');

    expect($board->boardMemberships()->count())->toEqual(1);
});

test('a board member role can be changed', function () {
    [$admin, $team, $board] = privateBoardFixture();

    $teammate = User::factory()->create();
    $team->members()->attach($teammate, ['role' => TeamRole::Member->value]);
    $board->boardMemberships()->create(['user_id' => $teammate->id, 'role' => TeamRole::Member]);

    $this->actingAs($admin)
        ->patch(route('board-members.update', ['current_team' => $team->slug, 'board' => $board]), [
            'user_id' => $teammate->id,
            'role' => TeamRole::Viewer->value,
        ])
        ->assertRedirect();

    expect($board->boardMemberships()->where('user_id', $teammate->id)->first()->role)
        ->toEqual(TeamRole::Viewer);
});

test('adding the same board member twice does not duplicate', function () {
    [$admin, $team, $board] = privateBoardFixture();

    $teammate = User::factory()->create();
    $team->members()->attach($teammate, ['role' => TeamRole::Member->value]);

    foreach (range(1, 2) as $ignored) {
        $this->actingAs($admin)
            ->post(route('board-members.store', ['current_team' => $team->slug, 'board' => $board]), [
                'user_id' => $teammate->id,
            ])
            ->assertRedirect();
    }

    expect($board->boardMemberships()->where('user_id', $teammate->id)->count())->toEqual(1);
});

test('a board from another workspace cannot have members managed', function () {
    [$admin, $team] = privateBoardFixture();
    [$otherAdmin, $otherTeam, $foreignBoard] = privateBoardFixture();

    $teammate = User::factory()->create();
    $team->members()->attach($teammate, ['role' => TeamRole::Member->value]);

    $this->actingAs($admin)
        ->post(route('board-members.store', ['current_team' => $team->slug, 'board' => $foreignBoard->slug]), [
            'user_id' => $teammate->id,
        ])
        ->assertNotFound();
});
