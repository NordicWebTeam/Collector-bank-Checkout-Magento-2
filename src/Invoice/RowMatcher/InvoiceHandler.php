<?php

namespace Webbhuset\CollectorCheckout\Invoice\RowMatcher;

use Webbhuset\CollectorCheckout\Helper\ProductType;
use Webbhuset\CollectorCheckout\Service\Sdk\Payment\Invoice\Article\ArticleList as ArticleList;

class InvoiceHandler
{
    /**
     * @var \Webbhuset\CollectorCheckout\Data\OrderHandler
     */
    protected $orderHandler;
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * rowMatcher constructor.
     */
    public function __construct(
        \Webbhuset\CollectorCheckout\Data\OrderHandler $orderHandler,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        $this->orderHandler         = $orderHandler;
        $this->orderRepository      = $orderRepository;
    }

    /**
     *
     * Add shipping as matchingArticles
     *
     * @param ArticleList                            $matchingArticles
     * @param ArticleList                            $articleList
     * @param \Magento\Sales\Model\Order\Creditmemo  $creditMemo
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return ArticleList
     * @throws \Webbhuset\CollectorCheckout\Exception\Exception
     */
    public function addShipping(
        ArticleList $matchingArticles,
        ArticleList $articleList,
        \Magento\Sales\Model\Order\Invoice $invoice,
        \Magento\Sales\Api\Data\OrderInterface $order
    ): ArticleList {
        $invoiceShippingAmount = $invoice->getShippingAmount();

        if ($invoiceShippingAmount >= 0
            && !$order->getPayment()->getShippingCaptured()
        ) {
            $shippingArticle = $articleList->getShippingArticle();
            if ($shippingArticle) {
                $matchingArticles->addArticle($shippingArticle);
            }
        }

        return $matchingArticles;
    }

    /**
     *
     * Add decimal rounding to matchingArticles
     *
     * @param ArticleList                            $matchingArticles
     * @param ArticleList                            $articleList
     * @param \Magento\Sales\Model\Order\Creditmemo  $creditMemo
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return ArticleList
     */
    public function addDecimalRounding(
        ArticleList $matchingArticles,
        ArticleList $articleList
    ): ArticleList {
        $decimalRounding = $articleList->getArticleBySku(\Webbhuset\CollectorCheckout\Gateway\Config::CURRENCY_ROUNDING_SKU);

        if ($decimalRounding) {
            $matchingArticles->addArticle($decimalRounding);
        }

        return $matchingArticles;
    }

    /**
     * isDecimalRoundingInvoiced checks if decimal rounding has been invoiced on the invoice
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return bool
     */
    public function isDecimalRoundingInvoiced(
        \Magento\Sales\Api\Data\OrderInterface $order
    ) {
        return $this->orderHandler->getDecimalRoundingInvoiced($order);
    }

    /**
     * setDecimalRoundingIsInvoiced set on the order that decimal rounding has been invoiced
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     */
    public function setDecimalRoundingIsInvoiced(
        \Magento\Sales\Api\Data\OrderInterface $order
    ):void {
        $this->orderHandler->setDecimalRoundingInvoiced($order);
    }
}
