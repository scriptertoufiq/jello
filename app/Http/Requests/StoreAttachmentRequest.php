<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreAttachmentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.config('boards.attachments.max_kilobytes')],
        ];
    }

    /**
     * Get the uploaded file.
     */
    public function uploadedFile(): UploadedFile
    {
        $file = $this->file('file');

        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }
}
