<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Observer\Voucher;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Invoice;
use Webbhuset\CollectorCheckout\Model\Total\Invoice\WalleyVoucher;
use Webbhuset\CollectorCheckout\Model\Voucher\QuoteVoucherCalculator;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\AmountRepository;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\DocumentAmounts;

/**
 * Event sales_order_invoice_save_after: stores the invoice's voucher amounts,
 * inside Magento's save transaction. Only invoices whose totals were collected
 * in this request carry a result; saving a loaded invoice leaves them as is.
 *
 * Magento saves invoice items after this event, so their amounts are stored
 * by SaveInvoiceItemVoucher. Only the invoice's own row is written here, so
 * saving the invoice again keeps the item rows.
 */
class SaveInvoiceVouchers implements ObserverInterface
{
    private AmountRepository $amountRepository;

    public function __construct(AmountRepository $amountRepository)
    {
        $this->amountRepository = $amountRepository;
    }

    public function execute(Observer $observer)
    {
        /** @var Invoice $invoice */
        $invoice = $observer->getEvent()->getInvoice();
        $result = WalleyVoucher::getResult($invoice);
        if (!$result || !$invoice->getId()) {
            return;
        }

        $shipping = $result->getLine(QuoteVoucherCalculator::SHIPPING_KEY);
        $this->amountRepository->saveHeader(AmountRepository::TYPE_INVOICE, new DocumentAmounts(
            (int) $invoice->getId(),
            (int) $invoice->getOrderId(),
            $result->getAmount(),
            $result->getBaseAmount(),
            $shipping->getAmount(),
            $shipping->getBaseAmount(),
            [],
            $result->getTaxAmount(),
            $result->getBaseTaxAmount(),
            $shipping->getTaxAmount(),
            $shipping->getBaseTaxAmount()
        ));
    }
}
