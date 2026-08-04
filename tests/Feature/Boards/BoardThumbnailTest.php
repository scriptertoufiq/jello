<?php

use App\Enums\TeamRole;
use App\Models\Board;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Build a workspace with the given user attached in the given role.
 *
 * @return array{0: User, 1: Team}
 */
function thumbnailWorkspace(TeamRole $role = TeamRole::Member): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => $role->value]);
    $user->update(['current_team_id' => $team->id]);

    return [$user, $team];
}

beforeEach(function () {
    Storage::fake(config('boards.thumbnails.disk'));
});

test('a board can be created with a thumbnail', function () {
    [$user, $team] = thumbnailWorkspace();

    $this->actingAs($user)
        ->post(route('boards.store', ['current_team' => $team->slug]), [
            'name' => 'Design Sprint',
            'thumbnail' => UploadedFile::fake()->image('cover.png', 800, 400),
        ])
        ->assertRedirect();

    $board = Board::firstOrFail();

    expect($board->hasThumbnail())->toBeTrue()
        ->and($board->thumbnail_disk)->toEqual(config('boards.thumbnails.disk'));

    Storage::disk($board->thumbnail_disk)->assertExists($board->thumbnail_path);
});

test('a board without a thumbnail falls back to its gradient', function () {
    [$user, $team] = thumbnailWorkspace();

    $board = Board::factory()->forTeam($team)->createdBy($user)->create(['background' => 'blue']);

    expect($board->hasThumbnail())->toBeFalse()
        ->and($board->thumbnailUrl())->toBeNull()
        ->and($board->backgroundGradient())->toEqual(config('boards.backgrounds.blue'));
});

test('an unknown background key still yields a usable gradient', function () {
    [$user, $team] = thumbnailWorkspace();

    $board = Board::factory()->forTeam($team)->createdBy($user)->create();
    $board->forceFill(['background' => 'not-a-real-key'])->save();

    expect($board->backgroundGradient())->toBeString()->not->toBeEmpty();
});

test('a thumbnail can be added to an existing board', function () {
    [$user, $team] = thumbnailWorkspace();

    $board = Board::factory()->forTeam($team)->createdBy($user)->create();

    $this->actingAs($user)
        ->patch(route('boards.update', ['current_team' => $team->slug, 'board' => $board]), [
            'name' => $board->name,
            'thumbnail' => UploadedFile::fake()->image('cover.jpg', 600, 300),
        ])
        ->assertRedirect();

    $board->refresh();

    expect($board->hasThumbnail())->toBeTrue();

    Storage::disk($board->thumbnail_disk)->assertExists($board->thumbnail_path);
});

test('replacing a thumbnail deletes the previous file', function () {
    [$user, $team] = thumbnailWorkspace();

    $board = Board::factory()->forTeam($team)->createdBy($user)->create();

    $this->actingAs($user)
        ->patch(route('boards.update', ['current_team' => $team->slug, 'board' => $board]), [
            'name' => $board->name,
            'thumbnail' => UploadedFile::fake()->image('first.png', 400, 200),
        ])
        ->assertRedirect();

    $firstPath = $board->fresh()->thumbnail_path;

    $this->actingAs($user)
        ->patch(route('boards.update', ['current_team' => $team->slug, 'board' => $board]), [
            'name' => $board->name,
            'thumbnail' => UploadedFile::fake()->image('second.png', 400, 200),
        ])
        ->assertRedirect();

    $secondPath = $board->fresh()->thumbnail_path;

    expect($secondPath)->not->toEqual($firstPath);

    Storage::disk(config('boards.thumbnails.disk'))->assertMissing($firstPath);
    Storage::disk(config('boards.thumbnails.disk'))->assertExists($secondPath);
});

test('a thumbnail can be removed', function () {
    [$user, $team] = thumbnailWorkspace();

    $board = Board::factory()->forTeam($team)->createdBy($user)->create();

    $this->actingAs($user)
        ->patch(route('boards.update', ['current_team' => $team->slug, 'board' => $board]), [
            'name' => $board->name,
            'thumbnail' => UploadedFile::fake()->image('cover.png', 400, 200),
        ])
        ->assertRedirect();

    $path = $board->fresh()->thumbnail_path;

    $this->actingAs($user)
        ->patch(route('boards.update', ['current_team' => $team->slug, 'board' => $board]), [
            'name' => $board->name,
            'remove_thumbnail' => '1',
        ])
        ->assertRedirect();

    $board->refresh();

    expect($board->hasThumbnail())->toBeFalse()
        ->and($board->thumbnail_path)->toBeNull()
        ->and($board->thumbnail_disk)->toBeNull();

    Storage::disk(config('boards.thumbnails.disk'))->assertMissing($path);
});

test('an update without a thumbnail keeps the existing one', function () {
    [$user, $team] = thumbnailWorkspace();

    $board = Board::factory()->forTeam($team)->createdBy($user)->create();

    $this->actingAs($user)
        ->patch(route('boards.update', ['current_team' => $team->slug, 'board' => $board]), [
            'name' => $board->name,
            'thumbnail' => UploadedFile::fake()->image('cover.png', 400, 200),
        ])
        ->assertRedirect();

    $path = $board->fresh()->thumbnail_path;

    $this->actingAs($user)
        ->patch(route('boards.update', ['current_team' => $team->slug, 'board' => $board]), [
            'name' => 'Renamed board',
        ])
        ->assertRedirect();

    expect($board->fresh()->thumbnail_path)->toEqual($path);

    Storage::disk(config('boards.thumbnails.disk'))->assertExists($path);
});

test('non image uploads are rejected', function () {
    [$user, $team] = thumbnailWorkspace();

    $this->actingAs($user)
        ->post(route('boards.store', ['current_team' => $team->slug]), [
            'name' => 'Bad upload',
            'thumbnail' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('thumbnail');

    expect(Board::count())->toEqual(0);
});

test('oversized images are rejected', function () {
    [$user, $team] = thumbnailWorkspace();

    $tooBig = config('boards.thumbnails.max_kilobytes') + 512;

    $this->actingAs($user)
        ->post(route('boards.store', ['current_team' => $team->slug]), [
            'name' => 'Huge upload',
            'thumbnail' => UploadedFile::fake()->image('big.png', 800, 400)->size($tooBig),
        ])
        ->assertSessionHasErrors('thumbnail');

    expect(Board::count())->toEqual(0);
});

test('images beyond the maximum dimensions are rejected', function () {
    [$user, $team] = thumbnailWorkspace();

    $tooWide = config('boards.thumbnails.max_dimension') + 100;

    $this->actingAs($user)
        ->post(route('boards.store', ['current_team' => $team->slug]), [
            'name' => 'Wide upload',
            'thumbnail' => UploadedFile::fake()->image('wide.png', $tooWide, 200),
        ])
        ->assertSessionHasErrors('thumbnail');

    expect(Board::count())->toEqual(0);
});

test('viewers cannot set a thumbnail', function () {
    [$owner, $team] = thumbnailWorkspace(TeamRole::Owner);

    $viewer = User::factory()->create();
    $team->members()->attach($viewer, ['role' => TeamRole::Viewer->value]);

    $board = Board::factory()->forTeam($team)->createdBy($owner)->create();

    $this->actingAs($viewer)
        ->patch(route('boards.update', ['current_team' => $team->slug, 'board' => $board]), [
            'name' => $board->name,
            'thumbnail' => UploadedFile::fake()->image('cover.png', 400, 200),
        ])
        ->assertForbidden();

    expect($board->fresh()->hasThumbnail())->toBeFalse();
});

test('duplicating a board copies its thumbnail to a separate file', function () {
    [$user, $team] = thumbnailWorkspace();

    $board = Board::factory()->forTeam($team)->createdBy($user)->create();

    $this->actingAs($user)
        ->patch(route('boards.update', ['current_team' => $team->slug, 'board' => $board]), [
            'name' => $board->name,
            'thumbnail' => UploadedFile::fake()->image('cover.png', 400, 200),
        ])
        ->assertRedirect();

    $board->refresh();

    $this->actingAs($user)
        ->post(route('boards.duplicate', ['current_team' => $team->slug, 'board' => $board]))
        ->assertRedirect();

    $copy = Board::where('id', '!=', $board->id)->firstOrFail();

    expect($copy->hasThumbnail())->toBeTrue()
        ->and($copy->thumbnail_path)->not->toEqual($board->thumbnail_path);

    $disk = Storage::disk(config('boards.thumbnails.disk'));

    $disk->assertExists($copy->thumbnail_path);
    $disk->assertExists($board->thumbnail_path);

    // Removing the copy's thumbnail must not touch the original's file.
    $this->actingAs($user)
        ->patch(route('boards.update', ['current_team' => $team->slug, 'board' => $copy]), [
            'name' => $copy->name,
            'remove_thumbnail' => '1',
        ])
        ->assertRedirect();

    $disk->assertExists($board->thumbnail_path);
});

test('the board index shows the thumbnail instead of the gradient', function () {
    [$user, $team] = thumbnailWorkspace();

    $board = Board::factory()->forTeam($team)->createdBy($user)->create();

    $this->actingAs($user)
        ->get(route('boards.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertDontSee('data-test="board-thumbnail"', false);

    $this->actingAs($user)
        ->patch(route('boards.update', ['current_team' => $team->slug, 'board' => $board]), [
            'name' => $board->name,
            'thumbnail' => UploadedFile::fake()->image('cover.png', 400, 200),
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('boards.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertSee('data-test="board-thumbnail"', false)
        ->assertSee($board->fresh()->thumbnailUrl(), false);
});

test('the board header shows the thumbnail', function () {
    [$user, $team] = thumbnailWorkspace();

    $board = Board::factory()->forTeam($team)->createdBy($user)->create();

    $this->actingAs($user)
        ->patch(route('boards.update', ['current_team' => $team->slug, 'board' => $board]), [
            'name' => $board->name,
            'thumbnail' => UploadedFile::fake()->image('cover.png', 400, 200),
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('boards.show', ['current_team' => $team->slug, 'board' => $board]))
        ->assertOk()
        ->assertSee('data-test="board-thumbnail"', false)
        ->assertSee($board->fresh()->thumbnailUrl(), false);
});

test('the board forms accept file uploads', function () {
    [$user, $team] = thumbnailWorkspace();

    $board = Board::factory()->forTeam($team)->createdBy($user)->create();

    $this->actingAs($user)
        ->get(route('boards.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertSee('enctype="multipart/form-data"', false)
        ->assertSee('create-board-thumbnail');

    $this->actingAs($user)
        ->get(route('boards.show', ['current_team' => $team->slug, 'board' => $board]))
        ->assertOk()
        ->assertSee('enctype="multipart/form-data"', false)
        ->assertSee('board-settings-thumbnail');
});
