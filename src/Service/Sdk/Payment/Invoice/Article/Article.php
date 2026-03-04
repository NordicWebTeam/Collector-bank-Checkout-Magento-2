<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Payment\Invoice\Article;

use Webbhuset\CollectorCheckout\Service\Sdk\Payment\Invoice\Rows\InvoiceRow;
use Webbhuset\CollectorCheckout\Service\Sdk\Payment\Invoice\Rows\InvoiceRowFactory;

class Article
{
    private string $articleId;
    private string $description;
    private int $quantity;
    private string $sku;
    private float $unitPrice;
    private float $vat;
    private string $type;
    private InvoiceRowFactory $invoiceRowFactory;

    public function __construct(
        string $articleId,
        string $description,
        int $quantity,
        InvoiceRowFactory $invoiceRowFactory,
        string $sku="",
        float $unitPrice = 0,
        float $vat = 0,
        string $type = "purchase"
    ) {
        $this->articleId    = $articleId;
        $this->description  = $description;
        $this->quantity     = $quantity;
        $this->sku          = $sku;
        $this->unitPrice    = $unitPrice;
        $this->vat          = $vat;
        $this->type         = $type;
        $this->invoiceRowFactory = $invoiceRowFactory;
    }

    /**
     * @return string
     */
    public function getSku(): string
    {
        return $this->sku;
    }

    /**
     * @param string $sku
     */
    public function setSku(string $sku): void
    {
        $this->sku = $sku;
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
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @return float|int
     */
    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    /**
     * @param int $quantity
     */
    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function toInvoiceRow(): InvoiceRow
    {
        return $this->invoiceRowFactory->create([
            'articleId' => (string) $this->articleId,
            'description' => (string) $this->description,
            'quantity' => (int) $this->quantity,
            'unitPrice' => (float) $this->unitPrice,
            'vat' => (float) $this->vat,
            'type' => (string) $this->type,
        ]);
    }


    public function toAdjustInvoiceRow(): InvoiceRow
    {
        return $this->invoiceRowFactory->create([
            'articleId' => (string) $this->articleId,
            'description' => (string) $this->description,
            'quantity' => (int) $this->quantity,
            'unitPrice' => (float) $this->unitPrice * (-1),
            'vat' => (float) $this->vat,
            'type' => (string) $this->type,
        ]);
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
            'Type'          => $this->type,
            'VAT'           => $this->vat,
        ];
    }
}
