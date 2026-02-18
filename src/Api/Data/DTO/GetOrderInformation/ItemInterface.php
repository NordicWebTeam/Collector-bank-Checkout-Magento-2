<?php declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Api\Data\DTO\GetOrderInformation;

/**
 * Interface for DTO of 'Item' part of Get Order Information
 */
interface ItemInterface
{
    public const ARTICLE_NUMBER = 'article_number';
    public const DESCRIPTION = 'description';
    public const QUANTIY = 'quantiy';
    public const VAT_RATE = 'vat_rate';
    public const PRICE = 'price';

    /**
     * @return string
     */
    public function getArticleNumber(): string;

    /**
     * @param string $articleNumber
     * @return self
     */
    public function setArticleNumber(string $articleNumber): self;

    /**
     * @return string
     */
    public function getDescription(): string;

    /**
     * @param string $description
     * @return self
     */
    public function setDescription(string $description): self;

    /**
     * @return float
     */
    public function getQuantiy(): float;

    /**
     * @param float $quantiy
     * @return self
     */
    public function setQuantiy(float $quantiy): self;

    /**
     * @return float
     */
    public function getVatRate(): float;

    /**
     * @param float $vatRate
     * @return self
     */
    public function setVatRate(float $vatRate): self;

    /**
     * @return float
     */
    public function getPrice(): float;

    /**
     * @param float $price
     * @return self
     */
    public function setPrice(float $price): self;

    /**
     * @return array
     */
    public function toArray(array $keys = []);
}
