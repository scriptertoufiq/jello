<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Label;
use App\Models\Team;
use App\Services\Cards\CardAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CardLabelController extends Controller
{
    public function __construct(private readonly CardAssignmentService $assignments) {}

    /**
     * Add or remove a label on the card.
     */
    public function toggle(Team $currentTeam, Card $card, Label $label): RedirectResponse
    {
        Gate::authorize('update', $card);

        // A card may only carry labels defined on its own board.
        if ($label->board_id !== $card->board()->id) {
            throw new NotFoundHttpException;
        }

        $this->assignments->toggleLabel($card, $label);

        return back();
    }
}
