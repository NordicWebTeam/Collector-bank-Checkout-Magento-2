<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher;

/**
 * Result of splitting applied vouchers over the lines and shipping of a quote,
 * order, invoice or credit memo. All amounts are incl. VAT.
 */
class VoucherAllocation
{
    /**
     * @var array<string|int, float>
     */
    private array $lineAmounts;
    private float $shippingAmount;

    /**
     * @var array<string, float>
     */
    private array $voucherAmounts;

    /**
     * @param array<string|int, float> $lineAmounts Voucher share per line key
     * @param array<string, float> $voucherAmounts Deducted amount per voucherId
     */
    public function __construct(array $lineAmounts, float $shippingAmount, array $voucherAmounts)
    {
        $this->lineAmounts = $lineAmounts;
        $this->shippingAmount = $shippingAmount;
        $this->voucherAmounts = $voucherAmounts;
    }

    /**
     * @return array<string|int, float>
     */
    public function getLineAmounts(): array
    {
        return $this->lineAmounts;
    }

    /**
     * @param string|int $lineKey
     */
    public function getLineAmount($lineKey): float
    {
        return $this->lineAmounts[$lineKey] ?? 0.0;
    }

    public function getShippingAmount(): float
    {
        return $this->shippingAmount;
    }

    /**
     * Deducted amount per voucherId, in the order the vouchers were applied.
     * Vouchers that could not be used at all are left out.
     *
     * @return array<string, float>
     */
    public function getVoucherAmounts(): array
    {
        return $this->voucherAmounts;
    }

    public function getTotal(): float
    {
        return round(array_sum($this->voucherAmounts), VoucherAllocator::PRECISION);
    }

    public function isEmpty(): bool
    {
        return $this->voucherAmounts === [];
    }
}
