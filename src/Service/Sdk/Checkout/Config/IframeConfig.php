<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Config;

class IframeConfig
    implements \Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Config\IframeConfigInterface
{
    private string $dataToken;
    private ?string $dataLang;
    private ?string $dataPadding;
    private ?string $dataContainerId;
    private ?string $dataActionColor;
    private ?string $dataActionTextColor;

    public function __construct(
        string $dataToken,
        ?string $dataLang = null,
        ?string $dataPadding = null,
        ?string $dataContainerId = null,
        ?string $dataActionColor = null,
        ?string $dataActionTextColor = null
    ) {
        $this->dataToken            = $dataToken;
        $this->dataLang             = $dataLang;
        $this->dataContainerId      = $dataContainerId;
        $this->dataActionColor      = $dataActionColor;
        $this->dataActionTextColor  = $dataActionTextColor;
        $this->dataPadding          = $dataPadding;
    }

    public function getSrc(string $mode = "production mode") : string
    {
        if ("production mode" == $mode) {
            return 'https://checkout.collector.se/walley-checkout-loader.js';
        }

        return 'https://checkout-uat.collector.se/walley-checkout-loader.js';
    }

    /**
     * The publicToken acquired when Initializing a Checkout Session.
     *
     * @return string
     */
    public function getDataToken() : string
    {
        return $this->dataToken;
    }

    /**
     * The display language. Currently supported combinations are:
     * sv-SE, en-SE, nb-NO, fi-FI, da-DK and en-DE. Both sv-SE and en-SE
     *
     * @return string
     */
    public function getDataLang(): ?string
    {
        return $this->dataLang;
    }

    public function getDataPadding(): ?string
    {
        return $this->dataPadding;
    }

    public function getDataContainerId(): ?string
    {
        return $this->dataContainerId;
    }

    public function getDataActionColor(): ?string
    {
        return $this->dataActionColor;
    }

    public function getDataActionTextColor(): ?string
    {
        return $this->dataActionTextColor;
    }
}
