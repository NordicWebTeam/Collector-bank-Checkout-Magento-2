<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Cart;

class Item
{
    private $id;
    private $description;
    private $unitPrice;
    private $quantity;
    private $vat;
    private $requiresElectronicId;
    private $sku;
    private $weight;

    public function __construct(
        string $id,
        string $description,
        float $unitPrice,
        int $quantity,
        float $vat,
        ?bool $requiresElectronicId = null,
        string $sku = "",
        ?float $weight = null
    ) {
        $this->id                   = mb_substr($id, 0, 50);
        $this->description          = mb_substr($description, 0, 50);
        $this->unitPrice            = $unitPrice;
        $this->quantity             = $quantity;
        $this->vat                  = $vat;
        $this->requiresElectronicId = $requiresElectronicId;
        $this->sku                  = mb_substr($sku, 0, 50);
        $this->weight               = ($weight == null) ? (0.00): ($weight);
    }

    public function getId() : string
    {
        return $this->id;
    }

    public function getDescription() : string
    {
        return $this->description;
    }

    public function getWeight(): float
    {
        return $this->weight;
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

    public function getRequiresElectronicId()
    {
        return $this->requiresElectronicId;
    }

    public function getSku()
    {
        return $this->sku;
    }

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    public function toArray() : array
    {
        $data = [
            'id'                    => $this->getId(),
            'description'           => $this->getDescription(),
            'unitPrice'             => $this->getUnitPrice(),
            'quantity'              => $this->getQuantity(),
            'vat'                   => $this->getVat(),
            'requiresElectronicId'  => $this->getRequiresElectronicId(),
            'sku'                   => $this->getSku(),
            'unitWeight'            => $this->getWeight()
        ];

        if ($data['requiresElectronicId'] === null) {
            unset($data['requiresElectronicId']);
        }

        if ($data['sku'] === null) {
            unset($data['sku']);
        }

        return $data;
    }
}
