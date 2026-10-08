<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout;

use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Errors\ValidationError;

/**
 * Parses data.appliedVouchers from the Walley checkout information.
 *
 * Invalid voucher data throws instead of being skipped: a skipped voucher
 * would create the Magento order with a different total than the customer
 * paid in Walley.
 */
class AppliedVoucherParser
{
    private const FIELD = 'appliedVouchers';

    /**
     * @param array<string, mixed> $data The "data" part of the checkout information response
     * @return AppliedVoucher[]
     * @throws ValidationError
     */
    public function fromResponseData(array $data): array
    {
        if (!array_key_exists(self::FIELD, $data) || $data[self::FIELD] === null) {
            return [];
        }
        if (!is_array($data[self::FIELD])) {
            throw new ValidationError(self::FIELD . ' must be a list');
        }

        $vouchers = [];
        foreach (array_values($data[self::FIELD]) as $index => $voucherData) {
            $voucher = $this->parseVoucher($voucherData, $index);
            if (isset($vouchers[$voucher->getVoucherId()])) {
                throw new ValidationError("Duplicate voucherId {$voucher->getVoucherId()} in " . self::FIELD);
            }
            $vouchers[$voucher->getVoucherId()] = $voucher;
        }

        return array_values($vouchers);
    }

    /**
     * @param mixed $voucherData
     * @throws ValidationError
     */
    private function parseVoucher($voucherData, int $index): AppliedVoucher
    {
        if (!is_array($voucherData)) {
            throw new ValidationError(self::FIELD . "[$index] must be an object");
        }

        return new AppliedVoucher(
            $this->requireString($voucherData, 'voucherId', $index),
            $this->requireString($voucherData, 'code', $index),
            isset($voucherData['description']) ? (string) $voucherData['description'] : '',
            $this->requirePositiveAmount($voucherData, 'discountAmount', $index)
        );
    }

    /**
     * @param array<string, mixed> $voucherData
     * @throws ValidationError
     */
    private function requireString(array $voucherData, string $field, int $index): string
    {
        $value = $voucherData[$field] ?? null;
        if (!is_scalar($value) || trim((string) $value) === '') {
            throw new ValidationError(self::FIELD . "[$index].$field is missing");
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed> $voucherData
     * @throws ValidationError
     */
    private function requirePositiveAmount(array $voucherData, string $field, int $index): float
    {
        $value = $voucherData[$field] ?? null;
        if (!is_numeric($value) || (float) $value <= 0) {
            throw new ValidationError(self::FIELD . "[$index].$field must be a positive number");
        }

        return (float) $value;
    }
}
