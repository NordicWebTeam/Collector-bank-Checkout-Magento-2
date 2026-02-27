<?php

namespace Webbhuset\CollectorCheckout;

use Webbhuset\CollectorCheckout\Exception\QuoteNotInSyncException;

class QuoteComparer
{
    protected $quoteConverter;
    protected $config;
    protected $storeManager;
    /**
     * @var Shipment\IsCustomDeliveryAdapter
     */
    private $isCustomDeliveryAdapter;

    public function __construct(
        \Webbhuset\CollectorCheckout\QuoteConverter $quoteConverter,
        \Webbhuset\CollectorCheckout\Config\Config $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Webbhuset\CollectorCheckout\Shipment\IsCustomDeliveryAdapter $isCustomDeliveryAdapter
    ) {
        $this->quoteConverter           = $quoteConverter;
        $this->config                   = $config;
        $this->storeManager             = $storeManager;
        $this->isCustomDeliveryAdapter  = $isCustomDeliveryAdapter;
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @param \Webbhuset\CollectorCheckout\Service\Sdk\Checkout\CheckoutData $checkoutData
     * @return bool
     * @throws QuoteNotInSyncException
     */
    public function isQuoteInSync(
        \Magento\Quote\Api\Data\CartInterface $quote,
        \Webbhuset\CollectorCheckout\Service\Sdk\Checkout\CheckoutData $checkoutData
    ): bool {
        $grandTotalInSync = $this->isGrandTotalSync($quote, $checkoutData);
        if (!$grandTotalInSync) {
            throw new QuoteNotInSyncException(__('Grand total not in sync'));
        }

        $cartInSync = $this->isCartItemsInSync($quote, $checkoutData);
        if (!$cartInSync) {
            throw new QuoteNotInSyncException(__('Items not in sync'));
        }

        return true;
    }

    public function isGrandTotalSync(
        \Magento\Quote\Model\Quote $quote,
        \Webbhuset\CollectorCheckout\Service\Sdk\Checkout\CheckoutData $checkoutData
    ) {
        $grandTotalCeil = ceil($quote->getGrandTotal());
        $collectorTotalCeil = ceil($this->calculateCollectorTotal($checkoutData));

        $grandTotalRound = round($quote->getGrandTotal());
        $collectorTotalRound = round($this->calculateCollectorTotal($checkoutData));

        return ($grandTotalCeil == $collectorTotalCeil)
            || ($grandTotalRound == $collectorTotalRound);
    }

    public function isCartItemsInSync(
        \Magento\Quote\Model\Quote $quote,
        \Webbhuset\CollectorCheckout\Service\Sdk\Checkout\CheckoutData $checkoutData
    ) {
        $collectorCartItems = $this->getCollectorCartAsArray($checkoutData);
        $cartItems = $this->getQuoteItemsAsArray($quote);

        array_walk($collectorCartItems, [$this, 'serializeElements']);
        array_walk($cartItems, [$this, 'serializeElements']);

        return empty(array_diff($collectorCartItems, $cartItems));
    }

    public function isCurrencyMatching()
    {
        $collectorCurrency = $this->config->getCurrency();
        $storeCurrency = $this->storeManager->getStore()->getCurrentCurrencyCode();

        return ($collectorCurrency == $storeCurrency);
    }

    protected function getQuoteItemsAsArray(
        \Magento\Quote\Model\Quote $quote
    ) {
        $cartItems = $this->quoteConverter->getCart($quote)->toArray();
        $cartItems = $cartItems['items'];

        array_walk($cartItems, [$this, 'removeExtraColumns']);
        array_walk($cartItems, [$this, 'trimIdField']);

        return $cartItems;
    }

    protected function getCollectorCartAsArray(
        \Webbhuset\CollectorCheckout\Service\Sdk\Checkout\CheckoutData $checkoutData
    ) {
        $checkoutItems = $checkoutData->getCart()->getItems();

        array_walk($checkoutItems, [$this, 'toArrayOnElements']);
        array_walk($checkoutItems, [$this, 'removeExtraColumns']);

        return $checkoutItems;
    }

    protected function getCollectorFeesAsArray(
        \Webbhuset\CollectorCheckout\Service\Sdk\Checkout\CheckoutData $checkoutData
    ) {
        $fees = [];
        $isCustomDeliveryAdapter = $this->isCustomDeliveryAdapter->execute($checkoutData);
        if ($isCustomDeliveryAdapter) {
            $fees[] = [
                'id' =>$this->isCustomDeliveryAdapter->getDeliveryMethod($checkoutData),
                'unitPrice' => $this->isCustomDeliveryAdapter->getDeliveryFee($checkoutData)
            ];
        }

        if (!$checkoutData->getFees()) {
            return $fees;
        }
        $checkoutItems = $checkoutData->getFees()->toArray();

        array_walk($checkoutItems, [$this, 'removeExtraColumns']);
        if ($isCustomDeliveryAdapter) {
            $checkoutItems = array_merge($checkoutItems, $fees);
        }

        return $checkoutItems;
    }

    protected function calculateCollectorTotal(
        \Webbhuset\CollectorCheckout\Service\Sdk\Checkout\CheckoutData $checkoutData
    ) {
        $cartTotal = $this->calculateCollectorCartTotal($checkoutData);
        $feesTotal = $this->calculateCollectorFeesTotal($checkoutData);

        return $cartTotal + $feesTotal;
    }

    protected function calculateCollectorCartTotal(
        \Webbhuset\CollectorCheckout\Service\Sdk\Checkout\CheckoutData $checkoutData
    ) {
        $cartItems = $this->getCollectorCartAsArray($checkoutData);

        $total = 0;
        foreach ($cartItems as $item) {
            $total += $item['unitPrice'] * $item['quantity'];
        }

        return $total;
    }

    protected function calculateCollectorFeesTotal(
        \Webbhuset\CollectorCheckout\Service\Sdk\Checkout\CheckoutData $checkoutData
    ) {
        $cartItems = $this->getCollectorFeesAsArray($checkoutData);

        $total = 0;
        foreach ($cartItems as $item) {
            $total += $item['unitPrice'];
        }

        return $total;
    }

    protected function trimIdField(&$item, $key)
    {
        $item['id'] = trim($item['id']);
    }

    protected function serializeElements(&$item, $key)
    {
        $item = serialize($item);
    }

    protected function removeExtraColumns(&$item, $key)
    {
        unset($item['requiresElectronicId'], $item['sku'], $item['description'], $item['unitWeight']);
    }

    protected function toArrayOnElements(&$item, $key)
    {
        $item = $item->toArray();
    }
}
