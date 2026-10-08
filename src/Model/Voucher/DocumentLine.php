<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher;

/**
 * An invoice or credit memo line (item or shipping) for voucher calculation,
 * with the VAT it would carry without any voucher.
 */
class DocumentLine
{
    private string $key;
    private float $rowTotal;
    private float $fullTax;
    private float $discountAmount;
    private float $discountTaxCompensation;
    private float $taxPercent;
    private float $baseRowTotal;
    private float $baseFullTax;
    private float $baseDiscountAmount;
    private float $baseDiscountTaxCompensation;

    /**
     * @param float $rowTotal Row total excl. VAT
     * @param float $fullTax VAT of the row without vouchers
     * @param float $discountAmount Rule discount of the row
     * @param float $discountTaxCompensation Magento's discount tax compensation of the row
     */
    public function __construct(
        string $key,
        float $rowTotal,
        float $fullTax,
        float $discountAmount,
        float $discountTaxCompensation,
        float $taxPercent,
        float $baseRowTotal,
        float $baseFullTax,
        ?float $baseDiscountAmount = null,
        ?float $baseDiscountTaxCompensation = null
    ) {
        $this->key = $key;
        $this->rowTotal = $rowTotal;
        $this->fullTax = $fullTax;
        $this->discountAmount = $discountAmount;
        $this->discountTaxCompensation = $discountTaxCompensation;
        $this->taxPercent = $taxPercent;
        $this->baseRowTotal = $baseRowTotal;
        $this->baseFullTax = $baseFullTax;
        $this->baseDiscountAmount = $baseDiscountAmount ?? $discountAmount;
        $this->baseDiscountTaxCompensation = $baseDiscountTaxCompensation ?? $discountTaxCompensation;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getTaxPercent(): float
    {
        return $this->taxPercent;
    }

    public function getFullTax(bool $base): float
    {
        return $base ? $this->baseFullTax : $this->fullTax;
    }

    /**
     * What the line costs incl. VAT after rule discounts, before vouchers.
     */
    public function getCapacity(bool $base): float
    {
        $capacity = $base
            ? $this->baseRowTotal + $this->baseFullTax + $this->baseDiscountTaxCompensation - $this->baseDiscountAmount
            : $this->rowTotal + $this->fullTax + $this->discountTaxCompensation - $this->discountAmount;

        return max(0.0, round($capacity, VoucherAllocator::PRECISION));
    }
}
