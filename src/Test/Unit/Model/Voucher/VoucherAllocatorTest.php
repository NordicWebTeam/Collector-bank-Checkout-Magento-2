<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Test\Unit\Model\Voucher;

use PHPUnit\Framework\TestCase;
use Webbhuset\CollectorCheckout\Model\Voucher\VoucherAllocator;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\AppliedVoucher;

class VoucherAllocatorTest extends TestCase
{
    private VoucherAllocator $allocator;

    protected function setUp(): void
    {
        $this->allocator = new VoucherAllocator();
    }

    public function testReturnsEmptyAllocationWithoutVouchers(): void
    {
        $allocation = $this->allocator->allocate([], ['item-1' => 100.0], 49.0);

        $this->assertTrue($allocation->isEmpty());
        $this->assertSame(0.0, $allocation->getTotal());
        $this->assertSame(['item-1' => 0.0], $allocation->getLineAmounts());
        $this->assertSame(0.0, $allocation->getShippingAmount());
        $this->assertSame([], $allocation->getVoucherAmounts());
    }

    public function testSplitsVoucherProportionallyAcrossItems(): void
    {
        $allocation = $this->allocator->allocate(
            [$this->buildVoucher('v1', 204.4)],
            ['item-1' => 100.0, 'item-2' => 200.0],
            0.0
        );

        $this->assertFalse($allocation->isEmpty());
        $this->assertSame(204.4, $allocation->getTotal());
        $this->assertSame(['item-1' => 68.13, 'item-2' => 136.27], $allocation->getLineAmounts());
        $this->assertSame(0.0, $allocation->getShippingAmount());
        $this->assertSame(['v1' => 204.4], $allocation->getVoucherAmounts());
    }

    public function testPutsRemainderOnShippingWhenVoucherExceedsItems(): void
    {
        $allocation = $this->allocator->allocate(
            [$this->buildVoucher('v1', 320.0)],
            ['item-1' => 100.0, 'item-2' => 200.0],
            49.0
        );

        $this->assertSame(['item-1' => 100.0, 'item-2' => 200.0], $allocation->getLineAmounts());
        $this->assertSame(20.0, $allocation->getShippingAmount());
        $this->assertSame(320.0, $allocation->getTotal());
    }

    public function testCapsVouchersAtOrderTotalInVoucherOrder(): void
    {
        $allocation = $this->allocator->allocate(
            [$this->buildVoucher('v1', 204.4), $this->buildVoucher('v2', 204.4)],
            ['item-1' => 100.0, 'item-2' => 200.0],
            0.0
        );

        $this->assertSame(300.0, $allocation->getTotal());
        $this->assertSame(['item-1' => 100.0, 'item-2' => 200.0], $allocation->getLineAmounts());
        $this->assertSame(['v1' => 204.4, 'v2' => 95.6], $allocation->getVoucherAmounts());
    }

    public function testOmitsVouchersThatAreFullyCappedAway(): void
    {
        $allocation = $this->allocator->allocate(
            [$this->buildVoucher('v1', 300.0), $this->buildVoucher('v2', 50.0)],
            ['item-1' => 300.0],
            0.0
        );

        $this->assertSame(['v1' => 300.0], $allocation->getVoucherAmounts());
    }

    public function testLineSharesAlwaysAddUpToTotal(): void
    {
        $allocation = $this->allocator->allocate(
            [$this->buildVoucher('v1', 10.0)],
            ['a' => 33.33, 'b' => 33.33, 'c' => 33.34],
            0.0
        );

        $lineAmounts = $allocation->getLineAmounts();
        $this->assertSame(10.0, round(array_sum($lineAmounts), 2));
        foreach ($lineAmounts as $amount) {
            $this->assertEqualsWithDelta(3.33, $amount, 0.011);
        }
    }

    public function testNeverAllocatesToZeroAmountLines(): void
    {
        $allocation = $this->allocator->allocate(
            [$this->buildVoucher('v1', 50.0)],
            ['paid' => 100.0, 'free' => 0.0],
            0.0
        );

        $this->assertSame(['paid' => 50.0, 'free' => 0.0], $allocation->getLineAmounts());
    }

    public function testReturnsEmptyAllocationWhenNothingCanBeDiscounted(): void
    {
        $allocation = $this->allocator->allocate([$this->buildVoucher('v1', 50.0)], ['free' => 0.0], 0.0);

        $this->assertTrue($allocation->isEmpty());
        $this->assertSame([], $allocation->getVoucherAmounts());
    }

    public function testKeepsIntegerLineKeys(): void
    {
        $allocation = $this->allocator->allocate([$this->buildVoucher('v1', 30.0)], [12 => 60.0, 15 => 30.0], 0.0);

        $this->assertSame([12 => 20.0, 15 => 10.0], $allocation->getLineAmounts());
    }

    public function testThrowsOnNegativeLineAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->allocator->allocate([$this->buildVoucher('v1', 10.0)], ['item-1' => -5.0], 0.0);
    }

    public function testThrowsOnNegativeShippingAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->allocator->allocate([$this->buildVoucher('v1', 10.0)], ['item-1' => 5.0], -1.0);
    }

    private function buildVoucher(string $voucherId, float $amount): AppliedVoucher
    {
        return new AppliedVoucher($voucherId, 'CODE-' . $voucherId, 'Reward vouchers', $amount);
    }
}
