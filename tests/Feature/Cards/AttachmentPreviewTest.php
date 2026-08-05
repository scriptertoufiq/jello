<?php

use App\Enums\BoardVisibility;
use App\Enums\TeamRole;
use App\Models\Attachment;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Build a workspace with a board, list, and card.
 *
 * @return array{0: User, 1: Team, 2: Board, 3: Card}
 */
function previewFixture(TeamRole $role = TeamRole::Member): array
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

/**
 * Attach a real file to a card through the upload endpoint.
 */
function attachTo(User $user, Team $team, Card $card, UploadedFile $file): Attachment
{
    test()->actingAs($user)
        ->post(route('attachments.store', ['current_team' => $team->slug, 'card' => $card]), ['file' => $file])
        ->assertRedirect();

    return $card->attachments()->latest('id')->firstOrFail();
}

beforeEach(function () {
    Storage::fake(config('boards.attachments.disk'));
});

test('new attachments are stored on a private disk', function () {
    [$user, $team, $board, $card] = previewFixture();

    $attachment = attachTo($user, $team, $card, UploadedFile::fake()->image('shot.png', 400, 300));

    // The public disk is served straight off the filesystem with no policy check.
    expect($attachment->disk)->not->toEqual('public')
        ->and(config('boards.attachments.disk'))->not->toEqual('public');
});

test('an image attachment can be previewed inline by a member', function () {
    [$user, $team, $board, $card] = previewFixture();

    $attachment = attachTo($user, $team, $card, UploadedFile::fake()->image('shot.png', 400, 300));

    $response = $this->actingAs($user)
        ->get(route('attachments.preview', ['current_team' => $team->slug, 'attachment' => $attachment]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect($response->headers->get('Content-Disposition'))->toContain('inline');
});

test('a guest cannot preview or download an attachment', function () {
    [$user, $team, $board, $card] = previewFixture();

    $attachment = attachTo($user, $team, $card, UploadedFile::fake()->image('secret.png', 200, 200));

    // The upload above signed the user in; drop that session first.
    Auth::logout();
    Session::flush();

    $this->get(route('attachments.preview', ['current_team' => $team->slug, 'attachment' => $attachment]))
        ->assertRedirect(route('login'));

    $this->get(route('attachments.download', ['current_team' => $team->slug, 'attachment' => $attachment]))
        ->assertRedirect(route('login'));
});

test('a user outside the workspace cannot preview an attachment', function () {
    [$user, $team, $board, $card] = previewFixture();

    $attachment = attachTo($user, $team, $card, UploadedFile::fake()->image('secret.png', 200, 200));

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('attachments.preview', ['current_team' => $team->slug, 'attachment' => $attachment]))
        ->assertForbidden();
});

test('a workspace member cannot preview an attachment on a private board they are not on', function () {
    [$owner, $team, $board, $card] = previewFixture(TeamRole::Owner);

    $board->forceFill(['visibility' => BoardVisibility::Private])->save();
    $board->boardMemberships()->create(['user_id' => $owner->id, 'role' => TeamRole::Admin]);

    $attachment = attachTo($owner, $team, $card, UploadedFile::fake()->image('secret.png', 200, 200));

    $outsider = User::factory()->create();
    $team->members()->attach($outsider, ['role' => TeamRole::Member->value]);

    $this->actingAs($outsider)
        ->get(route('attachments.preview', ['current_team' => $team->slug, 'attachment' => $attachment]))
        ->assertForbidden();
});

test('a preview 404s when the file is gone but the row remains', function () {
    [$user, $team, $board, $card] = previewFixture();

    $attachment = attachTo($user, $team, $card, UploadedFile::fake()->image('shot.png', 200, 200));

    Storage::disk($attachment->disk)->delete($attachment->path);

    $this->actingAs($user)
        ->get(route('attachments.preview', ['current_team' => $team->slug, 'attachment' => $attachment]))
        ->assertNotFound();
});

test('image attachments render as previews on the card page', function () {
    [$user, $team, $board, $card] = previewFixture();

    $attachment = attachTo($user, $team, $card, UploadedFile::fake()->image('screenshot.png', 400, 300));

    $this->actingAs($user)
        ->get(route('cards.show', ['current_team' => $team->slug, 'card' => $card]))
        ->assertOk()
        ->assertSee('data-test="attachment-preview-image"', false)
        ->assertSee($attachment->previewUrl(), false);
});

test('non image attachments render as file rows, not previews', function () {
    [$user, $team, $board, $card] = previewFixture();

    $attachment = attachTo($user, $team, $card, UploadedFile::fake()->create('spec.pdf', 40, 'application/pdf'));

    $this->actingAs($user)
        ->get(route('cards.show', ['current_team' => $team->slug, 'card' => $card]))
        ->assertOk()
        ->assertSee('data-test="attachment-file"', false)
        ->assertDontSee('data-test="attachment-preview-image"', false)
        ->assertSee($attachment->downloadUrl(), false);
});

test('image attachments render as previews in the card modal', function () {
    [$user, $team, $board, $card] = previewFixture();

    $attachment = attachTo($user, $team, $card, UploadedFile::fake()->image('screenshot.png', 400, 300));

    $this->actingAs($user);

    $html = Livewire::test('pages::boards.card-modal', ['board' => $board])
        ->call('openCard', $card->id)
        ->html();

    expect($html)->toContain('data-test="attachment-preview-image"')
        ->and($html)->toContain($attachment->previewUrl());
});

test('previews never point directly at the storage disk', function () {
    [$user, $team, $board, $card] = previewFixture();

    attachTo($user, $team, $card, UploadedFile::fake()->image('screenshot.png', 400, 300));

    $html = $this->actingAs($user)
        ->get(route('cards.show', ['current_team' => $team->slug, 'card' => $card]))
        ->assertOk()
        ->getContent();

    // /storage/... bypasses the policy entirely.
    expect($html)->not->toContain('/storage/cards/');
});

test('a mixed set of attachments splits into previews and files', function () {
    [$user, $team, $board, $card] = previewFixture();

    attachTo($user, $team, $card, UploadedFile::fake()->image('one.png', 200, 200));
    attachTo($user, $team, $card, UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf'));

    $this->actingAs($user)
        ->get(route('cards.show', ['current_team' => $team->slug, 'card' => $card]))
        ->assertOk()
        ->assertSee('data-test="attachment-previews"', false)
        ->assertSee('data-test="attachment-files"', false);
});

test('viewers see previews but no delete control', function () {
    [$owner, $team, $board, $card] = previewFixture(TeamRole::Owner);

    $attachment = attachTo($owner, $team, $card, UploadedFile::fake()->image('shot.png', 200, 200));

    $viewer = User::factory()->create();
    $team->members()->attach($viewer, ['role' => TeamRole::Viewer->value]);

    $this->actingAs($viewer)
        ->get(route('cards.show', ['current_team' => $team->slug, 'card' => $card]))
        ->assertOk()
        ->assertSee('data-test="attachment-preview-image"', false)
        ->assertDontSee('data-test="attachment-delete-button"', false);

    // And the preview itself is still readable by a viewer.
    $this->actingAs($viewer)
        ->get(route('attachments.preview', ['current_team' => $team->slug, 'attachment' => $attachment]))
        ->assertOk();
});
