<?php

namespace Webbhuset\CollectorCheckout\Controller\Update;

/**
 * Class Index
 *
 * @package Webbhuset\CollectorCheckout\Controller\Update
 */
class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \Webbhuset\CollectorCheckout\Service\Quote\SynchronizeByPublicToken
     */
    protected $synchronizeByPublicToken;

    /**
     * @var \Webbhuset\CollectorCheckout\Logger\Logger
     */
    protected $logger;

    /**
     * @var \Webbhuset\CollectorCheckout\Helper\BuildShippingUpdateResponse
     */
    protected $buildShippingUpdateResponse;

    /**
     * Index constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Webbhuset\CollectorCheckout\Service\Quote\SynchronizeByPublicToken $synchronizeByPublicToken
     * @param \Webbhuset\CollectorCheckout\Helper\BuildShippingUpdateResponse $buildShippingUpdateResponse
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Webbhuset\CollectorCheckout\Logger\Logger $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Webbhuset\CollectorCheckout\Service\Quote\SynchronizeByPublicToken $synchronizeByPublicToken,
        \Webbhuset\CollectorCheckout\Helper\BuildShippingUpdateResponse $buildShippingUpdateResponse,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Webbhuset\CollectorCheckout\Logger\Logger $logger
    ) {
        parent::__construct($context);

        $this->synchronizeByPublicToken = $synchronizeByPublicToken;
        $this->buildShippingUpdateResponse = $buildShippingUpdateResponse;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        $publicToken = (string)$this->getRequest()->getParam('publicToken');
        $eventName = $this->getRequest()->getParam('event');
        $quote = $this->synchronizeByPublicToken->execute($publicToken, $eventName);

        if (null === $quote || !$quote->getId()) {
            $result->setHttpResponseCode(404);
            $this->logger->addCritical(
                "Quote updater controller - Quote not found quoteId: $publicToken event: $eventName",
                $this->getRequest()
            );
            return $result->setData(['message' => __('Quote not found')]);
        }

        $result->setData($this->buildShippingUpdateResponse->execute($quote));

        return $result;
    }
}
