<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout;

/**
 * A Voyado voucher applied by the customer in Walley Checkout
 * (data.appliedVouchers in the checkout information).
 *
 * The discount amount is the voucher's face value incl. VAT. It can be higher
 * than what is actually deducted, when the vouchers exceed the order total.
 */
class AppliedVoucher
{
    private string $voucherId;
    private string $code;
    private string $description;
    private float $discountAmount;

    public function __construct(
        string $voucherId,
        string $code,
        string $description,
        float $discountAmount
    ) {
        $this->voucherId = $voucherId;
        $this->code = $code;
        $this->description = $description;
        $this->discountAmount = $discountAmount;
    }

    public function getVoucherId(): string
    {
        return $this->voucherId;
    }

    /**
     * Voucher code, also used as article number for the voucher row in the
     * Walley Payment API order. Kept as string to preserve leading zeros.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getDiscountAmount(): float
    {
        return $this->discountAmount;
    }

    /**
     * @return array{voucherId: string, code: string, description: string, discountAmount: float}
     */
    public function toArray(): array
    {
        return [
            'voucherId' => $this->voucherId,
            'code' => $this->code,
            'description' => $this->description,
            'discountAmount' => $this->discountAmount,
        ];
    }
}
