<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Customer;

class PrivateAddress
{
    private $firstName;
    private $lastName;
    private $coAddress;
    private $address;
    private $address2;
    private $postalCode;
    private $city;
    private $country;

    public function __construct(
        string $firstName,
        string $lastName,
        string $address,
        string $postalCode,
        string $city,
        string $country,
        ?string $address2 = null,
        ?string $coAddress = null
    ) {
        $this->firstName    = $firstName;
        $this->lastName     = $lastName;
        $this->coAddress    = $coAddress;
        $this->address      = $address;
        $this->address2     = $address2;
        $this->postalCode   = $postalCode;
        $this->city         = $city;
        $this->country      = $country;
    }

    public function getFirstName() : string
    {
        return $this->firstName;
    }

    public function getLastName() : string
    {
        return $this->lastName;
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
}
