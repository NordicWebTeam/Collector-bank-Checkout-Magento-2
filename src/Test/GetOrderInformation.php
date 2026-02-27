<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Test;

use Magento\Sales\Api\OrderRepositoryInterface;
use Webbhuset\CollectorCheckout\Adapter;
use Webbhuset\CollectorCheckout\Api\Data\DTO\GetOrderInformationInterface;
use Webbhuset\CollectorCheckout\Api\Data\DTO\GetOrderInformationInterfaceFactory;
use Webbhuset\CollectorCheckout\Api\Data\DTO\GetOrderInformation\ItemInterfaceFactory;
use Webbhuset\CollectorCheckout\Config\ConfigFactory;

class GetOrderInformation
{
    /**
     * @var ConfigFactory
     */
    private ConfigFactory $configFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var Adapter
     */
    private Adapter $adapter;

    /**
     * @var GetOrderInformationInterfaceFactory
     */
    private GetOrderInformationInterfaceFactory $getOrderInformationFactory;

    /**
     * @var ItemInterfaceFactory
     */
    private ItemInterfaceFactory $itemFactory;

    public function __construct(
        ConfigFactory $configFactory,
        OrderRepositoryInterface $orderRepository,
        Adapter $adapter,
        GetOrderInformationInterfaceFactory $getOrderInformationFactory,
        ItemInterfaceFactory $itemFactory
    ) {
        $this->configFactory = $configFactory;
        $this->orderRepository = $orderRepository;
        $this->adapter = $adapter;
        $this->getOrderInformationFactory = $getOrderInformationFactory;
        $this->itemFactory = $itemFactory;
    }

    /**
     * @param int $orderId
     * @return GetOrderInformationInterface
     */
    public function execute(int $orderId): GetOrderInformationInterface
    {
        $order = $this->orderRepository->get($orderId);
        $config = $this->configFactory->create(['storeId' => (int)$order->getStoreId()]);
        /** @var \Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Adapter\CurlWithAccessKey $adapter */
        $adapter = $this->adapter->getAdapter($config);
        $additionalInformation = $order->getPayment()->getAdditionalInformation();

        $result = $this->getOrderInformationFactory->create();
        $walleyOrderId = $additionalInformation['order_id'] ?? false;

        if (!$walleyOrderId) {
            return $result;
        }

        $rawResult = $adapter->getOrder($walleyOrderId);
        $resultData = $rawResult['data'] ?? [];
        $rawResultItems = $resultData['items'] ?? [];

        foreach ($rawResultItems as $rawResultItem) {
            $item = $this->itemFactory->create();
            $item->setArticleNumber($rawResultItem['articleNumber'] ?? '');
            $item->setDescription($rawResultItem['description'] ?? '');
            $item->setPrice($rawResultItem['price'] ?? 0);
            $item->setQuantiy($rawResultItem['quantity'] ?? 0);
            $item->setVatRate($rawResultItem['vatRate'] ?? 0);
            $result->addItem($item);
        }
        return $result;
    }
}
