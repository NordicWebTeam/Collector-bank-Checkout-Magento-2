<?php

namespace Webbhuset\CollectorCheckout\Invoice;

use Webbhuset\CollectorPaymentSDK\Invoice\Article\Article as Article;
use Webbhuset\CollectorPaymentSDK\Invoice\Article\ArticleList as ArticleList;
use Webbhuset\CollectorPaymentSDK\Invoice\Rows\InvoiceRow;
use Webbhuset\CollectorPaymentSDK\Invoice\Rows\InvoiceRows;
use Webbhuset\CollectorPaymentSDK\Invoice\Article\ArticleFactory;
use Webbhuset\CollectorPaymentSDK\Invoice\Article\ArticleListFactory;
use Webbhuset\CollectorPaymentSDK\Invoice\Rows\InvoiceRowFactory;
use Webbhuset\CollectorPaymentSDK\Invoice\Rows\InvoiceRowsFactory;

/**
 * Class RowMatcher
 *
 * @package Webbhuset\CollectorCheckout\Invoice
 */
class RowMatcher
{
    /**
     * @var \Webbhuset\CollectorCheckout\Data\OrderHandler
     */
    protected $orderHandler;
    /**
     * @var \Webbhuset\CollectorCheckout\Adapter
     */
    protected $adapter;

    /**
     * @var RowMatcher\CreditMemoHandler
     */
    protected $creditMemoHandler;
    /**
     * @var RowMatcher\InvoiceHandler
     */
    protected $invoiceHandler;
    private \Webbhuset\CollectorCheckout\Test\GetOrderInformation $getOrderInformation;
    private \Webbhuset\CollectorCheckout\Helper\GetMatchingArticles $getMatchingArticles;
    private ArticleListFactory $articleListFactory;
    private ArticleFactory $articleFactory;
    private InvoiceRowsFactory $invoiceRowsFactory;
    private InvoiceRowFactory $invoiceRowFactory;

    /**
     * rowMatcher constructor.
     */
    public function __construct(
        \Webbhuset\CollectorCheckout\Data\OrderHandler $orderHandler,
        \Webbhuset\CollectorCheckout\Adapter $adapter,
        \Webbhuset\CollectorCheckout\Helper\GetMatchingArticles $getMatchingArticles,
        \Webbhuset\CollectorCheckout\Test\GetOrderInformation $getOrderInformation,
        \Webbhuset\CollectorCheckout\Invoice\RowMatcher\CreditMemoHandler $creditMemoHandler,
        \Webbhuset\CollectorCheckout\Invoice\RowMatcher\InvoiceHandler $invoiceHandler,
        ArticleListFactory $articleListFactory,
        ArticleFactory $articleFactory,
        InvoiceRowsFactory $invoiceRowsFactory,
        InvoiceRowFactory $invoiceRowFactory
    ) {
        $this->orderHandler         = $orderHandler;
        $this->adapter              = $adapter;
        $this->creditMemoHandler    = $creditMemoHandler;
        $this->invoiceHandler       = $invoiceHandler;
        $this->getOrderInformation  = $getOrderInformation;
        $this->getMatchingArticles  = $getMatchingArticles;
        $this->articleListFactory   = $articleListFactory;
        $this->articleFactory       = $articleFactory;
        $this->invoiceRowsFactory   = $invoiceRowsFactory;
        $this->invoiceRowFactory    = $invoiceRowFactory;
    }

    /**
     * Converts an invoice to a collector article list
     *
     * @param \Magento\Sales\Model\Order\Creditmemo  $creditMemo
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return ArticleList
     */
    public function invoiceToArticleList(
        \Magento\Sales\Model\Order\Invoice $invoice,
        \Magento\Sales\Api\Data\OrderInterface $order
    ): \Webbhuset\CollectorPaymentSDK\Invoice\Article\ArticleList {
        $checkoutDataArticleList = $this->checkoutDataToArticleList($order);

        $matchingArticles = $this->articleListFactory->create();

        $matchingArticles = $this->getMatchingArticles->execute(
            $matchingArticles,
            $checkoutDataArticleList,
            $invoice,
            $order
        );

        $matchingArticles = $this->invoiceHandler->addShipping(
            $matchingArticles,
            $checkoutDataArticleList,
            $invoice,
            $order
        );

        $matchingArticles = $this->invoiceHandler->addDecimalRounding(
            $matchingArticles,
            $checkoutDataArticleList
        );

        return $matchingArticles;
    }

    /**
     * Returns the full article list from checkout data without doing any matching.
     * Used as a failsafe for full invoice activation to avoid complex matching edge cases.
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return ArticleList
     */
    public function fullInvoiceToArticleList(
        \Magento\Sales\Api\Data\OrderInterface $order
    ): \Webbhuset\CollectorPaymentSDK\Invoice\Article\ArticleList {
        return $this->checkoutDataToArticleList($order);
    }

    /**
     * Converts a credit memo to a collector article list that can be used to credit items using collectors payment api
     *
     * @param \Magento\Sales\Model\Order\Creditmemo  $creditMemo
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return ArticleList
     */
    public function creditMemoToArticleList(
        \Magento\Sales\Model\Order\Creditmemo $creditMemo,
        \Magento\Sales\Api\Data\OrderInterface $order
    ): \Webbhuset\CollectorPaymentSDK\Invoice\Article\ArticleList {
        $checkoutDataArticleList = $this->checkoutDataToArticleList($order);

        $matchingArticles = $this->articleListFactory->create();

        $matchingArticles = $this->getMatchingArticles->execute(
            $matchingArticles,
            $checkoutDataArticleList,
            $creditMemo,
            $order
        );
        $matchingArticles = $this->creditMemoHandler->addShipping(
            $matchingArticles,
            $checkoutDataArticleList,
            $creditMemo,
            $order
        );
        $matchingArticles = $this->creditMemoHandler->addDecimalRounding(
            $matchingArticles,
            $checkoutDataArticleList,
            $creditMemo,
            $order
        );


        return $matchingArticles;
    }

    /**
     * Returns the full article list from checkout data without doing any matching.
     * Used as a failsafe for full credit memo refund to avoid complex matching edge cases.
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return ArticleList
     */
    public function fullCreditMemoToArticleList(
        \Magento\Sales\Api\Data\OrderInterface $order
    ): \Webbhuset\CollectorPaymentSDK\Invoice\Article\ArticleList {
        return $this->checkoutDataToArticleList($order);
    }

    /**
     * Get the checkout data for an order from collector and return an article list
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return ArticleList
     */
    public function checkoutDataToArticleList(
        \Magento\Sales\Api\Data\OrderInterface $order
    ): \Webbhuset\CollectorPaymentSDK\Invoice\Article\ArticleList {
        $getOrderInformation = $this->getOrderInformation->execute((int)$order->getEntityId());
        $articleList = $this->articleListFactory->create();

        foreach ($getOrderInformation->getItems() as $item) {
            /** @var $item \Webbhuset\CollectorCheckoutSDK\Checkout\Order\Item */
            $article = $this->articleFactory->create([
                'articleId' => $item->getArticleNumber(),
                'description' => $item->getDescription(),
                'qty' => $item->getQuantiy(),
                'sku' => $item->getArticleNumber(),
                'unitPrice' => $item->getPrice(),
                'vat' => $item->getVatRate()
            ]);

            $articleList->addArticle($article);
        }

        return $articleList;
    }

    public function convertArticleListToInvoiceRows(
        ArticleList $articleList
    ): InvoiceRows {
        return $this->invoiceRowsFactory->create();
    }

    /**
     * Convert adjustment fee to invoice rows
     *
     * @param $adjustmentFee
     * @return InvoiceRow
     */
    public function adjustmentToInvoiceRows(
        $adjustmentFee,
        $taxPercent = 0
    ): \Webbhuset\CollectorPaymentSDK\Invoice\Rows\InvoiceRow {
        if ($adjustmentFee > 0) {
            $articleId = __('Discount');
            $description = __('Discount');
            $type = 'discount';
        } else {
            $articleId  = __('Fee');
            $description = __('Fee');
            $type = 'fee';
        }
        $qty = 1;

        return $this->invoiceRowFactory->create([
            'articleId' => $articleId,
            'description' => $description,
            'qty' => $qty,
            'adjustmentFee' => $adjustmentFee,
            'taxPercent' => (float) $taxPercent,
            'type' => $type
        ]);
    }
}
