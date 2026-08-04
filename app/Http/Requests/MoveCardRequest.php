<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveCardRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'board_list_id' => ['required', 'integer', Rule::exists('board_lists', 'id')],
            'index' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Get the destination list id.
     */
    public function boardListId(): int
    {
        return $this->integer('board_list_id');
    }

    /**
     * Get the destination index within the list.
     */
    public function index(): int
    {
        return $this->integer('index');
    }
}
