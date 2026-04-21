<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Customer;

class InitializeCustomer
{
    private $email;
    private $mobilePhoneNumber;
    private $nationalIdentificationNumber;
    private $postalCode;
    private array $deliveryAddress;
    private string $customerType;

    public function __construct(
        string $email,
        string $mobilePhoneNumber,
        array $deliveryAddress,
        string $customerType,
        ?string $nationalIdentificationNumber = null,
        ?string $postalCode = null
    ) {
        $this->email                        = $email;
        $this->mobilePhoneNumber            = $mobilePhoneNumber;
        $this->nationalIdentificationNumber = $nationalIdentificationNumber;
        $this->postalCode                   = $postalCode;
        $this->deliveryAddress              = $deliveryAddress;
        $this->customerType                 = $customerType;
    }

    public function getEmail() : string
    {
        return $this->email;
    }

    public function getMobilePhoneNumber() : string
    {
        return $this->mobilePhoneNumber;
    }

    public function getNationalIdentificationNumber() : string
    {
        return $this->nationalIdentificationNumber;
    }

    public function getPostalCode() : string
    {
        return $this->postalCode;
    }

    public function getDeliveryAddress()
    {
        return $this->deliveryAddress;
    }

    public function getCustomerType()
    {
        return $this->customerType;
    }

    public function toArray() : array
    {
        return [
            'email'                         => $this->getEmail(),
            'mobilePhoneNumber'             => $this->getMobilePhoneNumber(),
            'nationalIdentificationNumber'  => $this->getNationalIdentificationNumber(),
            'postalCode'                    => $this->getPostalCode(),
            'deliveryAddress'               => $this->getDeliveryAddress(),
            'customerType'                  => $this->getCustomerType(),
        ];
    }
}
