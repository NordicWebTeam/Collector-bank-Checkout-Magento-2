<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher;

/**
 * Voucher share of one quote line: the amount incl. VAT and the VAT part of
 * it, in quote and base currency. The net amount is what the voucher total
 * deducts; the VAT part is deducted from the line's tax.
 */
class LineShare
{
    private float $amount;
    private float $baseAmount;
    private float $taxAmount;
    private float $baseTaxAmount;

    public function __construct(float $amount, float $baseAmount, float $taxAmount, float $baseTaxAmount)
    {
        $this->amount = $amount;
        $this->baseAmount = $baseAmount;
        $this->taxAmount = $taxAmount;
        $this->baseTaxAmount = $baseTaxAmount;
    }

    public static function empty(): self
    {
        return new self(0.0, 0.0, 0.0, 0.0);
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getBaseAmount(): float
    {
        return $this->baseAmount;
    }

    public function getTaxAmount(): float
    {
        return $this->taxAmount;
    }

    public function getBaseTaxAmount(): float
    {
        return $this->baseTaxAmount;
    }

    public function getNetAmount(): float
    {
        return round($this->amount - $this->taxAmount, VoucherAllocator::PRECISION);
    }

    public function getBaseNetAmount(): float
    {
        return round($this->baseAmount - $this->baseTaxAmount, VoucherAllocator::PRECISION);
    }
}
