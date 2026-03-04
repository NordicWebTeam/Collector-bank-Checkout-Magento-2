<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Payment\Invoice\Rows;

use Webbhuset\CollectorCheckout\Service\Sdk\Payment\Invoice\Article\Article;
use Webbhuset\CollectorCheckout\Service\Sdk\Payment\Invoice\Article\ArticleFactory;

class InvoiceRow
{
    private string $articleId;
    private string $description;
    private int $quantity;
    private float $unitPrice;
    private float $vat;
    private string $type;
    private ArticleFactory $articleFactory;

    public function __construct(
        string $articleId,
        string $description,
        int $quantity,
        float $unitPrice,
        float $vat,
        ArticleFactory $articleFactory,
        string $type = 'Purchase'
    ) {
        $this->articleId    = $articleId;
        $this->description  = $description;
        $this->quantity     = $quantity;
        $this->unitPrice    = $unitPrice;
        $this->vat          = $vat;
        $this->type         = $type;
        $this->articleFactory = $articleFactory;
    }

    /**
     * @return string
     */
    public function getArticleId(): string
    {
        return $this->articleId;
    }

    /**
     * @param string $articleId
     */
    public function setArticleId(string $articleId): void
    {
        $this->articleId = $articleId;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    public function toArticle(): Article
    {
        return $this->articleFactory->create([
            'articleId' => (string) $this->articleId,
            'description' => (string) $this->description,
            'quantity' => (int) $this->quantity,
            'sku' => (string) $this->articleId,
            'unitPrice' => (float) $this->unitPrice,
            'vat' => (float) $this->vat,
            'type' => (string) $this->type,
        ]);
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return float
     */
    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    /**
     * @param float $unitPrice
     */
    public function setUnitPrice(float $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    /**
     * @return float
     */
    public function getVat(): float
    {
        return $this->vat;
    }

    /**
     * @param float $vat
     */
    public function setVat(float $vat): void
    {
        $this->vat = $vat;
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @param int $quantity
     */
    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    /**
     * @return array<string, int|float|string>
     */
    public function toArray(): array
    {
        return [
            'ArticleId'     => $this->articleId,
            'Description'   => $this->description,
            'Quantity'      => $this->quantity,
            'UnitPrice'     => $this->unitPrice,
            'VAT'           => $this->vat,
            'Type'          => $this->type
        ];
    }
}
