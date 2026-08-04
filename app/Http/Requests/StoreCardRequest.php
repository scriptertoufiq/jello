<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCardRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Get the validated card title.
     */
    public function title(): string
    {
        return $this->string('title')->toString();
    }
}
