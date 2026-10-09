<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher\Storage;

/**
 * Voucher amounts of one order, invoice or credit memo, incl. VAT, and the
 * VAT part of the total. The total is the item shares plus the shipping share.
 */
class DocumentAmounts
{
    private int $documentId;
    private int $orderId;
    private float $amount;
    private float $baseAmount;
    private float $shippingAmount;
    private float $baseShippingAmount;
    private float $taxAmount;
    private float $baseTaxAmount;
    private float $shippingTaxAmount;
    private float $baseShippingTaxAmount;

    /**
     * @var ItemAmount[]
     */
    private array $items;

    /**
     * @param int $documentId Order, invoice or credit memo id; equals $orderId for orders
     * @param ItemAmount[] $items
     */
    public function __construct(
        int $documentId,
        int $orderId,
        float $amount,
        float $baseAmount,
        float $shippingAmount,
        float $baseShippingAmount,
        array $items,
        float $taxAmount = 0.0,
        float $baseTaxAmount = 0.0,
        float $shippingTaxAmount = 0.0,
        float $baseShippingTaxAmount = 0.0
    ) {
        $this->documentId = $documentId;
        $this->orderId = $orderId;
        $this->amount = $amount;
        $this->baseAmount = $baseAmount;
        $this->shippingAmount = $shippingAmount;
        $this->baseShippingAmount = $baseShippingAmount;
        $this->items = array_values($items);
        $this->taxAmount = $taxAmount;
        $this->baseTaxAmount = $baseTaxAmount;
        $this->shippingTaxAmount = $shippingTaxAmount;
        $this->baseShippingTaxAmount = $baseShippingTaxAmount;
    }

    public function getDocumentId(): int
    {
        return $this->documentId;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getBaseAmount(): float
    {
        return $this->baseAmount;
    }

    public function getShippingAmount(): float
    {
        return $this->shippingAmount;
    }

    public function getBaseShippingAmount(): float
    {
        return $this->baseShippingAmount;
    }

    /**
     * VAT part of the total voucher amount
     */
    public function getTaxAmount(): float
    {
        return $this->taxAmount;
    }

    public function getBaseTaxAmount(): float
    {
        return $this->baseTaxAmount;
    }

    /**
     * VAT part of the voucher share on shipping
     */
    public function getShippingTaxAmount(): float
    {
        return $this->shippingTaxAmount;
    }

    public function getBaseShippingTaxAmount(): float
    {
        return $this->baseShippingTaxAmount;
    }

    /**
     * Voucher amount excl. VAT
     */
    public function getNetAmount(): float
    {
        return round($this->amount - $this->taxAmount, 2);
    }

    public function getBaseNetAmount(): float
    {
        return round($this->baseAmount - $this->baseTaxAmount, 2);
    }

    /**
     * @return ItemAmount[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return $this->amount == 0.0 && $this->baseAmount == 0.0;
    }
}
