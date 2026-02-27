<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Customer;

use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Customer\BusinessAddress;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Customer\AbstractCustomer;

class BusinessCustomer extends AbstractCustomer
{
    private $companyName;
    private $organizationNumber;
    private $invoiceReference;
    private $invoiceTag;
    private $email;
    private $firstName;
    private $lastName;
    private $mobilePhoneNumber;
    private $invoiceAddress;
    private $deliveryAddress;

    public function __construct(
        string $companyName,
        string $organizationNumber,
        string $invoiceReference,
        string $invoiceTag,
        string $email,
        string $firstName,
        string $lastName,
        string $mobilePhoneNumber,
        BusinessAddress $invoiceAddress,
        BusinessAddress $deliveryAddress
    ) {
        $this->companyName          = $companyName;
        $this->organizationNumber   = $organizationNumber;
        $this->invoiceReference     = $invoiceReference;
        $this->invoiceTag           = $invoiceTag;
        $this->email                = $email;
        $this->firstName            = $firstName;
        $this->lastName             = $lastName;
        $this->mobilePhoneNumber    = $mobilePhoneNumber;
        $this->invoiceAddress       = $invoiceAddress;
        $this->deliveryAddress      = $deliveryAddress;
    }

    public function getCompanyName() : string
    {
        return $this->companyName;
    }

    public function getOrganizationNumber() : string
    {
        return $this->organizationNumber;
    }

    public function getInvoiceReference() : string
    {
        return $this->invoiceReference;
    }

    public function getInvoiceTag() : string
    {
        return $this->invoiceTag;
    }

    public function getEmail() : string
    {
        return $this->email;
    }

    public function getFirstName() : string
    {
        return $this->firstName;
    }

    public function getLastName() : string
    {
        return $this->lastName;
    }

    public function getMobilePhoneNumber() : string
    {
        return $this->mobilePhoneNumber;
    }

    public function getInvoiceAddress() : BusinessAddress
    {
        return $this->invoiceAddress;
    }

    public function getDeliveryAddress() : BusinessAddress
    {
        return $this->deliveryAddress;
    }
}
