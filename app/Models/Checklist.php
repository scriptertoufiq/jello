<?php

namespace App\Models;

use Database\Factories\ChecklistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $card_id
 * @property string $name
 * @property float $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Card $card
 * @property-read Collection<int, ChecklistItem> $items
 */
#[Fillable(['card_id', 'name', 'position'])]
class Checklist extends Model
{
    /** @use HasFactory<ChecklistFactory> */
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
     * Get the card the checklist belongs to.
     *
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * Get the checklist's items, in order.
     *
     * @return HasMany<ChecklistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Get the number of completed items.
     */
    public function completedCount(): int
    {
        return $this->items->where('is_completed', true)->count();
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
        ];
    }
}
