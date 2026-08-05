<?php

use App\Enums\TeamRole;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Checklist;
use App\Models\Comment;
use App\Models\Label;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

/**
 * Build a workspace with a board, list, and card.
 *
 * @return array{0: User, 1: Team, 2: Board, 3: Card}
 */
function modalFixture(TeamRole $role = TeamRole::Member): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => $role->value]);
    $user->update(['current_team_id' => $team->id]);

    $board = Board::factory()->forTeam($team)->createdBy($user)->create();
    $list = BoardList::factory()->forBoard($board)->create(['name' => 'To Do']);
    $card = Card::factory()->forList($list)->createdBy($user)->create(['title' => 'Modal card']);

    return [$user, $team, $board, $card];
}

test('the board page mounts the card modal', function () {
    [$user, $team, $board] = modalFixture();

    $this->actingAs($user)
        ->get(route('boards.show', ['current_team' => $team->slug, 'board' => $board]))
        ->assertOk()
        ->assertSee('card-detail', false);
});

test('cards on the canvas open the modal instead of navigating', function () {
    [$user, $team, $board, $card] = modalFixture();

    $this->actingAs($user);

    $html = Livewire::test('pages::boards.canvas', ['board' => $board])->html();

    // Flux's trigger opens the modal; the Livewire event loads the card.
    expect($html)->toContain('data-flux-modal-trigger')
        ->and($html)->toContain("open-card', { cardId: {$card->id} }")
        ->and($html)->not->toContain('href="'.route('cards.show', ['card' => $card]).'"');
});

test('opening a card loads it into the modal', function () {
    [$user, $team, $board, $card] = modalFixture();

    $this->actingAs($user);

    Livewire::test('pages::boards.card-modal', ['board' => $board])
        ->assertSee('Loading card…')
        ->call('openCard', $card->id)
        ->assertSet('cardId', $card->id)
        ->assertSet('title', 'Modal card')
        ->assertSee('Modal card')
        ->assertSee('To Do');
});

test('the modal saves card edits', function () {
    [$user, $team, $board, $card] = modalFixture();

    $this->actingAs($user);

    Livewire::test('pages::boards.card-modal', ['board' => $board])
        ->call('openCard', $card->id)
        ->set('title', 'Renamed in the modal')
        ->set('description', 'Written in the modal')
        ->set('dueDate', '2026-12-01T09:00')
        ->call('saveCard')
        ->assertHasNoErrors();

    $card->refresh();

    expect($card->title)->toEqual('Renamed in the modal')
        ->and($card->description)->toEqual('Written in the modal')
        ->and($card->due_date->format('Y-m-d'))->toEqual('2026-12-01');
});

test('the modal validates a due date before the start date', function () {
    [$user, $team, $board, $card] = modalFixture();

    $this->actingAs($user);

    Livewire::test('pages::boards.card-modal', ['board' => $board])
        ->call('openCard', $card->id)
        ->set('startDate', '2026-12-10')
        ->set('dueDate', '2026-12-01T09:00')
        ->call('saveCard')
        ->assertHasErrors('dueDate');
});

test('the modal can comment and delete comments', function () {
    [$user, $team, $board, $card] = modalFixture();

    $this->actingAs($user);

    $component = Livewire::test('pages::boards.card-modal', ['board' => $board])
        ->call('openCard', $card->id)
        ->set('commentBody', 'From the modal')
        ->call('addComment')
        ->assertHasNoErrors();

    $comment = Comment::firstOrFail();

    expect($comment->body)->toEqual('From the modal');

    $component->assertSee('From the modal')
        ->call('deleteComment', $comment->id);

    expect(Comment::count())->toEqual(0);
});

test('the modal manages checklists and items', function () {
    [$user, $team, $board, $card] = modalFixture();

    $this->actingAs($user);

    $component = Livewire::test('pages::boards.card-modal', ['board' => $board])
        ->call('openCard', $card->id)
        ->set('newChecklistName', 'Steps')
        ->call('addChecklist')
        ->assertHasNoErrors();

    $checklist = Checklist::firstOrFail();

    $component->set("newChecklistItem.{$checklist->id}", 'First step')
        ->call('addChecklistItem', $checklist->id);

    $item = $checklist->items()->firstOrFail();

    expect($item->content)->toEqual('First step')
        ->and($item->is_completed)->toBeFalse();

    $component->call('toggleChecklistItem', $item->id);

    expect($item->fresh()->is_completed)->toBeTrue();
});

test('the modal toggles labels and assignees', function () {
    [$user, $team, $board, $card] = modalFixture();

    $teammate = User::factory()->create();
    $team->members()->attach($teammate, ['role' => TeamRole::Member->value]);

    $label = Label::factory()->forBoard($board)->create(['name' => 'Urgent']);

    $this->actingAs($user);

    $component = Livewire::test('pages::boards.card-modal', ['board' => $board])
        ->call('openCard', $card->id)
        ->call('toggleLabel', $label->id);

    expect($card->labels()->count())->toEqual(1);

    $component->call('toggleLabel', $label->id);

    expect($card->labels()->count())->toEqual(0);

    $component->call('assign', $teammate->id);

    expect($card->assignees()->count())->toEqual(1);

    $component->call('unassign', $teammate->id);

    expect($card->assignees()->count())->toEqual(0);
});

test('the modal archives a card and tells the canvas to refresh', function () {
    [$user, $team, $board, $card] = modalFixture();

    $this->actingAs($user);

    Livewire::test('pages::boards.card-modal', ['board' => $board])
        ->call('openCard', $card->id)
        ->call('archiveCard')
        ->assertSet('cardId', null)
        ->assertDispatched('card-saved')
        ->assertDispatched('close-card-modal');

    expect($card->fresh()->isArchived())->toBeTrue();
});

test('the modal bridges livewire window events to the document event flux listens for', function () {
    [$user, $team, $board, $card] = modalFixture();

    $this->actingAs($user);

    $html = Livewire::test('pages::boards.card-modal', ['board' => $board])->html();

    // Livewire dispatches on window; Flux listens on document. Without this
    // bridge the modal would stay open after archiving.
    expect($html)->toContain('close-card-modal.window')
        ->and($html)->toContain("modal-close', { name: 'card-detail' }");
});

test('the modal header clears the flux close button', function () {
    [$user, $team, $board, $card] = modalFixture();

    $this->actingAs($user);

    $html = Livewire::test('pages::boards.card-modal', ['board' => $board])
        ->call('openCard', $card->id)
        ->html();

    // Flux positions its close button absolutely at top-0 end-0 mt-4 me-4, so
    // the header row must reserve space or the controls sit on top of it.
    expect($html)->toContain('justify-between gap-4 pe-12')
        ->and($html)->toContain('card-modal-open-page');
});

test('the open as page control is labelled for screen readers', function () {
    [$user, $team, $board, $card] = modalFixture();

    $this->actingAs($user);

    $html = Livewire::test('pages::boards.card-modal', ['board' => $board])
        ->call('openCard', $card->id)
        ->html();

    expect($html)->toContain('Open as a full page');
});

test('the modal responds to the open-card event, not just a direct call', function () {
    [$user, $team, $board, $card] = modalFixture();

    $this->actingAs($user);

    Livewire::test('pages::boards.card-modal', ['board' => $board])
        ->dispatch('open-card', cardId: $card->id)
        ->assertSet('cardId', $card->id)
        ->assertSee('Modal card');
});

test('saving from the modal tells the canvas to refresh', function () {
    [$user, $team, $board, $card] = modalFixture();

    $this->actingAs($user);

    Livewire::test('pages::boards.card-modal', ['board' => $board])
        ->call('openCard', $card->id)
        ->set('title', 'Nudged')
        ->call('saveCard')
        ->assertDispatched('card-saved');
});

test('the canvas listens for the modal refresh event', function () {
    [$user, $team, $board, $card] = modalFixture();

    $this->actingAs($user);

    Livewire::test('pages::boards.canvas', ['board' => $board])
        ->call('refreshLists')
        ->assertHasNoErrors();
});

test('viewers see the modal read only', function () {
    [$owner, $team, $board, $card] = modalFixture(TeamRole::Owner);

    $viewer = User::factory()->create();
    $team->members()->attach($viewer, ['role' => TeamRole::Viewer->value]);

    $this->actingAs($viewer);

    $component = Livewire::test('pages::boards.card-modal', ['board' => $board])
        ->call('openCard', $card->id)
        ->assertSee('Modal card');

    $html = $component->html();

    expect($html)->not->toContain('card-modal-save')
        ->and($html)->not->toContain('card-modal-comment-submit');

    $component->set('title', 'Hacked')->call('saveCard')->assertForbidden();

    expect($card->fresh()->title)->toEqual('Modal card');
});

test('a card from another board cannot be opened in the modal', function () {
    [$user, $team, $board] = modalFixture();
    [$otherUser, $otherTeam, $otherBoard, $foreignCard] = modalFixture();

    $this->actingAs($user);

    $component = Livewire::test('pages::boards.card-modal', ['board' => $board]);

    expect(fn () => $component->call('openCard', $foreignCard->id))
        ->toThrow(ModelNotFoundException::class);
});

test('the standalone card page still works for direct links', function () {
    [$user, $team, $board, $card] = modalFixture();

    $this->actingAs($user)
        ->get(route('cards.show', ['current_team' => $team->slug, 'card' => $card]))
        ->assertOk()
        ->assertSee('Modal card');
});
