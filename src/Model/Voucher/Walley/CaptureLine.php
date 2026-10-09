<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher\Walley;

/**
 * An invoice line (item or shipping) as captured at Walley: unit price incl.
 * VAT, and the line's rule discount and voucher share incl. VAT, which become
 * per-unit discount and voucher rows.
 */
class CaptureLine
{
    private string $id;
    private string $description;
    private float $unitPrice;
    private int $quantity;
    private float $vat;
    private float $discountAmount;
    private float $voucherAmount;
    private string $voucherId;
    private string $voucherDescription;

    public function __construct(
        string $id,
        string $description,
        float $unitPrice,
        int $quantity,
        float $vat,
        float $discountAmount,
        float $voucherAmount,
        string $voucherId,
        string $voucherDescription
    ) {
        $this->id = $id;
        $this->description = $description;
        $this->unitPrice = $unitPrice;
        $this->quantity = $quantity;
        $this->vat = $vat;
        $this->discountAmount = $discountAmount;
        $this->voucherAmount = $voucherAmount;
        $this->voucherId = $voucherId;
        $this->voucherDescription = $voucherDescription;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getVat(): float
    {
        return $this->vat;
    }

    public function getDiscountAmount(): float
    {
        return $this->discountAmount;
    }

    public function getVoucherAmount(): float
    {
        return $this->voucherAmount;
    }

    public function getVoucherId(): string
    {
        return $this->voucherId;
    }

    public function getVoucherDescription(): string
    {
        return $this->voucherDescription;
    }
}
