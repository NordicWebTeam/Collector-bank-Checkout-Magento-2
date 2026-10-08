<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher;

use Webbhuset\CollectorCheckout\Model\Voucher\Storage\VoucherRecord;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\AppliedVoucher;

/**
 * Compares the vouchers stored on an order with the vouchers Walley reports.
 */
class VoucherComparison
{
    private const AMOUNT_TOLERANCE = 0.005;

    /**
     * @param VoucherRecord[] $stored
     * @param AppliedVoucher[] $applied
     * @return string[] Human readable differences, empty when they match
     */
    public function getDifferences(array $stored, array $applied): array
    {
        $storedById = [];
        foreach ($stored as $record) {
            $storedById[$record->getVoucherId()] = $record;
        }
        $appliedById = [];
        foreach ($applied as $voucher) {
            $appliedById[$voucher->getVoucherId()] = $voucher;
        }

        $differences = [];
        foreach ($storedById as $voucherId => $record) {
            $voucher = $appliedById[$voucherId] ?? null;
            if (!$voucher) {
                $differences[] = sprintf(
                    'Voucher %s (%s) is on the order but no longer applied at Walley',
                    $record->getCode(),
                    $voucherId
                );
            } elseif (abs($voucher->getDiscountAmount() - $record->getFaceAmount()) > self::AMOUNT_TOLERANCE) {
                $differences[] = sprintf(
                    'Voucher %s (%s) changed amount from %.2f to %.2f',
                    $record->getCode(),
                    $voucherId,
                    $record->getFaceAmount(),
                    $voucher->getDiscountAmount()
                );
            }
        }
        foreach ($appliedById as $voucherId => $voucher) {
            if (!isset($storedById[$voucherId])) {
                $differences[] = sprintf(
                    'Voucher %s (%s) is applied at Walley but not on the order',
                    $voucher->getCode(),
                    $voucherId
                );
            }
        }

        return $differences;
    }
}
