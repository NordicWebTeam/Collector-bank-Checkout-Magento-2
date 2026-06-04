<?php

namespace Webbhuset\CollectorCheckout\Controller\Success;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Webbhuset\CollectorCheckout\Checkout\Order\ManagerFactory;
use Webbhuset\CollectorCheckout\Logger\Logger;
use Webbhuset\CollectorCheckout\Service\Checkout\Storage;

/**
 * Class Index
 *
 * @package Webbhuset\CollectorCheckout\Controller\Success
 */
class Index extends Action
{
    /**
     * @var PageFactory
     */
    protected $pageFactory;

    /**
     * @var ManagerFactory
     */
    protected $orderManager;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var Storage
     */
    private Storage $storage;

    /**
     * Index constructor.
     *
     * @param Context                 $context
     * @param ManagerFactory          $orderManager
     * @param PageFactory             $pageFactory
     * @param Logger                  $logger
     * @param CartRepositoryInterface $quoteRepository
     * @param CheckoutSession         $checkoutSession
     * @param Storage                 $storage
     */
    public function __construct(
        Context $context,
        ManagerFactory $orderManager,
        PageFactory $pageFactory,
        Logger $logger,
        CartRepositoryInterface $quoteRepository,
        CheckoutSession $checkoutSession,
        Storage $storage
    ) {
        $this->pageFactory      = $pageFactory;
        $this->orderManager     = $orderManager;
        $this->logger           = $logger;
        $this->quoteRepository  = $quoteRepository;
        $this->checkoutSession  = $checkoutSession;
        $this->storage = $storage;

        parent::__construct($context);
    }

    /**
     * Execute success page controller action
     *
     * Loads the order by the reference token from the URL, updates the checkout
     * session with order information, and renders the success page
     *
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        $reference = $this->getRequest()->getParam('reference');
        $orderManager = $this->orderManager->create();

        $page = $this->pageFactory->create();
        try {
            $order = $orderManager->getOrderByPublicToken($reference);
            $quoteId = $order->getQuoteId();
            $quote = $this->quoteRepository->get($quoteId);
            if ($quote->getIsActive()) {
                $quote->setIsActive(0);
                $this->quoteRepository->save($quote);
            }

            $orderId = $order->getEntityId();
            $incrementOrderId = $order->getIncrementId();

            if (!$this->checkoutSession->getLastOrderId()) {
                $this->checkoutSession
                    ->setLastQuoteId($quoteId)
                    ->setLastSuccessQuoteId($quoteId)
                    ->setLastOrderId($orderId)
                    ->setLastRealOrderId($incrementOrderId)
                    ->setLastOrderStatus($order->getStatus());
            }

            $this->checkoutSession->setData('collectorbank_success_public_id', (string)$reference);
        } catch (NoSuchEntityException $e) {
            $this->logger->addCritical(
                "Failed to load success page - Could not open order by publicToken: $reference. "
                . $e->getMessage()
            );
            return $page;
        }

        $this->storage->setPublicToken((string)$reference);
        $this->storage->setSuccessOrder($order);
        return $page;
    }
}
