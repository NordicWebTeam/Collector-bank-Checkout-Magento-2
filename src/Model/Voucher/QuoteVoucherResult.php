<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher;

/**
 * Vouchers applied to one quote address: shares per item line and shipping,
 * incl. VAT and their VAT part, in quote and base currency.
 */
class QuoteVoucherResult
{
    /**
     * @var array<string, LineShare>
     */
    private array $lines;
    private LineShare $shipping;

    /**
     * @var array<string, float>
     */
    private array $voucherAmounts;

    /**
     * @var array<string, float>
     */
    private array $baseVoucherAmounts;

    /**
     * @param array<string, LineShare> $lines Share per line key
     * @param array<string, float> $voucherAmounts Deducted amount per voucherId
     * @param array<string, float> $baseVoucherAmounts Deducted base amount per voucherId
     */
    public function __construct(
        array $lines,
        LineShare $shipping,
        array $voucherAmounts,
        array $baseVoucherAmounts
    ) {
        $this->lines = $lines;
        $this->shipping = $shipping;
        $this->voucherAmounts = $voucherAmounts;
        $this->baseVoucherAmounts = $baseVoucherAmounts;
    }

    public function getLine(string $key): LineShare
    {
        return $this->lines[$key] ?? LineShare::empty();
    }

    /**
     * @return array<string, LineShare>
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    public function getShipping(): LineShare
    {
        return $this->shipping;
    }

    /**
     * @return array<string, float>
     */
    public function getVoucherAmounts(): array
    {
        return $this->voucherAmounts;
    }

    /**
     * @return array<string, float>
     */
    public function getBaseVoucherAmounts(): array
    {
        return $this->baseVoucherAmounts;
    }

    /**
     * Deducted amount incl. VAT; what the customer does not pay.
     */
    public function getAmount(): float
    {
        return $this->sum(fn (LineShare $share): float => $share->getAmount());
    }

    public function getBaseAmount(): float
    {
        return $this->sum(fn (LineShare $share): float => $share->getBaseAmount());
    }

    /**
     * VAT part of the deducted amount, deducted from the tax total.
     */
    public function getTaxAmount(): float
    {
        return $this->sum(fn (LineShare $share): float => $share->getTaxAmount());
    }

    public function getBaseTaxAmount(): float
    {
        return $this->sum(fn (LineShare $share): float => $share->getBaseTaxAmount());
    }

    /**
     * Deducted amount excl. VAT, deducted by the voucher total. Together with
     * the lower tax total, the grand total drops by getAmount().
     */
    public function getNetAmount(): float
    {
        return round($this->getAmount() - $this->getTaxAmount(), VoucherAllocator::PRECISION);
    }

    public function getBaseNetAmount(): float
    {
        return round($this->getBaseAmount() - $this->getBaseTaxAmount(), VoucherAllocator::PRECISION);
    }

    public function isEmpty(): bool
    {
        return $this->voucherAmounts === [];
    }

    private function sum(callable $getter): float
    {
        $sum = $getter($this->shipping);
        foreach ($this->lines as $share) {
            $sum += $getter($share);
        }

        return round($sum, VoucherAllocator::PRECISION);
    }
}
