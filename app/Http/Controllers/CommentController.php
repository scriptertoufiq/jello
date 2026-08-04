<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Models\Card;
use App\Models\Comment;
use App\Models\Team;
use App\Services\Cards\CommentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function __construct(private readonly CommentService $comments) {}

    /**
     * Add a comment to the card.
     */
    public function store(StoreCommentRequest $request, Team $currentTeam, Card $card): RedirectResponse
    {
        Gate::authorize('create', [Comment::class, $card]);

        $this->comments->create($card, $request->user(), $request->body());

        return back()->with('status', __('Comment added.'));
    }

    /**
     * Edit a comment.
     */
    public function update(UpdateCommentRequest $request, Team $currentTeam, Comment $comment): RedirectResponse
    {
        Gate::authorize('update', $comment);

        $this->comments->update($comment, $request->body());

        return back()->with('status', __('Comment updated.'));
    }

    /**
     * Delete a comment.
     */
    public function destroy(Team $currentTeam, Comment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);

        $this->comments->delete($comment);

        return back()->with('status', __('Comment deleted.'));
    }
}
