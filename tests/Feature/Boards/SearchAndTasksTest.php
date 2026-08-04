<?php

use App\Enums\TeamRole;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Label;
use App\Models\Team;
use App\Models\User;
use App\Notifications\CardAssigned;
use App\Notifications\CardDueSoon;
use Illuminate\Support\Facades\Notification;

/**
 * Build a workspace with a board and list.
 *
 * @return array{0: User, 1: Team, 2: Board, 3: BoardList}
 */
function searchFixture(): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $user->update(['current_team_id' => $team->id]);

    $board = Board::factory()->forTeam($team)->createdBy($user)->create(['name' => 'Roadmap']);
    $list = BoardList::factory()->forBoard($board)->create(['name' => 'To Do']);

    return [$user, $team, $board, $list];
}

test('search finds cards by title', function () {
    [$user, $team, $board, $list] = searchFixture();

    Card::factory()->forList($list)->createdBy($user)->create(['title' => 'Refactor the billing engine']);
    Card::factory()->forList($list)->createdBy($user)->create(['title' => 'Update the docs']);

    $this->actingAs($user)
        ->get(route('search', ['current_team' => $team->slug, 'term' => 'billing']))
        ->assertOk()
        ->assertSee('Refactor the billing engine')
        ->assertDontSee('Update the docs');
});

test('search finds cards by description', function () {
    [$user, $team, $board, $list] = searchFixture();

    Card::factory()->forList($list)->createdBy($user)->create([
        'title' => 'Mystery card',
        'description' => 'Contains the word pineapple',
    ]);

    $this->actingAs($user)
        ->get(route('search', ['current_team' => $team->slug, 'term' => 'pineapple']))
        ->assertOk()
        ->assertSee('Mystery card');
});

test('search can filter by assignee due date and label', function () {
    [$user, $team, $board, $list] = searchFixture();

    $teammate = User::factory()->create();
    $team->members()->attach($teammate, ['role' => TeamRole::Member->value]);

    $label = Label::factory()->forBoard($board)->create(['name' => 'Urgent']);

    $mine = Card::factory()->forList($list)->createdBy($user)->overdue()->create(['title' => 'Assigned to me']);
    $mine->assignees()->attach($user->id);
    $mine->labels()->attach($label->id);

    Card::factory()->forList($list)->createdBy($user)->create(['title' => 'Someone elses card']);

    $this->actingAs($user)
        ->get(route('search', ['current_team' => $team->slug, 'assignee' => $user->id]))
        ->assertOk()
        ->assertSee('Assigned to me')
        ->assertDontSee('Someone elses card');

    $this->actingAs($user)
        ->get(route('search', ['current_team' => $team->slug, 'due' => 'overdue']))
        ->assertOk()
        ->assertSee('Assigned to me')
        ->assertDontSee('Someone elses card');

    $this->actingAs($user)
        ->get(route('search', ['current_team' => $team->slug, 'label' => $label->id]))
        ->assertOk()
        ->assertSee('Assigned to me')
        ->assertDontSee('Someone elses card');
});

test('search never leaks cards from private boards or other workspaces', function () {
    [$user, $team, $board, $list] = searchFixture();

    $privateBoard = Board::factory()->forTeam($team)->createdBy($user)->private()->create();
    $privateList = BoardList::factory()->forBoard($privateBoard)->create();
    Card::factory()->forList($privateList)->createdBy($user)->create(['title' => 'Secret private card']);

    [$otherUser, $otherTeam, $otherBoard, $otherList] = searchFixture();
    Card::factory()->forList($otherList)->createdBy($otherUser)->create(['title' => 'Other workspace card']);

    $outsider = User::factory()->create();
    $team->members()->attach($outsider, ['role' => TeamRole::Member->value]);

    $this->actingAs($outsider)
        ->get(route('search', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertDontSee('Secret private card')
        ->assertDontSee('Other workspace card');
});

test('search excludes archived boards lists and cards', function () {
    [$user, $team, $board, $list] = searchFixture();

    Card::factory()->forList($list)->createdBy($user)->archived()->create(['title' => 'Archived card here']);

    $archivedList = BoardList::factory()->forBoard($board)->archived()->create();
    Card::factory()->forList($archivedList)->createdBy($user)->create(['title' => 'Card in archived list']);

    $archivedBoard = Board::factory()->forTeam($team)->createdBy($user)->archived()->create();
    $listOnArchived = BoardList::factory()->forBoard($archivedBoard)->create();
    Card::factory()->forList($listOnArchived)->createdBy($user)->create(['title' => 'Card on archived board']);

    $this->actingAs($user)
        ->get(route('search', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertDontSee('Archived card here')
        ->assertDontSee('Card in archived list')
        ->assertDontSee('Card on archived board');
});

test('my tasks separates overdue from upcoming', function () {
    [$user, $team, $board, $list] = searchFixture();

    $overdue = Card::factory()->forList($list)->createdBy($user)->overdue()->create(['title' => 'Overdue work']);
    $upcoming = Card::factory()->forList($list)->createdBy($user)->dueAt(now()->addWeek())->create(['title' => 'Future work']);
    $unassigned = Card::factory()->forList($list)->createdBy($user)->create(['title' => 'Nobodys work']);

    $overdue->assignees()->attach($user->id);
    $upcoming->assignees()->attach($user->id);

    $response = $this->actingAs($user)->get(route('my-tasks', ['current_team' => $team->slug]));

    $response->assertOk()
        ->assertSee('Overdue work')
        ->assertSee('Future work')
        ->assertDontSee('Nobodys work');

    expect($response->viewData('overdue')->pluck('title')->all())->toEqual(['Overdue work'])
        ->and($response->viewData('upcoming')->pluck('title')->all())->toEqual(['Future work']);
});

test('my tasks hides completed cards', function () {
    [$user, $team, $board, $list] = searchFixture();

    $done = Card::factory()->forList($list)->createdBy($user)->overdue()->completed()->create(['title' => 'Finished work']);
    $done->assignees()->attach($user->id);

    $this->actingAs($user)
        ->get(route('my-tasks', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertDontSee('Finished work');
});

test('a board can be duplicated with its lists cards and labels', function () {
    [$user, $team, $board, $list] = searchFixture();

    $label = Label::factory()->forBoard($board)->create(['name' => 'Urgent']);

    $card = Card::factory()->forList($list)->createdBy($user)->create(['title' => 'Copy me']);
    $card->labels()->attach($label->id);

    Card::factory()->forList($list)->createdBy($user)->archived()->create(['title' => 'Do not copy me']);

    $this->actingAs($user)
        ->post(route('boards.duplicate', ['current_team' => $team->slug, 'board' => $board]))
        ->assertRedirect();

    $copy = Board::where('team_id', $team->id)->where('id', '!=', $board->id)->firstOrFail();

    expect($copy->name)->toContain('Roadmap')
        ->and($copy->lists->pluck('name')->all())->toEqual(['To Do'])
        ->and($copy->labels->pluck('name')->all())->toEqual(['Urgent']);

    $copiedCards = $copy->lists->first()->cards;

    expect($copiedCards->pluck('title')->all())->toEqual(['Copy me'])
        ->and($copiedCards->first()->labels->pluck('name')->all())->toEqual(['Urgent']);

    // The copy's labels must be its own rows, not the original's.
    expect($copiedCards->first()->labels->first()->id)->not->toEqual($label->id);
});

test('viewers cannot duplicate a board', function () {
    [$user, $team, $board] = searchFixture();

    $viewer = User::factory()->create();
    $team->members()->attach($viewer, ['role' => TeamRole::Viewer->value]);

    $this->actingAs($viewer)
        ->post(route('boards.duplicate', ['current_team' => $team->slug, 'board' => $board]))
        ->assertForbidden();

    expect(Board::where('team_id', $team->id)->count())->toEqual(1);
});

test('assigning a member notifies them', function () {
    Notification::fake();

    [$user, $team, $board, $list] = searchFixture();

    $teammate = User::factory()->create();
    $team->members()->attach($teammate, ['role' => TeamRole::Member->value]);

    $card = Card::factory()->forList($list)->createdBy($user)->create();

    $this->actingAs($user)
        ->post(route('card-assignees.store', ['current_team' => $team->slug, 'card' => $card]), ['user_id' => $teammate->id])
        ->assertRedirect();

    Notification::assertSentTo($teammate, CardAssigned::class);
});

test('assigning yourself does not send a notification', function () {
    Notification::fake();

    [$user, $team, $board, $list] = searchFixture();

    $card = Card::factory()->forList($list)->createdBy($user)->create();

    $this->actingAs($user)
        ->post(route('card-assignees.store', ['current_team' => $team->slug, 'card' => $card]), ['user_id' => $user->id])
        ->assertRedirect();

    Notification::assertNothingSent();
});

test('the reminder command notifies assignees of cards due soon', function () {
    Notification::fake();

    [$user, $team, $board, $list] = searchFixture();

    $dueSoon = Card::factory()->forList($list)->createdBy($user)->dueAt(now()->addHours(4))->create();
    $dueSoon->assignees()->attach($user->id);

    $dueLater = Card::factory()->forList($list)->createdBy($user)->dueAt(now()->addWeek())->create();
    $dueLater->assignees()->attach($user->id);

    $this->artisan('boards:send-due-date-reminders')->assertSuccessful();

    Notification::assertSentToTimes($user, CardDueSoon::class, 1);
});

test('the reminder command skips completed and unassigned cards', function () {
    Notification::fake();

    [$user, $team, $board, $list] = searchFixture();

    $completed = Card::factory()->forList($list)->createdBy($user)->dueAt(now()->addHour())->completed()->create();
    $completed->assignees()->attach($user->id);

    Card::factory()->forList($list)->createdBy($user)->dueAt(now()->addHour())->create();

    $this->artisan('boards:send-due-date-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});
