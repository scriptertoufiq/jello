<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChecklistRequest;
use App\Models\Card;
use App\Models\Checklist;
use App\Models\Team;
use App\Services\Cards\ChecklistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ChecklistController extends Controller
{
    public function __construct(private readonly ChecklistService $checklists) {}

    /**
     * Add a checklist to the card.
     */
    public function store(StoreChecklistRequest $request, Team $currentTeam, Card $card): RedirectResponse
    {
        Gate::authorize('create', [Checklist::class, $card]);

        $this->checklists->create($card, $request->name());

        return back()->with('status', __('Checklist added.'));
    }

    /**
     * Rename a checklist.
     */
    public function update(StoreChecklistRequest $request, Team $currentTeam, Checklist $checklist): RedirectResponse
    {
        Gate::authorize('update', $checklist);

        $this->checklists->rename($checklist, $request->name());

        return back()->with('status', __('Checklist renamed.'));
    }

    /**
     * Delete a checklist and its items.
     */
    public function destroy(Team $currentTeam, Checklist $checklist): RedirectResponse
    {
        Gate::authorize('delete', $checklist);

        $this->checklists->delete($checklist);

        return back()->with('status', __('Checklist deleted.'));
    }
}
