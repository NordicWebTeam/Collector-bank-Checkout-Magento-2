<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Adapter;

use Magento\Framework\HTTP\Client\CurlFactory;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Config\ConfigInterface;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Errors\RequestError;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Errors\ResponseError;
use Webbhuset\CollectorCheckout\Service\Sdk\Payment\Invoice\Article\ArticleList;

class CurlWithAccessKey
    implements \Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Adapter\AdapterInterface
{
    private const HEADER_ACCEPTED = 'HTTP/1.1 202 Accepted';

    private string $baseUrl = 'https://api.walleypay.com';
    private string $baseTestUrl = 'https://api.uat.walleydev.com';
    private string $initializePath = '/checkouts';
    private string $updateCartPath = '/checkouts/{privateId}/cart';
    private string $updateFeesPath = '/checkouts/{privateId}/fees';
    private string $referencePath  = '/checkouts/{privateId}/reference';
    private string $acquireInfoPath = '/checkouts/{privateId}';
    private string $getOrderPath = '/manage/orders/{privateId}';
    private string $partActivatePath = '/manage/orders/{privateId}/capture';
    private string $partCreditPath = '/manage/orders/{privateId}/refund';
    private string $cancelInvoicePath = '/manage/orders/{privateId}/cancel';
    private string $reauthorizePath = '/manage/orders/{privateId}/reauthorize';
    private string $reauthorizeStatusPath = '/manage/orders/{privateId}/reauthorize';

    private ConfigInterface $config;
    private CurlFactory $curlFactory;

    public function __construct(
        ConfigInterface $config,
        CurlFactory $curlFactory
    ) {

        $this->config = $config;
        $this->curlFactory = $curlFactory;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(string $body, string $path, string $method, string $accessKey = "") : array
    {
        if ($accessKey === "") {
            $accessKey = $this->config->getAccessKey();
        }

        if ($method === 'GET') {
            return [
                'Authorization' => 'Bearer ' . $accessKey
            ];
        }

        $headers = [
            'Content-Type' => 'application/json',
            'charset' => 'utf-8',
            'Authorization' => 'Bearer ' . $accessKey,
        ];

        if ($body !== '') {
            $headers['Content-Length'] = (string) strlen($body);
        }

        return $headers;
    }

    public function getOrder(string $orderReference): array
    {
        $path = $this->getOrderPath;
        $path = $this->replacePathPrivate($path, $orderReference);

        $response = $this->sendRequest($path, '', 'GET');
        $responseBody = $this->extractBody($response);

        return $responseBody;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int,reauthorizationId:?string}
     */
    public function reauthorize(string $orderReference, array $payload):array
    {
        $path = $this->reauthorizePath;
        $path = $this->replacePathPrivate($path, $orderReference);

        $bodyJsonEncoded = json_encode($payload);
        $response = $this->sendRequest($path, $bodyJsonEncoded, 'POST');

        return [
            'status' => (int) $this->getStatusCodeFromResponse($response),
            'reauthorizationId' => $this->getReauthorizationIdFromResponse($response),
        ];

    }

    private function getReauthorizationIdFromResponse(array $response): ?string
    {
        if (empty($response['header'])) {
            return null;
        }

        $rawHeader = $response['header'];

        if (is_string($rawHeader)) {
            $headers = preg_split('/\r\n|\r|\n/', $rawHeader);

            if (count($headers) === 1) {
                $headers = preg_split('/(?=\s[A-Za-z0-9\-]+:)/', $rawHeader);
            }
        } else {
            $headers = (array)$rawHeader;
        }

        $location = null;
        foreach ($headers as $headerLine) {
            $headerLine = trim($headerLine);
            if (stripos($headerLine, 'Location:') === 0) {
                $location = trim(substr($headerLine, strlen('Location:')));
                break;
            }
        }

        if (!$location) {
            return null;
        }

        if (preg_match('#/reauthorize/([^/]+)$#i', $location, $matches)) {
            return $matches[1];
        }

        $parts = explode('/', trim($location, '/'));
        return end($parts) ?: null;
    }

    public function reauthorizeStatus(string $walleyOrderId, string $reauthorizeId): int
    {
        $path = $this->reauthorizeStatusPath;
        $path = $this->replacePathPrivate($path, $walleyOrderId) . '/' . $reauthorizeId;
        $response = $this->sendRequest($path);

        return (int) $this->getStatusCodeFromResponse($response);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function getStatusCodeFromResponse(array $response): int
    {
        $headerText = $response['header'];
        $statusLine = explode("\r\n", $headerText)[0]; // get the first line
        $parts = explode(' ', $statusLine); // split parts by space

        if (count($parts) < 2) {
            throw new \Exception('Invalid HTTP response header');
        }

        return (int) $parts[1];
    }

    public function getReauthorizeStatus(string $location): array
    {
        $response = $this->sendRequest($location, '', 'GET');
        $responseBody = $this->extractBody($response);

        return $responseBody;
    }

    public function partActivateInvoice(
        string $orderReference,
        ArticleList $articleList,
        string $correlationId
    ): string {
        $path = $this->replacePathPrivate($this->partActivatePath, $orderReference);
        $items = $this->convertArticleListToItems($articleList);

        $body = [
            'amount' => $this->getArticleListAmount($articleList),
            'actionReference' => $correlationId,
            'items' => $items,
        ];
        $bodyJsonEncoded = json_encode($body);
        $response = $this->sendRequest($path, $bodyJsonEncoded, 'POST');

        if (!$this->isResponseHeader202($response['status'])) {
            throw new ResponseError($body, $response);
        }

        return self::HEADER_ACCEPTED;
    }

    public function partCreditInvoice(
        string $orderReference,
        ArticleList $articleList,
        string $correlationId
    ): string {
        $path = $this->replacePathPrivate($this->partCreditPath, $orderReference);
        $items = $this->convertArticleListToItems($articleList);

        $body = [
            'amount' => $this->getArticleListAmount($articleList),
            'actionReference' => $correlationId,
            'items' => $items,
        ];
        $bodyJsonEncoded = json_encode($body);
        $response = $this->sendRequest($path, $bodyJsonEncoded, 'POST');

        if (isset($response['header'])
            && !$this->isResponseHeader202($response['status'])) {
            throw new ResponseError($body, $response);
        }

        return self::HEADER_ACCEPTED;
    }

    public function cancelInvoice(
        string $orderReference,
        ArticleList $articleList,
        string $correlationId
    ): string {
        $path = $this->replacePathPrivate($this->cancelInvoicePath, $orderReference);
        $items = $this->convertArticleListToItems($articleList);

        $body = [
            'amount' => $this->getArticleListAmount($articleList),
            'actionReference' => $correlationId,
            'items' => $items,
        ];
        $bodyJsonEncoded = json_encode($body);
        $response = $this->sendRequest($path, $bodyJsonEncoded, 'POST');

        if (isset($response['header'])
            && !$this->isResponseHeader202($response['header'])) {
            throw new ResponseError($body, $response);
        }

        return self::HEADER_ACCEPTED;
    }

    private function isResponseHeader202(int $status): bool
    {
        return (int) $status === 202;
    }

    private function replacePathPrivate(string $path, string $privateId) : string
    {
        $path = str_replace('{privateId}', $privateId, $path);

        return $path;
    }

    private function getArticleListAmount(ArticleList $articleList): float
    {
        $result = 0.0;
        $articleListArray = $articleList->getArticleList();
        foreach ($articleListArray as $article) {
            $result += $article['Quantity'] * $article['UnitPrice'];
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function convertArticleListToItems(ArticleList $articleList): array
    {
        $result = [];
        $articleListArray = $articleList->getArticleList();
        foreach ($articleListArray as $article) {
            $result[] = [
                'id' => $article['ArticleId'],
                'description' => $article['Description'],
                'quantity' => $article['Quantity'],
                'unitPrice' => $article['UnitPrice'],
                'type' => $article['Type'],
                'vat' => $article['VAT'],
            ];
        }

        return $result;
    }

    /**
     * Initialize checkout
     */
    public function initializeCheckout(array $data) : array
    {
        $path = $this->initializePath;
        $body = json_encode($data);

        $response = $this->sendRequest($path, $body, 'POST');
        $responseBody = $this->extractBody($response);

        if (!$this->validateResponse($responseBody)) {
            throw new ResponseError($body, $response);
        }

        return $responseBody;
    }

    public function updateCart(array $data, string $privateId) : array
    {
        $path = $this->updateCartPath;
        $path = $this->replacePathPrivate($path, $privateId);
        $body = json_encode($data);

        $response = $this->sendRequest($path, $body, 'PUT');
        $responseBody = $this->extractBody($response);

        if (!$this->validateResponse($responseBody)) {
            throw new ResponseError($body, $response);
        }

        return $responseBody;
    }

    public function updateFees(array $data, string $privateId) : array
    {
        $path = $this->updateFeesPath;
        $path = $this->replacePathPrivate($path, $privateId);
        $body = json_encode($data);

        $response = $this->sendRequest($path, $body, 'PUT');
        $responseBody = $this->extractBody($response);

        if (!$this->validateResponse($responseBody)) {
            throw new ResponseError($body, $response);
        }

        return $responseBody;
    }

    public function setOrderReference(string $reference, string $privateId) : array
    {
        $path = $this->referencePath;
        $path = $this->replacePathPrivate($path, $privateId);
        $data = [
            'Reference' => $reference,
        ];

        $body = json_encode($data);

        $response = $this->sendRequest($path, $body, 'PUT');
        $responseBody = $this->extractBody($response);

        if (!$this->validateResponse($responseBody)) {
            throw new ResponseError($body, $response);
        }

        return $responseBody;
    }

    public function acquireInformation(string $privateId) : array
    {
        $path = $this->acquireInfoPath;
        $path = $this->replacePathPrivate($path, $privateId);

        $response = $this->sendRequest($path, '', 'GET');
        $responseBody = $this->extractBody($response);

        if (!$this->validateResponse($responseBody)) {
            throw new ResponseError('', $response);
        }

        return $responseBody;
    }

    /**
     * @return array{header:string,body:string,status:int}
     */
    private function sendRequest(string $path, string $body = '', string $method = 'GET'): array
    {
        $url = $this->getBaseUrl() . $path;
        $headers = $this->getHeaders($body, $path, $method);
        $curl = $this->curlFactory->create();
        $curl->setHeaders($headers);
        $curl->setOptions([
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CONNECTTIMEOUT => 30,
        ]);

        try {
            if ($method === 'PUT') {
                $curl->setOption(CURLOPT_CUSTOMREQUEST, 'PUT');
                $curl->post($url, $body);
            } elseif ($method === 'POST') {
                $curl->post($url, $body);
            } else {
                $curl->get($url);
            }
        } catch (\Exception $exception) {
            throw new RequestError($body, 0, $exception->getMessage());
        }

        $httpCode = (int) $curl->getStatus();
        $responseBody = (string) $curl->getBody();
        $header = $this->buildRawHeaders($httpCode, (array) $curl->getHeaders());

        return [
            'header' => $header,
            'body' => $responseBody,
            'status' => $httpCode,
        ];
    }

    private function buildRawHeaders(int $statusCode, array $headers): string
    {
        $headerLines = ["HTTP/1.1 {$statusCode}"];
        foreach ($headers as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $singleValue) {
                    $headerLines[] = $name . ': ' . $singleValue;
                }
                continue;
            }

            $headerLines[] = $name . ': ' . $value;
        }

        return implode("\r\n", $headerLines);
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function extractBody(array $response): array
    {
        $body = $response['body'] ?? '';
        $decodedBody = json_decode($body, true) ?: [];

        return $decodedBody;
    }

    public function getBaseUrl(): string
    {
        if ($this->config->getIsTestMode()) {
            return $this->baseTestUrl;
        }

        return $this->baseUrl;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function validateResponse(array $response) : bool
    {
        if (
            is_array($response)
            && array_key_exists('error', $response)
            && empty($response['error'])
        ) {
            // good result should have 'error' => null
            return true;
        }

        return false;
    }
}
