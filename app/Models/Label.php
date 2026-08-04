<?php

namespace App\Models;

use Database\Factories\LabelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $board_id
 * @property string $name
 * @property string $color
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Board $board
 */
#[Fillable(['board_id', 'name', 'color'])]
class Label extends Model
{
    /** @use HasFactory<LabelFactory> */
    use HasFactory;

    /**
     * Get the board the label belongs to.
     *
     * @return BelongsTo<Board, $this>
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * Get the cards carrying this label.
     *
     * @return BelongsToMany<Card, $this>
     */
    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class, 'card_label');
    }
}
