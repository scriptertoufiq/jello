<?php

use App\Enums\TeamRole;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Label;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

/**
 * Build a workspace with a board and two ordered lists.
 *
 * @return array{0: User, 1: Team, 2: Board, 3: BoardList, 4: BoardList}
 */
function listViewFixture(TeamRole $role = TeamRole::Member): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => $role->value]);
    $user->update(['current_team_id' => $team->id]);

    $board = Board::factory()->forTeam($team)->createdBy($user)->create();
    $todo = BoardList::factory()->forBoard($board)->atPosition(100)->create(['name' => 'To Do']);
    $done = BoardList::factory()->forBoard($board)->atPosition(200)->create(['name' => 'Done']);

    return [$user, $team, $board, $todo, $done];
}

test('the board page renders the kanban canvas by default', function () {
    [$user, $team, $board] = listViewFixture();

    $this->actingAs($user)
        ->get(route('boards.show', ['current_team' => $team->slug, 'board' => $board]))
        ->assertOk()
        ->assertSee('data-test="board-canvas"', false)
        ->assertDontSee('data-test="board-list-view"', false);
});

test('the board page renders the list view when asked', function () {
    [$user, $team, $board] = listViewFixture();

    $this->actingAs($user)
        ->get(route('boards.show', ['current_team' => $team->slug, 'board' => $board, 'view' => 'list']))
        ->assertOk()
        ->assertSee('data-test="board-list-view"', false)
        ->assertDontSee('data-test="board-canvas"', false);
});

test('an unknown view falls back to the board', function () {
    [$user, $team, $board] = listViewFixture();

    $this->actingAs($user)
        ->get(route('boards.show', ['current_team' => $team->slug, 'board' => $board, 'view' => 'nonsense']))
        ->assertOk()
        ->assertSee('data-test="board-canvas"', false);
});

test('both view toggles are offered', function () {
    [$user, $team, $board] = listViewFixture();

    $this->actingAs($user)
        ->get(route('boards.show', ['current_team' => $team->slug, 'board' => $board]))
        ->assertOk()
        ->assertSee('board-view-toggle-board')
        ->assertSee('board-view-toggle-list');
});

test('the list view shows every card across lists', function () {
    [$user, $team, $board, $todo, $done] = listViewFixture();

    Card::factory()->forList($todo)->createdBy($user)->create(['title' => 'First task']);
    Card::factory()->forList($done)->createdBy($user)->create(['title' => 'Second task']);

    $this->actingAs($user);

    Livewire::test('pages::boards.list-view', ['board' => $board])
        ->assertSee('First task')
        ->assertSee('Second task')
        ->assertSee('To Do')
        ->assertSee('Done');
});

test('board order groups cards under their list in column order', function () {
    [$user, $team, $board, $todo, $done] = listViewFixture();

    Card::factory()->forList($done)->createdBy($user)->atPosition(10)->create(['title' => 'In done']);
    Card::factory()->forList($todo)->createdBy($user)->atPosition(10)->create(['title' => 'In todo']);

    $this->actingAs($user);

    $component = Livewire::test('pages::boards.list-view', ['board' => $board]);

    expect($component->get('cards')->pluck('title')->all())->toEqual(['In todo', 'In done']);

    $component->assertSee('list-view-group');
});

test('sorting by due date flattens across lists and puts undated cards last', function () {
    [$user, $team, $board, $todo, $done] = listViewFixture();

    Card::factory()->forList($todo)->createdBy($user)->create(['title' => 'No date']);
    Card::factory()->forList($done)->createdBy($user)->dueAt(now()->addDay())->create(['title' => 'Due soon']);
    Card::factory()->forList($todo)->createdBy($user)->dueAt(now()->addWeek())->create(['title' => 'Due later']);

    $this->actingAs($user);

    $component = Livewire::test('pages::boards.list-view', ['board' => $board])
        ->set('sort', 'due');

    expect($component->get('cards')->pluck('title')->all())
        ->toEqual(['Due soon', 'Due later', 'No date']);
});

test('sorting by title flattens alphabetically', function () {
    [$user, $team, $board, $todo, $done] = listViewFixture();

    Card::factory()->forList($done)->createdBy($user)->create(['title' => 'Alpha']);
    Card::factory()->forList($todo)->createdBy($user)->create(['title' => 'Zulu']);
    Card::factory()->forList($done)->createdBy($user)->create(['title' => 'Mike']);

    $this->actingAs($user);

    $component = Livewire::test('pages::boards.list-view', ['board' => $board])
        ->set('sort', 'title');

    expect($component->get('cards')->pluck('title')->all())->toEqual(['Alpha', 'Mike', 'Zulu']);
});

test('completed cards can be hidden', function () {
    [$user, $team, $board, $todo] = listViewFixture();

    Card::factory()->forList($todo)->createdBy($user)->create(['title' => 'Still open']);
    Card::factory()->forList($todo)->createdBy($user)->completed()->create(['title' => 'All finished']);

    $this->actingAs($user);

    Livewire::test('pages::boards.list-view', ['board' => $board])
        ->assertSee('All finished')
        ->set('hideCompleted', true)
        ->assertSee('Still open')
        ->assertDontSee('All finished');
});

test('archived cards and lists never appear', function () {
    [$user, $team, $board, $todo] = listViewFixture();

    Card::factory()->forList($todo)->createdBy($user)->create(['title' => 'Visible task']);
    Card::factory()->forList($todo)->createdBy($user)->archived()->create(['title' => 'Archived task']);

    $archivedList = BoardList::factory()->forBoard($board)->archived()->create();
    Card::factory()->forList($archivedList)->createdBy($user)->create(['title' => 'Task in archived list']);

    $this->actingAs($user);

    Livewire::test('pages::boards.list-view', ['board' => $board])
        ->assertSee('Visible task')
        ->assertDontSee('Archived task')
        ->assertDontSee('Task in archived list');
});

test('cards from other boards never appear', function () {
    [$user, $team, $board, $todo] = listViewFixture();
    [$otherUser, $otherTeam, $otherBoard, $otherTodo] = listViewFixture();

    Card::factory()->forList($todo)->createdBy($user)->create(['title' => 'Mine']);
    Card::factory()->forList($otherTodo)->createdBy($otherUser)->create(['title' => 'Theirs']);

    $this->actingAs($user);

    Livewire::test('pages::boards.list-view', ['board' => $board])
        ->assertSee('Mine')
        ->assertDontSee('Theirs');
});

test('a card can be completed from the list view', function () {
    [$user, $team, $board, $todo] = listViewFixture();

    $card = Card::factory()->forList($todo)->createdBy($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::boards.list-view', ['board' => $board])
        ->call('toggleComplete', $card->id)
        ->assertDispatched('card-saved');

    expect($card->fresh()->isComplete())->toBeTrue();
});

test('viewers cannot complete cards from the list view', function () {
    [$owner, $team, $board, $todo] = listViewFixture(TeamRole::Owner);

    $viewer = User::factory()->create();
    $team->members()->attach($viewer, ['role' => TeamRole::Viewer->value]);

    $card = Card::factory()->forList($todo)->createdBy($owner)->create();

    $this->actingAs($viewer);

    Livewire::test('pages::boards.list-view', ['board' => $board])
        ->call('toggleComplete', $card->id)
        ->assertForbidden();

    expect($card->fresh()->isComplete())->toBeFalse();
});

test('list view rows open the same card modal as the canvas', function () {
    [$user, $team, $board, $todo] = listViewFixture();

    $card = Card::factory()->forList($todo)->createdBy($user)->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::boards.list-view', ['board' => $board])->html();

    expect($html)->toContain('data-flux-modal-trigger')
        ->and($html)->toContain("open-card', { cardId: {$card->id} }");
});

test('the list view refreshes when the modal saves a card', function () {
    [$user, $team, $board, $todo] = listViewFixture();

    Card::factory()->forList($todo)->createdBy($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::boards.list-view', ['board' => $board])
        ->call('refreshCards')
        ->assertHasNoErrors();
});

test('the list view renders labels assignees and due dates', function () {
    [$user, $team, $board, $todo] = listViewFixture();

    $label = Label::factory()->forBoard($board)->create(['name' => 'Urgent']);
    $card = Card::factory()->forList($todo)->createdBy($user)->overdue()->create(['title' => 'Loaded card']);

    $card->labels()->attach($label->id);
    $card->assignees()->attach($user->id);

    $this->actingAs($user);

    Livewire::test('pages::boards.list-view', ['board' => $board])
        ->assertSee('Loaded card')
        ->assertSee('Urgent')
        ->assertSee($card->due_date->format('M j'));
});

test('the card modal is available in the list view too', function () {
    [$user, $team, $board] = listViewFixture();

    $this->actingAs($user)
        ->get(route('boards.show', ['current_team' => $team->slug, 'board' => $board, 'view' => 'list']))
        ->assertOk()
        ->assertSee('data-modal="card-detail"', false);
});
