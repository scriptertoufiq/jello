<?php

namespace App\Services\Support;

/**
 * Computes fractional ordering positions.
 *
 * Reordering stores a decimal position rather than a contiguous integer index,
 * so moving one item is a single-row update instead of a rewrite of every
 * sibling. Positions are only rebalanced when neighbouring values get too
 * close to split further, which in practice is rare.
 */
final class PositionCalculator
{
    /**
     * The spacing used when appending to the end of a sequence.
     */
    public const GAP = 65536.0;

    /**
     * The smallest gap that can still be split meaningfully.
     */
    public const MIN_GAP = 0.00001;

    /**
     * Compute a position that sorts between the two given neighbours.
     *
     * Pass null for a missing neighbour: a null $before means "insert first",
     * a null $after means "append last".
     */
    public function between(?float $before, ?float $after): float
    {
        if ($before === null && $after === null) {
            return self::GAP;
        }

        if ($before === null) {
            return (float) $after / 2;
        }

        if ($after === null) {
            return $before + self::GAP;
        }

        return ($before + $after) / 2;
    }

    /**
     * Determine whether two neighbouring positions are too close to split.
     */
    public function needsRebalance(?float $before, ?float $after): bool
    {
        if ($before === null || $after === null) {
            return false;
        }

        return abs($after - $before) < self::MIN_GAP;
    }

    /**
     * Compute evenly spaced positions for the given number of items.
     *
     * @return array<int, float>
     */
    public function sequence(int $count): array
    {
        return array_map(
            fn (int $index): float => ($index + 1) * self::GAP,
            range(0, max($count - 1, 0)),
        );
    }
}
