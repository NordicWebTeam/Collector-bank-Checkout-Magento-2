<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher\Storage;

use Webbhuset\CollectorCheckout\Api\Data\VoucherInterface;

/**
 * A stored voucher (table walley_voucher), linked to a quote and, once the
 * order is placed, to the order. Amounts are incl. VAT.
 */
class VoucherRecord implements VoucherInterface
{
    private ?int $quoteId;
    private ?int $orderId;
    private string $voucherId;
    private string $code;
    private string $description;
    private float $faceAmount;
    private float $amount;
    private float $baseAmount;

    public function __construct(
        ?int $quoteId,
        ?int $orderId,
        string $voucherId,
        string $code,
        string $description,
        float $faceAmount,
        float $amount,
        float $baseAmount
    ) {
        $this->quoteId = $quoteId;
        $this->orderId = $orderId;
        $this->voucherId = $voucherId;
        $this->code = $code;
        $this->description = $description;
        $this->faceAmount = $faceAmount;
        $this->amount = $amount;
        $this->baseAmount = $baseAmount;
    }

    public function getQuoteId(): ?int
    {
        return $this->quoteId;
    }

    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    public function getVoucherId(): string
    {
        return $this->voucherId;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Voucher face value as reported by Walley.
     */
    public function getFaceAmount(): float
    {
        return $this->faceAmount;
    }

    /**
     * Amount actually deducted, capped at what the order could absorb.
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getBaseAmount(): float
    {
        return $this->baseAmount;
    }
}
