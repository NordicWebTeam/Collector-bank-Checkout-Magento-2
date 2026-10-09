<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Test\Unit\Model\Voucher\Walley;

use PHPUnit\Framework\TestCase;
use Webbhuset\CollectorCheckout\Model\Voucher\Walley\CaptureLine;
use Webbhuset\CollectorCheckout\Model\Voucher\Walley\VoucherRowCalculator;
use Webbhuset\CollectorCheckout\Model\Voucher\Walley\WalleyRow;

class VoucherRowCalculatorTest extends TestCase
{
    private VoucherRowCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new VoucherRowCalculator();
    }

    public function testCaptureRowsForWalleysExample(): void
    {
        // Invoice 1: SKU-A 50 kr (1 unit) carrying 50 kr of the voucher
        $rows = $this->calculator->captureRows([$this->buildLine('SKU-A', 50.0, 1, 0.0, 50.0)], 0.0);

        $this->assertSame([
            ['SKU-A', 'Product SKU-A', 50.0, 1, 25.0],
            ['TEST50', 'Reward vouchers', -50.0, 1, 25.0],
        ], $this->toArrays($rows));
        $this->assertSame(0.0, $this->calculator->getTotal($rows));
    }

    public function testDiscountAndVoucherRowsPerUnit(): void
    {
        $rows = $this->calculator->captureRows([$this->buildLine('SKU-A', 100.0, 2, 20.0, 30.0)], 150.0);

        $this->assertSame([
            ['SKU-A', 'Product SKU-A', 100.0, 2, 25.0],
            ['SKU-A', 'Discount', -10.0, 2, 25.0],
            ['TEST50', 'Reward vouchers', -15.0, 2, 25.0],
        ], $this->toArrays($rows));
    }

    public function testRoundingRowMakesTheCaptureEqualTheInvoiceTotal(): void
    {
        // A voucher share of 10.00 over 3 units is 3.33 per unit, 9.99 in total
        $rows = $this->calculator->captureRows([$this->buildLine('SKU-A', 10.0, 3, 0.0, 10.0)], 20.0);

        $last = end($rows);
        $this->assertSame(VoucherRowCalculator::ROUNDING_ID, $last->getId());
        $this->assertSame(-0.01, $last->getUnitPrice());
        $this->assertSame(0.0, $last->getVat());
        $this->assertSame(20.0, $this->calculator->getTotal($rows));
    }

    public function testRefundRowsReuseTheCapturedUnitPrices(): void
    {
        $line = $this->buildLine('SKU-A', 10.0, 3, 0.0, 10.0);

        $rows = $this->calculator->refundRows([$this->calculator->refundLine($line, 1)]);

        $this->assertSame([
            ['SKU-A', 'Product SKU-A', 10.0, 1, 25.0],
            ['TEST50', 'Reward vouchers', -3.33, 1, 25.0],
        ], $this->toArrays($rows));
        $this->assertSame(6.67, $this->calculator->getTotal($rows));
    }

    public function testNoZeroRows(): void
    {
        $rows = $this->calculator->captureRows([$this->buildLine('SKU-A', 50.0, 1, 0.0, 0.0)], 50.0);

        $this->assertCount(1, $rows);
    }

    private function buildLine(string $sku, float $unitPrice, int $qty, float $discount, float $voucher): CaptureLine
    {
        return new CaptureLine($sku, "Product $sku", $unitPrice, $qty, 25.0, $discount, $voucher, 'TEST50', 'Reward vouchers');
    }

    /**
     * @param WalleyRow[] $rows
     * @return array<int, array{0: string, 1: string, 2: float, 3: int, 4: float}>
     */
    private function toArrays(array $rows): array
    {
        return array_map(
            fn (WalleyRow $row): array => [$row->getId(), $row->getDescription(), $row->getUnitPrice(), $row->getQuantity(), $row->getVat()],
            $rows
        );
    }
}
