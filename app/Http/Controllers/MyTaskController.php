<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Team;
use App\Services\Search\BoardSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MyTaskController extends Controller
{
    public function __construct(private readonly BoardSearchService $search) {}

    /**
     * List the open cards assigned to the current user.
     */
    public function index(Request $request, Team $currentTeam): View
    {
        Gate::authorize('viewAny', [Board::class, $currentTeam]);

        $cards = $this->search->assignedTo($currentTeam, $request->user());

        return view('pages.boards.my-tasks', [
            'team' => $currentTeam,
            'overdue' => $cards->filter->isOverdue()->values(),
            'upcoming' => $cards->reject->isOverdue()->values(),
        ]);
    }
}
