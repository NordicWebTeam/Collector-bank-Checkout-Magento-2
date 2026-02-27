<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Order;

class Item
{
    private $id;
    private $description;
    private $unitPrice;
    private $quantity;
    private $vat;
    private $sku;

    public function __construct(
        string $id,
        string $description,
        float $unitPrice,
        int $quantity,
        float $vat,
        string $sku
    ) {
        $this->id                   = $id;
        $this->description          = $description;
        $this->unitPrice            = $unitPrice;
        $this->quantity             = $quantity;
        $this->vat                  = $vat;
        $this->sku                  = $sku;
    }

    public function getId() : string
    {
        return $this->id;
    }

    public function getDescription() : string
    {
        return $this->description;
    }

    public function getUnitPrice() : float
    {
        return $this->unitPrice;
    }

    public function getQuantity() : int
    {
        return $this->quantity;
    }

    public function getVat() : float
    {
        return $this->vat;
    }

    public function getSku() : string
    {
        return $this->sku;
    }
}
