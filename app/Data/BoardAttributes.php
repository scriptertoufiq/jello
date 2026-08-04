<?php

namespace App\Data;

use App\Enums\BoardVisibility;
use App\Models\Board;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The writable attributes of a board, resolved into concrete types.
 *
 * Form requests hand the service one of these rather than a loose array, so
 * the service never has to guess at types or defaults.
 */
final readonly class BoardAttributes
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $background,
        public BoardVisibility $visibility,
    ) {}

    /**
     * Build the attributes from a validated request.
     *
     * Pass the board being edited so that fields the form omitted keep their
     * current value instead of being reset to a default.
     */
    public static function fromRequest(FormRequest $request, ?Board $current = null): self
    {
        $name = $request->input('name');
        $description = $request->input('description');
        $background = $request->input('background');
        $visibility = $request->input('visibility');

        return new self(
            name: is_string($name) ? $name : (string) $current?->name,
            description: $request->has('description')
                ? (is_string($description) && trim($description) !== '' ? $description : null)
                : $current?->description,
            background: is_string($background) ? $background : ($current->background ?? 'slate'),
            visibility: (is_string($visibility) ? BoardVisibility::tryFrom($visibility) : null)
                ?? $current->visibility
                ?? BoardVisibility::Workspace,
        );
    }

    /**
     * Get the attributes as a mass-assignable array.
     *
     * @return array{name: string, description: string|null, background: string, visibility: BoardVisibility}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'background' => $this->background,
            'visibility' => $this->visibility,
        ];
    }
}
