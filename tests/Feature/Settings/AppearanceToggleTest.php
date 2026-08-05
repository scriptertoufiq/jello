<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

/**
 * Build a signed-in user with a workspace.
 *
 * @return array{0: User, 1: Team}
 */
function appearanceUser(): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->update(['current_team_id' => $team->id]);

    return [$user, $team];
}

test('the app header offers an appearance toggle', function () {
    [$user, $team] = appearanceUser();

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertSee('data-test="appearance-toggle"', false)
        ->assertSee('$flux.dark = ! $flux.dark', false);
});

test('the toggle shows a sun in dark mode and a moon in light mode', function () {
    [$user, $team] = appearanceUser();

    $html = $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('x-show="$flux.dark"')
        ->and($html)->toContain('x-show="! $flux.dark"');
});

test('no layout forces dark mode any more', function () {
    [$user, $team] = appearanceUser();

    // A hardcoded `class="dark"` on <html> overrides the user's choice and
    // silently breaks both this toggle and the appearance settings page.
    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertDontSee('<html lang="en" class="dark">', false);
});

test('the guest auth pages do not force dark mode either', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('<html lang="en" class="dark">', false);
});

test('every layout leaves the html class to flux', function () {
    $layouts = glob(resource_path('views/layouts/**/*.blade.php'));

    expect($layouts)->not->toBeEmpty();

    foreach ($layouts as $layout) {
        expect(file_get_contents($layout))
            ->not->toContain('class="dark"', "[{$layout}] hardcodes dark mode.");
    }
});

test('the appearance settings page still offers the three way choice', function () {
    [$user, $team] = appearanceUser();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertSee('Light')
        ->assertSee('Dark')
        ->assertSee('System');
});

test('the toggle is present on a board page too', function () {
    [$user, $team] = appearanceUser();

    $this->actingAs($user)
        ->get(route('boards.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertSee('data-test="appearance-toggle"', false);
});
