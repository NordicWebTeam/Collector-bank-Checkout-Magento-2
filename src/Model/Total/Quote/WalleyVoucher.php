<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Total\Quote;

use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Tax\Model\Config as TaxConfig;
use Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector;
use Webbhuset\CollectorCheckout\Model\Voucher\AppliedTaxesAdjuster;
use Webbhuset\CollectorCheckout\Model\Voucher\LineShare;
use Webbhuset\CollectorCheckout\Model\Voucher\QuoteVoucherCalculator;
use Webbhuset\CollectorCheckout\Model\Voucher\QuoteVoucherProvider;
use Webbhuset\CollectorCheckout\Model\Voucher\QuoteVoucherResult;
use Webbhuset\CollectorCheckout\Model\Voucher\QuoteVoucherTotals;
use Webbhuset\CollectorCheckout\Model\Voucher\TaxableLine;

/**
 * Deducts Voyado vouchers applied in Walley Checkout from the quote.
 *
 * Runs after Magento's tax totals (see etc/sales.xml) and handles VAT itself,
 * independent of Magento's discount tax settings: vouchers are incl. VAT, and
 * their VAT part is deducted from the items' tax, the tax total and the tax
 * breakdowns. The voucher total deducts the rest, so the grand total drops by
 * the voucher amount. Item discount fields are not touched, so the cart sent
 * to Walley is unaffected.
 *
 * Fixed product taxes (FPT/WEEE) are not covered by vouchers.
 */
class WalleyVoucher extends AbstractTotal
{
    public const CODE = 'walley_voucher';
    public const ITEM_SHARE_KEY = 'walley_voucher_share';
    public const RESULT_KEY = 'walley_voucher_result';

    private const TAX_TOTAL_CODE = 'tax';

    private QuoteVoucherProvider $provider;
    private QuoteVoucherCalculator $calculator;
    private AppliedTaxesAdjuster $appliedTaxesAdjuster;
    private TaxConfig $taxConfig;
    private QuoteVoucherTotals $quoteVoucherTotals;

    public function __construct(
        QuoteVoucherProvider $provider,
        QuoteVoucherCalculator $calculator,
        AppliedTaxesAdjuster $appliedTaxesAdjuster,
        TaxConfig $taxConfig,
        QuoteVoucherTotals $quoteVoucherTotals
    ) {
        $this->provider = $provider;
        $this->calculator = $calculator;
        $this->appliedTaxesAdjuster = $appliedTaxesAdjuster;
        $this->taxConfig = $taxConfig;
        $this->quoteVoucherTotals = $quoteVoucherTotals;
        $this->setCode(self::CODE);
    }

    public function collect(Quote $quote, ShippingAssignmentInterface $shippingAssignment, Total $total)
    {
        parent::collect($quote, $shippingAssignment, $total);

        $items = $this->getTaxableItems($shippingAssignment->getItems() ?: []);
        foreach ($items as $item) {
            $item->unsetData(self::ITEM_SHARE_KEY);
        }
        $total->unsetData(self::RESULT_KEY);
        // Totals are copied onto the address with addData(), which never removes keys
        $shippingAssignment->getShipping()->getAddress()->unsetData(self::RESULT_KEY);

        // Walley Checkout has one delivery address; applying all vouchers per address would multiply them
        $vouchers = $items && !$quote->getIsMultiShipping() ? $this->provider->getVouchers($quote) : [];
        if (!$vouchers) {
            return $this;
        }

        $lines = [];
        foreach ($items as $itemCode => $item) {
            $lines[] = new TaxableLine(
                $itemCode,
                $this->getItemAmount($item),
                (float) $item->getTaxPercent(),
                (float) $item->getTaxAmount(),
                (float) $item->getBaseTaxAmount()
            );
        }
        $result = $this->calculator->calculate(
            $vouchers,
            $lines,
            $this->getShippingLine($total),
            (float) $quote->getBaseToQuoteRate() ?: 1.0
        );

        $this->deductTax($items, $total, $result);
        $total->setData(self::RESULT_KEY, $result);
        $total->setTotalAmount(self::CODE, -$result->getNetAmount());
        $total->setBaseTotalAmount(self::CODE, -$result->getBaseNetAmount());

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetch(Quote $quote, Total $total)
    {
        $result = $total->getData(self::RESULT_KEY);
        if (!$result instanceof QuoteVoucherResult) {
            // A quote loaded in this request, e.g. by the checkout totals API, has no result in memory
            $result = $this->quoteVoucherTotals->getCollectedResult($quote);
        }
        if (!$result instanceof QuoteVoucherResult || $result->isEmpty()) {
            return null;
        }

        // Excl. VAT when the cart shows the subtotal excl. VAT, so the totals rows add up
        $amount = $this->taxConfig->displayCartSubtotalExclTax($quote->getStore())
            ? $result->getNetAmount()
            : $result->getAmount();

        return [
            'code' => self::CODE,
            'title' => __('Voyado voucher'),
            'value' => -$amount,
        ];
    }

    /**
     * Deducts the VAT part of the vouchers from item tax, shipping tax, the
     * tax total and the tax breakdowns.
     *
     * @param array<string, AbstractItem> $items Keyed by tax calculation item code
     */
    private function deductTax(array $items, Total $total, QuoteVoucherResult $result): void
    {
        $sharesByItemCode = [];
        foreach ($items as $itemCode => $item) {
            $share = $result->getLine($itemCode);
            $item->setData(self::ITEM_SHARE_KEY, $share);
            $this->reduceItemTax($item, $share);
            // Parents of dynamic bundles show the sum of their children's tax
            if ($item->getParentItem()) {
                $this->reduceItemTax($item->getParentItem(), $share);
            }
            $sharesByItemCode[$itemCode] = $share;
        }

        $shipping = $result->getShipping();
        $total->setShippingTaxAmount(max(0.0, (float) $total->getShippingTaxAmount() - $shipping->getTaxAmount()));
        $total->setBaseShippingTaxAmount(
            max(0.0, (float) $total->getBaseShippingTaxAmount() - $shipping->getBaseTaxAmount())
        );
        $sharesByItemCode[CommonTaxCollector::ITEM_CODE_SHIPPING] = $shipping;

        $total->setTotalAmount(
            self::TAX_TOTAL_CODE,
            (float) $total->getTotalAmount(self::TAX_TOTAL_CODE) - $result->getTaxAmount()
        );
        $total->setBaseTotalAmount(
            self::TAX_TOTAL_CODE,
            (float) $total->getBaseTotalAmount(self::TAX_TOTAL_CODE) - $result->getBaseTaxAmount()
        );

        $breakdown = $this->appliedTaxesAdjuster->reduce(
            $total->getItemsAppliedTaxes() ?: [],
            $total->getAppliedTaxes() ?: [],
            $sharesByItemCode
        );
        $total->setItemsAppliedTaxes($breakdown->getItemsAppliedTaxes());
        $total->setAppliedTaxes($breakdown->getAppliedTaxes());
        foreach ($items as $itemCode => $item) {
            if (isset($breakdown->getItemsAppliedTaxes()[$itemCode])) {
                $item->setAppliedTaxes($breakdown->getItemsAppliedTaxes()[$itemCode]);
            }
        }
    }

    private function reduceItemTax(AbstractItem $item, LineShare $share): void
    {
        $item->setTaxAmount(max(0.0, round((float) $item->getTaxAmount() - $share->getTaxAmount(), 2)));
        $item->setBaseTaxAmount(max(0.0, round((float) $item->getBaseTaxAmount() - $share->getBaseTaxAmount(), 2)));
    }

    /**
     * The items Magento's tax calculation prices: children of items whose
     * children are calculated (dynamic bundles), otherwise the item itself.
     * Keyed by tax calculation item code, as in the tax breakdowns.
     *
     * @param AbstractItem[] $items
     * @return array<string, AbstractItem>
     */
    private function getTaxableItems(array $items): array
    {
        $taxableItems = [];
        foreach ($items as $item) {
            if ($item->getParentItem()) {
                continue;
            }
            $lineItems = $item->getHasChildren() && $item->isChildrenCalculated() ? $item->getChildren() : [$item];
            foreach ($lineItems as $lineItem) {
                $itemCode = (string) ($lineItem->getTaxCalculationItemId() ?: 'item-' . count($taxableItems));
                $taxableItems[$itemCode] = $lineItem;
            }
        }

        return $taxableItems;
    }

    /**
     * What the item costs incl. VAT after discounts, for any tax setting.
     */
    private function getItemAmount(AbstractItem $item): float
    {
        return max(0.0, (float) $item->getRowTotal()
            + (float) $item->getTaxAmount()
            + (float) $item->getDiscountTaxCompensationAmount()
            - (float) $item->getDiscountAmount());
    }

    /**
     * What shipping costs incl. VAT after shipping discount, with the rate
     * from the shipping tax breakdown.
     */
    private function getShippingLine(Total $total): TaxableLine
    {
        $amount = (float) $total->getShippingAmount()
            + (float) $total->getShippingTaxAmount()
            + (float) $total->getShippingDiscountTaxCompensationAmount()
            - (float) $total->getShippingDiscountAmount();

        $taxPercent = 0.0;
        foreach (($total->getItemsAppliedTaxes() ?: [])[CommonTaxCollector::ITEM_CODE_SHIPPING] ?? [] as $tax) {
            $taxPercent += (float) ($tax['percent'] ?? 0);
        }

        return new TaxableLine(
            QuoteVoucherCalculator::SHIPPING_KEY,
            max(0.0, $amount),
            $taxPercent,
            (float) $total->getShippingTaxAmount(),
            (float) $total->getBaseShippingTaxAmount()
        );
    }
}
