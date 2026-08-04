<?php

namespace App\Models;

use Database\Factories\BoardListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A column on a board.
 *
 * Named BoardList because "list" is a reserved word in several databases.
 *
 * @property int $id
 * @property int $board_id
 * @property string $name
 * @property float $position
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Board $board
 * @property-read Collection<int, Card> $cards
 */
#[Fillable(['board_id', 'name', 'position', 'archived_at'])]
class BoardList extends Model
{
    /** @use HasFactory<BoardListFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
    ];

    /**
     * Get the board the list belongs to.
     *
     * @return BelongsTo<Board, $this>
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * Get the cards in this list, in board order.
     *
     * @return HasMany<Card, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Determine if the list is archived.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Scope the query to lists that are not archived.
     *
     * @param  Builder<BoardList>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'float',
            'archived_at' => 'datetime',
        ];
    }
}
