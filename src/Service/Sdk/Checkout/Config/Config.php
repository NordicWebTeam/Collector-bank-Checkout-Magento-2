<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Config;

class Config implements \Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Config\ConfigInterface
{
    private string $accessKey = '';
    private string $countryCode = '';
    private string $storeId = '';
    private bool $isTestMode = false;
    private string $merchantTermsUri = '';
    private string $redirectPageUri = '';
    private string $notificationUri = '';
    private string $validationUri = '';
    private string $profileName = '';

    public function getAccessKey() : string
    {
        return $this->accessKey;
    }

    public function setAccessKey(string $accessKey) : Config
    {
        $this->accessKey = $accessKey;

        return $this;
    }

    public function getCountryCode() : string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode) : Config
    {
        $this->countryCode = $countryCode;


        return $this;
    }

    public function getIsTestMode() : bool
    {
        return $this->isTestMode;
    }

    public function setIsTestMode(bool $bool) : Config
    {
        $this->isTestMode = $bool;

        return $this;
    }

    public function getStoreId() : string
    {
        return $this->storeId;
    }

    public function setStoreId(string $storeId) : Config
    {
        $this->storeId = $storeId;

        return $this;
    }

    public function getMerchantTermsUri() : string
    {
        return $this->merchantTermsUri;
    }

    public function setMerchantTermsUri(string $merchantTermsUri) : Config
    {
        $this->merchantTermsUri = $merchantTermsUri;

        return $this;
    }

    public function getRedirectPageUri(): string
    {
        return $this->redirectPageUri;
    }

    public function setRedirectPageUri(string $redirectPageUri) : Config
    {
        $this->redirectPageUri = $redirectPageUri;

        return $this;
    }

    public function getNotificationUri() : string
    {
        return $this->notificationUri;
    }

    public function setNotificationUri(string $notificationUri) : Config
    {
        $this->notificationUri = $notificationUri;

        return $this;
    }

    public function getValidationUri(): string
    {
        return $this->validationUri;
    }

    public function setValidationUri(string $validationUri) : Config
    {
        $this->validationUri = $validationUri;

        return $this;
    }

    public function getProfileName() : string
    {
        return $this->profileName;
    }

    public function setProfileName(string $profileName) : Config
    {
        $this->profileName = $profileName;

        return $this;
    }
}
