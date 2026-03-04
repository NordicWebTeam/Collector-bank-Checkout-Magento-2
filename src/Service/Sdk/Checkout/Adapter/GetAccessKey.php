<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Adapter;

use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Config\ConfigInterface;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Errors\RequestError;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Errors\ResponseError;

class GetAccessKey
{
    private string $baseUrl = 'https://api.walleypay.com';
    private string $baseTestUrl = 'https://api.uat.walleydev.com';
    private string $accessTokenPath = '/oauth2/v2.0/token/';
    private string $grantType = "client_credentials";
    private string $scopeUat = "705798e0-8cef-427c-ae00-6023deba29af/.default";
    private string $scopeProd = "a3f3019f-2be9-41cc-a254-7bb347238e89/.default";
    private ConfigInterface $config;

    public function __construct(
        ConfigInterface $config
    ) {
        $this->config = $config;
    }

    public function getAccessKey(): string
    {
        $clientId = $this->config->getClientId();
        $clientSecret = $this->config->getClientSecret();
        $grantType = $this->grantType;
        $scope = $this->scopeProd;
        if ($this->config->getIsTestModeOath()) {
            $scope = $this->scopeUat;
        }
        $requestBody = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => $grantType,
            'scope' => $scope,
        ];
        $response = $this->sendAccessKeyRequest($this->accessTokenPath, $requestBody);
        $responseBody = $this->extractBody($response);

        if (!isset($responseBody['access_token'])) {
            throw new ResponseError($requestBody, $response);
        }

        return $responseBody['access_token'];
    }

    /**
     * @param array<string, string> $body
     * @return array{header:string,body:string}
     */
    private function sendAccessKeyRequest(string $path, array $body): array
    {
        $url = $this->getBaseUrl() . $path;

        $ch = curl_init();
        $options = [
            CURLOPT_URL             => $url,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_ENCODING        => '',
            CURLOPT_HEADER          => true,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => false,
            CURLOPT_CONNECTTIMEOUT  => 30,
            CURLINFO_HEADER_OUT     => true,
            CURLOPT_POST            => true,
            CURLOPT_POSTFIELDS      => $body
        ];

        curl_setopt_array($ch, $options);
        $response   = curl_exec($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error      = curl_error($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($error) {
            throw new RequestError($body, 0, $error);
        }

        $header = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        return [
            'header' => $header,
            'body' => $responseBody,
        ];
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
     * @return array<string, mixed>
     */
    private function extractBody(array $response): array
    {
        $body = $response['body'] ?? '';
        $decodedBody = json_decode($body, true) ?: [];

        return $decodedBody;
    }
}
