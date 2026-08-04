<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Team;
use App\Models\User;
use App\Services\Support\PositionCalculator;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed a populated workspace so the board UI can be reviewed by hand.
     *
     * Creates demo@example.com (password: password) as the workspace owner,
     * plus one member and one viewer for checking role-gated controls.
     */
    public function run(): void
    {
        $owner = User::factory()->create([
            'name' => 'Demo Owner',
            'email' => 'demo@example.com',
        ]);

        $member = User::factory()->create([
            'name' => 'Demo Member',
            'email' => 'member@example.com',
        ]);

        $viewer = User::factory()->create([
            'name' => 'Demo Viewer',
            'email' => 'viewer@example.com',
        ]);

        // The factory derives its slug from a random name, so set both here.
        $team = Team::factory()->create([
            'name' => 'Acme Product',
            'slug' => 'acme-product',
        ]);

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $team->members()->attach($viewer, ['role' => TeamRole::Viewer->value]);

        foreach ([$owner, $member, $viewer] as $user) {
            $user->update(['current_team_id' => $team->id]);
        }

        $this->seedRoadmap($team, $owner);

        Board::factory()->forTeam($team)->createdBy($owner)->create([
            'name' => 'Design System',
            'description' => 'Component work and design debt.',
            'background' => 'violet',
        ]);

        Board::factory()->forTeam($team)->createdBy($owner)->archived()->create([
            'name' => 'Q3 Retrospective',
            'background' => 'amber',
        ]);
    }

    /**
     * Build a realistic roadmap board with populated columns.
     */
    private function seedRoadmap(Team $team, User $owner): void
    {
        $board = Board::factory()->forTeam($team)->createdBy($owner)->create([
            'name' => 'Product Roadmap',
            'description' => 'What we are building this quarter.',
            'background' => 'blue',
        ]);

        $columns = [
            'Backlog' => [
                ['title' => 'Research competitor onboarding flows'],
                ['title' => 'Draft pricing page copy'],
                ['title' => 'Audit mobile navigation'],
            ],
            'In Progress' => [
                ['title' => 'Build board drag and drop', 'due' => '+3 days'],
                ['title' => 'Workspace member roles', 'due' => '-1 day'],
            ],
            'In Review' => [
                ['title' => 'Card due date reminders'],
            ],
            'Done' => [
                ['title' => 'Set up the workspace switcher', 'complete' => true],
                ['title' => 'Ship authentication', 'complete' => true],
            ],
        ];

        $listPositions = (new PositionCalculator)->sequence(count($columns));
        $columnIndex = 0;

        foreach ($columns as $name => $cards) {
            $list = BoardList::factory()->forBoard($board)->create([
                'name' => $name,
                'position' => $listPositions[$columnIndex],
            ]);

            $cardPositions = (new PositionCalculator)->sequence(count($cards));

            foreach ($cards as $index => $card) {
                Card::factory()
                    ->forList($list)
                    ->createdBy($owner)
                    ->create([
                        'title' => $card['title'],
                        'description' => null,
                        'position' => $cardPositions[$index],
                        'due_date' => isset($card['due']) ? now()->modify($card['due']) : null,
                        'completed_at' => isset($card['complete']) ? now() : null,
                    ]);
            }

            $columnIndex++;
        }
    }
}
