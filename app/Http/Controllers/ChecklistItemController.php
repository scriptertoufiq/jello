<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChecklistItemRequest;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Team;
use App\Services\Cards\ChecklistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ChecklistItemController extends Controller
{
    public function __construct(private readonly ChecklistService $checklists) {}

    /**
     * Append an item to the checklist.
     */
    public function store(StoreChecklistItemRequest $request, Team $currentTeam, Checklist $checklist): RedirectResponse
    {
        Gate::authorize('update', $checklist);

        $this->checklists->addItem($checklist, $request->content());

        return back()->with('status', __('Item added.'));
    }

    /**
     * Update an item's text.
     */
    public function update(StoreChecklistItemRequest $request, Team $currentTeam, ChecklistItem $item): RedirectResponse
    {
        Gate::authorize('update', $item->checklist);

        $this->checklists->updateItem($item, $request->content());

        return back()->with('status', __('Item updated.'));
    }

    /**
     * Toggle an item's completed state.
     */
    public function toggle(Team $currentTeam, ChecklistItem $item): RedirectResponse
    {
        Gate::authorize('update', $item->checklist);

        $this->checklists->toggleItem($item);

        return back();
    }

    /**
     * Delete an item.
     */
    public function destroy(Team $currentTeam, ChecklistItem $item): RedirectResponse
    {
        Gate::authorize('update', $item->checklist);

        $this->checklists->deleteItem($item);

        return back()->with('status', __('Item deleted.'));
    }
}
