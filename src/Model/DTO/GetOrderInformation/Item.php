<?php declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\DTO\GetOrderInformation;

use Magento\Framework\DataObject;
use Webbhuset\CollectorCheckout\Api\Data\DTO\GetOrderInformation\ItemInterface;

class Item extends DataObject implements ItemInterface
{
    /**
     * @return string
     */
    public function getArticleNumber(): string
    {
        return (string)$this->getData(self::ARTICLE_NUMBER);
    }

    /**
     * @param string $articleNumber
     * @return ItemInterface
     */
    public function setArticleNumber(string $articleNumber): ItemInterface
    {
        return $this->setData(self::ARTICLE_NUMBER, $articleNumber);
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return (string)$this->getData(self::DESCRIPTION);
    }

    /**
     * @param string $description
     * @return ItemInterface
     */
    public function setDescription(string $description): ItemInterface
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    /**
     * @return float
     */
    public function getQuantiy(): float
    {
        return (float)$this->getData(self::QUANTIY);
    }

    /**
     * @param float $quantiy
     * @return ItemInterface
     */
    public function setQuantiy(float $quantiy): ItemInterface
    {
        return $this->setData(self::QUANTIY, $quantiy);
    }

    /**
     * @return float
     */
    public function getVatRate(): float
    {
        return (float)$this->getData(self::VAT_RATE);
    }

    /**
     * @param float $vatRate
     * @return ItemInterface
     */
    public function setVatRate(float $vatRate): ItemInterface
    {
        return $this->setData(self::VAT_RATE, $vatRate);
    }

    /**
     * @return float
     */
    public function getPrice(): float
    {
        return (float)$this->getData(self::PRICE);
    }

    /**
     * @param float $price
     * @return ItemInterface
     */
    public function setPrice(float $price): ItemInterface
    {
        return $this->setData(self::PRICE, $price);
    }
}