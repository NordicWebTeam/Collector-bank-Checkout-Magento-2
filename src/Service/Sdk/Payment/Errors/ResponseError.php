<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Payment\Errors;

class ResponseError extends \Exception
{
    private string $request;
    private \SoapFault $soapError;

    public function __construct(
        \SoapFault $soapError,
        string $request,
        int $errorCode,
        string $errorString
    ) {
        $this->soapError = $soapError;
        $this->request = $request;

        parent::__construct($errorString, $errorCode);
    }

    /**
     *
     * Returns the SOAP request sent which resulted in an exception
     *
     * @return string SOAP request sent
     */
    public function getRequest(): string
    {
        return $this->request;
    }

    /**
     *
     * Returns the SoapFault exception to be used for debugging
     *
     * @return \SoapFault
     */
    public function getSoapError(): \SoapFault
    {
        return $this->soapError;
    }
}
