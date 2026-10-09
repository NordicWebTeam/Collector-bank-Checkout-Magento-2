<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher\Storage;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * Stores applied vouchers in walley_voucher, linked to quote and order.
 */
class VoucherRepository
{
    private const TABLE = 'walley_voucher';

    private ResourceConnection $resourceConnection;

    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Replaces the vouchers of a quote that is not yet placed as an order.
     *
     * @param VoucherRecord[] $records
     */
    public function replaceForQuote(int $quoteId, array $records): void
    {
        $connection = $this->getConnection();
        $table = $this->getTable();
        $rows = array_map(fn (VoucherRecord $record): array => $this->toRow($quoteId, $record), $records);

        $connection->beginTransaction();
        try {
            $connection->delete($table, ['quote_id = ?' => $quoteId, 'order_id IS NULL']);
            if ($rows) {
                $connection->insertMultiple($table, $rows);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Links the quote's vouchers to the placed order.
     */
    public function assignToOrder(int $quoteId, int $orderId): void
    {
        $this->getConnection()->update(
            $this->getTable(),
            ['order_id' => $orderId],
            ['quote_id = ?' => $quoteId, 'order_id IS NULL']
        );
    }

    /**
     * @return VoucherRecord[]
     */
    public function getByQuoteId(int $quoteId): array
    {
        return $this->fetch('quote_id', $quoteId);
    }

    /**
     * @return VoucherRecord[]
     */
    public function getByOrderId(int $orderId): array
    {
        return $this->fetch('order_id', $orderId);
    }

    /**
     * @param int[] $orderIds
     * @return array<int, VoucherRecord[]> Vouchers per order id; orders without vouchers are left out
     */
    public function getByOrderIds(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable())
            ->where('order_id IN (?)', array_map('intval', $orderIds))
            ->order('entity_id ASC');

        $grouped = [];
        foreach ($connection->fetchAll($select) as $row) {
            $grouped[(int) $row['order_id']][] = $this->fromRow($row);
        }

        return $grouped;
    }

    /**
     * @return VoucherRecord[]
     */
    private function fetch(string $column, int $id): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable())
            ->where("$column = ?", $id)
            ->order('entity_id ASC');

        return array_map([$this, 'fromRow'], $connection->fetchAll($select));
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(int $quoteId, VoucherRecord $record): array
    {
        return [
            'quote_id' => $quoteId,
            'order_id' => null,
            'voucher_id' => $record->getVoucherId(),
            'code' => $record->getCode(),
            'description' => $record->getDescription(),
            'face_amount' => $record->getFaceAmount(),
            'amount' => $record->getAmount(),
            'base_amount' => $record->getBaseAmount(),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function fromRow(array $row): VoucherRecord
    {
        return new VoucherRecord(
            $row['quote_id'] !== null ? (int) $row['quote_id'] : null,
            $row['order_id'] !== null ? (int) $row['order_id'] : null,
            (string) $row['voucher_id'],
            (string) $row['code'],
            (string) $row['description'],
            (float) $row['face_amount'],
            (float) $row['amount'],
            (float) $row['base_amount']
        );
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }

    private function getTable(): string
    {
        return $this->resourceConnection->getTableName(self::TABLE);
    }
}
