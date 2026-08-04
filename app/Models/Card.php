<?php

namespace App\Models;

use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $board_list_id
 * @property string $title
 * @property string|null $description
 * @property float $position
 * @property Carbon|null $start_date
 * @property Carbon|null $due_date
 * @property Carbon|null $completed_at
 * @property string|null $cover_color
 * @property int $created_by
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read BoardList $list
 * @property-read User $creator
 * @property-read Collection<int, Label> $labels
 * @property-read Collection<int, User> $assignees
 * @property-read Collection<int, Checklist> $checklists
 * @property-read Collection<int, Comment> $comments
 * @property-read Collection<int, Attachment> $attachments
 * @property-read Collection<int, Activity> $activities
 */
#[Fillable([
    'board_list_id',
    'title',
    'description',
    'position',
    'start_date',
    'due_date',
    'completed_at',
    'cover_color',
    'created_by',
    'archived_at',
])]
class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
    ];

    /**
     * Get the list the card sits in.
     *
     * @return BelongsTo<BoardList, $this>
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(BoardList::class, 'board_list_id');
    }

    /**
     * Get the user who created the card.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the labels applied to the card.
     *
     * @return BelongsToMany<Label, $this>
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'card_label');
    }

    /**
     * Get the users assigned to the card.
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'card_user')->withTimestamps();
    }

    /**
     * Get the card's checklists, in order.
     *
     * @return HasMany<Checklist, $this>
     */
    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Get the card's comments, newest first.
     *
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    /**
     * Get the card's attachments, newest first.
     *
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->latest();
    }

    /**
     * Get the card's activity feed, newest first.
     *
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest();
    }

    /**
     * Get the board the card ultimately belongs to.
     */
    public function board(): Board
    {
        return $this->list->board;
    }

    /**
     * Determine if the card is archived.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Determine if the card has been marked complete.
     */
    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Determine if the card is past its due date and still open.
     */
    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && ! $this->isComplete()
            && $this->due_date->isPast();
    }

    /**
     * Scope the query to cards that are not archived.
     *
     * @param  Builder<Card>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * Scope the query to cards on the given board.
     *
     * @param  Builder<Card>  $query
     */
    #[Scope]
    protected function onBoard(Builder $query, Board $board): void
    {
        $query->whereHas('list', fn (Builder $query) => $query->where('board_id', $board->id));
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
            'start_date' => 'date',
            'due_date' => 'datetime',
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
