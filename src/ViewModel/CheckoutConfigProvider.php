<?php declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Checkout\Model\CompositeConfigProvider;

/**
 * Checkout config provider view model
 */
class CheckoutConfigProvider implements ArgumentInterface
{
    /**
     * @var CompositeConfigProvider
     */
    private CompositeConfigProvider $configProvider;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    public function __construct(
        CompositeConfigProvider $configProvider,
        SerializerInterface $serializer
    ) {
        $this->configProvider = $configProvider;
        $this->serializer = $serializer;

        if (class_exists('Magento\Framework\Serialize\Serializer\JsonHexTag')) {
            $this->serializer = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Framework\Serialize\Serializer\JsonHexTag::class);
        }
    }

    /**
     * @return string
     */
    public function getSerializedCheckoutConfig(): string
    {
        try {
            return $this->serializer->serialize($this->configProvider->getConfig());
        } catch (\Exception $e) {
            return '';
        }
    }
}
