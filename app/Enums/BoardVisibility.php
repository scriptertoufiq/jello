<?php

namespace App\Enums;

enum BoardVisibility: string
{
    /**
     * Every member of the workspace can see the board.
     */
    case Workspace = 'workspace';

    /**
     * Only users explicitly added to the board can see it.
     */
    case Private = 'private';

    /**
     * Get the display label for the visibility.
     */
    public function label(): string
    {
        return match ($this) {
            self::Workspace => __('Workspace'),
            self::Private => __('Private'),
        };
    }

    /**
     * Get the description shown alongside the visibility option.
     */
    public function description(): string
    {
        return match ($this) {
            self::Workspace => __('Everyone in the workspace can see this board.'),
            self::Private => __('Only board members can see this board.'),
        };
    }

    /**
     * Get the icon representing the visibility.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Workspace => 'users',
            self::Private => 'lock-closed',
        };
    }

    /**
     * Get the visibilities as select options.
     *
     * @return array<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $visibility): array => [
            'value' => $visibility->value,
            'label' => $visibility->label(),
            'description' => $visibility->description(),
        ], self::cases());
    }
}
