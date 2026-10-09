<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Observer\Voucher;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Webbhuset\CollectorCheckout\Model\Total\Creditmemo\WalleyVoucher;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\AmountRepository;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\ItemAmount;

/**
 * Event sales_creditmemo_item_save_after: stores the item's voucher share once
 * the item has an id, inside Magento's credit memo save transaction.
 */
class SaveCreditmemoItemVoucher implements ObserverInterface
{
    private AmountRepository $amountRepository;

    public function __construct(AmountRepository $amountRepository)
    {
        $this->amountRepository = $amountRepository;
    }

    public function execute(Observer $observer)
    {
        /** @var CreditmemoItem $item */
        $item = $observer->getEvent()->getCreditmemoItem();
        $creditmemo = $item ? $item->getCreditmemo() : null;
        $result = $creditmemo ? WalleyVoucher::getResult($creditmemo) : null;
        if (!$result || !$item->getId()) {
            return;
        }

        $share = $result->getLine((string) $item->getOrderItemId());
        if ($share->getAmount() == 0.0 && $share->getBaseAmount() == 0.0) {
            return;
        }
        $this->amountRepository->saveItem(AmountRepository::TYPE_CREDITMEMO, (int) $creditmemo->getId(), new ItemAmount(
            (int) $item->getId(),
            (int) $item->getOrderItemId(),
            $share->getAmount(),
            $share->getBaseAmount(),
            $share->getTaxAmount(),
            $share->getBaseTaxAmount()
        ));
    }
}
