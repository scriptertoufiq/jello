<?php

namespace App\Http\Requests;

use App\Enums\BoardVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBoardRequest extends FormRequest
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
        ];
    }
}
