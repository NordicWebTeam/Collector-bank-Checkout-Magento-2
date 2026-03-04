<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout;

use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Fees\Fee;

class Fees
{
    private $shippingFee;
    private $directInvoiceFee;

    public function __construct(
        ?Fee $shippingFee = null,
        ?Fee $directInvoiceFee = null
    ) {
        $this->shippingFee      = $shippingFee;
        $this->directInvoiceFee = $directInvoiceFee;
    }

    public function getShippingFee() : Fee
    {
        return $this->shippingFee;
    }

    public function getDirectInvoiceFee() : Fee
    {
        return $this->directInvoiceFee;
    }

    public function toArray() : array
    {
        $fees = [];

        if ($this->shippingFee) {
            $fees['shipping'] = $this->shippingFee->toArray();
        }

        if ($this->directInvoiceFee) {
            $fees['directInvoiceFee'] = $this->directInvoiceFee->toArray();
        }

        return $fees;
    }
}
