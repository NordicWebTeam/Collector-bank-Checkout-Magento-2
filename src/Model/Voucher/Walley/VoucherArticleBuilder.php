<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher\Walley;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Webbhuset\CollectorCheckout\Model\Total\Invoice\WalleyVoucher as InvoiceVoucher;
use Webbhuset\CollectorCheckout\Model\Voucher\LineShare;
use Webbhuset\CollectorCheckout\Model\Voucher\QuoteVoucherCalculator;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\AmountRepository;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\CreditedQuantityReader;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\DocumentAmounts;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\VoucherRecord;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\VoucherRepository;
use Webbhuset\CollectorCheckout\Model\Voucher\VoucherAllocator;
use Webbhuset\CollectorCheckout\Service\Sdk\Payment\Invoice\Article\ArticleFactory;
use Webbhuset\CollectorCheckout\Service\Sdk\Payment\Invoice\Article\ArticleList;
use Webbhuset\CollectorCheckout\Service\Sdk\Payment\Invoice\Article\ArticleListFactory;

/**
 * Walley rows for capturing and refunding orders with vouchers. These orders
 * are captured with replaceItems, with rows built from the Magento invoice,
 * because the voucher share of each capture is decided in Magento. Refunds use
 * the same rows for the credited quantities, so they match the captured rows.
 */
class VoucherArticleBuilder
{
    private const MULTIPLE_VOUCHERS_ID = 'VOUCHER';
    private const MULTIPLE_VOUCHERS_DESCRIPTION = 'Voyado voucher';
    private const SHIPPING_ID = 'shipping';

    private AmountRepository $amountRepository;
    private VoucherRepository $voucherRepository;
    private CreditedQuantityReader $creditedQuantityReader;
    private VoucherRowCalculator $calculator;
    private ArticleListFactory $articleListFactory;
    private ArticleFactory $articleFactory;
    private OrderItemRepositoryInterface $orderItemRepository;

    public function __construct(
        AmountRepository $amountRepository,
        VoucherRepository $voucherRepository,
        CreditedQuantityReader $creditedQuantityReader,
        VoucherRowCalculator $calculator,
        ArticleListFactory $articleListFactory,
        ArticleFactory $articleFactory,
        OrderItemRepositoryInterface $orderItemRepository
    ) {
        $this->amountRepository = $amountRepository;
        $this->voucherRepository = $voucherRepository;
        $this->creditedQuantityReader = $creditedQuantityReader;
        $this->calculator = $calculator;
        $this->articleListFactory = $articleListFactory;
        $this->articleFactory = $articleFactory;
        $this->orderItemRepository = $orderItemRepository;
    }

    public function isVoucherOrder(OrderInterface $order): bool
    {
        if (!$order->getEntityId()) {
            return false;
        }
        $amounts = $this->amountRepository->get(AmountRepository::TYPE_ORDER, (int) $order->getEntityId());

        return $amounts !== null && !$amounts->isEmpty();
    }

    /**
     * Rows for capturing an invoice; they add up to the invoice grand total.
     *
     * @throws LocalizedException When the invoice or its voucher amounts are missing
     */
    public function forInvoice(Invoice $invoice): ArticleList
    {
        if (!$invoice->getOrder() || !$invoice->getAllItems()) {
            throw new LocalizedException(__('The invoice to capture could not be found.'));
        }
        if ($this->getInvoiceShares($invoice) === null) {
            throw new LocalizedException(__('The Walley voucher amounts of the invoice could not be found.'));
        }
        $rows = $this->calculator->captureRows(
            array_values($this->getCaptureLines($invoice)),
            (float) $invoice->getGrandTotal()
        );

        return $this->toArticleList($rows);
    }

    /**
     * Rows for refunding a credit memo, with the unit prices captured on the
     * credit memo's invoice. Includes the invoice's rounding row when this
     * credit memo completes the refund of that invoice.
     */
    public function forCreditmemo(Creditmemo $creditmemo): ArticleList
    {
        $invoice = $creditmemo->getInvoice();
        if (!$invoice || !$invoice->getId()) {
            throw new LocalizedException(
                __('Orders with Walley vouchers can only be refunded online from an invoice.')
            );
        }
        if ((float) $creditmemo->getAdjustmentPositive() > 0 || (float) $creditmemo->getAdjustmentNegative() > 0) {
            throw new LocalizedException(__(
                'Adjustment refunds and fees can not be refunded online for orders with Walley vouchers. '
                . 'Please use the merchant portal for other cases.'
            ));
        }
        $order = $creditmemo->getOrder();
        if ((float) $creditmemo->getShippingAmount() > 0
            && round((float) $creditmemo->getShippingAmount(), 2) !== round((float) $order->getShippingInvoiced(), 2)
        ) {
            throw new LocalizedException(
                __('Can only refund the whole shipping amount. Please use the merchant portal for other cases.')
            );
        }

        $captured = $this->getCaptureLines($invoice);
        $lines = [];
        foreach ($creditmemo->getAllItems() as $item) {
            $key = (string) $item->getOrderItemId();
            if ($this->getOrderItem($item)->isDummy() || $item->getQty() <= 0 || !isset($captured[$key])) {
                continue;
            }
            $lines[] = $this->calculator->refundLine($captured[$key], $this->getWholeQty($item));
        }
        if ((float) $creditmemo->getShippingAmount() > 0) {
            $shippingLine = $this->getCapturedShippingLine($creditmemo);
            if ($shippingLine) {
                $lines[] = $this->calculator->refundLine($shippingLine, 1);
            }
        }

        $rows = $this->calculator->refundRows($lines);
        if ($this->completesInvoice($creditmemo, $invoice)) {
            $rows = array_merge($rows, $this->getRoundingRows($invoice));
        }

        return $this->toArticleList($rows);
    }

    /**
     * Amount of an article list, as sent to Walley.
     */
    public function getTotal(ArticleList $articleList): float
    {
        $total = 0.0;
        foreach ($articleList->getArticleList() as $article) {
            $total += $article['UnitPrice'] * $article['Quantity'];
        }

        return round($total, VoucherAllocator::PRECISION);
    }

    /**
     * @return array<string, CaptureLine> Keyed by order item id, shipping under QuoteVoucherCalculator::SHIPPING_KEY
     */
    private function getCaptureLines(Invoice $invoice): array
    {
        $order = $invoice->getOrder();
        [$voucherId, $voucherDescription] = $this->getVoucherLabel((int) $order->getEntityId());
        $shares = $this->getInvoiceShares($invoice) ?? [];

        $lines = [];
        foreach ($invoice->getAllItems() as $item) {
            $orderItem = $this->getOrderItem($item);
            if ($orderItem->isDummy() || $item->getQty() <= 0) {
                continue;
            }
            $key = (string) $item->getOrderItemId();
            $share = $shares[$key] ?? LineShare::empty();
            $quantity = $this->getWholeQty($item);
            $discount = 0.0;
            if ((float) $item->getDiscountAmount() > 0) {
                // Rule discount incl. VAT: price incl. VAT minus what the line costs before the voucher
                $capacity = (float) $item->getRowTotal() + (float) $item->getTaxAmount() + $share->getTaxAmount()
                    + (float) $item->getDiscountTaxCompensationAmount() - (float) $item->getDiscountAmount();
                $discount = max(0.0, round((float) $item->getPriceInclTax() * $quantity - $capacity, VoucherAllocator::PRECISION));
            }

            $lines[$key] = new CaptureLine(
                (string) $orderItem->getSku(),
                (string) $orderItem->getName(),
                (float) $item->getPriceInclTax(),
                $quantity,
                (float) $orderItem->getTaxPercent(),
                $discount,
                $share->getAmount(),
                $voucherId,
                $voucherDescription
            );
        }

        if ((float) $invoice->getShippingAmount() > 0) {
            $lines[QuoteVoucherCalculator::SHIPPING_KEY] = $this->buildShippingLine(
                $invoice,
                $shares[QuoteVoucherCalculator::SHIPPING_KEY] ?? LineShare::empty(),
                $voucherId,
                $voucherDescription
            );
        }

        return $lines;
    }

    private function buildShippingLine(Invoice $invoice, LineShare $share, string $voucherId, string $voucherDescription): CaptureLine
    {
        $order = $invoice->getOrder();
        $shippingAmount = (float) $invoice->getShippingAmount();
        $fullTax = (float) $invoice->getShippingTaxAmount() + $share->getTaxAmount();
        $inclTax = (float) $order->getShippingInclTax();
        $discount = 0.0;
        if ((float) $order->getShippingDiscountAmount() > 0) {
            $capacity = $shippingAmount + $fullTax + (float) $order->getShippingDiscountTaxCompensationAmount()
                - (float) $order->getShippingDiscountAmount();
            $discount = max(0.0, round($inclTax - $capacity, VoucherAllocator::PRECISION));
        }

        return new CaptureLine(
            (string) ($order->getShippingMethod() ?: self::SHIPPING_ID),
            (string) ($order->getShippingDescription() ?: self::SHIPPING_ID),
            $inclTax,
            1,
            $shippingAmount > 0 ? round($fullTax / $shippingAmount * 100, 2) : 0.0,
            $discount,
            $share->getAmount(),
            $voucherId,
            $voucherDescription
        );
    }

    /**
     * Voucher share per invoice line: from the invoice's totals collection
     * when it is being captured, otherwise from storage. An invoice without
     * voucher, e.g. after the voucher is used up, has an empty result.
     *
     * @return array<string, LineShare>|null Null when the invoice has no voucher amounts at all
     */
    private function getInvoiceShares(Invoice $invoice): ?array
    {
        $result = InvoiceVoucher::getResult($invoice);
        if ($result) {
            $shares = [];
            foreach ($result->getShares() as $key => $share) {
                $shares[(string) $key] = $share;
            }

            return $shares;
        }

        $stored = $invoice->getId()
            ? $this->amountRepository->get(AmountRepository::TYPE_INVOICE, (int) $invoice->getId())
            : null;
        if (!$stored) {
            return null;
        }

        return $this->toShares($stored);
    }

    /**
     * @return array<string, LineShare>
     */
    private function toShares(DocumentAmounts $stored): array
    {
        $shares = [];
        foreach ($stored->getItems() as $item) {
            $shares[(string) $item->getOrderItemId()] = new LineShare(
                $item->getAmount(),
                $item->getBaseAmount(),
                $item->getTaxAmount(),
                $item->getBaseTaxAmount()
            );
        }
        $shares[QuoteVoucherCalculator::SHIPPING_KEY] = new LineShare(
            $stored->getShippingAmount(),
            $stored->getBaseShippingAmount(),
            $stored->getShippingTaxAmount(),
            $stored->getBaseShippingTaxAmount()
        );

        return $shares;
    }

    /**
     * Shipping is captured with one invoice, not necessarily the one being
     * credited.
     */
    private function getCapturedShippingLine(Creditmemo $creditmemo): ?CaptureLine
    {
        foreach ($creditmemo->getOrder()->getInvoiceCollection() as $invoice) {
            if ((float) $invoice->getShippingAmount() > 0 && !$invoice->isCanceled()) {
                $invoice->setOrder($creditmemo->getOrder());

                return $this->getCaptureLines($invoice)[QuoteVoucherCalculator::SHIPPING_KEY] ?? null;
            }
        }

        return null;
    }

    /**
     * Whether, after this credit memo, everything captured with the invoice is
     * refunded: all its quantities and, if it carried shipping, the shipping.
     *
     * Called from the refund gateway command, which Magento runs after adding
     * the credit memo to the order's refunded totals and before saving it.
     */
    private function completesInvoice(Creditmemo $creditmemo, Invoice $invoice): bool
    {
        $creditedBefore = $this->creditedQuantityReader->getForInvoice(
            (int) $invoice->getId(),
            $creditmemo->getId() ? (int) $creditmemo->getId() : null
        );
        $creditedNow = [];
        foreach ($creditmemo->getAllItems() as $item) {
            $creditedNow[(int) $item->getOrderItemId()] = (float) $item->getQty();
        }
        foreach ($invoice->getAllItems() as $item) {
            if ($this->getOrderItem($item)->isDummy() || $item->getQty() <= 0) {
                continue;
            }
            $orderItemId = (int) $item->getOrderItemId();
            if (($creditedBefore[$orderItemId] ?? 0.0) + ($creditedNow[$orderItemId] ?? 0.0) < (float) $item->getQty()) {
                return false;
            }
        }
        if ((float) $invoice->getShippingAmount() > 0) {
            // Already includes this credit memo, see above
            return (float) $creditmemo->getOrder()->getShippingRefunded() >= (float) $invoice->getShippingAmount();
        }

        return true;
    }

    /**
     * @return WalleyRow[]
     */
    private function getRoundingRows(Invoice $invoice): array
    {
        $rows = $this->calculator->captureRows(array_values($this->getCaptureLines($invoice)), (float) $invoice->getGrandTotal());

        return array_values(array_filter(
            $rows,
            fn (WalleyRow $row): bool => $row->getId() === VoucherRowCalculator::ROUNDING_ID
        ));
    }

    /**
     * Walley rows have whole quantities.
     *
     * @param \Magento\Sales\Model\Order\Invoice\Item|\Magento\Sales\Model\Order\Creditmemo\Item $item
     * @throws LocalizedException For decimal quantities
     */
    private function getWholeQty($item): int
    {
        $quantity = (float) $item->getQty();
        if (abs($quantity - round($quantity)) > 0.0001) {
            throw new LocalizedException(
                __('Decimal quantities can not be captured or refunded for orders with Walley vouchers.')
            );
        }

        return (int) round($quantity);
    }

    /**
     * The document item's order item; loaded by id when the document's order
     * object does not contain it.
     *
     * @param \Magento\Sales\Model\Order\Invoice\Item|\Magento\Sales\Model\Order\Creditmemo\Item $item
     * @return \Magento\Sales\Model\Order\Item
     */
    private function getOrderItem($item): OrderItemInterface
    {
        return $item->getOrderItem() ?: $this->orderItemRepository->get((int) $item->getOrderItemId());
    }

    /**
     * @return array{0: string, 1: string} Id and description of the voucher rows
     */
    private function getVoucherLabel(int $orderId): array
    {
        $vouchers = $this->voucherRepository->getByOrderId($orderId);
        if (count($vouchers) === 1) {
            /** @var VoucherRecord $voucher */
            $voucher = reset($vouchers);

            return [$voucher->getCode(), $voucher->getDescription() ?: self::MULTIPLE_VOUCHERS_DESCRIPTION];
        }

        return [self::MULTIPLE_VOUCHERS_ID, self::MULTIPLE_VOUCHERS_DESCRIPTION];
    }

    /**
     * @param WalleyRow[] $rows
     */
    private function toArticleList(array $rows): ArticleList
    {
        $articleList = $this->articleListFactory->create();
        foreach ($rows as $row) {
            $articleList->addArticle($this->articleFactory->create([
                'articleId' => $row->getId(),
                'description' => $row->getDescription(),
                'quantity' => $row->getQuantity(),
                'sku' => $row->getId(),
                'unitPrice' => $row->getUnitPrice(),
                'vat' => $row->getVat(),
            ]));
        }

        return $articleList;
    }
}
