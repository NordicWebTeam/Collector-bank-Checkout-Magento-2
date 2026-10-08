<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher;

/**
 * A quote line (item or shipping) that a voucher can be applied to.
 */
class TaxableLine
{
    private string $key;
    private float $amount;
    private float $taxPercent;
    private ?float $maxTaxAmount;
    private ?float $baseMaxTaxAmount;

    /**
     * @param string $key Identifies the line in the result
     * @param float $amount Amount the voucher can cover, incl. VAT and after other discounts
     * @param float $taxPercent Tax rate of the line
     * @param float|null $maxTaxAmount VAT on the line, the most voucher VAT it can carry; null for no limit
     * @param float|null $baseMaxTaxAmount The same in base currency
     */
    public function __construct(
        string $key,
        float $amount,
        float $taxPercent,
        ?float $maxTaxAmount = null,
        ?float $baseMaxTaxAmount = null
    ) {
        $this->key = $key;
        $this->amount = $amount;
        $this->taxPercent = $taxPercent;
        $this->maxTaxAmount = $maxTaxAmount;
        $this->baseMaxTaxAmount = $baseMaxTaxAmount;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getTaxPercent(): float
    {
        return $this->taxPercent;
    }

    public function getMaxTaxAmount(bool $base): ?float
    {
        return $base ? $this->baseMaxTaxAmount : $this->maxTaxAmount;
    }
}
