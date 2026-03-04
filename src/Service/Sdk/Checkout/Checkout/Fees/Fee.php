<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Fees;

class Fee
{
    private $id;
    private $description;
    private $unitPrice;
    private $vat;
    private $sku;

    public function __construct(
        string $id,
        string $description,
        float $unitPrice,
        float $vat,
        string $sku = ""
    ) {
        $this->id = $id;
        $this->description = $description;
        $this->unitPrice = $unitPrice;
        $this->vat = $vat;
        $this->sku = $sku;
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

    public function getVat() : float
    {
        return $this->vat;
    }

    public function getSku() : string
    {
        return $this->sku;
    }

    public function toArray() : array
    {
        return [
            'id'            => $this->id,
            'description'   => $this->description,
            'unitPrice'     => $this->unitPrice,
            'vat'           => $this->vat,
            'sku'           => $this->sku
        ];
    }
}
