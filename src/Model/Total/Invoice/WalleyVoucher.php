<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Total\Invoice;

use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Item as InvoiceItem;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;
use Webbhuset\CollectorCheckout\Model\Voucher\DocumentLine;
use Webbhuset\CollectorCheckout\Model\Voucher\DocumentVoucherCalculator;
use Webbhuset\CollectorCheckout\Model\Voucher\DocumentVoucherResult;
use Webbhuset\CollectorCheckout\Model\Voucher\LineShare;
use Webbhuset\CollectorCheckout\Model\Voucher\QuoteVoucherCalculator;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\AmountRepository;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\DocumentAmounts;
use Webbhuset\CollectorCheckout\Model\Voucher\VoucherAllocator;

/**
 * Voucher on invoices, front-loaded: each invoice uses as much of the voucher
 * not yet invoiced as it can carry (see DocumentVoucherCalculator).
 *
 * Front-loading only applies when all lines of the order have the same VAT
 * rate. With mixed rates, where the voucher lands changes how much VAT it
 * removes, so invoices could not add up to the order; the voucher is then
 * invoiced in proportion to each item's share of it on the order.
 *
 * Magento invoices each item's VAT in proportion to quantity, from the order
 * item's VAT, which is already lowered by the item's proportional voucher
 * share. This total sets each invoice item's VAT to its full VAT for the
 * invoiced quantity minus its front-loaded voucher VAT, and deducts the
 * voucher's net amount. Runs after tax, before the grand total (etc/sales.xml).
 */
class WalleyVoucher extends AbstractTotal
{
    public const RESULT_KEY = 'walley_voucher_result';

    private AmountRepository $amountRepository;
    private DocumentVoucherCalculator $calculator;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        AmountRepository $amountRepository,
        DocumentVoucherCalculator $calculator,
        array $data = []
    ) {
        parent::__construct($data);
        $this->amountRepository = $amountRepository;
        $this->calculator = $calculator;
    }

    public function collect(Invoice $invoice)
    {
        $invoice->unsetData(self::RESULT_KEY);
        $order = $invoice->getOrder();
        $orderAmounts = $order && $order->getId()
            ? $this->amountRepository->get(AmountRepository::TYPE_ORDER, (int) $order->getId())
            : null;
        if (!$orderAmounts || $orderAmounts->isEmpty()) {
            return $this;
        }
        $invoiced = $this->amountRepository->getOrderTotals(AmountRepository::TYPE_INVOICE, (int) $order->getId());

        $lines = [];
        $items = [];
        foreach ($invoice->getAllItems() as $item) {
            if ($item->getOrderItem()->isDummy() || $item->getQty() <= 0) {
                continue;
            }
            $key = (string) $item->getOrderItemId();
            $lines[] = $this->buildItemLine($item, $key, $orderAmounts, $invoiced);
            $items[$key] = $item;
        }
        $includesShipping = (float) $invoice->getShippingAmount() > 0;
        if ($includesShipping) {
            $lines[] = $this->buildShippingLine($invoice, $orderAmounts);
        }

        $result = $this->hasSingleTaxRate($invoice, $orderAmounts)
            ? $this->calculator->calculate(
                $lines,
                round($orderAmounts->getAmount() - $invoiced->getAmount(), VoucherAllocator::PRECISION),
                round($orderAmounts->getTaxAmount() - $invoiced->getTaxAmount(), VoucherAllocator::PRECISION),
                $invoice->isLast(),
                (float) $order->getBaseToOrderRate() ?: 1.0,
                round($orderAmounts->getBaseAmount() - $invoiced->getBaseAmount(), VoucherAllocator::PRECISION),
                round($orderAmounts->getBaseTaxAmount() - $invoiced->getBaseTaxAmount(), VoucherAllocator::PRECISION)
            )
            : $this->getProportionalResult($lines, $items, $includesShipping, $orderAmounts, $invoiced);

        // The invoice VAT becomes the sum of the line VAT; Magento's own total is
        // capped and rounded in ways that no longer match once the voucher moves
        $tax = 0.0;
        $baseTax = 0.0;
        foreach ($items as $key => $item) {
            // Numeric string keys become integers in PHP arrays
            $key = (string) $key;
            $item->setTaxAmount($result->getLineTax($key));
            $item->setBaseTaxAmount($result->getBaseLineTax($key));
            $tax += $result->getLineTax($key);
            $baseTax += $result->getBaseLineTax($key);
        }
        if ($includesShipping) {
            $shippingKey = QuoteVoucherCalculator::SHIPPING_KEY;
            $invoice->setShippingTaxAmount($result->getLineTax($shippingKey));
            $invoice->setBaseShippingTaxAmount($result->getBaseLineTax($shippingKey));
        }
        $tax += (float) $invoice->getShippingTaxAmount();
        $baseTax += (float) $invoice->getBaseShippingTaxAmount();

        $taxChange = round($tax, VoucherAllocator::PRECISION) - (float) $invoice->getTaxAmount();
        $baseTaxChange = round($baseTax, VoucherAllocator::PRECISION) - (float) $invoice->getBaseTaxAmount();
        $invoice->setTaxAmount(round($tax, VoucherAllocator::PRECISION));
        $invoice->setBaseTaxAmount(round($baseTax, VoucherAllocator::PRECISION));
        $invoice->setGrandTotal((float) $invoice->getGrandTotal() + $taxChange - $result->getNetAmount());
        $invoice->setBaseGrandTotal(
            (float) $invoice->getBaseGrandTotal() + $baseTaxChange - $result->getBaseNetAmount()
        );
        $invoice->setData(self::RESULT_KEY, $result);

        return $this;
    }

    /**
     * Full VAT for the invoiced quantity: the item's VAT plus its voucher VAT,
     * minus what is already invoiced of both, in proportion to quantity. The
     * item's last invoice takes the rest, as Magento does.
     */
    private function buildItemLine(
        InvoiceItem $item,
        string $key,
        DocumentAmounts $orderAmounts,
        DocumentAmounts $invoiced
    ): DocumentLine {
        $orderItem = $item->getOrderItem();
        $voucherTax = $this->getItemTax($orderAmounts, (int) $orderItem->getId(), false);
        $baseVoucherTax = $this->getItemTax($orderAmounts, (int) $orderItem->getId(), true);
        $invoicedVoucherTax = $this->getItemTax($invoiced, (int) $orderItem->getId(), false);
        $baseInvoicedVoucherTax = $this->getItemTax($invoiced, (int) $orderItem->getId(), true);

        $fullTax = (float) $orderItem->getTaxAmount() + $voucherTax
            - (float) $orderItem->getTaxInvoiced() - $invoicedVoucherTax;
        $baseFullTax = (float) $orderItem->getBaseTaxAmount() + $baseVoucherTax
            - (float) $orderItem->getBaseTaxInvoiced() - $baseInvoicedVoucherTax;
        if (!$item->isLast()) {
            $ratio = (float) $item->getQty() / ((float) $orderItem->getQtyOrdered() - (float) $orderItem->getQtyInvoiced());
            $fullTax = $fullTax * $ratio;
            $baseFullTax = $baseFullTax * $ratio;
        }

        return new DocumentLine(
            $key,
            (float) $item->getRowTotal(),
            round($fullTax, VoucherAllocator::PRECISION),
            (float) $item->getDiscountAmount(),
            (float) $item->getDiscountTaxCompensationAmount(),
            (float) $orderItem->getTaxPercent(),
            (float) $item->getBaseRowTotal(),
            round($baseFullTax, VoucherAllocator::PRECISION),
            (float) $item->getBaseDiscountAmount(),
            (float) $item->getBaseDiscountTaxCompensationAmount()
        );
    }

    /**
     * Shipping is invoiced in full with the first invoice. Its voucher VAT is
     * the order's voucher VAT minus the items' voucher VAT.
     */
    private function buildShippingLine(Invoice $invoice, DocumentAmounts $orderAmounts): DocumentLine
    {
        $order = $invoice->getOrder();
        $itemsTax = 0.0;
        $baseItemsTax = 0.0;
        foreach ($orderAmounts->getItems() as $orderItemAmount) {
            $itemsTax += $orderItemAmount->getTaxAmount();
            $baseItemsTax += $orderItemAmount->getBaseTaxAmount();
        }
        $shippingAmount = (float) $order->getShippingAmount();
        $shippingFullTax = (float) $order->getShippingTaxAmount() + $orderAmounts->getTaxAmount() - $itemsTax;

        return new DocumentLine(
            QuoteVoucherCalculator::SHIPPING_KEY,
            (float) $invoice->getShippingAmount(),
            round($shippingFullTax, VoucherAllocator::PRECISION),
            (float) $order->getShippingDiscountAmount(),
            (float) $order->getShippingDiscountTaxCompensationAmount(),
            $shippingAmount > 0 ? round($shippingFullTax / $shippingAmount * 100, 2) : 0.0,
            (float) $invoice->getBaseShippingAmount(),
            round(
                (float) $order->getBaseShippingTaxAmount() + $orderAmounts->getBaseTaxAmount() - $baseItemsTax,
                VoucherAllocator::PRECISION
            ),
            (float) $order->getBaseShippingDiscountAmount(),
            (float) $order->getBaseShippingDiscountTaxCompensationAmnt()
        );
    }

    /**
     * Whether all order lines with an amount, shipping included, have the
     * same VAT rate. The shipping rate is derived from its VAT before voucher.
     */
    private function hasSingleTaxRate(Invoice $invoice, DocumentAmounts $orderAmounts): bool
    {
        $order = $invoice->getOrder();
        $rates = [];
        foreach ($order->getAllItems() as $orderItem) {
            if (!$orderItem->isDummy() && (float) $orderItem->getRowTotal() > 0) {
                $rates[] = round((float) $orderItem->getTaxPercent(), 1);
            }
        }
        if ((float) $order->getShippingAmount() > 0) {
            $rates[] = round($this->buildShippingLine($invoice, $orderAmounts)->getTaxPercent(), 1);
        }

        return count(array_unique($rates)) <= 1;
    }

    /**
     * Proportional fallback: each invoiced item gets its share of the item's
     * voucher not yet invoiced, by quantity; an item's last invoice takes the
     * rest. Shipping gets the order's shipping share.
     *
     * @param DocumentLine[] $lines
     * @param array<string, InvoiceItem> $items
     */
    private function getProportionalResult(
        array $lines,
        array $items,
        bool $includesShipping,
        DocumentAmounts $orderAmounts,
        DocumentAmounts $invoiced
    ): DocumentVoucherResult {
        $shares = [];
        foreach ($items as $key => $item) {
            $orderItem = $item->getOrderItem();
            $orderItemId = (int) $orderItem->getId();
            $ratio = $item->isLast()
                ? 1.0
                : (float) $item->getQty() / ((float) $orderItem->getQtyOrdered() - (float) $orderItem->getQtyInvoiced());
            $remaining = fn (bool $base, bool $tax): float =>
                $this->getItemValue($orderAmounts, $orderItemId, $base, $tax)
                - $this->getItemValue($invoiced, $orderItemId, $base, $tax);
            $shares[(string) $key] = new LineShare(
                round($remaining(false, false) * $ratio, VoucherAllocator::PRECISION),
                round($remaining(true, false) * $ratio, VoucherAllocator::PRECISION),
                round($remaining(false, true) * $ratio, VoucherAllocator::PRECISION),
                round($remaining(true, true) * $ratio, VoucherAllocator::PRECISION)
            );
        }
        if ($includesShipping) {
            $itemsTax = 0.0;
            $baseItemsTax = 0.0;
            foreach ($orderAmounts->getItems() as $orderItemAmount) {
                $itemsTax += $orderItemAmount->getTaxAmount();
                $baseItemsTax += $orderItemAmount->getBaseTaxAmount();
            }
            $shares[QuoteVoucherCalculator::SHIPPING_KEY] = new LineShare(
                $orderAmounts->getShippingAmount(),
                $orderAmounts->getBaseShippingAmount(),
                round($orderAmounts->getTaxAmount() - $itemsTax, VoucherAllocator::PRECISION),
                round($orderAmounts->getBaseTaxAmount() - $baseItemsTax, VoucherAllocator::PRECISION)
            );
        }

        $linesByKey = [];
        foreach ($lines as $line) {
            $linesByKey[$line->getKey()] = $line;
        }

        return new DocumentVoucherResult($shares, $linesByKey);
    }

    private function getItemValue(DocumentAmounts $amounts, int $orderItemId, bool $base, bool $tax): float
    {
        foreach ($amounts->getItems() as $item) {
            if ($item->getOrderItemId() === $orderItemId) {
                if ($tax) {
                    return $base ? $item->getBaseTaxAmount() : $item->getTaxAmount();
                }

                return $base ? $item->getBaseAmount() : $item->getAmount();
            }
        }

        return 0.0;
    }

    private function getItemTax(DocumentAmounts $amounts, int $orderItemId, bool $base): float
    {
        foreach ($amounts->getItems() as $item) {
            if ($item->getOrderItemId() === $orderItemId) {
                return $base ? $item->getBaseTaxAmount() : $item->getTaxAmount();
            }
        }

        return 0.0;
    }

    /**
     * @return DocumentVoucherResult|null Result of the last collection, in memory only
     */
    public static function getResult(Invoice $invoice): ?DocumentVoucherResult
    {
        $result = $invoice->getData(self::RESULT_KEY);

        return $result instanceof DocumentVoucherResult ? $result : null;
    }
}
