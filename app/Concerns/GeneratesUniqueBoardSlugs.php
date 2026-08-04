<?php

namespace App\Concerns;

use Illuminate\Support\Str;

trait GeneratesUniqueBoardSlugs
{
    /**
     * Generate a slug that is unique within the given team.
     *
     * Board slugs only need to be unique per workspace, so two teams may each
     * have a board at /{team}/boards/roadmap.
     */
    protected static function generateUniqueBoardSlug(string $name, int $teamId, ?int $excludeId = null): string
    {
        $defaultSlug = Str::slug($name) ?: 'board';

        $existingSlugs = static::withTrashed()
            ->where('team_id', $teamId)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->where(function ($query) use ($defaultSlug) {
                $query->where('slug', $defaultSlug)
                    ->orWhere('slug', 'like', $defaultSlug.'-%');
            })
            ->pluck('slug');

        if ($existingSlugs->isEmpty()) {
            return $defaultSlug;
        }

        $maxSuffix = $existingSlugs
            ->map(function (string $slug) use ($defaultSlug): ?int {
                if ($slug === $defaultSlug) {
                    return 0;
                }

                if (preg_match('/^'.preg_quote($defaultSlug, '/').'-(\d+)$/', $slug, $matches)) {
                    return (int) $matches[1];
                }

                return null;
            })
            ->filter(fn (?int $suffix): bool => $suffix !== null)
            ->max() ?? 0;

        return $defaultSlug.'-'.($maxSuffix + 1);
    }
}
