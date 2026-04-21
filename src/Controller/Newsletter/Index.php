<?php

namespace Webbhuset\CollectorCheckout\Controller\Newsletter;

/**
 * Class Index
 *
 * @package Webbhuset\CollectorCheckout\Controller\Newsletter
 */
class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Webbhuset\CollectorCheckout\Service\Newsletter\UpdateQuoteSubscription
     */
    protected $updateQuoteSubscription;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * Index constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Webbhuset\CollectorCheckout\Service\Newsletter\UpdateQuoteSubscription $updateQuoteSubscription
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Webbhuset\CollectorCheckout\Service\Newsletter\UpdateQuoteSubscription $updateQuoteSubscription,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->updateQuoteSubscription = $updateQuoteSubscription;
        $this->resultJsonFactory = $resultJsonFactory;

        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        $subscribe = (string)$this->getRequest()->getParam('subscribe');
        $this->updateQuoteSubscription->execute($subscribe);

        $result = $this->resultJsonFactory->create();
        $result->setData(
            [
                'newsletter' => $subscribe
            ]
        );

        return $result;
    }
}
