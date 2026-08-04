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

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $background
 * @property BoardVisibility $visibility
 * @property int $created_by
 * @property float $position
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read User $creator
 * @property-read Collection<int, BoardMember> $boardMemberships
 * @property-read Collection<int, User> $members
 */
#[Fillable(['team_id', 'name', 'slug', 'description', 'background', 'visibility', 'created_by', 'position', 'archived_at'])]
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
