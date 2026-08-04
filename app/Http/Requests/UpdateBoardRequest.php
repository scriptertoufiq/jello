<?php

namespace App\Http\Requests;

use App\Enums\BoardVisibility;
use Illuminate\Validation\Rule;

/**
 * Extends the store request so the cover image rules and accessor are shared.
 */
class UpdateBoardRequest extends StoreBoardRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'background' => ['sometimes', 'required', 'string', Rule::in(array_keys(config('boards.backgrounds')))],
            'visibility' => ['sometimes', 'required', Rule::enum(BoardVisibility::class)],
            'thumbnail' => $this->thumbnailRules(),
            'remove_thumbnail' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Determine whether the board's cover image should be cleared.
     */
    public function wantsThumbnailRemoved(): bool
    {
        return $this->boolean('remove_thumbnail');
    }
}
