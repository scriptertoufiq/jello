<?php

namespace App\Services\Cards;

use App\Models\Card;
use App\Models\Comment;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Owns every write to the comments table.
 */
final class CommentService
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Add a comment to the card.
     */
    public function create(Card $card, User $author, string $body): Comment
    {
        return DB::transaction(function () use ($card, $author, $body): Comment {
            $comment = $card->comments()->create([
                'user_id' => $author->id,
                'body' => $body,
            ]);

            $this->activity->forCard($card, 'comment.created', actor: $author);

            return $comment;
        });
    }

    /**
     * Edit a comment's body.
     */
    public function update(Comment $comment, string $body): Comment
    {
        $comment->fill(['body' => $body])->save();

        return $comment;
    }

    /**
     * Soft delete a comment.
     */
    public function delete(Comment $comment): void
    {
        $comment->delete();
    }
}
