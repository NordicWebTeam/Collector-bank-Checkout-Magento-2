<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Payment\Invoice\Article;

use Webbhuset\CollectorCheckout\Service\Sdk\Payment\Invoice\Rows\InvoiceRows;
use Webbhuset\CollectorCheckout\Service\Sdk\Payment\Invoice\Rows\InvoiceRowsFactory;

class ArticleList
{
    private array $articles = [];
    private InvoiceRowsFactory $invoiceRowsFactory;

    public function __construct(InvoiceRowsFactory $invoiceRowsFactory)
    {
        $this->invoiceRowsFactory = $invoiceRowsFactory;
    }

    public function addArticle(Article $article): void
    {
        $this->articles[] = $article;
    }

    public function getArticleBySku(string $sku): ?Article
    {
        foreach ($this->articles as $article) {
            if ($sku == $article->getSku()) {
                return $article;
            }
        }

        return null;
    }

    /**
     * Get article by SKU and remove it from the list to prevent duplicate matches
     *
     * @param string $sku
     * @return Article|null
     */
    public function getAndRemoveArticleBySku(string $sku): ?Article
    {
        foreach ($this->articles as $key => $article) {
            if ($sku == $article->getSku()) {
                unset($this->articles[$key]);
                return $article;
            }
        }

        return null;
    }

    public function getDiscountArticleBySku(string $sku): ?Article
    {
        foreach ($this->articles as $article) {
            if ($sku == $article->getSku() && $article->getUnitPrice() < 0) {
                return $article;
            }
        }

        return null;
    }

    /**
     * Get discount article by SKU and remove it from the list to prevent duplicate matches
     *
     * @param string $sku
     * @return Article|null
     */
    public function getAndRemoveDiscountArticleBySku(string $sku): ?Article
    {
        foreach ($this->articles as $key => $article) {
            if ($sku == $article->getSku() && $article->getUnitPrice() < 0) {
                unset($this->articles[$key]);
                return $article;
            }
        }

        return null;
    }

    public function removeDecimalRounding(): void
    {
        $result = [];
        foreach ($this->articles as $article) {
            if (\Webbhuset\CollectorCheckout\Gateway\Config::CURRENCY_ROUNDING_SKU != $article->getSku()) {
                $result[] = $article;
            }
        }

        $this->articles = $result;
    }

    public function getDecimalRounding(): ?Article
    {
        return $this->getArticleBySku(\Webbhuset\CollectorCheckout\Gateway\Config::CURRENCY_ROUNDING_SKU);
    }

    public function getShippingArticle(): ?Article
    {
        return $this->getArticleBySku("Frakt") ?: $this->getArticleBySku("Fragt") ?: $this->getArticleBySku("Toimituskulut");
    }

    public function getInvoiceRows():InvoiceRows
    {
        $invoiceRows = $this->invoiceRowsFactory->create();
        /** @var Article $article */
        foreach ($this->articles as $article) {
            $invoiceRows->addInvoiceRow($article->toAdjustInvoiceRow());
        }

        return $invoiceRows;
    }

    /**
     * @return array<int, array<string, int|float|string>>
     */
    public function getArticleList(): array
    {
        $result = [];
        foreach ($this->articles as $article) {
            $result[] = $article->toArray();
        }

        return $result;
    }
}
