<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher;

/**
 * Voucher amounts of one invoice or credit memo: the share and voucher VAT
 * per line, and the VAT each line should carry after the voucher.
 */
class DocumentVoucherResult
{
    /**
     * @var array<string, LineShare>
     */
    private array $shares;

    /**
     * @var array<string, DocumentLine>
     */
    private array $lines;

    /**
     * @param array<string, LineShare> $shares Share per line key, shipping under QuoteVoucherCalculator::SHIPPING_KEY
     * @param array<string, DocumentLine> $lines
     */
    public function __construct(array $shares, array $lines)
    {
        $this->shares = $shares;
        $this->lines = $lines;
    }

    public function getLine(string $key): LineShare
    {
        return $this->shares[$key] ?? LineShare::empty();
    }

    public function getShipping(): LineShare
    {
        return $this->getLine(QuoteVoucherCalculator::SHIPPING_KEY);
    }

    /**
     * @return array<string, LineShare>
     */
    public function getShares(): array
    {
        return $this->shares;
    }

    /**
     * @return array<string, DocumentLine>
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    /**
     * VAT the line should carry: its full VAT minus the voucher VAT.
     */
    public function getLineTax(string $key): float
    {
        return $this->getLineTaxIn($key, false);
    }

    public function getBaseLineTax(string $key): float
    {
        return $this->getLineTaxIn($key, true);
    }

    public function getAmount(): float
    {
        return $this->sum(fn (LineShare $share): float => $share->getAmount());
    }

    public function getBaseAmount(): float
    {
        return $this->sum(fn (LineShare $share): float => $share->getBaseAmount());
    }

    public function getTaxAmount(): float
    {
        return $this->sum(fn (LineShare $share): float => $share->getTaxAmount());
    }

    public function getBaseTaxAmount(): float
    {
        return $this->sum(fn (LineShare $share): float => $share->getBaseTaxAmount());
    }

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
        return $this->getAmount() == 0.0 && $this->getBaseAmount() == 0.0;
    }

    private function getLineTaxIn(string $key, bool $base): float
    {
        if (!isset($this->lines[$key])) {
            return 0.0;
        }
        $share = $this->getLine($key);
        $voucherTax = $base ? $share->getBaseTaxAmount() : $share->getTaxAmount();

        return max(0.0, round($this->lines[$key]->getFullTax($base) - $voucherTax, VoucherAllocator::PRECISION));
    }

    private function sum(callable $getter): float
    {
        $sum = 0.0;
        foreach ($this->shares as $share) {
            $sum += $getter($share);
        }

        return round($sum, VoucherAllocator::PRECISION);
    }
}
