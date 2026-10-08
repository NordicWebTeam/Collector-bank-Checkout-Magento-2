<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher;

use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\AppliedVoucher;

/**
 * Splits applied vouchers over item lines and shipping.
 *
 * The vouchers are capped at what can be discounted. Items are covered first,
 * in proportion to their amounts, and any remainder goes to shipping. Amounts
 * are handled as integer minor units and split with MinorUnitSplitter, so the
 * line shares always add up to the total and never exceed a line's own amount.
 */
class VoucherAllocator
{
    public const PRECISION = 2;

    public const MINOR_UNITS = 100;

    private MinorUnitSplitter $splitter;

    public function __construct(?MinorUnitSplitter $splitter = null)
    {
        $this->splitter = $splitter ?? new MinorUnitSplitter();
    }

    /**
     * @param AppliedVoucher[] $vouchers In the order they were applied
     * @param array<string|int, float> $lineAmounts Amount each line can absorb, incl. VAT
     * @param float $shippingAmount Amount shipping can absorb, incl. VAT
     * @throws \InvalidArgumentException When an amount is negative
     */
    public function allocate(array $vouchers, array $lineAmounts, float $shippingAmount): VoucherAllocation
    {
        $lineUnits = array_map(fn (float $amount): int => $this->toUnits($amount, 'Line amount'), $lineAmounts);
        $shippingUnits = $this->toUnits($shippingAmount, 'Shipping amount');

        $voucherUnits = $this->capVouchers($vouchers, array_sum($lineUnits) + $shippingUnits);
        $appliedUnits = array_sum($voucherUnits);
        $itemUnits = min($appliedUnits, array_sum($lineUnits));

        return new VoucherAllocation(
            array_map(fn (int $units): float => $this->toAmount($units), $this->splitter->split($itemUnits, $lineUnits)),
            $this->toAmount($appliedUnits - $itemUnits),
            array_map(fn (int $units): float => $this->toAmount($units), $voucherUnits)
        );
    }

    /**
     * @param AppliedVoucher[] $vouchers
     * @return array<string, int> Deducted units per voucherId, fully capped vouchers left out
     */
    private function capVouchers(array $vouchers, int $availableUnits): array
    {
        $voucherUnits = [];
        $remainingUnits = $availableUnits;
        foreach ($vouchers as $voucher) {
            $units = min($this->toUnits($voucher->getDiscountAmount(), 'Voucher amount'), $remainingUnits);
            if ($units <= 0) {
                continue;
            }
            $voucherUnits[$voucher->getVoucherId()] = $units;
            $remainingUnits -= $units;
        }

        return $voucherUnits;
    }

    private function toUnits(float $amount, string $label): int
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException("$label can not be negative: $amount");
        }

        return (int) round($amount * self::MINOR_UNITS);
    }

    private function toAmount(int $units): float
    {
        return round($units / self::MINOR_UNITS, self::PRECISION);
    }
}
