<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Observer\Voucher;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Webbhuset\CollectorCheckout\Model\Total\Creditmemo\WalleyVoucher;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\AmountRepository;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\DocumentAmounts;

/**
 * Event sales_order_creditmemo_save_after: stores the credit memo's voucher
 * amounts, inside Magento's save transaction. Magento saves credit memo items
 * after this event, so their amounts are stored by SaveCreditmemoItemVoucher.
 * Only the credit memo's own row is written here, so saving it again keeps
 * the item rows.
 */
class SaveCreditmemoVouchers implements ObserverInterface
{
    private AmountRepository $amountRepository;

    public function __construct(AmountRepository $amountRepository)
    {
        $this->amountRepository = $amountRepository;
    }

    public function execute(Observer $observer)
    {
        /** @var Creditmemo $creditmemo */
        $creditmemo = $observer->getEvent()->getCreditmemo();
        $result = WalleyVoucher::getResult($creditmemo);
        if (!$result || !$creditmemo->getId()) {
            return;
        }

        $shipping = $result->getShipping();
        $this->amountRepository->saveHeader(AmountRepository::TYPE_CREDITMEMO, new DocumentAmounts(
            (int) $creditmemo->getId(),
            (int) $creditmemo->getOrderId(),
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
