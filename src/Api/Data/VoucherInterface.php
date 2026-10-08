<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Api\Data;

/**
 * Voyado voucher applied to an order in Walley Checkout. Amounts are incl. VAT.
 *
 * @api
 */
interface VoucherInterface
{
    /**
     * Walley voucher id
     *
     * @return string
     */
    public function getVoucherId(): string;

    /**
     * Voucher code, also the article number of the voucher row at Walley
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * @return string
     */
    public function getDescription(): string;

    /**
     * Voucher face value as reported by Walley
     *
     * @return float
     */
    public function getFaceAmount(): float;

    /**
     * Amount deducted from the order, in order currency
     *
     * @return float
     */
    public function getAmount(): float;

    /**
     * Amount deducted from the order, in base currency
     *
     * @return float
     */
    public function getBaseAmount(): float;
}
