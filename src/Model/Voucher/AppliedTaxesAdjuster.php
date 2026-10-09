<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher;

/**
 * Reduces Magento's tax breakdowns by the VAT part of vouchers.
 *
 * Works on the arrays Magento's tax collector builds: items_applied_taxes
 * (per tax calculation item code, a list of applied rates) and applied_taxes
 * (per rate id). They become sales_order_tax_item and sales_order_tax when
 * the order is placed, so they must agree with the lowered tax amounts.
 */
class AppliedTaxesAdjuster
{
    private MinorUnitSplitter $splitter;

    public function __construct(?MinorUnitSplitter $splitter = null)
    {
        $this->splitter = $splitter ?? new MinorUnitSplitter();
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $itemsAppliedTaxes
     * @param array<string, array<string, mixed>> $appliedTaxes
     * @param array<string, LineShare> $sharesByItemCode Voucher share per tax calculation item code
     */
    public function reduce(array $itemsAppliedTaxes, array $appliedTaxes, array $sharesByItemCode): AppliedTaxesResult
    {
        foreach ($sharesByItemCode as $itemCode => $share) {
            if (!isset($itemsAppliedTaxes[$itemCode]) || $share->getTaxAmount() <= 0) {
                continue;
            }
            $amountReductions = $this->splitByPercent($share->getTaxAmount(), $itemsAppliedTaxes[$itemCode]);
            $baseReductions = $this->splitByPercent($share->getBaseTaxAmount(), $itemsAppliedTaxes[$itemCode]);

            foreach ($itemsAppliedTaxes[$itemCode] as $index => $itemTax) {
                $amountReduction = min($amountReductions[$index], (float) $itemTax['amount']);
                $baseReduction = min($baseReductions[$index], (float) $itemTax['base_amount']);
                $itemsAppliedTaxes[$itemCode][$index] = $this->subtract($itemTax, $amountReduction, $baseReduction);

                $rateId = $itemTax['id'] ?? null;
                if ($rateId !== null && isset($appliedTaxes[$rateId])) {
                    $appliedTaxes[$rateId] = $this->subtract($appliedTaxes[$rateId], $amountReduction, $baseReduction);
                }
            }
        }

        return new AppliedTaxesResult($itemsAppliedTaxes, $appliedTaxes);
    }

    /**
     * @param array<int, array<string, mixed>> $lineTaxes
     * @return array<int, float> Reduction per applied rate of the line
     */
    private function splitByPercent(float $amount, array $lineTaxes): array
    {
        $weights = array_map(
            fn (array $tax): int => (int) round((float) ($tax['percent'] ?? 0) * VoucherAllocator::MINOR_UNITS),
            $lineTaxes
        );
        $units = $this->splitter->split((int) round($amount * VoucherAllocator::MINOR_UNITS), $weights);

        return array_map(fn (int $unit): float => $unit / VoucherAllocator::MINOR_UNITS, $units);
    }

    /**
     * @param array<string, mixed> $tax
     * @return array<string, mixed>
     */
    private function subtract(array $tax, float $amount, float $baseAmount): array
    {
        $tax['amount'] = max(0.0, round((float) $tax['amount'] - $amount, VoucherAllocator::PRECISION));
        $tax['base_amount'] = max(0.0, round((float) $tax['base_amount'] - $baseAmount, VoucherAllocator::PRECISION));

        return $tax;
    }
}
