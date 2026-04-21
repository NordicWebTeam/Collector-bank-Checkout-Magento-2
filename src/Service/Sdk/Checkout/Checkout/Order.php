<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout;

use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Order\Item;

class Order
{
    private array $items = [];
    private $totalAmount;

    public function __construct(
        array $items,
        int $totalAmount
    ) {
        // Type check items
        foreach ($items as $item) {
            $this->addItem($item);
        }

        $this->totalAmount = $totalAmount;
    }

    private function addItem(Item $item)
    {
        $this->items[] = $item;
    }

    public function getItems() : array
    {
        return $this->items;
    }

    public function getTotalAmount() : int
    {
        return $this->totalAmount;
    }
}
