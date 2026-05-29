<?php

namespace Webbhuset\CollectorCheckout\Controller\Reinit;

use Magento\Framework\Exception\NoSuchEntityException;
use Webbhuset\CollectorCheckout\Adapter;
use Webbhuset\CollectorCheckout\Config\ConfigFactory;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Status;

class Index extends \Magento\Framework\App\Action\Action
{
    protected $resultJsonFactory;
    protected $checkoutSession;
    protected $quoteRepository;
    protected $quoteCollection;

    /**
     * @var ConfigFactory
     */
    private ConfigFactory $configFactory;

    /**
     * @var \Webbhuset\CollectorCheckout\Checkout\Order\ManagerFactory
     */
    private $orderManager;

    private Adapter $adapter;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Webbhuset\CollectorCheckout\Checkout\Order\ManagerFactory $orderManager,
        \Webbhuset\CollectorCheckout\Config\ConfigFactory $configFactory,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Model\ResourceModel\Quote\Collection $quoteCollection,
        Adapter $adapter
    ) {
        parent::__construct($context);

        $this->resultJsonFactory = $resultJsonFactory;
        $this->checkoutSession   = $checkoutSession;
        $this->quoteRepository   = $quoteRepository;
        $this->quoteCollection   = $quoteCollection;
        $this->configFactory     = $configFactory;
        $this->orderManager      = $orderManager;
        $this->adapter           = $adapter;
    }

    public function execute()
    {
        $publicId = $this->getRequest()->getParam('publicId');

        $quote = $this->quoteCollection->addFieldToFilter('collectorbank_public_id', $publicId)
            ->setOrder('entity_id', 'DESC')
            ->getFirstItem();

        if (!$quote->getId()) {
            return $this->createResult(
                'Could not find quote',
                404,
                false
            );
        }

        try {
            $order = $this->orderManager->create()->getOrderByPublicToken($publicId);
            $config = $this->configFactory->create(['storeId' => $quote->getStoreId()]);
            $acknowledged  = $config->getOrderStatusAcknowledged();
            if ($order->getStatus() == $acknowledged) {
                return $this->createResult(
                    'Quote not restored',
                    200,
                    false
                );
            }
        } catch (NoSuchEntityException $e) {

        }

        $checkoutData = $this->adapter->acquireCheckoutInformationFromQuote($quote);
        if ($checkoutData->getStatus()->getStatus() === Status::PURCHASE_COMPLETED) {
            return $this->createResult(
                'Quote not restored',
                200,
                false
            );
        }
        if ($quote->getIsActive()) {
            return $this->createResult(
                'Quote not restored',
                200,
                true
            );
        }

        $quote->setIsActive(1)->setReservedOrderId(null);

        $this->quoteRepository->save($quote);
        $this->checkoutSession->replaceQuote($quote)
            ->unsLastRealOrderId();

        return $this->createResult(
            'Quote restored',
            200,
            true
        );
    }

    public function createResult($message, $httpResponseCode, $isReinited)
    {
        $jsonResult = $this->resultJsonFactory->create();

        $response = [
            'title' => __($message),
            'reinitialized' => $isReinited,
        ];
        $jsonResult->setHttpResponseCode($httpResponseCode);
        $jsonResult->setHeader("Content-Type", "application/json", true);
        $jsonResult->setData($response);

        return $jsonResult;
    }
}
