<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Webbhuset\CollectorCheckout\Model\Total\Creditmemo\WalleyVoucher as CreditmemoVoucher;
use Webbhuset\CollectorCheckout\Model\Total\Invoice\WalleyVoucher as InvoiceVoucher;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\AmountRepository;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\DocumentAmounts;

/**
 * Voucher amounts of an order, invoice or credit memo, for display. Saved
 * documents are read from storage; invoices and credit memos not saved yet,
 * e.g. in the admin create forms, from their totals collection.
 */
class DocumentAmountsReader
{
    private AmountRepository $amountRepository;

    public function __construct(AmountRepository $amountRepository)
    {
        $this->amountRepository = $amountRepository;
    }

    /**
     * @param mixed $source Order, invoice or credit memo
     */
    public function read($source): ?DocumentAmounts
    {
        $type = $this->getType($source);
        if ($type === null) {
            return null;
        }
        if ($source->getId()) {
            return $this->amountRepository->get($type, (int) $source->getId());
        }

        $result = $source instanceof Invoice ? InvoiceVoucher::getResult($source) : null;
        if ($source instanceof Creditmemo) {
            $result = CreditmemoVoucher::getResult($source);
        }

        return $result ? $this->fromResult($result, (int) $source->getOrderId()) : null;
    }

    /**
     * @param mixed $source
     */
    private function getType($source): ?string
    {
        if ($source instanceof Order) {
            return AmountRepository::TYPE_ORDER;
        }
        if ($source instanceof Invoice) {
            return AmountRepository::TYPE_INVOICE;
        }
        if ($source instanceof Creditmemo) {
            return AmountRepository::TYPE_CREDITMEMO;
        }

        return null;
    }

    private function fromResult(DocumentVoucherResult $result, int $orderId): DocumentAmounts
    {
        $shipping = $result->getShipping();

        return new DocumentAmounts(
            0,
            $orderId,
            $result->getAmount(),
            $result->getBaseAmount(),
            $shipping->getAmount(),
            $shipping->getBaseAmount(),
            [],
            $result->getTaxAmount(),
            $result->getBaseTaxAmount(),
            $shipping->getTaxAmount(),
            $shipping->getBaseTaxAmount()
        );
    }
}
