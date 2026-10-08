<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher;

use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\AppliedVoucher;

/**
 * Calculates how applied vouchers affect a quote address.
 *
 * Vouchers are split over the lines in quote currency and, separately, in
 * base currency, so each currency adds up exactly. The voucher amounts are
 * incl. VAT. Their VAT part is calculated per tax rate on the rate's total,
 * the way Walley calculates VAT on a voucher row, and then split over the
 * lines with that rate. Magento rounds VAT per unit, so the voucher VAT is
 * capped at the VAT the lines actually carry.
 */
class QuoteVoucherCalculator
{
    public const SHIPPING_KEY = 'shipping';

    private const PERCENT = 100;
    private const RATE_KEY_PRECISION = 4;

    private VoucherAllocator $allocator;
    private MinorUnitSplitter $splitter;

    public function __construct(VoucherAllocator $allocator, ?MinorUnitSplitter $splitter = null)
    {
        $this->allocator = $allocator;
        $this->splitter = $splitter ?? new MinorUnitSplitter();
    }

    /**
     * @param AppliedVoucher[] $vouchers In the order they were applied, amounts in quote currency
     * @param TaxableLine[] $lines Item lines, amounts in quote currency
     * @param TaxableLine $shipping Shipping, amount in quote currency
     * @param float $baseToQuoteRate Quote currency amount per base currency unit
     * @throws \InvalidArgumentException When the rate is not positive
     */
    public function calculate(
        array $vouchers,
        array $lines,
        TaxableLine $shipping,
        float $baseToQuoteRate
    ): QuoteVoucherResult {
        if ($baseToQuoteRate <= 0) {
            throw new \InvalidArgumentException("Base to quote rate must be positive: $baseToQuoteRate");
        }

        $allLines = array_merge($lines, [$shipping]);
        $allocation = $this->allocate($vouchers, $lines, $shipping, 1.0);
        $baseAllocation = $this->allocate($vouchers, $lines, $shipping, $baseToQuoteRate);
        $amounts = $this->getShares($allocation, $allLines);
        $baseAmounts = $this->getShares($baseAllocation, $allLines);
        $taxAmounts = $this->calculateTax($amounts, $allLines, false);
        $baseTaxAmounts = $this->calculateTax($baseAmounts, $allLines, true);

        $shares = [];
        foreach ($allLines as $line) {
            $key = $line->getKey();
            $shares[$key] = new LineShare(
                $this->toAmount($amounts[$key]),
                $this->toAmount($baseAmounts[$key]),
                $this->toAmount($taxAmounts[$key]),
                $this->toAmount($baseTaxAmounts[$key])
            );
        }
        $shippingShare = $shares[$shipping->getKey()];
        unset($shares[$shipping->getKey()]);

        return new QuoteVoucherResult(
            $shares,
            $shippingShare,
            $allocation->getVoucherAmounts(),
            $baseAllocation->getVoucherAmounts()
        );
    }

    /**
     * @param AppliedVoucher[] $vouchers
     * @param TaxableLine[] $lines
     */
    private function allocate(array $vouchers, array $lines, TaxableLine $shipping, float $rate): VoucherAllocation
    {
        $lineAmounts = [];
        foreach ($lines as $line) {
            $lineAmounts[$line->getKey()] = $this->convert($line->getAmount(), $rate);
        }
        $convertedVouchers = array_map(
            fn (AppliedVoucher $voucher): AppliedVoucher => new AppliedVoucher(
                $voucher->getVoucherId(),
                $voucher->getCode(),
                $voucher->getDescription(),
                $this->convert($voucher->getDiscountAmount(), $rate)
            ),
            $vouchers
        );

        return $this->allocator->allocate($convertedVouchers, $lineAmounts, $this->convert($shipping->getAmount(), $rate));
    }

    /**
     * @param TaxableLine[] $lines Item lines and shipping
     * @return array<string, int> Share per line key in minor units
     */
    private function getShares(VoucherAllocation $allocation, array $lines): array
    {
        $shares = [];
        foreach ($lines as $line) {
            $amount = $line->getKey() === self::SHIPPING_KEY
                ? $allocation->getShippingAmount()
                : $allocation->getLineAmount($line->getKey());
            $shares[$line->getKey()] = $this->toUnits($amount);
        }

        return $shares;
    }

    /**
     * VAT part per line: calculated per tax rate on the rate's total, then
     * split over the lines with that rate in proportion to their shares,
     * never more than the VAT a line carries.
     *
     * @param array<string, int> $shares Share per line key in minor units
     * @param TaxableLine[] $lines
     * @return array<string, int> VAT per line key in minor units
     */
    private function calculateTax(array $shares, array $lines, bool $base): array
    {
        $groups = [];
        $caps = [];
        foreach ($lines as $line) {
            $rateKey = number_format($line->getTaxPercent(), self::RATE_KEY_PRECISION, '.', '');
            $groups[$rateKey][$line->getKey()] = $shares[$line->getKey()];
            // A line's voucher VAT can never exceed its own share, nor the VAT the line carries
            $share = $shares[$line->getKey()];
            $maxTax = $line->getMaxTaxAmount($base);
            $caps[$line->getKey()] = $maxTax === null ? $share : max(0, min($share, $this->toUnits($maxTax)));
        }

        $taxAmounts = [];
        foreach ($groups as $rateKey => $groupShares) {
            $rate = (float) $rateKey;
            $groupTax = (int) round(array_sum($groupShares) * $rate / (self::PERCENT + $rate));
            $groupCaps = array_intersect_key($caps, $groupShares);
            $groupTax = min($groupTax, array_sum($groupCaps));
            $taxAmounts += $this->applyCaps($this->splitter->split($groupTax, $groupShares), $groupCaps);
        }

        return $taxAmounts;
    }

    /**
     * Lowers shares above their cap and gives the excess to lines with room,
     * in line order. The total is kept, as it never exceeds the sum of caps.
     *
     * @param array<string, int> $shares
     * @param array<string, int> $caps
     * @return array<string, int>
     */
    private function applyCaps(array $shares, array $caps): array
    {
        $excess = 0;
        foreach ($shares as $key => $share) {
            if ($share > $caps[$key]) {
                $excess += $share - $caps[$key];
                $shares[$key] = $caps[$key];
            }
        }
        foreach ($shares as $key => $share) {
            if ($excess === 0) {
                break;
            }
            $added = min($caps[$key] - $share, $excess);
            $shares[$key] += $added;
            $excess -= $added;
        }

        return $shares;
    }

    private function convert(float $amount, float $rate): float
    {
        return round($amount / $rate, VoucherAllocator::PRECISION);
    }

    private function toUnits(float $amount): int
    {
        return (int) round($amount * VoucherAllocator::MINOR_UNITS);
    }

    private function toAmount(int $units): float
    {
        return round($units / VoucherAllocator::MINOR_UNITS, VoucherAllocator::PRECISION);
    }
}
