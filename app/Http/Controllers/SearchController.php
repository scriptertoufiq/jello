<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Team;
use App\Services\Search\BoardSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SearchController extends Controller
{
    public function __construct(private readonly BoardSearchService $search) {}

    /**
     * Search cards across the workspace.
     */
    public function index(Request $request, Team $currentTeam): View
    {
        Gate::authorize('viewAny', [Board::class, $currentTeam]);

        $user = $request->user();

        $filters = [
            'term' => $request->string('term')->trim()->value() ?: null,
            'board' => $request->integer('board') ?: null,
            'assignee' => $request->integer('assignee') ?: null,
            'label' => $request->integer('label') ?: null,
            'due' => $request->string('due')->value() ?: null,
        ];

        return view('pages.boards.search', [
            'team' => $currentTeam,
            'filters' => $filters,
            'cards' => $this->search->search($currentTeam, $user, $filters),
            'boards' => $this->search->visibleBoards($currentTeam, $user),
            'members' => $currentTeam->members,
        ]);
    }
}
