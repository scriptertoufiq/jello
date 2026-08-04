<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueBoardSlugs;
use App\Enums\BoardVisibility;
use Database\Factories\BoardFactory;
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
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $background
 * @property string|null $thumbnail_disk
 * @property string|null $thumbnail_path
 * @property BoardVisibility $visibility
 * @property int $created_by
 * @property float $position
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read User $creator
 * @property-read Collection<int, BoardList> $lists
 * @property-read Collection<int, Label> $labels
 * @property-read Collection<int, Activity> $activities
 * @property-read Collection<int, BoardMember> $boardMemberships
 * @property-read Collection<int, User> $members
 */
#[Fillable([
    'team_id',
    'name',
    'slug',
    'description',
    'background',
    'thumbnail_disk',
    'thumbnail_path',
    'visibility',
    'created_by',
    'position',
    'archived_at',
])]
class Board extends Model
{
    /** @use HasFactory<BoardFactory> */
    use GeneratesUniqueBoardSlugs, HasFactory, SoftDeletes;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'background' => 'slate',
        'visibility' => BoardVisibility::Workspace->value,
        'position' => 0,
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Board $board) {
            if (empty($board->slug)) {
                $board->slug = static::generateUniqueBoardSlug($board->name, $board->team_id);
            }
        });

        static::updating(function (Board $board) {
            if ($board->isDirty('name')) {
                $board->slug = static::generateUniqueBoardSlug($board->name, $board->team_id, $board->id);
            }
        });
    }

    /**
     * Get the workspace the board belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who created the board.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the board's lists (columns), in board order.
     *
     * @return HasMany<BoardList, $this>
     */
    public function lists(): HasMany
    {
        return $this->hasMany(BoardList::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Get the labels available on this board.
     *
     * @return HasMany<Label, $this>
     */
    public function labels(): HasMany
    {
        return $this->hasMany(Label::class)->orderBy('name');
    }

    /**
     * Get the board's activity feed, newest first.
     *
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest();
    }

    /**
     * Get the explicit board memberships.
     *
     * Only meaningful for private boards; workspace boards are visible to
     * every workspace member without a row here.
     *
     * @return HasMany<BoardMember, $this>
     */
    public function boardMemberships(): HasMany
    {
        return $this->hasMany(BoardMember::class);
    }

    /**
     * Get the users explicitly added to the board.
     *
     * @return BelongsToMany<User, $this, BoardMember, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'board_members', 'board_id', 'user_id')
            ->using(BoardMember::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Determine if the board has a cover image.
     */
    public function hasThumbnail(): bool
    {
        return $this->thumbnail_path !== null && $this->thumbnail_disk !== null;
    }

    /**
     * Get the public URL of the board's cover image.
     *
     * Returns null when no thumbnail is set, so callers can fall back to the
     * background gradient.
     */
    public function thumbnailUrl(): ?string
    {
        if (! $this->hasThumbnail()) {
            return null;
        }

        return Storage::disk($this->thumbnail_disk)->url($this->thumbnail_path);
    }

    /**
     * Get the Tailwind gradient classes for the board's background.
     */
    public function backgroundGradient(): string
    {
        $gradients = config('boards.backgrounds');

        return $gradients[$this->background] ?? reset($gradients);
    }

    /**
     * Determine if the board is archived.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Determine if the board is private.
     */
    public function isPrivate(): bool
    {
        return $this->visibility === BoardVisibility::Private;
    }

    /**
     * Scope the query to boards that are not archived.
     *
     * @param  Builder<Board>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * Scope the query to archived boards.
     *
     * @param  Builder<Board>  $query
     */
    #[Scope]
    protected function archived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    /**
     * Scope the query to boards the given user is allowed to see.
     *
     * Workspace boards are visible to all members; private boards only to
     * their explicit members.
     *
     * @param  Builder<Board>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user) {
            $query->where('visibility', BoardVisibility::Workspace)
                ->orWhereHas('boardMemberships', fn (Builder $query) => $query->where('user_id', $user->id));
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => BoardVisibility::class,
            'position' => 'float',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
