<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An entry in a board's activity feed.
 *
 * @property int $id
 * @property int $team_id
 * @property int $board_id
 * @property int|null $card_id
 * @property int|null $user_id
 * @property string $action
 * @property array<string, mixed>|null $properties
 * @property Carbon|null $created_at
 * @property-read Board $board
 * @property-read Card|null $card
 * @property-read User|null $user
 */
#[Fillable(['team_id', 'board_id', 'card_id', 'user_id', 'action', 'properties'])]
class Activity extends Model
{
    /**
     * Activities are append-only, so only a created_at column exists.
     *
     * @var string|null
     */
    public const UPDATED_AT = null;

    /**
     * Get the board the activity happened on.
     *
     * @return BelongsTo<Board, $this>
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * Get the card the activity relates to, if any.
     *
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * Get the user who performed the action.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get a human readable description of the activity.
     */
    public function describe(): string
    {
        $actor = $this->user->name ?? __('Someone');

        return match ($this->action) {
            'card.created' => __(':actor added this card', ['actor' => $actor]),
            'card.moved' => __(':actor moved this card to :list', ['actor' => $actor, 'list' => $this->property('list', '—')]),
            'card.completed' => __(':actor marked this card complete', ['actor' => $actor]),
            'card.reopened' => __(':actor reopened this card', ['actor' => $actor]),
            'card.archived' => __(':actor archived this card', ['actor' => $actor]),
            'card.restored' => __(':actor restored this card', ['actor' => $actor]),
            'card.updated' => __(':actor updated this card', ['actor' => $actor]),
            'card.assigned' => __(':actor assigned :user', ['actor' => $actor, 'user' => $this->property('user', '—')]),
            'card.unassigned' => __(':actor unassigned :user', ['actor' => $actor, 'user' => $this->property('user', '—')]),
            'comment.created' => __(':actor commented', ['actor' => $actor]),
            'attachment.uploaded' => __(':actor attached :name', ['actor' => $actor, 'name' => $this->property('name', '—')]),
            default => __(':actor made a change', ['actor' => $actor]),
        };
    }

    /**
     * Read a value from the properties payload.
     */
    private function property(string $key, string $default): string
    {
        $value = $this->properties[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }
}
