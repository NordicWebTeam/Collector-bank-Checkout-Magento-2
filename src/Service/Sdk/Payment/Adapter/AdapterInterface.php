<?php
declare(strict_types=1);

/**
 *
Production environment API URL's
InvoiceService
The main API used for invoice handling (purchases, activations, returns etc.)
https://ecommerce.collector.se/v3.0/InvoiceServiceV33.svc

InformationService
API used for account & transaction information on existing invoices and customers
https://eCommerce.collector.se/v3.0/informationservicev2.svc


Test environment API URL's
InvoiceService
The main API used for invoice handling (purchases, activations, returns etc.)
https://eCommerceTest.collector.se/v3.0/InvoiceServiceV33.svc

InformationService
API used for account & transaction information on existing invoices and customers
https://eCommerceTest.collector.se/v3.0/informationservicev2.svc


 */

namespace Webbhuset\CollectorCheckout\Service\Sdk\Payment\Adapter;

use Webbhuset\CollectorCheckout\Service\Sdk\Payment\Config\ConfigInterface as ConfigInterface;
use Webbhuset\CollectorCheckout\Service\Sdk\Payment\Errors\ResponseError;

interface AdapterInterface
{
    public function __construct(ConfigInterface $config);

    /**
     *
     * Activates the invoice. Returns response if success otherwise throws ResponseError
     *
     * @throws ResponseError
     * @param array $data
     * @return array $response
     */
    public function activateInvoice(array $data): array;

    /**
     *
     * Cancel the invoice. Returns response if success otherwise throws ResponseError
     *
     * @throws ResponseError
     * @param array $data
     * @return array $response
     */
    public function cancelInvoice(array $data): array;

    /**
     *
     * Credits the invoice. Returns response if success otherwise throws ResponseError
     *
     * @throws ResponseError
     * @param array $data
     * @return array $response
     */
    public function creditInvoice(array $data): array;

    /**
     *
     * Gets the status of the the invoice. Returns response if success otherwise throws ResponseError
     *
     * @throws ResponseError
     * @param array $data
     * @return array $response
     */
    public function getInvoiceInformation(array $data) :array;

    /**
     * Part activate the invoice. . Returns response if success otherwise throws ResponseError
     *
     * @param array $data
     * @param array $idempotencyKey
     * @return array
     */
    public function partActivateInvoice(array $data, $idempotencyKey = ""):array;

    /**
     * Part credit the invoice. . Returns response if success otherwise throws ResponseError
     *
     * @param array $data
     * @return array
     */
    public function partCreditInvoice(array $data):array;

    /**
     * Adjust the invoice. . Returns response if success otherwise throws ResponseError
     *
     * @param array $data
     * @return array
     */
    public function adjustInvoice(array $data):array;
}
