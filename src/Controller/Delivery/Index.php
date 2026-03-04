<?php

namespace Webbhuset\CollectorCheckout\Controller\Delivery;

use Magento\Framework\Api\SimpleDataObjectConverter;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\ShippingMethodManagementInterface;
use Webbhuset\CollectorCheckout\Checkout\Quote\Manager;
use Webbhuset\CollectorCheckout\Logger\Logger;
use Webbhuset\CollectorCheckout\Shipment\ConvertToShipment;
use Webbhuset\CollectorCheckout\Shipment\GetIconForShippingMethod;

/**
 * Class Index
 *
 * @package Webbhuset\CollectorCheckout\Controller\Update
 */
class Index extends Action
{

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var Manager
     */
    private $quoteManager;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var ShippingMethodManagementInterface
     */
    private $shippingMethodManagement;

    /**
     * @var ConvertToShipment
     */
    private $convertToShipment;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    public function __construct(
        Context $context,
        Manager $quoteManager,
        ShippingMethodManagementInterface $shippingMethodManagement,
        ConvertToShipment $convertToShipment,
        RequestInterface $request,
        CartRepositoryInterface $cartRepository,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);

        $this->resultJsonFactory = $resultJsonFactory;
        $this->quoteManager = $quoteManager;
        $this->request = $request;
        $this->shippingMethodManagement = $shippingMethodManagement;
        $this->convertToShipment = $convertToShipment;
        $this->cartRepository = $cartRepository;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $privateId = $this->request->getParam('privateId');
        try {
            $quote = $this->quoteManager->getQuoteByPrivateId($privateId);
            $shippingAddress = $quote->getShippingAddress();
            $postalCode = $this->request->getParam('postalCode');
            if ($postalCode) {
                $shippingAddress->setPostcode($postalCode);
            }
            $countryCode = $this->request->getParam('countryCode');
            if ($countryCode) {
                $shippingAddress->setCountryId($countryCode);
            }
            $shippingMethods = $this->shippingMethodManagement->estimateByExtendedAddress(
                $quote->getId(),
                $quote->getShippingAddress()
            );
            if ($countryCode || $postalCode) {
                $this->cartRepository->save($quote);
            }

            $shipment = $this->convertToShipment->execute($shippingMethods, $quote);
        } catch (NoSuchEntityException $e) {
            $shipment = [];
        }
        $result->setData($shipment);
        $result->setHeader('Expires', date('Y-m-d H:i:s', strtotime('+ 1 day')));

        return $result;
    }
}
