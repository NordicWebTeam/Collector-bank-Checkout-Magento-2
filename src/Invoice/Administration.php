<?php

namespace Webbhuset\CollectorCheckout\Invoice;

use Webbhuset\CollectorCheckoutSDK\Adapter\CurlWithAccessKey;
use Webbhuset\CollectorCheckout\Api\Data\DTO\Invoice\AdministrationResultInterfaceFactory;
use Webbhuset\CollectorCheckout\Api\Data\DTO\Invoice\AdministrationResultInterface;

/**
 * Class Administration
 *
 * @package Webbhuset\CollectorCheckout\Invoice
 */
class Administration
{
    /**
     * @var \Webbhuset\CollectorCheckout\Config\ConfigFactory
     */
    protected $configFactory;
    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;
    /**
     * @var Transaction\ManagerFactory
     */
    protected $transaction;
    /**
     * @var \Webbhuset\CollectorCheckout\Logger\Logger
     */
    protected $logger;
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;
    private \Webbhuset\CollectorCheckout\Adapter $adapter;
    private \Webbhuset\CollectorCheckout\Data\ExtractWalleyOrderId $extractWalleyOrderId;
    /**
     * @var RowMatcher
     */
    private RowMatcher $rowMatcher;

    /**
     * @var AdministrationResultInterfaceFactory
     */
    private AdministrationResultInterfaceFactory $administrationResultFactory;

    /**
     * Administration constructor.
     *
     * @param \Webbhuset\CollectorCheckout\Config\ConfigFactory $config
     * @param \Magento\Sales\Model\Service\InvoiceService           $invoiceService
     * @param Transaction\ManagerFactory                            $transaction
     * @param \Magento\Sales\Api\OrderRepositoryInterface           $orderRepository
     * @param \Webbhuset\CollectorCheckout\Data\OrderHandler    $orderHandler
     * @param \Webbhuset\CollectorCheckout\Logger\Logger        $logger
     */
    public function __construct(
        \Webbhuset\CollectorCheckout\Config\ConfigFactory $configFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Webbhuset\CollectorCheckout\Adapter $adapter,
        \Webbhuset\CollectorCheckout\Invoice\Transaction\ManagerFactory $transaction,
        \Webbhuset\CollectorCheckout\Data\ExtractWalleyOrderId $extractWalleyOrderId,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Webbhuset\CollectorCheckout\Invoice\RowMatcher $rowMatcher,
        \Webbhuset\CollectorCheckout\Logger\Logger $logger,
        AdministrationResultInterfaceFactory $administrationResultFactory
    ) {
        $this->configFactory   = $configFactory;
        $this->invoiceService  = $invoiceService;
        $this->transaction     = $transaction;
        $this->logger          = $logger;
        $this->orderRepository = $orderRepository;
        $this->adapter = $adapter;
        $this->extractWalleyOrderId = $extractWalleyOrderId;
        $this->rowMatcher = $rowMatcher;
        $this->administrationResultFactory = $administrationResultFactory;
    }

    /**
     * Cancel the invoice in collector bank portal
     *
     * @param string $invoiceNo
     * @param string $orderId
     * @return AdministrationResultInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function cancelInvoice(string $invoiceNo, string $orderId): AdministrationResultInterface
    {
        $config = $this->getConfig($orderId);

        /** @var \Webbhuset\CollectorCheckoutSDK\Adapter\CurlWithAccessKey $adapter */
        $adapter = $this->adapter->getAdapter($config);
        $walleyOrderId = $this->extractWalleyOrderId->execute((int)$orderId);
        $order = $this->orderRepository->get($orderId);
        $articleList = $this->rowMatcher->checkoutDataToArticleList($order);
        $uniqid = uniqid();
        $adapter->cancelInvoice($walleyOrderId, $articleList, $uniqid);

        $this->logger->addInfo(
            "Invoice cancelled online orderId: {$orderId} invoiceNo: {$walleyOrderId} "
        );

        $result = $this->administrationResultFactory->create();
        $result->setNewInvoiceNumber($uniqid);
        return $result;
    }

    /**
     * Credit an invoice in collector bank portal
     *
     * @param string $invoiceNo
     * @param string $orderId
     * @return AdministrationResultInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function partCreditInvoice(
        string $invoiceNo,
        \Webbhuset\CollectorPaymentSDK\Invoice\Article\ArticleList $articleList,
        string $orderId
    ): AdministrationResultInterface {
        $config = $this->getConfig($orderId);

        /** @var \Webbhuset\CollectorCheckoutSDK\Adapter\CurlWithAccessKey $adapter */
        $adapter = $this->adapter->getAdapter($config);
        $walleyOrderId = $this->extractWalleyOrderId->execute((int)$orderId);
        $uniqid = uniqid();
        $adapter->partCreditInvoice($walleyOrderId, $articleList, $uniqid);
        $this->logger->addInfo(
            "Invoice credited online orderId: {$orderId} invoiceNo: {$walleyOrderId} "
        );

        $result = $this->administrationResultFactory->create();
        $result->setNewInvoiceNumber($uniqid);
        return $result;
    }


    /**
     * Credit an invoice in collector bank portal
     *
     * @param string $invoiceNo
     * @param string $orderId
     * @return AdministrationResultInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function partActivateInvoice(
        string $invoiceNo,
        \Webbhuset\CollectorPaymentSDK\Invoice\Article\ArticleList $articleList,
        string $orderId,
        string $correlationId
    ): AdministrationResultInterface {
        $config = $this->getConfig($orderId);

        /** @var \Webbhuset\CollectorCheckoutSDK\Adapter\CurlWithAccessKey $adapter */
        $adapter = $this->adapter->getAdapter($config);
        $walleyOrderId = $this->extractWalleyOrderId->execute((int)$orderId);
        $uniq = uniqid();
        $adapter->partActivateInvoice($walleyOrderId, $articleList, $uniq);

        $this->logger->addInfo(
            "Invoice activated online orderId: {$orderId} invoiceNo: {$walleyOrderId} "
        );

        $result = $this->administrationResultFactory->create();
        $result->setNewInvoiceNumber($uniq);
        return $result;
    }


    /**
     * Invoice an order offline
     *
     * @param \Magento\Sales\Model\Order $order
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function invoiceOrderOffline(
        \Magento\Sales\Model\Order $order
    ) {
        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        $this->logger->addInfo(
            "Invoice order offline orderId: {$order->getIncrementId()} qouteId: {$order->getQuoteId()} "
        );

        $this->transaction->create()->addInvoiceTransaction($invoice);
    }

    /**
     * Get order config
     *
     * @param string $orderId
     * @return \Webbhuset\CollectorCheckout\Config\Config
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getConfig(
        string $orderId
    ) {
        $order = $this->orderRepository->get($orderId);
        $config = $this->configFactory->create(['storeId' => (int)$order->getStoreId()]);

        return $config;
    }
}
