<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Models\Card;
use App\Models\Team;
use App\Services\Cards\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __construct(private readonly AttachmentService $attachments) {}

    /**
     * Attach an uploaded file to the card.
     */
    public function store(StoreAttachmentRequest $request, Team $currentTeam, Card $card): RedirectResponse
    {
        Gate::authorize('create', [Attachment::class, $card]);

        $this->attachments->store($card, $request->user(), $request->uploadedFile());

        return back()->with('status', __('File attached.'));
    }

    /**
     * Download an attachment.
     */
    public function download(Team $currentTeam, Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->name);
    }

    /**
     * Delete an attachment.
     */
    public function destroy(Team $currentTeam, Attachment $attachment): RedirectResponse
    {
        Gate::authorize('delete', $attachment);

        $this->attachments->delete($attachment);

        return back()->with('status', __('Attachment removed.'));
    }
}
