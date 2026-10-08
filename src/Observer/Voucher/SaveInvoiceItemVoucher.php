<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Observer\Voucher;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Invoice\Item as InvoiceItem;
use Webbhuset\CollectorCheckout\Model\Total\Invoice\WalleyVoucher;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\AmountRepository;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\ItemAmount;

/**
 * Event sales_invoice_item_save_after: stores the item's voucher share once
 * the item has an id, inside Magento's invoice save transaction.
 */
class SaveInvoiceItemVoucher implements ObserverInterface
{
    private AmountRepository $amountRepository;

    public function __construct(AmountRepository $amountRepository)
    {
        $this->amountRepository = $amountRepository;
    }

    public function execute(Observer $observer)
    {
        /** @var InvoiceItem $item */
        $item = $observer->getEvent()->getInvoiceItem();
        $invoice = $item ? $item->getInvoice() : null;
        $result = $invoice ? WalleyVoucher::getResult($invoice) : null;
        if (!$result || !$item->getId()) {
            return;
        }

        $share = $result->getLine((string) $item->getOrderItemId());
        if ($share->getAmount() == 0.0 && $share->getBaseAmount() == 0.0) {
            return;
        }
        $this->amountRepository->saveItem(AmountRepository::TYPE_INVOICE, (int) $invoice->getId(), new ItemAmount(
            (int) $item->getId(),
            (int) $item->getOrderItemId(),
            $share->getAmount(),
            $share->getBaseAmount(),
            $share->getTaxAmount(),
            $share->getBaseTaxAmount()
        ));
    }
}
