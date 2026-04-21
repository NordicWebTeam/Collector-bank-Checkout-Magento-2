<?php

namespace Webbhuset\CollectorCheckout\Controller\Validation;

/**
 * Validation Controller
 *
 * @package Webbhuset\CollectorCheckout\Controller\Validation
 */
class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $jsonResult;

    /**
     * @var \Webbhuset\CollectorCheckout\Service\Validation\ProcessReference
     */
    protected $processReferenceService;

    /**
     * Index constructor.
     *
     * @param \Magento\Framework\App\Action\Context                             $context
     * @param \Magento\Framework\Controller\Result\JsonFactory                  $jsonResult
     * @param \Webbhuset\CollectorCheckout\Service\Validation\ProcessReference $processReferenceService
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $jsonResult,
        \Webbhuset\CollectorCheckout\Service\Validation\ProcessReference $processReferenceService
    ) {
        $this->jsonResult      = $jsonResult;
        $this->processReferenceService = $processReferenceService;

        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $reference = $this->getRequest()->getParam('reference');
        $jsonResult = $this->jsonResult->create();
        $result = $this->processReferenceService->execute((string) $reference);

        $jsonResult->setHeader("Content-Type", "application/json", true);
        $jsonResult->setHttpResponseCode($result->getHttpCode());
        $jsonResult->setData($result->getData());

        return $jsonResult;
    }
}
