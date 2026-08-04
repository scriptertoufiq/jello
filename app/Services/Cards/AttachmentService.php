<?php

namespace App\Services\Cards;

use App\Models\Attachment;
use App\Models\Card;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Owns attachment storage and the attachments table.
 */
final class AttachmentService
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Store an uploaded file against the card.
     */
    public function store(Card $card, User $uploader, UploadedFile $file): Attachment
    {
        $disk = config('boards.attachments.disk');

        $path = $file->store("cards/{$card->id}", $disk);

        return DB::transaction(function () use ($card, $uploader, $file, $disk, $path): Attachment {
            $attachment = $card->attachments()->create([
                'user_id' => $uploader->id,
                'disk' => $disk,
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);

            $this->activity->forCard($card, 'attachment.uploaded', ['name' => $attachment->name], $uploader);

            return $attachment;
        });
    }

    /**
     * Delete the attachment and the file behind it.
     */
    public function delete(Attachment $attachment): void
    {
        DB::transaction(function () use ($attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);

            $attachment->delete();
        });
    }
}
