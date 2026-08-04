<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferOwnershipRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'different:current_user', Rule::exists('users', 'id')],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['current_user' => $this->user()?->id]);
    }

    /**
     * Get the id of the incoming owner.
     */
    public function userId(): int
    {
        return $this->integer('user_id');
    }
}
