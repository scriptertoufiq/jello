<?php

namespace App\Http\Requests;

use App\Enums\BoardVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreBoardRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'background' => ['nullable', 'string', Rule::in(array_keys(config('boards.backgrounds')))],
            'visibility' => ['nullable', Rule::enum(BoardVisibility::class)],
            'thumbnail' => $this->thumbnailRules(),
        ];
    }

    /**
     * Get the validation rules for an uploaded cover image.
     *
     * @return array<int, mixed>
     */
    protected function thumbnailRules(): array
    {
        return [
            'nullable',
            'image',
            'mimes:'.implode(',', config('boards.thumbnails.mimes')),
            'max:'.config('boards.thumbnails.max_kilobytes'),
            Rule::dimensions()
                ->maxWidth(config('boards.thumbnails.max_dimension'))
                ->maxHeight(config('boards.thumbnails.max_dimension')),
        ];
    }

    /**
     * Get the uploaded cover image, if one was provided.
     */
    public function thumbnail(): ?UploadedFile
    {
        $file = $this->file('thumbnail');

        return $file instanceof UploadedFile ? $file : null;
    }
}
