<?php

use App\Enums\BoardVisibility;
use App\Enums\TeamRole;
use App\Models\Board;
use App\Models\Team;
use App\Models\User;

/**
 * Create a workspace with the given user attached in the given role.
 *
 * @return array{0: User, 1: Team}
 */
function workspaceFor(TeamRole $role = TeamRole::Owner): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => $role->value]);
    $user->update(['current_team_id' => $team->id]);

    return [$user, $team];
}

test('members can see the boards index', function () {
    [$user, $team] = workspaceFor(TeamRole::Member);

    $board = Board::factory()->forTeam($team)->createdBy($user)->create(['name' => 'Roadmap']);

    $this->actingAs($user)
        ->get(route('boards.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertSee($board->name);
});

test('members can create a board', function () {
    [$user, $team] = workspaceFor(TeamRole::Member);

    $this->actingAs($user)
        ->post(route('boards.store', ['current_team' => $team->slug]), [
            'name' => 'Product Roadmap',
            'description' => 'Where the work lives',
            'background' => 'blue',
            'visibility' => BoardVisibility::Workspace->value,
        ])
        ->assertRedirect();

    $board = Board::where('team_id', $team->id)->firstOrFail();

    expect($board->name)->toEqual('Product Roadmap')
        ->and($board->slug)->toEqual('product-roadmap')
        ->and($board->background)->toEqual('blue')
        ->and($board->created_by)->toEqual($user->id)
        ->and($board->visibility)->toEqual(BoardVisibility::Workspace);
});

test('viewers cannot create a board', function () {
    [$user, $team] = workspaceFor(TeamRole::Viewer);

    $this->actingAs($user)
        ->post(route('boards.store', ['current_team' => $team->slug]), ['name' => 'Nope'])
        ->assertForbidden();

    expect(Board::count())->toEqual(0);
});

test('viewers can see boards but not the create button', function () {
    [$user, $team] = workspaceFor(TeamRole::Viewer);

    Board::factory()->forTeam($team)->createdBy($user)->create(['name' => 'Roadmap']);

    $this->actingAs($user)
        ->get(route('boards.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertSee('Roadmap')
        ->assertDontSee('boards-new-board-button');
});

test('the new board button is wired to the flux modal trigger', function () {
    [$user, $team] = workspaceFor(TeamRole::Member);

    $html = $this->actingAs($user)
        ->get(route('boards.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->getContent();

    // Flux only listens for `modal-show`; `open-modal` is silently ignored.
    expect($html)->not->toContain('open-modal')
        ->and($html)->toContain('data-flux-modal-trigger')
        ->and($html)->toContain("modal-show', { name: 'create-board' }");
});

test('the board menu opens its modals with the event flux listens for', function () {
    [$user, $team] = workspaceFor();

    $board = Board::factory()->forTeam($team)->createdBy($user)->create();

    $html = $this->actingAs($user)
        ->get(route('boards.show', ['current_team' => $team->slug, 'board' => $board]))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('open-modal')
        ->and($html)->toContain("modal-show', { name: 'board-settings' }")
        ->and($html)->toContain("modal-show', { name: 'delete-board' }")
        ->and($html)->toContain("modal-show', { name: 'board-members' }");
});

test('the archive and delete forms are reachable from the board menu', function () {
    [$user, $team] = workspaceFor();

    $board = Board::factory()->forTeam($team)->createdBy($user)->create();

    $html = $this->actingAs($user)
        ->get(route('boards.show', ['current_team' => $team->slug, 'board' => $board]))
        ->assertOk()
        ->getContent();

    // $refs cannot reach these forms from the dropdown's Alpine scope.
    // (Flux components use $refs internally within their own scope; that is fine.)
    expect($html)->not->toContain('$refs.archiveBoard')
        ->and($html)->not->toContain('$refs.restoreBoard')
        ->and($html)->toContain('id="archive-board-form"')
        ->and($html)->toContain('id="restore-board-form"')
        ->and($html)->toContain("getElementById('archive-board-form')");
});

test('an archived board offers a working restore control', function () {
    [$user, $team] = workspaceFor();

    $board = Board::factory()->forTeam($team)->createdBy($user)->archived()->create();

    $html = $this->actingAs($user)
        ->get(route('boards.show', ['current_team' => $team->slug, 'board' => $board]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain("getElementById('restore-board-form')")
        ->and($html)->toContain('id="restore-board-form"')
        ->and($html)->not->toContain('$refs.restoreBoard');
});

test('board creation is validated', function () {
    [$user, $team] = workspaceFor();

    $this->actingAs($user)
        ->post(route('boards.store', ['current_team' => $team->slug]), [
            'name' => '',
            'background' => 'not-a-real-background',
        ])
        ->assertSessionHasErrors(['name', 'background']);
});

test('board slugs are unique per workspace but may repeat across workspaces', function () {
    [$user, $team] = workspaceFor();
    [$otherUser, $otherTeam] = workspaceFor();

    $first = Board::factory()->forTeam($team)->createdBy($user)->create(['name' => 'Roadmap']);
    $second = Board::factory()->forTeam($team)->createdBy($user)->create(['name' => 'Roadmap']);
    $elsewhere = Board::factory()->forTeam($otherTeam)->createdBy($otherUser)->create(['name' => 'Roadmap']);

    expect($first->slug)->toEqual('roadmap')
        ->and($second->slug)->toEqual('roadmap-1')
        ->and($elsewhere->slug)->toEqual('roadmap');
});

test('members can update a board', function () {
    [$user, $team] = workspaceFor(TeamRole::Member);

    $board = Board::factory()->forTeam($team)->createdBy($user)->create(['name' => 'Old name']);

    $this->actingAs($user)
        ->patch(route('boards.update', ['current_team' => $team->slug, 'board' => $board]), [
            'name' => 'New name',
            'background' => 'rose',
            'visibility' => BoardVisibility::Workspace->value,
        ])
        ->assertRedirect();

    expect($board->fresh()->name)->toEqual('New name')
        ->and($board->fresh()->slug)->toEqual('new-name')
        ->and($board->fresh()->background)->toEqual('rose');
});

test('members cannot delete a board but admins can', function () {
    [$member, $team] = workspaceFor(TeamRole::Member);

    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $board = Board::factory()->forTeam($team)->createdBy($member)->create();

    $this->actingAs($member)
        ->delete(route('boards.destroy', ['current_team' => $team->slug, 'board' => $board]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('boards.destroy', ['current_team' => $team->slug, 'board' => $board]))
        ->assertRedirect(route('boards.index', ['current_team' => $team->slug]));

    expect(Board::find($board->id))->toBeNull()
        ->and($board->fresh()->trashed())->toBeTrue();
});

test('boards can be archived and restored', function () {
    [$user, $team] = workspaceFor(TeamRole::Member);

    $board = Board::factory()->forTeam($team)->createdBy($user)->create(['name' => 'Quarterly Planning Board']);

    $this->actingAs($user)
        ->post(route('boards.archive', ['current_team' => $team->slug, 'board' => $board]))
        ->assertRedirect();

    expect($board->fresh()->isArchived())->toBeTrue();

    $this->actingAs($user)
        ->get(route('boards.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertDontSee($board->name);

    $this->actingAs($user)
        ->get(route('boards.index', ['current_team' => $team->slug, 'archived' => 1]))
        ->assertOk()
        ->assertSee($board->name);

    $this->actingAs($user)
        ->post(route('boards.restore', ['current_team' => $team->slug, 'board' => $board]))
        ->assertRedirect();

    expect($board->fresh()->isArchived())->toBeFalse();
});

test('private boards are hidden from workspace members who are not board members', function () {
    [$owner, $team] = workspaceFor();

    $outsider = User::factory()->create();
    $team->members()->attach($outsider, ['role' => TeamRole::Member->value]);

    $board = Board::factory()->forTeam($team)->createdBy($owner)->private()->create(['name' => 'Secret Plans']);
    $board->boardMemberships()->create(['user_id' => $owner->id, 'role' => TeamRole::Admin]);

    $this->actingAs($outsider)
        ->get(route('boards.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertDontSee('Secret Plans');

    $this->actingAs($outsider)
        ->get(route('boards.show', ['current_team' => $team->slug, 'board' => $board]))
        ->assertForbidden();

    $this->actingAs($owner)
        ->get(route('boards.show', ['current_team' => $team->slug, 'board' => $board]))
        ->assertOk();
});

test('creating a private board adds the creator as a board member', function () {
    [$user, $team] = workspaceFor();

    $this->actingAs($user)
        ->post(route('boards.store', ['current_team' => $team->slug]), [
            'name' => 'Private Board',
            'visibility' => BoardVisibility::Private->value,
        ])
        ->assertRedirect();

    $board = Board::where('team_id', $team->id)->firstOrFail();

    expect($board->boardMemberships()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('switching a board to private keeps the creator able to reach it', function () {
    [$user, $team] = workspaceFor();

    $board = Board::factory()->forTeam($team)->createdBy($user)->create();

    $this->actingAs($user)
        ->patch(route('boards.update', ['current_team' => $team->slug, 'board' => $board]), [
            'name' => $board->name,
            'visibility' => BoardVisibility::Private->value,
        ])
        ->assertRedirect();

    expect($board->fresh()->isPrivate())->toBeTrue();

    $this->actingAs($user)
        ->get(route('boards.show', ['current_team' => $team->slug, 'board' => $board]))
        ->assertOk();
});

test('boards from another workspace are not reachable', function () {
    [$user, $team] = workspaceFor();
    [$otherUser, $otherTeam] = workspaceFor();

    $foreignBoard = Board::factory()->forTeam($otherTeam)->createdBy($otherUser)->create();

    $this->actingAs($user)
        ->get(route('boards.show', ['current_team' => $team->slug, 'board' => $foreignBoard->slug]))
        ->assertNotFound();
});

test('non members cannot reach a workspace board list', function () {
    [$owner, $team] = workspaceFor();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('boards.index', ['current_team' => $team->slug]))
        ->assertForbidden();
});

test('guests are redirected to login', function () {
    [$user, $team] = workspaceFor();

    $this->get(route('boards.index', ['current_team' => $team->slug]))
        ->assertRedirect(route('login'));
});

test('new boards are appended after existing ones', function () {
    [$user, $team] = workspaceFor();

    $first = Board::factory()->forTeam($team)->createdBy($user)->create(['position' => 100]);

    $this->actingAs($user)
        ->post(route('boards.store', ['current_team' => $team->slug]), ['name' => 'Second'])
        ->assertRedirect();

    $second = Board::where('name', 'Second')->firstOrFail();

    expect($second->position)->toBeGreaterThan($first->position);
});
