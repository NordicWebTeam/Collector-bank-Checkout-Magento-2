<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher;

use Webbhuset\CollectorCheckout\Model\Voucher\Storage\DocumentAmounts;

/**
 * Builds the "Voyado voucher" totals row for orders, invoices and credit
 * memos, the same way Magento shows discounts: excl. VAT when the subtotal is
 * shown excl. VAT, so the totals rows add up, otherwise incl. VAT.
 */
class TotalRowBuilder
{
    public const CODE = 'walley_voucher';

    /**
     * Rows the voucher row is placed after, in order of preference. When the
     * store shows amounts both excl. and incl. VAT, Magento's tax block adds
     * separate *_incl rows after the excl. ones.
     */
    private const ANCHORS = ['discount', 'shipping_incl', 'shipping', 'subtotal_incl', 'subtotal_excl', 'subtotal'];

    /**
     * @return array{code: string, value: float, base_value: float}|null Null when there is nothing to show
     */
    public function build(?DocumentAmounts $amounts, bool $subtotalExclTax): ?array
    {
        if ($amounts === null || $amounts->isEmpty()) {
            return null;
        }

        return [
            'code' => self::CODE,
            'value' => -($subtotalExclTax ? $amounts->getNetAmount() : $amounts->getAmount()),
            'base_value' => -($subtotalExclTax ? $amounts->getBaseNetAmount() : $amounts->getBaseAmount()),
        ];
    }

    /**
     * @param string[] $existingCodes Codes of the totals already added
     * @return string|null The total to place the voucher row after; null for the end
     */
    public function getAnchor(array $existingCodes): ?string
    {
        foreach (self::ANCHORS as $anchor) {
            if (in_array($anchor, $existingCodes, true)) {
                return $anchor;
            }
        }

        return null;
    }
}
