<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Errors;

class ResponseError extends \Exception
{
    /**
     * @var mixed
     */
    private $request;
    /**
     * @var array<string, mixed>
     */
    private $response;

    public function __construct($request, array $response)
    {
        $this->request = $request;
        $this->response = $response;

        $responseData = $this->getResponseBody();

        $message = isset($responseData['error']['message'])
            ? $responseData['error']['message']
            : 'Something went wrong with the request';

        $code = isset($responseData['error']['code'])
            ? $responseData['error']['code']
            : 1;

        parent::__construct($message, $code);
    }

    public function getErrors() : array
    {
        $response = $this->getResponse();
        if (isset($response['error']['errors'])) {
            return $response['error']['errors'];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponse(): array
    {
        return $this->response;
    }


    public function getErrorLogMessageFromResponse(): string
    {
        $errorLogMessage = "";
        $errorLogMessage .= $this->getMessage();
        $responseBody = $this->getResponseBody();

        if (isset($responseBody['error']['errors'][0]['reason'])
            && isset($responseBody['error']['errors'][0]['message'])
        ) {
            $reason = $responseBody['error']['errors'][0]['reason'];
            $message = $responseBody['error']['errors'][0]['message'];

            $errorLogMessage .= " $reason: $message";
        }

        return $errorLogMessage;
    }


    /**
     * @return array<string, mixed>
     */
    public function getResponseBody(): array
    {
        $body = $this->response['body'] ?? '';
        $decodedBody = json_decode($body, true) ?: [];

        return $decodedBody;
    }

    /**
     * @return mixed
     */
    public function getRequest()
    {
        return $this->request;
    }
}
