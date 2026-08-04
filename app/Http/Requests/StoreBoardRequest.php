<?php

namespace App\Http\Requests;

use App\Enums\BoardVisibility;
use Illuminate\Foundation\Http\FormRequest;
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
        ];
    }
}
