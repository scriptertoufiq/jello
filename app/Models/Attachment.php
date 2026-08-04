<?php

namespace App\Models;

use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * @property int $id
 * @property int $card_id
 * @property int $user_id
 * @property string $disk
 * @property string $path
 * @property string $name
 * @property string|null $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Card $card
 * @property-read User $uploader
 */
#[Fillable(['card_id', 'user_id', 'disk', 'path', 'name', 'mime_type', 'size'])]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    /**
     * Get the card the attachment belongs to.
     *
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * Get the user who uploaded the attachment.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get a temporary or public URL for the stored file.
     */
    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Determine if the attachment is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    /**
     * Get the file size formatted for display.
     */
    public function humanSize(): string
    {
        return (string) Number::fileSize($this->size, precision: 1);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
