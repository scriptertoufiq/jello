<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCardRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'cover_color' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }

    /**
     * Get the card attributes that were submitted, with empty strings nulled.
     *
     * @return array<string, string|null>
     */
    public function attributesToUpdate(): array
    {
        $updates = [];

        foreach (['title', 'description', 'start_date', 'due_date', 'cover_color'] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            $updates[$field] = is_string($value) && trim($value) !== '' ? $value : null;
        }

        return $updates;
    }
}
