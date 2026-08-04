<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChecklistItemRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * Get the validated item content.
     */
    public function content(): string
    {
        return $this->string('content')->toString();
    }
}
