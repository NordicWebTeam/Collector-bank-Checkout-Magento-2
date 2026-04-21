<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Payment\Invoice;

use Webbhuset\CollectorCheckout\Service\Sdk\Payment\Adapter\AdapterInterface as AdapterInterface;

class Administration
{
    private AdapterInterface $adapter;
    /**
     * @var array<int, string>
     */
    private array $invoiceStatusCodes =
        [
            0 => 'On hold',
            1 => 'Preliminary',
            2 => 'Canceled',
            3 => 'Delivered',
            4 => 'Expired',
            5 => 'Rejected',
            6 => 'Signing',
            7 => 'Strong customer verification'
        ];

    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    public function partCreditInvoice(
        string $invoiceNo,
        \Webbhuset\CollectorCheckout\Service\Sdk\Payment\Invoice\Article\ArticleList $articleList,
        int $correlationId = 0
    ): array {
        $data = [
            'CorrelationId' => $correlationId,
            'CreditDate'    => date("Y-m-d"),
            'InvoiceNo'     => $invoiceNo,
            'ArticleList'   => $articleList->getArticleList()
        ];

        $response = $this->adapter->partCreditInvoice($data);

        return $response;
    }

    public function partActivateInvoice(
        string $invoiceNo,
        \Webbhuset\CollectorCheckout\Service\Sdk\Payment\Invoice\Article\ArticleList $articleList,
        int $correlationId = 0
    ): array {
        $data = [
            'CorrelationId' => $correlationId,
            'InvoiceNo'     => $invoiceNo,
            'ArticleList'   => $articleList->getArticleList()
        ];
        $idempotencyKey = $invoiceNo;

        $response = $this->adapter->partActivateInvoice($data, $idempotencyKey);

        return $response;
    }

    public function adjustInvoice(
        string $invoiceNo,
        array $invoiceRows,
        int $correlationId = 0
    ): array {
        $data = [
            'CorrelationId' => $correlationId,
            'InvoiceNo'     => $invoiceNo,
            'InvoiceRows'   => $invoiceRows
        ];
        $response = $this->adapter->adjustInvoice($data);

        return $response;
    }

    public function activateInvoice(string $invoiceNo, int $correlationId = 0): array
    {
        $data = [
            'CorrelationId' => $correlationId,
            'InvoiceNo' => $invoiceNo
        ];
        $response = $this->adapter->activateInvoice($data);

        return $response;
    }

    public function cancelInvoice(string $invoiceNo, int $correlationId = 0): array
    {
        $data = [
            'CorrelationId' => $correlationId,
            'InvoiceNo' => $invoiceNo
        ];
        $response = $this->adapter->cancelInvoice($data);

        return $response;
    }

    public function creditInvoice(string $invoiceNo, int $correlationId = 0): array
    {
        $data = [
            'CorrelationId' => $correlationId,
            'InvoiceNo' => $invoiceNo,
            'CreditDate' => date('c')
        ];
        $response = $this->adapter->creditInvoice($data);

        return $response;
    }

    public function getInvoiceInformation(string $invoiceNo, string $clientIpAddress, int $correlationId = 0): array
    {
        $data = [
            'CorrelationId' => $correlationId,
            'InvoiceNo' => $invoiceNo,
            'ClientIpAddress' => $clientIpAddress
        ];
        $response = $this->adapter->getInvoiceInformation($data);

        if (isset($response['Status'])) {
            $statusCode = (int)$response['Status'];
            $response['StatusText'] = $this->invoiceStatusCodes[$statusCode];
        }

        return $response;
    }
}
