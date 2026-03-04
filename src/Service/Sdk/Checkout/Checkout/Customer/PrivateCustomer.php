<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Customer;

use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Customer\PrivateAddress;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Customer\AbstractCustomer;

class PrivateCustomer extends AbstractCustomer
{
    private $email;
    private $mobilePhoneNumber;
    private $deliveryMobilePhoneNumber;
    private $invoiceAddress;
    private $deliveryAddress;
    private $nationalIdentificationNumber;

    public function __construct(
        string $email,
        string $mobilePhoneNumber,
        string $deliveryMobilePhoneNumber,
        PrivateAddress $invoiceAddress,
        PrivateAddress $deliveryAddress,
        string $nationalIdentificationNumber = ''
    ) {
        $this->email                        = $email;
        $this->mobilePhoneNumber            = $mobilePhoneNumber;
        $this->deliveryMobilePhoneNumber    = $deliveryMobilePhoneNumber;
        $this->invoiceAddress               = $invoiceAddress;
        $this->deliveryAddress              = $deliveryAddress;
        $this->nationalIdentificationNumber = $nationalIdentificationNumber;
    }

    public function getEmail() : string
    {
        return $this->email;
    }

    public function getMobilePhoneNumber() : string
    {
        return $this->mobilePhoneNumber;
    }

    public function getDeliveryMobilePhoneNumber() : string
    {
        return $this->deliveryMobilePhoneNumber;
    }

    public function getInvoiceAddress() : PrivateAddress
    {
        return $this->invoiceAddress;
    }

    public function getDeliveryAddress() : PrivateAddress
    {
        return $this->deliveryAddress;
    }

    public function getNationalIdentificationNumber(): string
    {
        return $this->nationalIdentificationNumber;
    }
}
