<?php

use App\Enums\TeamRole;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Comment;
use App\Models\Label;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Build a workspace with a board, list, and a card.
 *
 * @return array{0: User, 1: Team, 2: Board, 3: Card}
 */
function cardDetailFixture(TeamRole $role = TeamRole::Member): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => $role->value]);
    $user->update(['current_team_id' => $team->id]);

    $board = Board::factory()->forTeam($team)->createdBy($user)->create();
    $list = BoardList::factory()->forBoard($board)->create();
    $card = Card::factory()->forList($list)->createdBy($user)->create();

    return [$user, $team, $board, $card];
}

test('the card detail page renders', function () {
    [$user, $team, $board, $card] = cardDetailFixture();

    $this->actingAs($user)
        ->get(route('cards.show', ['current_team' => $team->slug, 'card' => $card]))
        ->assertOk()
        ->assertSee($card->title)
        ->assertSee($board->name);
});

test('viewers can read a card but not edit it', function () {
    [$user, $team, $board, $card] = cardDetailFixture(TeamRole::Viewer);

    $this->actingAs($user)
        ->get(route('cards.show', ['current_team' => $team->slug, 'card' => $card]))
        ->assertOk()
        ->assertSee($card->title)
        ->assertDontSee('card-save-button');

    $this->actingAs($user)
        ->patch(route('cards.update', ['current_team' => $team->slug, 'card' => $card]), ['title' => 'Hacked'])
        ->assertForbidden();
});

test('members can comment and delete their own comment', function () {
    [$user, $team, $board, $card] = cardDetailFixture();

    $this->actingAs($user)
        ->post(route('comments.store', ['current_team' => $team->slug, 'card' => $card]), ['body' => 'Looks good'])
        ->assertRedirect();

    $comment = Comment::firstOrFail();

    expect($comment->body)->toEqual('Looks good')
        ->and($comment->user_id)->toEqual($user->id);

    $this->actingAs($user)
        ->delete(route('comments.destroy', ['current_team' => $team->slug, 'comment' => $comment]))
        ->assertRedirect();

    expect(Comment::find($comment->id))->toBeNull();
});

test('viewers cannot comment', function () {
    [$user, $team, $board, $card] = cardDetailFixture(TeamRole::Viewer);

    $this->actingAs($user)
        ->post(route('comments.store', ['current_team' => $team->slug, 'card' => $card]), ['body' => 'Nope'])
        ->assertForbidden();

    expect(Comment::count())->toEqual(0);
});

test('members cannot delete another members comment but admins can', function () {
    [$author, $team, $board, $card] = cardDetailFixture();

    $otherMember = User::factory()->create();
    $team->members()->attach($otherMember, ['role' => TeamRole::Member->value]);

    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $comment = Comment::factory()->forCard($card)->writtenBy($author)->create();

    $this->actingAs($otherMember)
        ->delete(route('comments.destroy', ['current_team' => $team->slug, 'comment' => $comment]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('comments.destroy', ['current_team' => $team->slug, 'comment' => $comment]))
        ->assertRedirect();

    expect(Comment::find($comment->id))->toBeNull();
});

test('only the author can edit a comment', function () {
    [$author, $team, $board, $card] = cardDetailFixture();

    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $comment = Comment::factory()->forCard($card)->writtenBy($author)->create(['body' => 'Original']);

    $this->actingAs($admin)
        ->patch(route('comments.update', ['current_team' => $team->slug, 'comment' => $comment]), ['body' => 'Edited'])
        ->assertForbidden();

    expect($comment->fresh()->body)->toEqual('Original');

    $this->actingAs($author)
        ->patch(route('comments.update', ['current_team' => $team->slug, 'comment' => $comment]), ['body' => 'Edited'])
        ->assertRedirect();

    expect($comment->fresh()->body)->toEqual('Edited');
});

test('checklists and items can be managed', function () {
    [$user, $team, $board, $card] = cardDetailFixture();

    $this->actingAs($user)
        ->post(route('checklists.store', ['current_team' => $team->slug, 'card' => $card]), ['name' => 'Steps'])
        ->assertRedirect();

    $checklist = Checklist::firstOrFail();

    $this->actingAs($user)
        ->post(route('checklist-items.store', ['current_team' => $team->slug, 'checklist' => $checklist]), ['content' => 'First step'])
        ->assertRedirect();

    $item = ChecklistItem::firstOrFail();

    expect($item->is_completed)->toBeFalse();

    $this->actingAs($user)
        ->post(route('checklist-items.toggle', ['current_team' => $team->slug, 'item' => $item]))
        ->assertRedirect();

    expect($item->fresh()->is_completed)->toBeTrue()
        ->and($checklist->fresh()->completedCount())->toEqual(1);

    $this->actingAs($user)
        ->delete(route('checklists.destroy', ['current_team' => $team->slug, 'checklist' => $checklist]))
        ->assertRedirect();

    expect(Checklist::count())->toEqual(0)
        ->and(ChecklistItem::count())->toEqual(0);
});

test('assignees can be added and removed', function () {
    [$user, $team, $board, $card] = cardDetailFixture();

    $teammate = User::factory()->create();
    $team->members()->attach($teammate, ['role' => TeamRole::Member->value]);

    $this->actingAs($user)
        ->post(route('card-assignees.store', ['current_team' => $team->slug, 'card' => $card]), ['user_id' => $teammate->id])
        ->assertRedirect();

    expect($card->assignees()->whereKey($teammate->id)->exists())->toBeTrue();

    $this->actingAs($user)
        ->delete(route('card-assignees.destroy', ['current_team' => $team->slug, 'card' => $card, 'user' => $teammate]))
        ->assertRedirect();

    expect($card->assignees()->count())->toEqual(0);
});

test('a non member cannot be assigned to a card', function () {
    [$user, $team, $board, $card] = cardDetailFixture();

    $stranger = User::factory()->create();

    $this->actingAs($user)
        ->post(route('card-assignees.store', ['current_team' => $team->slug, 'card' => $card]), ['user_id' => $stranger->id])
        ->assertStatus(422);

    expect($card->assignees()->count())->toEqual(0);
});

test('assigning the same user twice does not duplicate', function () {
    [$user, $team, $board, $card] = cardDetailFixture();

    $teammate = User::factory()->create();
    $team->members()->attach($teammate, ['role' => TeamRole::Member->value]);

    foreach (range(1, 2) as $ignored) {
        $this->actingAs($user)
            ->post(route('card-assignees.store', ['current_team' => $team->slug, 'card' => $card]), ['user_id' => $teammate->id])
            ->assertRedirect();
    }

    expect($card->assignees()->count())->toEqual(1);
});

test('creating a board seeds the default labels', function () {
    [$user, $team] = cardDetailFixture();

    $this->actingAs($user)
        ->post(route('boards.store', ['current_team' => $team->slug]), ['name' => 'Labelled Board'])
        ->assertRedirect();

    $board = Board::where('name', 'Labelled Board')->firstOrFail();

    expect($board->labels->pluck('name')->all())
        ->toEqual(collect(config('boards.default_labels'))->pluck('name')->sort()->values()->all());
});

test('labels can be toggled on a card', function () {
    [$user, $team, $board, $card] = cardDetailFixture();

    $label = Label::factory()->forBoard($board)->create();

    $this->actingAs($user)
        ->post(route('cards.labels.toggle', ['current_team' => $team->slug, 'card' => $card, 'label' => $label]))
        ->assertRedirect();

    expect($card->labels()->count())->toEqual(1);

    $this->actingAs($user)
        ->post(route('cards.labels.toggle', ['current_team' => $team->slug, 'card' => $card, 'label' => $label]))
        ->assertRedirect();

    expect($card->labels()->count())->toEqual(0);
});

test('a label from another board cannot be applied', function () {
    [$user, $team, $board, $card] = cardDetailFixture();
    [$otherUser, $otherTeam, $otherBoard] = cardDetailFixture();

    $foreignLabel = Label::factory()->forBoard($otherBoard)->create();

    $this->actingAs($user)
        ->post(route('cards.labels.toggle', ['current_team' => $team->slug, 'card' => $card, 'label' => $foreignLabel]))
        ->assertNotFound();

    expect($card->labels()->count())->toEqual(0);
});

test('label creation validates the colour', function () {
    [$user, $team, $board] = cardDetailFixture();

    $this->actingAs($user)
        ->post(route('labels.store', ['current_team' => $team->slug, 'board' => $board]), [
            'name' => 'Nope',
            'color' => 'chartreuse',
        ])
        ->assertSessionHasErrors('color');
});

test('files can be attached and removed', function () {
    Storage::fake(config('boards.attachments.disk'));

    [$user, $team, $board, $card] = cardDetailFixture();

    $this->actingAs($user)
        ->post(route('attachments.store', ['current_team' => $team->slug, 'card' => $card]), [
            'file' => UploadedFile::fake()->create('spec.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect();

    $attachment = $card->attachments()->firstOrFail();

    expect($attachment->name)->toEqual('spec.pdf')
        ->and($attachment->size)->toBeGreaterThan(0);

    Storage::disk($attachment->disk)->assertExists($attachment->path);

    $this->actingAs($user)
        ->delete(route('attachments.destroy', ['current_team' => $team->slug, 'attachment' => $attachment]))
        ->assertRedirect();

    Storage::disk($attachment->disk)->assertMissing($attachment->path);

    expect($card->attachments()->count())->toEqual(0);
});

test('viewers cannot attach files', function () {
    Storage::fake(config('boards.attachments.disk'));

    [$user, $team, $board, $card] = cardDetailFixture(TeamRole::Viewer);

    $this->actingAs($user)
        ->post(route('attachments.store', ['current_team' => $team->slug, 'card' => $card]), [
            'file' => UploadedFile::fake()->create('spec.pdf', 10),
        ])
        ->assertForbidden();
});

test('card lifecycle changes are recorded in the activity feed', function () {
    [$user, $team, $board, $card] = cardDetailFixture();

    $done = BoardList::factory()->forBoard($board)->create(['name' => 'Done']);

    $this->actingAs($user)
        ->post(route('cards.move', ['current_team' => $team->slug, 'card' => $card]), [
            'board_list_id' => $done->id,
            'index' => 0,
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('cards.complete', ['current_team' => $team->slug, 'card' => $card]))
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('comments.store', ['current_team' => $team->slug, 'card' => $card]), ['body' => 'Done!'])
        ->assertRedirect();

    $actions = $card->activities()->pluck('action')->all();

    expect($actions)->toContain('card.moved')
        ->and($actions)->toContain('card.completed')
        ->and($actions)->toContain('comment.created');

    $moved = $card->activities()->where('action', 'card.moved')->firstOrFail();

    expect($moved->properties['list'])->toEqual('Done')
        ->and($moved->describe())->toContain($user->name)
        ->and($moved->describe())->toContain('Done');
});

test('activity records the acting user', function () {
    [$user, $team, $board, $card] = cardDetailFixture();

    $this->actingAs($user)
        ->post(route('cards.complete', ['current_team' => $team->slug, 'card' => $card]))
        ->assertRedirect();

    $activity = $card->activities()->where('action', 'card.completed')->firstOrFail();

    expect($activity->user_id)->toEqual($user->id)
        ->and($activity->board_id)->toEqual($board->id)
        ->and($activity->team_id)->toEqual($team->id);
});

test('card detail from another workspace is not reachable', function () {
    [$user, $team] = cardDetailFixture();
    [$otherUser, $otherTeam, $otherBoard, $foreignCard] = cardDetailFixture();

    $this->actingAs($user)
        ->get(route('cards.show', ['current_team' => $team->slug, 'card' => $foreignCard]))
        ->assertForbidden();
});
