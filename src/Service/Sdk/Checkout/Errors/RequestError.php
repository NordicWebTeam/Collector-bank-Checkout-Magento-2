<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Errors;

class RequestError extends \Exception
{
    /**
     * @var mixed
     */
    private $request;

    public function __construct($request, int $code, string $message)
    {
        $this->request = $request;

        parent::__construct($message, $code);
    }

    /**
     * @return mixed
     */
    public function getRequest()
    {
        return $this->request;
    }
}
