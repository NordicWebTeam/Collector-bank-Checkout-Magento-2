<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Adapter;

interface AdapterInterface
{
    public function __construct(
        \Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Config\ConfigInterface $config,
        \Magento\Framework\HTTP\Client\CurlFactory $curlFactory
    );

    public function initializeCheckout(array $data) : array;

    public function updateCart(array $data, string $privateId) : array;

    public function updateFees(array $data, string $privateId) : array;

    public function setOrderReference(string $reference, string $privateId) : array;

    public function acquireInformation(string $privateId) : array;
}
