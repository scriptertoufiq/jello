<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLabelRequest extends FormRequest
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
            'color' => ['required', 'string', Rule::in(array_keys(config('boards.label_colors')))],
        ];
    }

    /**
     * Get the validated label name.
     */
    public function name(): string
    {
        return $this->string('name')->toString();
    }

    /**
     * Get the validated label colour key.
     */
    public function color(): string
    {
        return $this->string('color')->toString();
    }
}
