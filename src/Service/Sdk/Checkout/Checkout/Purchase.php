<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout;

use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Purchase\Result;


class Purchase
{
    private $amountToPay;
    private $paymentName;
    private $invoiceDeliveryMethod;
    private $purchaseIdentifier;
    private $orderId;
    private $result;

    public function __construct(
        $amountToPay,
        string $paymentName,
        string $invoiceDeliveryMethod,
        string $purchaseIdentifier,
        string $orderId,
        Result $result
    ) {
        $this->amountToPay              = $amountToPay;
        $this->paymentName              = $paymentName;
        $this->invoiceDeliveryMethod    = $invoiceDeliveryMethod;
        $this->purchaseIdentifier       = $purchaseIdentifier;
        $this->orderId                  = $orderId;
        $this->result                   = $result;
    }

    public function getAmountToPay() : int
    {
        return (int) $this->amountToPay;
    }

    public function getPaymentName() : string
    {
        return $this->paymentName;
    }

    public function getInvoiceDeliveryMethod() : string
    {
        return $this->invoiceDeliveryMethod;
    }

    public function getPurchaseIdentifier() : string
    {
        return $this->purchaseIdentifier;
    }

    public function getOrderId() : string
    {
        return $this->orderId;
    }

    public function getResult() : Result
    {
        return $this->result;
    }
}
