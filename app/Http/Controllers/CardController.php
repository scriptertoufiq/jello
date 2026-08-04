<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveCardRequest;
use App\Http\Requests\StoreCardRequest;
use App\Http\Requests\UpdateCardRequest;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Team;
use App\Services\Cards\CardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CardController extends Controller
{
    public function __construct(private readonly CardService $cards) {}

    /**
     * Add a card to the given list.
     */
    public function store(StoreCardRequest $request, Team $currentTeam, BoardList $list): RedirectResponse
    {
        Gate::authorize('create', [Card::class, $list]);

        $this->cards->create($list, $request->user(), $request->title());

        return back()->with('status', __('Card added.'));
    }

    /**
     * Show a single card's detail page.
     */
    public function show(Team $currentTeam, Card $card): View
    {
        Gate::authorize('view', $card);

        $card->load([
            'list.board.team',
            'creator',
            'labels',
            'assignees',
            'checklists.items',
            'comments.author',
            'attachments.uploader',
            'activities.user',
        ]);

        return view('pages.cards.show', [
            'team' => $currentTeam,
            'board' => $card->board(),
            'card' => $card,
        ]);
    }

    /**
     * Update the card's details.
     */
    public function update(UpdateCardRequest $request, Team $currentTeam, Card $card): RedirectResponse
    {
        Gate::authorize('update', $card);

        $this->cards->update($card, $request->attributesToUpdate());

        return back()->with('status', __('Card updated.'));
    }

    /**
     * Move the card into a list at a given index.
     */
    public function move(MoveCardRequest $request, Team $currentTeam, Card $card): RedirectResponse
    {
        Gate::authorize('move', $card);

        $destination = BoardList::findOrFail($request->boardListId());

        // A card may only move between lists on the same board.
        if ($destination->board_id !== $card->list->board_id) {
            throw new NotFoundHttpException;
        }

        $this->cards->move($card, $destination, $request->index());

        return back()->with('status', __('Card moved.'));
    }

    /**
     * Toggle the card's completion state.
     */
    public function complete(Team $currentTeam, Card $card): RedirectResponse
    {
        Gate::authorize('update', $card);

        $this->cards->toggleComplete($card);

        return back()->with('status', __('Card updated.'));
    }

    /**
     * Archive the card.
     */
    public function archive(Team $currentTeam, Card $card): RedirectResponse
    {
        Gate::authorize('archive', $card);

        $this->cards->archive($card);

        return back()->with('status', __('Card archived.'));
    }

    /**
     * Restore an archived card.
     */
    public function restore(Team $currentTeam, Card $card): RedirectResponse
    {
        Gate::authorize('archive', $card);

        $this->cards->restore($card);

        return back()->with('status', __('Card restored.'));
    }

    /**
     * Delete the card.
     */
    public function destroy(Team $currentTeam, Card $card): RedirectResponse
    {
        Gate::authorize('delete', $card);

        $this->cards->delete($card);

        return back()->with('status', __('Card deleted.'));
    }
}
