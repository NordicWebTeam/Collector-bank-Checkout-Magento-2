<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher\Storage;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * Stores voucher amounts linked to orders, invoices and credit memos, in one
 * header table and one item table per document type.
 */
class AmountRepository
{
    public const TYPE_ORDER = 'order';
    public const TYPE_INVOICE = 'invoice';
    public const TYPE_CREDITMEMO = 'creditmemo';

    /**
     * Core document tables and their cancelled state, to leave cancelled
     * invoices and credit memos out of totals.
     */
    private const CANCELABLE = [
        self::TYPE_INVOICE => ['table' => 'sales_invoice', 'state' => 3],
        self::TYPE_CREDITMEMO => ['table' => 'sales_creditmemo', 'state' => 3],
    ];

    private const TABLES = [
        self::TYPE_ORDER => [
            'header' => 'walley_voucher_order',
            'header_key' => 'order_id',
            'item' => 'walley_voucher_order_item',
            'item_key' => 'order_item_id',
        ],
        self::TYPE_INVOICE => [
            'header' => 'walley_voucher_invoice',
            'header_key' => 'invoice_id',
            'item' => 'walley_voucher_invoice_item',
            'item_key' => 'invoice_item_id',
        ],
        self::TYPE_CREDITMEMO => [
            'header' => 'walley_voucher_creditmemo',
            'header_key' => 'creditmemo_id',
            'item' => 'walley_voucher_creditmemo_item',
            'item_key' => 'creditmemo_item_id',
        ],
    ];

    private ResourceConnection $resourceConnection;

    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Saves the amounts of a document, replacing any previously saved amounts.
     */
    public function save(string $type, DocumentAmounts $amounts): void
    {
        $tables = $this->getTables($type);
        $connection = $this->getConnection();
        $headerTable = $this->resourceConnection->getTableName($tables['header']);
        $itemTable = $this->resourceConnection->getTableName($tables['item']);

        $connection->beginTransaction();
        try {
            $connection->insertOnDuplicate($headerTable, $this->toHeaderRow($type, $amounts));
            $connection->delete($itemTable, [$tables['header_key'] . ' = ?' => $amounts->getDocumentId()]);
            $itemRows = array_map(
                fn (ItemAmount $item): array => $this->toItemRow($type, $amounts->getDocumentId(), $item),
                $amounts->getItems()
            );
            if ($itemRows) {
                $connection->insertMultiple($itemTable, $itemRows);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Saves only the document's own amounts, leaving its item rows untouched.
     * Used when the items are saved separately (see saveItem()), so that saving
     * the document again does not lose them.
     */
    public function saveHeader(string $type, DocumentAmounts $amounts): void
    {
        $this->getConnection()->insertOnDuplicate(
            $this->resourceConnection->getTableName($this->getTables($type)['header']),
            $this->toHeaderRow($type, $amounts)
        );
    }

    /**
     * Saves one item's amounts, e.g. when the item is saved after its document.
     */
    public function saveItem(string $type, int $documentId, ItemAmount $item): void
    {
        $tables = $this->getTables($type);
        $this->getConnection()->insertOnDuplicate(
            $this->resourceConnection->getTableName($tables['item']),
            $this->toItemRow($type, $documentId, $item)
        );
    }

    public function get(string $type, int $documentId): ?DocumentAmounts
    {
        $tables = $this->getTables($type);
        $connection = $this->getConnection();

        $header = $connection->fetchRow(
            $connection->select()
                ->from($this->resourceConnection->getTableName($tables['header']))
                ->where($tables['header_key'] . ' = ?', $documentId)
        );
        if (!$header) {
            return null;
        }

        $itemRows = $connection->fetchAll(
            $connection->select()
                ->from($this->resourceConnection->getTableName($tables['item']))
                ->where($tables['header_key'] . ' = ?', $documentId)
        );

        return new DocumentAmounts(
            $documentId,
            (int) $header['order_id'],
            (float) $header['amount'],
            (float) $header['base_amount'],
            (float) ($header['shipping_amount'] ?? 0),
            (float) ($header['base_shipping_amount'] ?? 0),
            array_map(fn (array $row): ItemAmount => $this->fromItemRow($type, $row), $itemRows),
            (float) ($header['tax_amount'] ?? 0),
            (float) ($header['base_tax_amount'] ?? 0),
            (float) ($header['shipping_tax_amount'] ?? 0),
            (float) ($header['base_shipping_tax_amount'] ?? 0)
        );
    }

    /**
     * Sums all documents of a type for an order, e.g. everything invoiced so
     * far, leaving out cancelled invoices and credit memos. Items are summed
     * per order item; the document id is 0.
     */
    public function getOrderTotals(string $type, int $orderId): DocumentAmounts
    {
        $tables = $this->getTables($type);
        $connection = $this->getConnection();
        $headerTable = $this->resourceConnection->getTableName($tables['header']);
        $itemTable = $this->resourceConnection->getTableName($tables['item']);

        $header = $connection->fetchRow(
            $connection->select()
                ->from($headerTable, [
                    'amount' => 'COALESCE(SUM(amount), 0)',
                    'base_amount' => 'COALESCE(SUM(base_amount), 0)',
                    'shipping_amount' => 'COALESCE(SUM(shipping_amount), 0)',
                    'base_shipping_amount' => 'COALESCE(SUM(base_shipping_amount), 0)',
                    'tax_amount' => 'COALESCE(SUM(tax_amount), 0)',
                    'base_tax_amount' => 'COALESCE(SUM(base_tax_amount), 0)',
                    'shipping_tax_amount' => 'COALESCE(SUM(shipping_tax_amount), 0)',
                    'base_shipping_tax_amount' => 'COALESCE(SUM(base_shipping_tax_amount), 0)',
                ])
                ->where('order_id = ?', $orderId)
                ->where($this->getNotCanceledCondition($type, $tables['header_key']))
        );

        $itemRows = $connection->fetchAll(
            $connection->select()
                ->from(['item' => $itemTable], [
                    'order_item_id' => 'item.order_item_id',
                    'amount' => 'SUM(item.amount)',
                    'base_amount' => 'SUM(item.base_amount)',
                    'tax_amount' => 'SUM(item.tax_amount)',
                    'base_tax_amount' => 'SUM(item.base_tax_amount)',
                ])
                ->join(['header' => $headerTable], "header.{$tables['header_key']} = item.{$tables['header_key']}", [])
                ->where('header.order_id = ?', $orderId)
                ->where($this->getNotCanceledCondition($type, "header.{$tables['header_key']}"))
                ->group('item.order_item_id')
        );

        return new DocumentAmounts(
            0,
            $orderId,
            (float) $header['amount'],
            (float) $header['base_amount'],
            (float) $header['shipping_amount'],
            (float) $header['base_shipping_amount'],
            array_map(
                fn (array $row): ItemAmount => new ItemAmount(
                    (int) $row['order_item_id'],
                    (int) $row['order_item_id'],
                    (float) $row['amount'],
                    (float) $row['base_amount'],
                    (float) $row['tax_amount'],
                    (float) $row['base_tax_amount']
                ),
                $itemRows
            ),
            (float) $header['tax_amount'],
            (float) $header['base_tax_amount'],
            (float) $header['shipping_tax_amount'],
            (float) $header['base_shipping_tax_amount']
        );
    }

    /**
     * Sums the credit memos of one invoice. Items are summed per order item;
     * the document id is 0.
     */
    public function getCreditedForInvoice(int $invoiceId, int $orderId): DocumentAmounts
    {
        $connection = $this->getConnection();
        $creditmemoTable = $this->resourceConnection->getTableName('sales_creditmemo');
        $headerTable = $this->resourceConnection->getTableName(self::TABLES[self::TYPE_CREDITMEMO]['header']);
        $itemTable = $this->resourceConnection->getTableName(self::TABLES[self::TYPE_CREDITMEMO]['item']);

        $header = $connection->fetchRow(
            $connection->select()
                ->from(['header' => $headerTable], [
                    'amount' => 'COALESCE(SUM(header.amount), 0)',
                    'base_amount' => 'COALESCE(SUM(header.base_amount), 0)',
                    'shipping_amount' => 'COALESCE(SUM(header.shipping_amount), 0)',
                    'base_shipping_amount' => 'COALESCE(SUM(header.base_shipping_amount), 0)',
                    'tax_amount' => 'COALESCE(SUM(header.tax_amount), 0)',
                    'base_tax_amount' => 'COALESCE(SUM(header.base_tax_amount), 0)',
                    'shipping_tax_amount' => 'COALESCE(SUM(header.shipping_tax_amount), 0)',
                    'base_shipping_tax_amount' => 'COALESCE(SUM(header.base_shipping_tax_amount), 0)',
                ])
                ->join(['creditmemo' => $creditmemoTable], 'creditmemo.entity_id = header.creditmemo_id', [])
                ->where('creditmemo.invoice_id = ?', $invoiceId)
                ->where('creditmemo.state != ?', self::CANCELABLE[self::TYPE_CREDITMEMO]['state'])
        );
        $itemRows = $connection->fetchAll(
            $connection->select()
                ->from(['item' => $itemTable], [
                    'order_item_id' => 'item.order_item_id',
                    'amount' => 'SUM(item.amount)',
                    'base_amount' => 'SUM(item.base_amount)',
                    'tax_amount' => 'SUM(item.tax_amount)',
                    'base_tax_amount' => 'SUM(item.base_tax_amount)',
                ])
                ->join(['creditmemo' => $creditmemoTable], 'creditmemo.entity_id = item.creditmemo_id', [])
                ->where('creditmemo.invoice_id = ?', $invoiceId)
                ->where('creditmemo.state != ?', self::CANCELABLE[self::TYPE_CREDITMEMO]['state'])
                ->group('item.order_item_id')
        );

        return new DocumentAmounts(
            0,
            $orderId,
            (float) $header['amount'],
            (float) $header['base_amount'],
            (float) $header['shipping_amount'],
            (float) $header['base_shipping_amount'],
            array_map(
                fn (array $row): ItemAmount => new ItemAmount(
                    (int) $row['order_item_id'],
                    (int) $row['order_item_id'],
                    (float) $row['amount'],
                    (float) $row['base_amount'],
                    (float) $row['tax_amount'],
                    (float) $row['base_tax_amount']
                ),
                $itemRows
            ),
            (float) $header['tax_amount'],
            (float) $header['base_tax_amount'],
            (float) $header['shipping_tax_amount'],
            (float) $header['base_shipping_tax_amount']
        );
    }

    /**
     * SQL condition leaving out cancelled core documents; always true for orders.
     */
    private function getNotCanceledCondition(string $type, string $documentIdColumn): string
    {
        if (!isset(self::CANCELABLE[$type])) {
            return '1 = 1';
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName(self::CANCELABLE[$type]['table']), ['entity_id'])
            ->where('state = ?', self::CANCELABLE[$type]['state']);

        return sprintf('%s NOT IN (%s)', $documentIdColumn, $select->assemble());
    }

    /**
     * @return array<string, mixed>
     */
    private function toHeaderRow(string $type, DocumentAmounts $amounts): array
    {
        $row = [
            self::TABLES[$type]['header_key'] => $amounts->getDocumentId(),
            'amount' => $amounts->getAmount(),
            'base_amount' => $amounts->getBaseAmount(),
            'shipping_amount' => $amounts->getShippingAmount(),
            'base_shipping_amount' => $amounts->getBaseShippingAmount(),
            'tax_amount' => $amounts->getTaxAmount(),
            'base_tax_amount' => $amounts->getBaseTaxAmount(),
            'shipping_tax_amount' => $amounts->getShippingTaxAmount(),
            'base_shipping_tax_amount' => $amounts->getBaseShippingTaxAmount(),
        ];
        if ($type !== self::TYPE_ORDER) {
            $row['order_id'] = $amounts->getOrderId();
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function toItemRow(string $type, int $documentId, ItemAmount $item): array
    {
        $tables = self::TABLES[$type];

        return [
            $tables['item_key'] => $item->getItemId(),
            $tables['header_key'] => $documentId,
            'order_item_id' => $item->getOrderItemId(),
            'amount' => $item->getAmount(),
            'base_amount' => $item->getBaseAmount(),
            'tax_amount' => $item->getTaxAmount(),
            'base_tax_amount' => $item->getBaseTaxAmount(),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function fromItemRow(string $type, array $row): ItemAmount
    {
        return new ItemAmount(
            (int) $row[self::TABLES[$type]['item_key']],
            (int) $row['order_item_id'],
            (float) $row['amount'],
            (float) $row['base_amount'],
            (float) ($row['tax_amount'] ?? 0),
            (float) ($row['base_tax_amount'] ?? 0)
        );
    }

    /**
     * @return array{header: string, header_key: string, item: string, item_key: string}
     */
    private function getTables(string $type): array
    {
        if (!isset(self::TABLES[$type])) {
            throw new \InvalidArgumentException("Unknown voucher document type: $type");
        }

        return self::TABLES[$type];
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
