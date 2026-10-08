<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher\Walley;

use Webbhuset\CollectorCheckout\Gateway\Config;
use Webbhuset\CollectorCheckout\Model\Voucher\VoucherAllocator;

/**
 * Builds the rows sent to Walley for voucher orders, which are captured with
 * replaceItems: per line a product row, and per unit a rule discount row and a
 * voucher row. A rounding row makes a capture equal the invoice total.
 *
 * Walley matches refunds exactly on id, description and unit price against
 * the captured rows, so refunds reuse the captured unit prices.
 */
class VoucherRowCalculator
{
    public const ROUNDING_ID = Config::CURRENCY_ROUNDING_SKU;
    public const ROUNDING_DESCRIPTION = 'Currency rounding';
    public const DISCOUNT_DESCRIPTION = 'Discount';

    private const MAX_LENGTH = 50;

    /**
     * @param CaptureLine[] $lines
     * @param float $total The invoice grand total the capture must equal
     * @return WalleyRow[]
     */
    public function captureRows(array $lines, float $total): array
    {
        $rows = [];
        foreach ($lines as $line) {
            $rows = array_merge($rows, $this->lineRows($line, $line->getQuantity()));
        }

        $rounding = round($total - $this->getTotal($rows), VoucherAllocator::PRECISION);
        if ($rounding != 0.0) {
            $rows[] = new WalleyRow(self::ROUNDING_ID, self::ROUNDING_DESCRIPTION, $rounding, 1, 0.0);
        }

        return $rows;
    }

    /**
     * A captured line for a lower quantity: the same unit prices, so the
     * refund rows match the captured rows.
     */
    public function refundLine(CaptureLine $captured, int $quantity): CaptureLine
    {
        return new CaptureLine(
            $captured->getId(),
            $captured->getDescription(),
            $captured->getUnitPrice(),
            $quantity,
            $captured->getVat(),
            $this->perUnit($captured->getDiscountAmount(), $captured->getQuantity()) * $quantity,
            $this->perUnit($captured->getVoucherAmount(), $captured->getQuantity()) * $quantity,
            $captured->getVoucherId(),
            $captured->getVoucherDescription()
        );
    }

    /**
     * @param CaptureLine[] $lines Lines made with refundLine()
     * @return WalleyRow[]
     */
    public function refundRows(array $lines): array
    {
        $rows = [];
        foreach ($lines as $line) {
            $rows = array_merge($rows, $this->lineRows($line, $line->getQuantity()));
        }

        return $rows;
    }

    /**
     * @param WalleyRow[] $rows
     */
    public function getTotal(array $rows): float
    {
        $total = 0.0;
        foreach ($rows as $row) {
            $total += $row->getUnitPrice() * $row->getQuantity();
        }

        return round($total, VoucherAllocator::PRECISION);
    }

    /**
     * @return WalleyRow[]
     */
    private function lineRows(CaptureLine $line, int $quantity): array
    {
        if ($quantity <= 0) {
            return [];
        }
        $rows = [new WalleyRow(
            $this->truncate($line->getId()),
            $this->truncate($line->getDescription()),
            round($line->getUnitPrice(), VoucherAllocator::PRECISION),
            $quantity,
            $line->getVat()
        )];

        $discountUnit = $this->perUnit($line->getDiscountAmount(), $quantity);
        if ($discountUnit > 0) {
            $rows[] = new WalleyRow(
                $this->truncate($line->getId()),
                self::DISCOUNT_DESCRIPTION,
                -$discountUnit,
                $quantity,
                $line->getVat()
            );
        }
        $voucherUnit = $this->perUnit($line->getVoucherAmount(), $quantity);
        if ($voucherUnit > 0) {
            $rows[] = new WalleyRow(
                $this->truncate($line->getVoucherId()),
                $this->truncate($line->getVoucherDescription()),
                -$voucherUnit,
                $quantity,
                $line->getVat()
            );
        }

        return $rows;
    }

    private function perUnit(float $amount, int $quantity): float
    {
        return $quantity > 0 ? round($amount / $quantity, VoucherAllocator::PRECISION) : 0.0;
    }

    private function truncate(string $value): string
    {
        return mb_substr($value, 0, self::MAX_LENGTH);
    }
}
