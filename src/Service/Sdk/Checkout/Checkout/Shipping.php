<?php
declare(strict_types=1);



namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout;


class Shipping
{
    private array $shippingData = [];

    public function __construct(
        $shippingData
    ) {
        $this->shippingData = $shippingData;
    }

    public function getData()
    {
        return $this->shippingData;
    }
}