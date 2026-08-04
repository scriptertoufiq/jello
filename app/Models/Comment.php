<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $card_id
 * @property int $user_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Card $card
 * @property-read User $author
 */
#[Fillable(['card_id', 'user_id', 'body'])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the card the comment belongs to.
     *
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * Get the comment's author.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Determine if the given user wrote this comment.
     */
    public function wasWrittenBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
