<?php

use App\Enums\TeamRole;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

/**
 * Build a workspace with a board, one list, and the given user role.
 *
 * @return array{0: User, 1: Team, 2: Board, 3: BoardList}
 */
function cardFixture(TeamRole $role = TeamRole::Member): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => $role->value]);
    $user->update(['current_team_id' => $team->id]);

    $board = Board::factory()->forTeam($team)->createdBy($user)->create();
    $list = BoardList::factory()->forBoard($board)->create(['name' => 'To Do']);

    return [$user, $team, $board, $list];
}

test('members can add a card', function () {
    [$user, $team, $board, $list] = cardFixture();

    $this->actingAs($user)
        ->post(route('cards.store', ['current_team' => $team->slug, 'list' => $list]), ['title' => 'Ship it'])
        ->assertRedirect();

    $card = Card::firstOrFail();

    expect($card->title)->toEqual('Ship it')
        ->and($card->board_list_id)->toEqual($list->id)
        ->and($card->created_by)->toEqual($user->id);
});

test('viewers cannot add a card', function () {
    [$user, $team, $board, $list] = cardFixture(TeamRole::Viewer);

    $this->actingAs($user)
        ->post(route('cards.store', ['current_team' => $team->slug, 'list' => $list]), ['title' => 'Nope'])
        ->assertForbidden();

    expect(Card::count())->toEqual(0);
});

test('new cards are appended to the end of the list', function () {
    [$user, $team, $board, $list] = cardFixture();

    Card::factory()->forList($list)->createdBy($user)->atPosition(100)->create(['title' => 'First']);

    $this->actingAs($user)
        ->post(route('cards.store', ['current_team' => $team->slug, 'list' => $list]), ['title' => 'Second'])
        ->assertRedirect();

    expect($list->cards()->pluck('title')->all())->toEqual(['First', 'Second']);
});

test('cards can be updated', function () {
    [$user, $team, $board, $list] = cardFixture();

    $card = Card::factory()->forList($list)->createdBy($user)->create();

    $this->actingAs($user)
        ->patch(route('cards.update', ['current_team' => $team->slug, 'card' => $card]), [
            'title' => 'Renamed',
            'description' => 'Some detail',
            'due_date' => '2026-12-01 09:00:00',
        ])
        ->assertRedirect();

    $card->refresh();

    expect($card->title)->toEqual('Renamed')
        ->and($card->description)->toEqual('Some detail')
        ->and($card->due_date->format('Y-m-d'))->toEqual('2026-12-01');
});

test('a due date before the start date is rejected', function () {
    [$user, $team, $board, $list] = cardFixture();

    $card = Card::factory()->forList($list)->createdBy($user)->create();

    $this->actingAs($user)
        ->patch(route('cards.update', ['current_team' => $team->slug, 'card' => $card]), [
            'start_date' => '2026-12-10',
            'due_date' => '2026-12-01',
        ])
        ->assertSessionHasErrors('due_date');
});

test('cards can be completed archived and deleted', function () {
    [$user, $team, $board, $list] = cardFixture();

    $card = Card::factory()->forList($list)->createdBy($user)->create();

    $this->actingAs($user)
        ->post(route('cards.complete', ['current_team' => $team->slug, 'card' => $card]))
        ->assertRedirect();

    expect($card->fresh()->isComplete())->toBeTrue();

    $this->actingAs($user)
        ->post(route('cards.archive', ['current_team' => $team->slug, 'card' => $card]))
        ->assertRedirect();

    expect($card->fresh()->isArchived())->toBeTrue();

    $this->actingAs($user)
        ->delete(route('cards.destroy', ['current_team' => $team->slug, 'card' => $card]))
        ->assertRedirect();

    expect(Card::find($card->id))->toBeNull();
});

test('a card can be moved to the top middle and bottom of its list', function (int $index, array $expected) {
    [$user, $team, $board, $list] = cardFixture();

    $a = Card::factory()->forList($list)->createdBy($user)->atPosition(100)->create(['title' => 'A']);
    $b = Card::factory()->forList($list)->createdBy($user)->atPosition(200)->create(['title' => 'B']);
    $c = Card::factory()->forList($list)->createdBy($user)->atPosition(300)->create(['title' => 'C']);
    $moving = Card::factory()->forList($list)->createdBy($user)->atPosition(400)->create(['title' => 'M']);

    $this->actingAs($user)
        ->post(route('cards.move', ['current_team' => $team->slug, 'card' => $moving]), [
            'board_list_id' => $list->id,
            'index' => $index,
        ])
        ->assertRedirect();

    expect($list->cards()->pluck('title')->all())->toEqual($expected);
})->with([
    'to the top' => [0, ['M', 'A', 'B', 'C']],
    'to the middle' => [2, ['A', 'B', 'M', 'C']],
    'to the bottom' => [3, ['A', 'B', 'C', 'M']],
]);

test('a card can be moved to another list', function () {
    [$user, $team, $board, $todo] = cardFixture();

    $done = BoardList::factory()->forBoard($board)->create(['name' => 'Done']);

    $card = Card::factory()->forList($todo)->createdBy($user)->create(['title' => 'Move me']);
    Card::factory()->forList($done)->createdBy($user)->atPosition(100)->create(['title' => 'Already there']);

    $this->actingAs($user)
        ->post(route('cards.move', ['current_team' => $team->slug, 'card' => $card]), [
            'board_list_id' => $done->id,
            'index' => 0,
        ])
        ->assertRedirect();

    expect($card->fresh()->board_list_id)->toEqual($done->id)
        ->and($done->cards()->pluck('title')->all())->toEqual(['Move me', 'Already there'])
        ->and($todo->cards()->count())->toEqual(0);
});

test('moved cards keep distinct positions', function () {
    [$user, $team, $board, $list] = cardFixture();

    $cards = collect(range(1, 5))->map(
        fn (int $i) => Card::factory()->forList($list)->createdBy($user)->atPosition($i * 100)->create(['title' => "C{$i}"])
    );

    $this->actingAs($user);

    foreach ($cards as $card) {
        $this->post(route('cards.move', ['current_team' => $team->slug, 'card' => $card]), [
            'board_list_id' => $list->id,
            'index' => 0,
        ])->assertRedirect();
    }

    $positions = $list->cards()->pluck('position');

    expect($positions->unique()->count())->toEqual($positions->count());
});

test('a card cannot be moved into a list on another board', function () {
    [$user, $team, $board, $list] = cardFixture();
    [$otherUser, $otherTeam, $otherBoard, $foreignList] = cardFixture();

    $card = Card::factory()->forList($list)->createdBy($user)->create();

    $this->actingAs($user)
        ->post(route('cards.move', ['current_team' => $team->slug, 'card' => $card]), [
            'board_list_id' => $foreignList->id,
            'index' => 0,
        ])
        ->assertNotFound();

    expect($card->fresh()->board_list_id)->toEqual($list->id);
});

test('viewers cannot move cards', function () {
    [$user, $team, $board, $list] = cardFixture(TeamRole::Viewer);

    $card = Card::factory()->forList($list)->createdBy($user)->create();

    $this->actingAs($user)
        ->post(route('cards.move', ['current_team' => $team->slug, 'card' => $card]), [
            'board_list_id' => $list->id,
            'index' => 0,
        ])
        ->assertForbidden();
});

test('cards from another workspace cannot be touched', function () {
    [$user, $team] = cardFixture();
    [$otherUser, $otherTeam, $otherBoard, $foreignList] = cardFixture();

    $foreignCard = Card::factory()->forList($foreignList)->createdBy($otherUser)->create(['title' => 'Theirs']);

    $this->actingAs($user)
        ->patch(route('cards.update', ['current_team' => $team->slug, 'card' => $foreignCard]), ['title' => 'Hijacked'])
        ->assertForbidden();

    expect($foreignCard->fresh()->title)->toEqual('Theirs');
});

test('the canvas can add and move cards', function () {
    [$user, $team, $board, $todo] = cardFixture();

    $done = BoardList::factory()->forBoard($board)->create(['name' => 'Done']);

    $this->actingAs($user);

    $component = Livewire::test('pages::boards.canvas', ['board' => $board])
        ->set("newCardTitle.{$todo->id}", 'From the canvas')
        ->call('addCard', $todo->id)
        ->assertHasNoErrors();

    $card = Card::firstOrFail();

    expect($card->title)->toEqual('From the canvas');

    $component->call('moveCard', $card->id, $done->id, 0)
        ->assertHasNoErrors();

    expect($card->fresh()->board_list_id)->toEqual($done->id);
});

test('the canvas rejects a card from another board', function () {
    [$user, $team, $board, $list] = cardFixture();
    [$otherUser, $otherTeam, $otherBoard, $foreignList] = cardFixture();

    $foreignCard = Card::factory()->forList($foreignList)->createdBy($otherUser)->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::boards.canvas', ['board' => $board]);

    expect(fn () => $component->call('moveCard', $foreignCard->id, $list->id, 0))
        ->toThrow(ModelNotFoundException::class);

    expect($foreignCard->fresh()->board_list_id)->toEqual($foreignList->id);
});

test('archived cards are hidden from the canvas', function () {
    [$user, $team, $board, $list] = cardFixture();

    Card::factory()->forList($list)->createdBy($user)->create(['title' => 'Visible card']);
    Card::factory()->forList($list)->createdBy($user)->archived()->create(['title' => 'Hidden card']);

    $this->actingAs($user);

    Livewire::test('pages::boards.canvas', ['board' => $board])
        ->assertSee('Visible card')
        ->assertDontSee('Hidden card');
});
