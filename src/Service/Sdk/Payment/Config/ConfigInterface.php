<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Payment\Config;

/**
 * Interface ConfigInterface
 *
 * @package Config
 */
interface ConfigInterface
{

    /**
     *
     * Gets access key
     *
     * @return string $accessKey
     */
    public function getAccessKey(): string;

    /**
     *
     * Gets the storeId
     *
     * @return int storeId
     */
    public function getStoreId(): string;


    /**
     *
     * Gets the country code
     *
     * @return string countryCode
     */
    public function getCountryCode(): string;


    /**
     *
     * Checks whether test mode has been set
     *
     * @return bool true if test mode
     */
    public function isTestMode(): bool;

    /**
     *
     * Checks if production mode is set
     *
     * @return bool true if production mode
     */
    public function isProductionMode(): bool;

}
