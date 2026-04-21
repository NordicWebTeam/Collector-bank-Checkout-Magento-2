<?php declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\DTO;

use Magento\Framework\DataObject;
use Webbhuset\CollectorCheckout\Api\Data\DTO\GetOrderInformation\ItemInterface;
use Webbhuset\CollectorCheckout\Api\Data\DTO\GetOrderInformationInterface;

/**
 * DTO for Get Order Information
 */
class GetOrderInformation extends DataObject implements GetOrderInformationInterface
{
    /**
     * @param ItemInterface $item
     * @return void
     */
    public function addItem(ItemInterface $item): void
    {
        $items = $this->_data['items'] ?? [];
        $items[] = $item;
        $this->_data['items'] = $items;
    }

    /**
     * @return ItemInterface[]
     */
    public function getItems(): array
    {
        $itemsData = $this->getData('items');
        return empty($itemsData) ? [] : $itemsData;
    }

    /**
     * Converts 'items' to array
     *
     * @param array $keys
     * @return void
     */
    public function toArray(array $keys = [])
    {
        $result = parent::toArray($keys);
        if (!empty($keys) && !in_array('items', $keys)) {
            return $result;
        }

        $arrayedItems = [];
        foreach ($this->getItems() as $item) {
            $arrayedItems[] = $item->toArray();
        }

        $result['items'] = $arrayedItems;
        return $result;
    }
}
