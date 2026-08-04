<?php

namespace App\Models;

use Database\Factories\ChecklistItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $checklist_id
 * @property string $content
 * @property bool $is_completed
 * @property float $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Checklist $checklist
 */
#[Fillable(['checklist_id', 'content', 'is_completed', 'position'])]
class ChecklistItem extends Model
{
    /** @use HasFactory<ChecklistItemFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_completed' => false,
        'position' => 0,
    ];

    /**
     * Get the checklist the item belongs to.
     *
     * @return BelongsTo<Checklist, $this>
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'position' => 'float',
        ];
    }
}
