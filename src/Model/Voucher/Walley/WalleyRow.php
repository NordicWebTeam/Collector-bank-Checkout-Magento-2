<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher\Walley;

/**
 * One row sent to Walley when capturing or refunding. Unit prices incl. VAT.
 */
class WalleyRow
{
    private string $id;
    private string $description;
    private float $unitPrice;
    private int $quantity;
    private float $vat;

    public function __construct(string $id, string $description, float $unitPrice, int $quantity, float $vat)
    {
        $this->id = $id;
        $this->description = $description;
        $this->unitPrice = $unitPrice;
        $this->quantity = $quantity;
        $this->vat = $vat;
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
}
