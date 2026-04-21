<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Customer;

class BusinessAddress
{
    private $companyName;
    private $coAddress;
    private $address;
    private $address2;
    private $postalCode;
    private $city;
    private $country;
    private $firstName;
    private $lastName;

    public function __construct(
        string $companyName,
        string $address,
        string $postalCode,
        string $city,
        string $country,
        ?string $address2 = null,
        ?string $coAddress = null,
        ?string $firstName = null,
        ?string $lastName = null
    ) {
        $this->companyName  = $companyName;
        $this->coAddress    = $coAddress;
        $this->address      = $address;
        $this->address2     = $address2;
        $this->postalCode   = $postalCode;
        $this->city         = $city;
        $this->country      = $country;
        $this->firstName    = $firstName;
        $this->lastName     = $lastName;
    }

    public function getCompanyName() : string
    {
        return $this->companyName;
    }

    public function getCoAddress()
    {
        return $this->coAddress;
    }

    public function getAddress() : string
    {
        return $this->address;
    }

    public function getAddress2()
    {
        return $this->address2;
    }

    public function getPostalCode() : string
    {
        return $this->postalCode;
    }

    public function getCity() : string
    {
        return $this->city;
    }

    public function getCountry() : string
    {
        return $this->country;
    }

    public function getFirstName() : string
    {
        return $this->firstName;
    }

    public function getLastName() : string
    {
        return $this->lastName;
    }
}
