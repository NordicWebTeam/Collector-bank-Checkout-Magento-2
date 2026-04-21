<?php declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Config\IframeConfigFactory;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Config\IframeConfig;
use Webbhuset\CollectorCheckout\Config\ConfigFactory;
use Webbhuset\CollectorCheckout\Config\Config;
use Webbhuset\CollectorCheckout\Service\Checkout\Storage;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Iframe as IframeScript;

/**
 * View model for rendering checkout iframe
 */
class Iframe implements ArgumentInterface
{
    /**
     * @var IframeConfigFactory
     */
    private IframeConfigFactory $iframeConfigFactory;

    /**
     * @var ConfigFactory
     */
    private ConfigFactory $configFactory;

    /**
     * @var Storage
     */
    private Storage $storage;

    /**
     * @var IframeConfig|null
     */
    private ?IframeConfig $iframeConfig = null;

    /**
     * @var Config|null
     */
    private ?Config $config = null;

    public function __construct(
        IframeConfigFactory $iframeConfigFactory,
        ConfigFactory $configFactory,
        Storage $storage
    ) {
        $this->iframeConfigFactory = $iframeConfigFactory;
        $this->configFactory = $configFactory;
        $this->storage = $storage;
    }

    /**
     * @return string
     */
    public function getDataVersion(): string
    {
        return $this->getConfig()->getDisplayCheckoutVersion() !== 'v1' ? 'v2' : 'v1';
    }

    /**
     * @return string
     */
    public function getIframe(): string
    {
        return IframeScript::getScript($this->getIframeConfig(), $this->getConfig()->getMode());
    }

    /**
     * @return string
     */
    public function getIframeSrc(): string
    {
        return $this->getIframeConfig()->getSrc($this->getConfig()->getMode());
    }

    /**
     * @return string
     */
    public function getDataToken(): string
    {
        return (string)$this->getIframeConfig()->getDataToken();
    }

    /**
     * @return string
     */
    public function getDataLang(): string
    {
        return (string)$this->getIframeConfig()->getDataLang();
    }

    /**
     * @return string|null
     */
    public function getDataActionColor(): ?string
    {
        return $this->getIframeConfig()->getDataActionColor();
    }

    /**
     * @return string|null
     */
    public function getDataActionTextColor(): ?string
    {
        return $this->getIframeConfig()->getDataActionTextColor();
    }

    /**
     * @return IframeConfig
     */
    private function getIframeConfig(): IframeConfig
    {
        if (null === $this->iframeConfig) {
            $publicToken = $this->storage->getPublicToken();

            $this->iframeConfig = $this->iframeConfigFactory->create([
                'dataToken' => (string)$publicToken,
                'dataLang' => $this->getConfig()->getStyleDataLang(),
                'dataPadding' => $this->getConfig()->getStyleDataPadding(),
                'dataContainerId' => $this->getConfig()->getStyleDataContainerId(),
                'dataActionColor' => $this->getConfig()->getStyleDataActionColor(),
                'dataActionTextColor' => $this->getConfig()->getStyleDataActionTextColor()
            ]);
        }

        return $this->iframeConfig;
    }

    /**
     * @return Config
     */
    public function getConfig(): Config
    {
        if (null === $this->config) {
            $this->config = $this->configFactory->create([]);
        }

        return $this->config;
    }
}
