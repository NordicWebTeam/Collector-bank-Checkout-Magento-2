<?php
declare(strict_types=1);



namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout;

use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Errors\ValidationError;

class Status
{
    const INITIALIZED           = 'Initialized';
    const CUSTOMER_IDENTIFIED   = 'CustomerIdentified';
    const COMMITTED_TO_PURCHASE = 'CommittedToPurchase';
    const PURCHASE_COMPLETED    = 'PurchaseCompleted';
    const SESSION_ABORTED       = 'Aborted';

    private $status;
    public $validStatuses = [
        self::INITIALIZED,
        self::CUSTOMER_IDENTIFIED,
        self::COMMITTED_TO_PURCHASE,
        self::PURCHASE_COMPLETED,
        self::SESSION_ABORTED,
    ];

    public function __construct(
        string $status
    ) {
        if (!in_array($status, $this->validStatuses)) {
            $validStatusesString = implode(', ', $this->validStatuses);
            $msg = "Status \"{$status}\" must be one of {$validStatusesString}";
            throw new ValidationError($msg);
        }

        $this->status = $status;
    }

    public function getStatus()
    {
        return $this->status;
    }
}
