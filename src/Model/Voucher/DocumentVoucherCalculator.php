<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher;

use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\AppliedVoucher;

/**
 * Calculates the voucher on an invoice, front-loaded as Walley prefers: each
 * invoice uses as much of the voucher not yet invoiced as it can carry,
 * covering items first and then shipping, like on the quote. The last invoice
 * gets exactly the voucher VAT that remains, so the order adds up to the öre.
 */
class DocumentVoucherCalculator
{
    private const REMAINING_VOUCHER_ID = 'remaining';

    private QuoteVoucherCalculator $quoteVoucherCalculator;
    private MinorUnitSplitter $splitter;

    public function __construct(QuoteVoucherCalculator $quoteVoucherCalculator, ?MinorUnitSplitter $splitter = null)
    {
        $this->quoteVoucherCalculator = $quoteVoucherCalculator;
        $this->splitter = $splitter ?? new MinorUnitSplitter();
    }

    /**
     * @param DocumentLine[] $lines Item lines, plus shipping under QuoteVoucherCalculator::SHIPPING_KEY
     * @param float $remainingAmount Voucher amount incl. VAT not yet invoiced
     * @param float $remainingTax VAT part of it
     * @param bool $isLast Whether this is the order's last invoice
     * @param float $baseToOrderRate Order currency amount per base currency unit
     * @param float|null $baseRemainingAmount Base currency; derived from the rate when null
     * @param float|null $baseRemainingTax Base currency; derived from the rate when null
     */
    public function calculate(
        array $lines,
        float $remainingAmount,
        float $remainingTax,
        bool $isLast,
        float $baseToOrderRate,
        ?float $baseRemainingAmount = null,
        ?float $baseRemainingTax = null
    ): DocumentVoucherResult {
        $linesByKey = [];
        foreach ($lines as $line) {
            $linesByKey[$line->getKey()] = $line;
        }
        if ($remainingAmount <= 0) {
            return new DocumentVoucherResult([], $linesByKey);
        }

        $shipping = $linesByKey[QuoteVoucherCalculator::SHIPPING_KEY] ?? null;
        $itemLines = array_values(array_filter(
            $lines,
            fn (DocumentLine $line): bool => $line->getKey() !== QuoteVoucherCalculator::SHIPPING_KEY
        ));

        $quoteResult = $this->quoteVoucherCalculator->calculate(
            [new AppliedVoucher(self::REMAINING_VOUCHER_ID, self::REMAINING_VOUCHER_ID, '', $remainingAmount)],
            array_map(fn (DocumentLine $line): TaxableLine => $this->toTaxableLine($line), $itemLines),
            $shipping ? $this->toTaxableLine($shipping) : new TaxableLine(QuoteVoucherCalculator::SHIPPING_KEY, 0.0, 0.0),
            $baseToOrderRate
        );

        $shares = $quoteResult->getLines();
        if ($shipping) {
            $shares[QuoteVoucherCalculator::SHIPPING_KEY] = $quoteResult->getShipping();
        }

        if ($isLast && $baseRemainingAmount !== null) {
            $shares = $this->forceBaseAmount($shares, $baseRemainingAmount, $quoteResult->getAmount(), $remainingAmount);
        }
        $result = new DocumentVoucherResult($shares, $linesByKey);

        return $isLast
            ? $this->withTaxTotal(
                $result,
                $remainingTax,
                $baseRemainingTax ?? round($remainingTax / $baseToOrderRate, VoucherAllocator::PRECISION)
            )
            : $result;
    }

    /**
     * Sets the result's total voucher VAT, e.g. to exactly what remains on
     * the last document, moving the rounding difference between lines.
     */
    public function withTaxTotal(
        DocumentVoucherResult $result,
        float $targetTax,
        float $baseTargetTax
    ): DocumentVoucherResult {
        $shares = $this->forceTax($result->getShares(), $result->getLines(), $targetTax, false);
        $shares = $this->forceTax($shares, $result->getLines(), $baseTargetTax, true);

        return new DocumentVoucherResult($shares, $result->getLines());
    }

    private function toTaxableLine(DocumentLine $line): TaxableLine
    {
        return new TaxableLine(
            $line->getKey(),
            $line->getCapacity(false),
            $line->getTaxPercent(),
            $line->getFullTax(false),
            $line->getFullTax(true)
        );
    }

    /**
     * On the last invoice the voucher VAT must equal what remains, so the
     * order's VAT adds up. Moves the rounding difference to lines with room:
     * a line's voucher VAT stays between 0 and both its share and its VAT.
     *
     * @param array<string, LineShare> $shares
     * @param array<string, DocumentLine> $lines
     * @return array<string, LineShare>
     */
    private function forceTax(array $shares, array $lines, float $targetTax, bool $base): array
    {
        $current = [];
        $max = [];
        foreach ($shares as $key => $share) {
            $current[$key] = $this->toUnits($base ? $share->getBaseTaxAmount() : $share->getTaxAmount());
            $shareUnits = $this->toUnits($base ? $share->getBaseAmount() : $share->getAmount());
            $max[$key] = isset($lines[$key])
                ? min($shareUnits, $this->toUnits($lines[$key]->getFullTax($base)))
                : $shareUnits;
        }

        $difference = $this->toUnits($targetTax) - array_sum($current);
        foreach (array_keys($current) as $key) {
            if ($difference === 0) {
                break;
            }
            $room = $difference > 0 ? $max[$key] - $current[$key] : -$current[$key];
            $change = $difference > 0 ? min($room, $difference) : max($room, $difference);
            $current[$key] += $change;
            $difference -= $change;
        }

        $result = [];
        foreach ($shares as $key => $share) {
            $tax = $current[$key] / VoucherAllocator::MINOR_UNITS;
            $result[$key] = $base
                ? new LineShare($share->getAmount(), $share->getBaseAmount(), $share->getTaxAmount(), $tax)
                : new LineShare($share->getAmount(), $share->getBaseAmount(), $tax, $share->getBaseTaxAmount());
        }

        return $result;
    }

    /**
     * When the last invoice uses up the voucher, its base amount must equal
     * the base amount that remains; converting each line by rate can be an
     * öre off. The difference goes to the first line with a base amount.
     *
     * @param array<string, LineShare> $shares
     * @return array<string, LineShare>
     */
    private function forceBaseAmount(array $shares, float $baseTarget, float $usedAmount, float $remainingAmount): array
    {
        if ($this->toUnits($usedAmount) !== $this->toUnits($remainingAmount)) {
            return $shares;
        }
        $baseUsed = 0;
        foreach ($shares as $share) {
            $baseUsed += $this->toUnits($share->getBaseAmount());
        }
        $difference = $this->toUnits($baseTarget) - $baseUsed;
        foreach ($shares as $key => $share) {
            if ($difference === 0) {
                break;
            }
            $base = $this->toUnits($share->getBaseAmount());
            if ($base + $difference < 0 || $base === 0) {
                continue;
            }
            $shares[$key] = new LineShare(
                $share->getAmount(),
                ($base + $difference) / VoucherAllocator::MINOR_UNITS,
                $share->getTaxAmount(),
                $share->getBaseTaxAmount()
            );
            $difference = 0;
        }

        return $shares;
    }

    private function toUnits(float $amount): int
    {
        return (int) round($amount * VoucherAllocator::MINOR_UNITS);
    }
}
