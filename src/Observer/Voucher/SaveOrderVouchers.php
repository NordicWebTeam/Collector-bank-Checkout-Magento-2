<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Observer\Voucher;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Webbhuset\CollectorCheckout\Model\Total\Quote\WalleyVoucher;
use Webbhuset\CollectorCheckout\Model\Voucher\LineShare;
use Webbhuset\CollectorCheckout\Model\Voucher\QuoteVoucherProvider;
use Webbhuset\CollectorCheckout\Model\Voucher\QuoteVoucherTotals;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\AmountRepository;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\DocumentAmounts;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\ItemAmount;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\VoucherRepository;

/**
 * Event sales_model_service_quote_submit_success: links the quote's vouchers
 * to the placed order and stores the voucher share of each order item.
 * Order and order items have ids at this point.
 *
 * Errors are not caught: an order without its voucher amounts would be
 * invoiced and credited at Walley with wrong amounts.
 */
class SaveOrderVouchers implements ObserverInterface
{
    private QuoteVoucherTotals $quoteVoucherTotals;
    private QuoteVoucherProvider $provider;
    private VoucherRepository $voucherRepository;
    private AmountRepository $amountRepository;

    public function __construct(
        QuoteVoucherTotals $quoteVoucherTotals,
        QuoteVoucherProvider $provider,
        VoucherRepository $voucherRepository,
        AmountRepository $amountRepository
    ) {
        $this->quoteVoucherTotals = $quoteVoucherTotals;
        $this->provider = $provider;
        $this->voucherRepository = $voucherRepository;
        $this->amountRepository = $amountRepository;
    }

    public function execute(Observer $observer)
    {
        /** @var Quote $quote */
        $quote = $observer->getEvent()->getQuote();
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();

        $result = $this->quoteVoucherTotals->getResult($quote);
        if (!$result) {
            return;
        }

        $orderId = (int) $order->getEntityId();
        $this->provider->saveDeductedAmounts($quote, $result);
        $this->voucherRepository->assignToOrder((int) $quote->getId(), $orderId);

        $shipping = $result->getShipping();
        $this->amountRepository->save(AmountRepository::TYPE_ORDER, new DocumentAmounts(
            $orderId,
            $orderId,
            $result->getAmount(),
            $result->getBaseAmount(),
            $shipping->getAmount(),
            $shipping->getBaseAmount(),
            $this->getItemAmounts($quote, $order),
            $result->getTaxAmount(),
            $result->getBaseTaxAmount(),
            $shipping->getTaxAmount(),
            $shipping->getBaseTaxAmount()
        ));
    }

    /**
     * @return ItemAmount[]
     */
    private function getItemAmounts(Quote $quote, Order $order): array
    {
        $sharesByQuoteItem = [];
        foreach ($quote->getAllItems() as $quoteItem) {
            $share = $quoteItem->getData(WalleyVoucher::ITEM_SHARE_KEY);
            if ($share instanceof LineShare && $share->getAmount() > 0) {
                $sharesByQuoteItem[(int) $quoteItem->getId()] = $share;
            }
        }

        $itemAmounts = [];
        foreach ($order->getAllItems() as $orderItem) {
            $share = $sharesByQuoteItem[(int) $orderItem->getQuoteItemId()] ?? null;
            if (!$share) {
                continue;
            }
            $orderItemId = (int) $orderItem->getItemId();
            $itemAmounts[] = new ItemAmount(
                $orderItemId,
                $orderItemId,
                $share->getAmount(),
                $share->getBaseAmount(),
                $share->getTaxAmount(),
                $share->getBaseTaxAmount()
            );
        }

        return $itemAmounts;
    }
}
