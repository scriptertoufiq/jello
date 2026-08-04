<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCardAssigneeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * Get the id of the user being assigned.
     */
    public function userId(): int
    {
        return $this->integer('user_id');
    }
}
