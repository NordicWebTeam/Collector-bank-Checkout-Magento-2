<?php
declare(strict_types=1);



namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Purchase;

use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Errors\ValidationError;

class Result
{
    // The invoice is pending and waiting for activation by Merchant.
    const PRELIMINARY = 'Preliminary';
    //The purchase is under fraud investigation. Once the status changes on the invoice, a notification will be sent.
    const ON_HOLD = 'OnHold';
    //The invoice has been automatically activated.
    const ACTIVATED = 'Activated';
    // The invoice has been rejected and cannot be activated.
    const REJECTED = 'Rejected';
    // The invoice is waiting for electronic signing of a credit agreement by the end customer. Once the status changes on the invoice, a notification will be sent.
    const SIGNING = 'Signing';
    // The invoice has been completed. This normally means swish payments
    const COMPLETED = 'Completed';

    private $result;
    public $validResults = [
        self::PRELIMINARY,
        self::ON_HOLD,
        self::ACTIVATED,
        self::REJECTED,
        self::SIGNING,
        self::COMPLETED
    ];

    public function __construct(
        string $result
    ) {
        if (!in_array($result, $this->validResults)) {
            $validResultsString = implode(', ', $this->validResults);
            $msg = "Status \"{$result}\" must be one of {$validResultsString}";
            throw new \ValidationError($msg);
        }

        $this->result = $result;
    }

    public function getResult()
    {
        return $this->result;
    }
}
