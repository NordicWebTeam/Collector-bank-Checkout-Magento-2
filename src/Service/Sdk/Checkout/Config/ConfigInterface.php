<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Config;

interface ConfigInterface
{
    public function getAccessKey() : string;
    public function getCountryCode() : string;
    public function getStoreId() : string;

    public function getIsTestMode() : bool;

    public function getMerchantTermsUri() : string;
    public function getRedirectPageUri(): string;
    public function getNotificationUri() : string;
    public function getValidationUri(): string;
    public function getProfileName(): string;
    public function getCustomFields(): array;
}
