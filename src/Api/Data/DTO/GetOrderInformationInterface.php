<?php declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Api\Data\DTO;

use Webbhuset\CollectorCheckout\Api\Data\DTO\GetOrderInformation\ItemInterface;

/**
 * Interface for DTO of 'Item' part of Get Order Information Item
 */
interface GetOrderInformationInterface
{
    /**
     * @return ItemInterface[]
     */
    public function getItems(): array;

    /**
     * @param ItemInterface $item
     * @return void
     */
    public function addItem(ItemInterface $item): void;

    /**
     * @return string
     */
    public function toJson();
}
