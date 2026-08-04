<?php

namespace App\Http\Requests;

use App\Enums\TeamRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBoardMemberRequest extends FormRequest
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
            'role' => ['nullable', Rule::enum(TeamRole::class)],
        ];
    }

    /**
     * Get the id of the user being added.
     */
    public function userId(): int
    {
        return $this->integer('user_id');
    }

    /**
     * Get the board role to grant.
     */
    public function role(): TeamRole
    {
        $role = $this->input('role');

        return (is_string($role) ? TeamRole::tryFrom($role) : null) ?? TeamRole::Member;
    }
}
