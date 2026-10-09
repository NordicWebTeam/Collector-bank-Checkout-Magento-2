<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher;

/**
 * Splits an amount in integer minor units (öre, cent) over weights with the
 * largest remainder method: every exact share is floored, and the remaining
 * units go to the weights with the largest fractional parts. The shares
 * always add up to the amount, and no share exceeds its weight when the
 * amount does not exceed the total weight.
 */
class MinorUnitSplitter
{
    /**
     * @param array<string|int, int> $weights Non-negative weights
     * @return array<string|int, int> Share per weight key
     */
    public function split(int $units, array $weights): array
    {
        $totalWeight = array_sum($weights);
        if ($units === 0 || $totalWeight === 0) {
            return array_map(fn (): int => 0, $weights);
        }

        $shares = [];
        $remainders = [];
        foreach ($weights as $key => $weight) {
            $exactNumerator = $units * $weight;
            $shares[$key] = intdiv($exactNumerator, $totalWeight);
            $remainders[$key] = $exactNumerator % $totalWeight;
        }

        // Largest remainder first; ties go to the earlier key (arsort is not stable before PHP 8)
        $positions = array_flip(array_map('strval', array_keys($weights)));
        $keys = array_keys($remainders);
        usort($keys, function ($left, $right) use ($remainders, $positions): int {
            return $remainders[$right] <=> $remainders[$left]
                ?: $positions[(string) $left] <=> $positions[(string) $right];
        });
        $unitsLeft = $units - array_sum($shares);
        foreach ($keys as $key) {
            if ($unitsLeft === 0) {
                break;
            }
            if ($remainders[$key] === 0) {
                continue;
            }
            $shares[$key]++;
            $unitsLeft--;
        }

        return $shares;
    }
}
