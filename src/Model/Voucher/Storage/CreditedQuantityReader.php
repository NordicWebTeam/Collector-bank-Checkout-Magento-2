<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher\Storage;

use Magento\Framework\App\ResourceConnection;

/**
 * Quantities already credited from one invoice, per order item.
 */
class CreditedQuantityReader
{
    private ResourceConnection $resourceConnection;

    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param int|null $excludeCreditmemoId Credit memo to leave out, e.g. the one being refunded
     * @return array<int, float> Credited quantity per order item id
     */
    public function getForInvoice(int $invoiceId, ?int $excludeCreditmemoId = null): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(['item' => $this->resourceConnection->getTableName('sales_creditmemo_item')], [
                'order_item_id' => 'item.order_item_id',
                'qty' => 'SUM(item.qty)',
            ])
            ->join(
                ['creditmemo' => $this->resourceConnection->getTableName('sales_creditmemo')],
                'creditmemo.entity_id = item.parent_id',
                []
            )
            ->where('creditmemo.invoice_id = ?', $invoiceId)
            ->where('creditmemo.state != ?', \Magento\Sales\Model\Order\Creditmemo::STATE_CANCELED)
            ->group('item.order_item_id');
        if ($excludeCreditmemoId) {
            $select->where('creditmemo.entity_id != ?', $excludeCreditmemoId);
        }

        $quantities = [];
        foreach ($connection->fetchAll($select) as $row) {
            $quantities[(int) $row['order_item_id']] = (float) $row['qty'];
        }

        return $quantities;
    }
}
