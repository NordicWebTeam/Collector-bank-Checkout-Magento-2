<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Total\Creditmemo;

use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;
use Webbhuset\CollectorCheckout\Model\Voucher\DocumentLine;
use Webbhuset\CollectorCheckout\Model\Voucher\DocumentVoucherCalculator;
use Webbhuset\CollectorCheckout\Model\Voucher\DocumentVoucherResult;
use Webbhuset\CollectorCheckout\Model\Voucher\LineShare;
use Webbhuset\CollectorCheckout\Model\Voucher\QuoteVoucherCalculator;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\AmountRepository;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\CreditedQuantityReader;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\DocumentAmounts;
use Webbhuset\CollectorCheckout\Model\Voucher\VoucherAllocator;

/**
 * Voucher on credit memos. Each credited item gets its share of the voucher
 * on the invoice being credited, in proportion to quantity, so a refund
 * matches what was captured. Shipping gets the voucher share not yet credited
 * on the order's shipping. Without an invoice, the order's invoiced amounts
 * are used.
 *
 * As for invoices, each item's VAT is set to its full VAT for the credited
 * quantity minus its voucher VAT, and the voucher's net amount is deducted.
 * The last credit memo gets exactly the voucher VAT that remains.
 */
class WalleyVoucher extends AbstractTotal
{
    public const RESULT_KEY = 'walley_voucher_result';

    private const VALUE_AMOUNT = 'amount';
    private const VALUE_TAX = 'tax';

    private AmountRepository $amountRepository;
    private CreditedQuantityReader $creditedQuantityReader;
    private DocumentVoucherCalculator $calculator;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        AmountRepository $amountRepository,
        CreditedQuantityReader $creditedQuantityReader,
        DocumentVoucherCalculator $calculator,
        array $data = []
    ) {
        parent::__construct($data);
        $this->amountRepository = $amountRepository;
        $this->creditedQuantityReader = $creditedQuantityReader;
        $this->calculator = $calculator;
    }

    public function collect(Creditmemo $creditmemo)
    {
        $creditmemo->unsetData(self::RESULT_KEY);
        $order = $creditmemo->getOrder();
        $orderId = $order && $order->getId() ? (int) $order->getId() : 0;
        $orderAmounts = $orderId ? $this->amountRepository->get(AmountRepository::TYPE_ORDER, $orderId) : null;
        if (!$orderAmounts || $orderAmounts->isEmpty()) {
            return $this;
        }

        $invoicedAll = $this->amountRepository->getOrderTotals(AmountRepository::TYPE_INVOICE, $orderId);
        $creditedAll = $this->amountRepository->getOrderTotals(AmountRepository::TYPE_CREDITMEMO, $orderId);
        $invoice = $creditmemo->getInvoice();
        $invoiceId = $invoice && $invoice->getId() ? (int) $invoice->getId() : 0;
        $source = $invoiceId ? $this->amountRepository->get(AmountRepository::TYPE_INVOICE, $invoiceId) : null;
        $credited = $invoiceId ? $this->amountRepository->getCreditedForInvoice($invoiceId, $orderId) : null;
        $creditedQty = $invoiceId ? $this->creditedQuantityReader->getForInvoice($invoiceId) : [];

        $lines = [];
        $shares = [];
        $items = [];
        foreach ($creditmemo->getAllItems() as $item) {
            if ($item->getOrderItem()->isDummy() || $item->getQty() <= 0) {
                continue;
            }
            $key = (string) $item->getOrderItemId();
            $lines[$key] = $this->buildItemLine($item, $key, $invoicedAll, $creditedAll);
            $shares[$key] = $invoiceId
                ? $this->capAtOrderRemainder(
                    $this->getInvoiceItemShare($item, $invoice, $source, $credited, $creditedQty),
                    (int) $item->getOrderItemId(),
                    $invoicedAll,
                    $creditedAll
                )
                : $this->getOrderItemShare($item, $invoicedAll, $creditedAll);
            $items[$key] = $item;
        }
        $includesShipping = (float) $creditmemo->getShippingAmount() > 0;
        if ($includesShipping) {
            $lines[QuoteVoucherCalculator::SHIPPING_KEY] = $this->buildShippingLine($creditmemo, $orderAmounts);
            $shares[QuoteVoucherCalculator::SHIPPING_KEY] = $this->getShippingShare($creditmemo, $invoicedAll, $creditedAll);
        }

        $result = new DocumentVoucherResult($shares, $lines);
        if ($creditmemo->isLast()) {
            // Magento's isLast() only looks at items; shipping may be credited later
            $shippingTax = $includesShipping ? 0.0
                : $invoicedAll->getShippingTaxAmount() - $creditedAll->getShippingTaxAmount();
            $baseShippingTax = $includesShipping ? 0.0
                : $invoicedAll->getBaseShippingTaxAmount() - $creditedAll->getBaseShippingTaxAmount();
            $result = $this->calculator->withTaxTotal(
                $result,
                round($invoicedAll->getTaxAmount() - $creditedAll->getTaxAmount() - $shippingTax, VoucherAllocator::PRECISION),
                round(
                    $invoicedAll->getBaseTaxAmount() - $creditedAll->getBaseTaxAmount() - $baseShippingTax,
                    VoucherAllocator::PRECISION
                )
            );
        }

        $this->apply($creditmemo, $items, $includesShipping, $result);

        return $this;
    }

    /**
     * Sets the line VAT and the document's VAT to their sum. Magento caps a
     * credit memo's VAT at its invoice's VAT, which no longer matches the
     * items once the voucher is front-loaded, so it is replaced, not adjusted.
     *
     * @param array<string, CreditmemoItem> $items
     */
    private function apply(Creditmemo $creditmemo, array $items, bool $includesShipping, DocumentVoucherResult $result): void
    {
        $tax = 0.0;
        $baseTax = 0.0;
        foreach ($items as $key => $item) {
            $key = (string) $key;
            $item->setTaxAmount($result->getLineTax($key));
            $item->setBaseTaxAmount($result->getBaseLineTax($key));
            $tax += $result->getLineTax($key);
            $baseTax += $result->getBaseLineTax($key);
        }
        if ($includesShipping) {
            $shippingKey = QuoteVoucherCalculator::SHIPPING_KEY;
            $creditmemo->setShippingTaxAmount($result->getLineTax($shippingKey));
            $creditmemo->setBaseShippingTaxAmount($result->getBaseLineTax($shippingKey));
        }
        $tax += (float) $creditmemo->getShippingTaxAmount();
        $baseTax += (float) $creditmemo->getBaseShippingTaxAmount();

        $taxChange = round($tax, VoucherAllocator::PRECISION) - (float) $creditmemo->getTaxAmount();
        $baseTaxChange = round($baseTax, VoucherAllocator::PRECISION) - (float) $creditmemo->getBaseTaxAmount();
        $creditmemo->setTaxAmount(round($tax, VoucherAllocator::PRECISION));
        $creditmemo->setBaseTaxAmount(round($baseTax, VoucherAllocator::PRECISION));
        $creditmemo->setGrandTotal((float) $creditmemo->getGrandTotal() + $taxChange - $result->getNetAmount());
        $creditmemo->setBaseGrandTotal(
            (float) $creditmemo->getBaseGrandTotal() + $baseTaxChange - $result->getBaseNetAmount()
        );
        $creditmemo->setData(self::RESULT_KEY, $result);
    }

    /**
     * Full VAT for the credited quantity: the VAT invoiced plus the voucher VAT
     * invoiced, minus what is already refunded of both, in proportion to
     * quantity. The item's last credit memo takes the rest, as Magento does.
     */
    private function buildItemLine(
        CreditmemoItem $item,
        string $key,
        DocumentAmounts $invoicedAll,
        DocumentAmounts $creditedAll
    ): DocumentLine {
        $orderItem = $item->getOrderItem();
        $orderItemId = (int) $orderItem->getId();
        $fullTax = (float) $orderItem->getTaxInvoiced() + $this->getValue($invoicedAll, $orderItemId, false, self::VALUE_TAX)
            - (float) $orderItem->getTaxRefunded() - $this->getValue($creditedAll, $orderItemId, false, self::VALUE_TAX);
        $baseFullTax = (float) $orderItem->getBaseTaxInvoiced()
            + $this->getValue($invoicedAll, $orderItemId, true, self::VALUE_TAX)
            - (float) $orderItem->getBaseTaxRefunded()
            - $this->getValue($creditedAll, $orderItemId, true, self::VALUE_TAX);
        if (!$item->isLast()) {
            $ratio = (float) $item->getQty() / ((float) $orderItem->getQtyInvoiced() - (float) $orderItem->getQtyRefunded());
            $fullTax *= $ratio;
            $baseFullTax *= $ratio;
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
     * The item's share of the voucher on the credited invoice, by quantity.
     *
     * @param array<int, float> $creditedQty
     */
    private function getInvoiceItemShare(
        CreditmemoItem $item,
        \Magento\Sales\Model\Order\Invoice $invoice,
        ?DocumentAmounts $source,
        ?DocumentAmounts $credited,
        array $creditedQty
    ): LineShare {
        $orderItemId = (int) $item->getOrderItemId();
        $invoicedQty = 0.0;
        foreach ($invoice->getAllItems() as $invoiceItem) {
            if ((int) $invoiceItem->getOrderItemId() === $orderItemId) {
                $invoicedQty += (float) $invoiceItem->getQty();
            }
        }
        $availableQty = $invoicedQty - ($creditedQty[$orderItemId] ?? 0.0);

        return $this->getShare($item, $orderItemId, $availableQty, $source, $credited);
    }

    /**
     * Never credits more voucher for an item than remains on the order, e.g.
     * when credit memos with and without an invoice are mixed.
     */
    private function capAtOrderRemainder(
        LineShare $share,
        int $orderItemId,
        DocumentAmounts $invoicedAll,
        DocumentAmounts $creditedAll
    ): LineShare {
        $remaining = fn (bool $base, string $value): float => max(0.0, round(
            $this->getValue($invoicedAll, $orderItemId, $base, $value)
                - $this->getValue($creditedAll, $orderItemId, $base, $value),
            VoucherAllocator::PRECISION
        ));

        return new LineShare(
            min($share->getAmount(), $remaining(false, self::VALUE_AMOUNT)),
            min($share->getBaseAmount(), $remaining(true, self::VALUE_AMOUNT)),
            min($share->getTaxAmount(), $remaining(false, self::VALUE_TAX)),
            min($share->getBaseTaxAmount(), $remaining(true, self::VALUE_TAX))
        );
    }

    /**
     * Without an invoice: the item's share of the voucher invoiced on the
     * order, by quantity.
     */
    private function getOrderItemShare(
        CreditmemoItem $item,
        DocumentAmounts $invoicedAll,
        DocumentAmounts $creditedAll
    ): LineShare {
        $orderItem = $item->getOrderItem();
        $availableQty = (float) $orderItem->getQtyInvoiced() - (float) $orderItem->getQtyRefunded();

        return $this->getShare($item, (int) $orderItem->getId(), $availableQty, $invoicedAll, $creditedAll);
    }

    private function getShare(
        CreditmemoItem $item,
        int $orderItemId,
        float $availableQty,
        ?DocumentAmounts $source,
        ?DocumentAmounts $credited
    ): LineShare {
        if (!$source || $availableQty <= 0) {
            return LineShare::empty();
        }
        $ratio = min(1.0, (float) $item->getQty() / $availableQty);
        $remaining = fn (bool $base, string $value): float => round(
            ($this->getValue($source, $orderItemId, $base, $value)
                - ($credited ? $this->getValue($credited, $orderItemId, $base, $value) : 0.0)) * $ratio,
            VoucherAllocator::PRECISION
        );

        return new LineShare(
            $remaining(false, self::VALUE_AMOUNT),
            $remaining(true, self::VALUE_AMOUNT),
            $remaining(false, self::VALUE_TAX),
            $remaining(true, self::VALUE_TAX)
        );
    }

    /**
     * Shipping is invoiced once, so its voucher share comes from the order's
     * invoiced shipping share not yet credited, in proportion to the shipping
     * amount credited.
     */
    private function getShippingShare(
        Creditmemo $creditmemo,
        DocumentAmounts $invoicedAll,
        DocumentAmounts $creditedAll
    ): LineShare {
        $order = $creditmemo->getOrder();
        $refundable = (float) $order->getShippingInvoiced() - (float) $order->getShippingRefunded();
        if ($refundable <= 0) {
            return LineShare::empty();
        }
        $ratio = min(1.0, (float) $creditmemo->getShippingAmount() / $refundable);

        return new LineShare(
            round(($invoicedAll->getShippingAmount() - $creditedAll->getShippingAmount()) * $ratio, 2),
            round(($invoicedAll->getBaseShippingAmount() - $creditedAll->getBaseShippingAmount()) * $ratio, 2),
            round(($invoicedAll->getShippingTaxAmount() - $creditedAll->getShippingTaxAmount()) * $ratio, 2),
            round(($invoicedAll->getBaseShippingTaxAmount() - $creditedAll->getBaseShippingTaxAmount()) * $ratio, 2)
        );
    }

    /**
     * Shipping VAT before voucher for the credited shipping amount.
     */
    private function buildShippingLine(Creditmemo $creditmemo, DocumentAmounts $orderAmounts): DocumentLine
    {
        $order = $creditmemo->getOrder();
        $orderShipping = (float) $order->getShippingAmount();
        $baseOrderShipping = (float) $order->getBaseShippingAmount();
        // The order's shipping voucher VAT is its total voucher VAT minus the items' voucher VAT
        $itemsTax = 0.0;
        $baseItemsTax = 0.0;
        foreach ($orderAmounts->getItems() as $orderItemAmount) {
            $itemsTax += $orderItemAmount->getTaxAmount();
            $baseItemsTax += $orderItemAmount->getBaseTaxAmount();
        }
        $fullTax = (float) $order->getShippingTaxAmount() + $orderAmounts->getTaxAmount() - $itemsTax;
        $baseFullTax = (float) $order->getBaseShippingTaxAmount() + $orderAmounts->getBaseTaxAmount() - $baseItemsTax;
        $ratio = $orderShipping > 0 ? (float) $creditmemo->getShippingAmount() / $orderShipping : 0.0;
        $baseRatio = $baseOrderShipping > 0 ? (float) $creditmemo->getBaseShippingAmount() / $baseOrderShipping : 0.0;

        return new DocumentLine(
            QuoteVoucherCalculator::SHIPPING_KEY,
            (float) $creditmemo->getShippingAmount(),
            round($fullTax * $ratio, VoucherAllocator::PRECISION),
            0.0,
            0.0,
            $orderShipping > 0 ? round($fullTax / $orderShipping * 100, 2) : 0.0,
            (float) $creditmemo->getBaseShippingAmount(),
            round($baseFullTax * $baseRatio, VoucherAllocator::PRECISION)
        );
    }

    private function getValue(DocumentAmounts $amounts, int $orderItemId, bool $base, string $value): float
    {
        foreach ($amounts->getItems() as $item) {
            if ($item->getOrderItemId() !== $orderItemId) {
                continue;
            }
            if ($value === self::VALUE_TAX) {
                return $base ? $item->getBaseTaxAmount() : $item->getTaxAmount();
            }

            return $base ? $item->getBaseAmount() : $item->getAmount();
        }

        return 0.0;
    }

    /**
     * @return DocumentVoucherResult|null Result of the last collection, in memory only
     */
    public static function getResult(Creditmemo $creditmemo): ?DocumentVoucherResult
    {
        $result = $creditmemo->getData(self::RESULT_KEY);

        return $result instanceof DocumentVoucherResult ? $result : null;
    }
}
