<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher\Storage;

/**
 * Voucher share of one order, invoice or credit memo item, incl. VAT, and
 * the VAT part of it.
 */
class ItemAmount
{
    private int $itemId;
    private int $orderItemId;
    private float $amount;
    private float $baseAmount;
    private float $taxAmount;
    private float $baseTaxAmount;

    /**
     * @param int $itemId Order item, invoice item or credit memo item id
     * @param int $orderItemId Order item id; equals $itemId for order items
     */
    public function __construct(
        int $itemId,
        int $orderItemId,
        float $amount,
        float $baseAmount,
        float $taxAmount = 0.0,
        float $baseTaxAmount = 0.0
    ) {
        $this->itemId = $itemId;
        $this->orderItemId = $orderItemId;
        $this->amount = $amount;
        $this->baseAmount = $baseAmount;
        $this->taxAmount = $taxAmount;
        $this->baseTaxAmount = $baseTaxAmount;
    }

    public function getItemId(): int
    {
        return $this->itemId;
    }

    public function getOrderItemId(): int
    {
        return $this->orderItemId;
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
}
