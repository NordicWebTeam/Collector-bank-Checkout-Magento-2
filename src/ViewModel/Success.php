<?php declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\ViewModel;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order;
use Webbhuset\CollectorCheckout\Service\Checkout\Storage;

class Success implements ArgumentInterface
{
    /**
     * @var Storage
     */
    private Storage $storage;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    private array $analytics = [];

    private array $enhancedAnalytics = [];

    public function __construct(
        Storage $storage,
        SerializerInterface $serializer
    ) {
        $this->storage = $storage;
        $this->serializer = $serializer;

        if (class_exists('Magento\Framework\Serialize\Serializer\JsonHexTag')) {
            $this->serializer = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Framework\Serialize\Serializer\JsonHexTag::class);
        }
    }

    /**
     * Json encoded string of ecommerce datalayer variables
     *
     * @return false|string
     */
    public function getAnalyticsDataLayer()
    {
        $order = $this->storage->getSuccessOrder();
        if (empty($this->enhancedAnalytics) && null !== $order) {
            $this->setAnalyticsDatalayer($order);
        }
        return $this->serializer->serialize($this->analytics);
    }

    /**
     * RJson encoded string of ecommerce datalayer variables
     *
     * @return false|string
     */
    public function getEnhancedEcommerceDatalayer()
    {
        $order = $this->storage->getSuccessOrder();
        if (empty($this->enhancedAnalytics) && null !== $order) {
            $order = $this->storage->getSuccessOrder();
            $this->setEnhancedEcommerceDatalayer($order);
        }
        return $this->serializer->serialize($this->enhancedAnalytics);
    }

    /**
     * Sets the analytics data layer array based on order data
     *
     * @param \Magento\Sales\Api\Data\OrderInterface|Order $order
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function setAnalyticsDatalayer(\Magento\Sales\Api\Data\OrderInterface $order)
    {
        $products = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $products[] = [
                'sku'      => $item->getSku(),
                'name'     => $item->getName(),
                'price'    => $item->getPrice(),
                'quantity' => round(floatval($item->getQtyOrdered()))
            ];
        }

        $this->analytics = [
            'transactionId'       => $order->getIncrementId(),
            'transactionAffiliation' => $order->getStore()->getName(),
            'transactionTotal'    => $order->getGrandTotal(),
            'transactionTax'      => $order->getTaxAmount(),
            'transactionShipping' => $order->getShippingAmount(),
            'transactionProducts' => $products
        ];
    }

    /**
     * Sets the enhanced ecommerce data layer array based on order data
     *
     * @param \Magento\Sales\Api\Data\OrderInterface|Order $order
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function setEnhancedEcommerceDatalayer(\Magento\Sales\Api\Data\OrderInterface $order)
    {
        $products = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $products[] = [
                'id'      => $item->getSku(),
                'name'     => $item->getName(),
                'price'    => $item->getPrice(),
                'quantity' => round(floatval($item->getQtyOrdered()))
            ];
        }

        $this->enhancedAnalytics = [
            'ecommerce' => [
                'purchase' => [
                    'actionField' => [
                        'id'       => $order->getIncrementId(),
                        'affiliation' =>  $order->getStore()->getName(),
                        'revenue'    => $order->getGrandTotal(),
                        'tax'      => $order->getTaxAmount(),
                        'shipping' => $order->getShippingAmount(),
                    ],
                    'products' => $products
                ]
            ]
        ];
    }
}
