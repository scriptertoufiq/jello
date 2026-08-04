<?php

use App\Enums\TeamRole;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

/**
 * Build a workspace with a board and the given user role.
 *
 * @return array{0: User, 1: Team, 2: Board}
 */
function boardFor(TeamRole $role = TeamRole::Member): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => $role->value]);
    $user->update(['current_team_id' => $team->id]);

    $board = Board::factory()->forTeam($team)->createdBy($user)->create();

    return [$user, $team, $board];
}

test('creating a board seeds the default lists', function () {
    [$user, $team] = boardFor();

    $this->actingAs($user)
        ->post(route('boards.store', ['current_team' => $team->slug]), ['name' => 'Fresh Board'])
        ->assertRedirect();

    $board = Board::where('name', 'Fresh Board')->firstOrFail();

    expect($board->lists->pluck('name')->all())->toEqual(config('boards.default_lists'));
});

test('default lists are created in order', function () {
    [$user, $team] = boardFor();

    $this->actingAs($user)
        ->post(route('boards.store', ['current_team' => $team->slug]), ['name' => 'Ordered Board'])
        ->assertRedirect();

    $board = Board::where('name', 'Ordered Board')->firstOrFail();
    $positions = $board->lists->pluck('position')->all();

    expect($positions)->toEqual(collect($positions)->sort()->values()->all());
});

test('members can add a list', function () {
    [$user, $team, $board] = boardFor();

    $this->actingAs($user)
        ->post(route('lists.store', ['current_team' => $team->slug, 'board' => $board]), ['name' => 'Backlog'])
        ->assertRedirect();

    expect($board->lists()->where('name', 'Backlog')->exists())->toBeTrue();
});

test('viewers cannot add a list', function () {
    [$user, $team, $board] = boardFor(TeamRole::Viewer);

    $this->actingAs($user)
        ->post(route('lists.store', ['current_team' => $team->slug, 'board' => $board]), ['name' => 'Backlog'])
        ->assertForbidden();

    expect($board->lists()->count())->toEqual(0);
});

test('lists can be renamed archived and deleted', function () {
    [$user, $team, $board] = boardFor();

    $list = BoardList::factory()->forBoard($board)->create(['name' => 'Old']);

    $this->actingAs($user)
        ->patch(route('lists.update', ['current_team' => $team->slug, 'list' => $list]), ['name' => 'New'])
        ->assertRedirect();

    expect($list->fresh()->name)->toEqual('New');

    $this->actingAs($user)
        ->post(route('lists.archive', ['current_team' => $team->slug, 'list' => $list]))
        ->assertRedirect();

    expect($list->fresh()->isArchived())->toBeTrue();

    $this->actingAs($user)
        ->delete(route('lists.destroy', ['current_team' => $team->slug, 'list' => $list]))
        ->assertRedirect();

    expect(BoardList::find($list->id))->toBeNull();
});

test('deleting a list deletes its cards', function () {
    [$user, $team, $board] = boardFor();

    $list = BoardList::factory()->forBoard($board)->create();
    $card = Card::factory()->forList($list)->createdBy($user)->create();

    $this->actingAs($user)
        ->delete(route('lists.destroy', ['current_team' => $team->slug, 'list' => $list]))
        ->assertRedirect();

    expect(Card::find($card->id))->toBeNull();
});

test('lists from another workspace cannot be touched', function () {
    [$user, $team] = boardFor();
    [$otherUser, $otherTeam, $otherBoard] = boardFor();

    $foreignList = BoardList::factory()->forBoard($otherBoard)->create();

    $this->actingAs($user)
        ->patch(route('lists.update', ['current_team' => $team->slug, 'list' => $foreignList]), ['name' => 'Hijacked'])
        ->assertForbidden();

    expect($foreignList->fresh()->name)->not->toEqual('Hijacked');
});

test('lists can be reordered', function () {
    [$user, $team, $board] = boardFor();

    $first = BoardList::factory()->forBoard($board)->atPosition(100)->create(['name' => 'First']);
    $second = BoardList::factory()->forBoard($board)->atPosition(200)->create(['name' => 'Second']);
    $third = BoardList::factory()->forBoard($board)->atPosition(300)->create(['name' => 'Third']);

    $this->actingAs($user);

    Livewire::test('pages::boards.canvas', ['board' => $board])
        ->call('moveList', $third->id, 0)
        ->assertHasNoErrors();

    expect($board->lists()->pluck('name')->all())->toEqual(['Third', 'First', 'Second']);
});

test('the canvas renders lists and cards', function () {
    [$user, $team, $board] = boardFor();

    $list = BoardList::factory()->forBoard($board)->create(['name' => 'To Do']);
    $card = Card::factory()->forList($list)->createdBy($user)->create(['title' => 'Write the tests']);

    $this->actingAs($user);

    Livewire::test('pages::boards.canvas', ['board' => $board])
        ->assertSee('To Do')
        ->assertSee('Write the tests');
});

test('the canvas renders the drag and drop wiring for members', function () {
    [$user, $team, $board] = boardFor();

    $list = BoardList::factory()->forBoard($board)->create();
    $card = Card::factory()->forList($list)->createdBy($user)->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::boards.canvas', ['board' => $board])->html();

    expect($html)
        ->toContain('wire:sort="moveList($item, $position)"')
        ->toContain('wire:sort:item="'.$list->id.'"')
        ->toContain('wire:sort:group="cards"')
        ->toContain('wire:sort="moveCard($item, '.$list->id.', $position)"')
        ->toContain('wire:sort:item="'.$card->id.'"')
        ->toContain('wire:sort:handle');
});

test('the canvas does not make anything draggable for viewers', function () {
    [$user, $team, $board] = boardFor(TeamRole::Viewer);

    $list = BoardList::factory()->forBoard($board)->create();
    Card::factory()->forList($list)->createdBy($user)->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::boards.canvas', ['board' => $board])->html();

    expect($html)
        ->not->toContain('wire:sort="moveList')
        ->not->toContain('wire:sort="moveCard')
        ->not->toContain('wire:sort:group')
        ->not->toContain('wire:sort:handle');
});

test('archived lists are hidden from the canvas', function () {
    [$user, $team, $board] = boardFor();

    BoardList::factory()->forBoard($board)->create(['name' => 'Visible']);
    BoardList::factory()->forBoard($board)->archived()->create(['name' => 'Hidden']);

    $this->actingAs($user);

    Livewire::test('pages::boards.canvas', ['board' => $board])
        ->assertSee('Visible')
        ->assertDontSee('Hidden');
});
