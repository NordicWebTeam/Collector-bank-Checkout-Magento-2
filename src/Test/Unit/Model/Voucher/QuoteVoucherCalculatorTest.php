<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Test\Unit\Model\Voucher;

use PHPUnit\Framework\TestCase;
use Webbhuset\CollectorCheckout\Model\Voucher\QuoteVoucherCalculator;
use Webbhuset\CollectorCheckout\Model\Voucher\TaxableLine;
use Webbhuset\CollectorCheckout\Model\Voucher\VoucherAllocator;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\AppliedVoucher;

class QuoteVoucherCalculatorTest extends TestCase
{
    private QuoteVoucherCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new QuoteVoucherCalculator(new VoucherAllocator());
    }

    public function testCalculatesVoucherVatPerRateAndSplitsItOverLines(): void
    {
        $result = $this->calculator->calculate(
            [$this->buildVoucher(204.4)],
            [new TaxableLine('a', 100.0, 25.0), new TaxableLine('b', 200.0, 25.0)],
            $this->buildShipping(0.0),
            1.0
        );

        $this->assertSame(204.4, $result->getAmount());
        $this->assertSame(40.88, $result->getTaxAmount());
        $this->assertSame(163.52, $result->getNetAmount());
        $this->assertSame(68.13, $result->getLine('a')->getAmount());
        $this->assertSame(13.63, $result->getLine('a')->getTaxAmount());
        $this->assertSame(54.5, $result->getLine('a')->getNetAmount());
        $this->assertSame(136.27, $result->getLine('b')->getAmount());
        $this->assertSame(27.25, $result->getLine('b')->getTaxAmount());
    }

    public function testOnlyTaxedLinesGetVoucherVat(): void
    {
        $result = $this->calculator->calculate(
            [$this->buildVoucher(50.0)],
            [new TaxableLine('untaxed', 68.0, 0.0), new TaxableLine('taxed', 38.0, 25.0)],
            $this->buildShipping(0.0),
            1.0
        );

        $this->assertSame(32.08, $result->getLine('untaxed')->getAmount());
        $this->assertSame(0.0, $result->getLine('untaxed')->getTaxAmount());
        $this->assertSame(17.92, $result->getLine('taxed')->getAmount());
        $this->assertSame(3.58, $result->getLine('taxed')->getTaxAmount());
        $this->assertSame(3.58, $result->getTaxAmount());
    }

    public function testRatesAreRoundedPerRateGroup(): void
    {
        $result = $this->calculator->calculate(
            [$this->buildVoucher(30.0)],
            [
                new TaxableLine('food', 50.0, 12.0),
                new TaxableLine('book', 50.0, 6.0),
                new TaxableLine('shirt', 50.0, 25.0),
            ],
            $this->buildShipping(0.0),
            1.0
        );

        $this->assertSame(1.07, $result->getLine('food')->getTaxAmount());
        $this->assertSame(0.57, $result->getLine('book')->getTaxAmount());
        $this->assertSame(2.0, $result->getLine('shirt')->getTaxAmount());
        $this->assertSame(3.64, $result->getTaxAmount());
    }

    public function testVoucherVatNeverExceedsTheVatOnTheLines(): void
    {
        // Magento rounds VAT per unit: 3 x 0.20 = 0.60, while 3.03 x 25/125 rounds to 0.61
        $result = $this->calculator->calculate(
            [$this->buildVoucher(3.03)],
            [new TaxableLine('a', 3.03, 25.0, 0.60, 0.60)],
            $this->buildShipping(0.0),
            1.0
        );

        $this->assertSame(0.6, $result->getLine('a')->getTaxAmount());
        $this->assertSame(0.6, $result->getLine('a')->getBaseTaxAmount());
        $this->assertSame(2.43, $result->getNetAmount());
    }

    public function testVatCappedOnOneLineMovesToLinesWithRoom(): void
    {
        $result = $this->calculator->calculate(
            [$this->buildVoucher(20.0)],
            [new TaxableLine('a', 10.0, 25.0, 1.5, 1.5), new TaxableLine('b', 10.0, 25.0, 2.0, 2.0)],
            $this->buildShipping(0.0),
            1.0
        );

        $this->assertSame(1.5, $result->getLine('a')->getTaxAmount());
        $this->assertSame(2.0, $result->getLine('b')->getTaxAmount());
        $this->assertSame(3.5, $result->getTaxAmount());
        $this->assertSame(20.0, $result->getAmount());
    }

    public function testShippingShareGetsShippingVat(): void
    {
        $result = $this->calculator->calculate(
            [$this->buildVoucher(320.0)],
            [new TaxableLine('a', 100.0, 25.0), new TaxableLine('b', 200.0, 25.0)],
            $this->buildShipping(50.0),
            1.0
        );

        $this->assertSame(20.0, $result->getShipping()->getAmount());
        $this->assertSame(4.0, $result->getShipping()->getTaxAmount());
        $this->assertSame(64.0, $result->getTaxAmount());
        $this->assertSame(256.0, $result->getNetAmount());
    }

    public function testConvertsToBaseCurrencyWithSeparateRounding(): void
    {
        $result = $this->calculator->calculate(
            [$this->buildVoucher(204.4)],
            [new TaxableLine('a', 100.0, 25.0), new TaxableLine('b', 200.0, 25.0)],
            $this->buildShipping(0.0),
            2.0
        );

        $this->assertSame(102.2, $result->getBaseAmount());
        $this->assertSame(34.07, $result->getLine('a')->getBaseAmount());
        $this->assertSame(68.13, $result->getLine('b')->getBaseAmount());
        $this->assertSame(20.44, $result->getBaseTaxAmount());
        $this->assertSame(6.81, $result->getLine('a')->getBaseTaxAmount());
        $this->assertSame(13.63, $result->getLine('b')->getBaseTaxAmount());
        $this->assertSame(81.76, $result->getBaseNetAmount());
    }

    public function testCapsVouchersAndReportsDeductedAmountPerVoucher(): void
    {
        $result = $this->calculator->calculate(
            [$this->buildVoucher(204.4, 'v1'), $this->buildVoucher(204.4, 'v2')],
            [new TaxableLine('a', 100.0, 25.0), new TaxableLine('b', 200.0, 25.0)],
            $this->buildShipping(0.0),
            1.0
        );

        $this->assertSame(300.0, $result->getAmount());
        $this->assertSame(60.0, $result->getTaxAmount());
        $this->assertSame(['v1' => 204.4, 'v2' => 95.6], $result->getVoucherAmounts());
        $this->assertSame(['v1' => 204.4, 'v2' => 95.6], $result->getBaseVoucherAmounts());
    }

    public function testEmptyResultWithoutVouchers(): void
    {
        $result = $this->calculator->calculate(
            [],
            [new TaxableLine('a', 100.0, 25.0)],
            $this->buildShipping(0.0),
            1.0
        );

        $this->assertTrue($result->isEmpty());
        $this->assertSame(0.0, $result->getNetAmount());
        $this->assertSame(0.0, $result->getLine('a')->getTaxAmount());
    }

    public function testThrowsOnInvalidBaseRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->calculate([], [], $this->buildShipping(0.0), 0.0);
    }

    private function buildShipping(float $amount): TaxableLine
    {
        return new TaxableLine(QuoteVoucherCalculator::SHIPPING_KEY, $amount, 25.0);
    }

    private function buildVoucher(float $amount, string $voucherId = 'v1'): AppliedVoucher
    {
        return new AppliedVoucher($voucherId, 'CODE-' . $voucherId, 'Reward vouchers', $amount);
    }
}
