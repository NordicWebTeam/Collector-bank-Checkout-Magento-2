<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Test\Unit\Model\Voucher;

use PHPUnit\Framework\TestCase;
use Webbhuset\CollectorCheckout\Model\Voucher\DocumentLine;
use Webbhuset\CollectorCheckout\Model\Voucher\DocumentVoucherCalculator;
use Webbhuset\CollectorCheckout\Model\Voucher\QuoteVoucherCalculator;
use Webbhuset\CollectorCheckout\Model\Voucher\VoucherAllocator;

/**
 * Walley's example: SKU-A 50 kr and SKU-B 150 kr incl. 25% VAT, voucher 60 kr.
 * Proportional order shares: A 15 (VAT 3), B 45 (VAT 9).
 */
class DocumentVoucherCalculatorTest extends TestCase
{
    private DocumentVoucherCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new DocumentVoucherCalculator(new QuoteVoucherCalculator(new VoucherAllocator()));
    }

    public function testFirstInvoiceUsesAsMuchVoucherAsItCanCarry(): void
    {
        // Invoice 1: SKU-A only. Full VAT 10, of which Magento already removed 3 (order share).
        $result = $this->calculator->calculate(
            [new DocumentLine('A', 40.0, 10.0, 0.0, 0.0, 25.0, 40.0, 10.0)],
            60.0,
            12.0,
            false,
            1.0
        );

        $this->assertSame(50.0, $result->getAmount());
        $this->assertSame(10.0, $result->getTaxAmount());
        $this->assertSame(50.0, $result->getLine('A')->getAmount());
        $this->assertSame(10.0, $result->getLine('A')->getTaxAmount());
        $this->assertSame(0.0, $result->getLineTax('A'));
        $this->assertSame(40.0, $result->getNetAmount());
    }

    public function testLastInvoiceTakesExactlyTheRemainingVoucher(): void
    {
        // Invoice 2: SKU-B, last invoice. Remaining voucher 10 with VAT 2.
        $result = $this->calculator->calculate(
            [new DocumentLine('B', 120.0, 30.0, 0.0, 0.0, 25.0, 120.0, 30.0)],
            10.0,
            2.0,
            true,
            1.0
        );

        $this->assertSame(10.0, $result->getAmount());
        $this->assertSame(2.0, $result->getTaxAmount());
        $this->assertSame(28.0, $result->getLineTax('B'));
        $this->assertSame(8.0, $result->getNetAmount());
    }

    public function testLastInvoiceForcesRemainingVatEvenWhenRoundingDiffers(): void
    {
        // Group VAT would round 3.03 * 0.2 = 0.61, but only 0.60 voucher VAT remains
        $result = $this->calculator->calculate(
            [new DocumentLine('a', 2.43, 0.60, 0.0, 0.0, 25.0, 2.43, 0.60)],
            3.03,
            0.60,
            true,
            1.0
        );

        $this->assertSame(0.6, $result->getTaxAmount());
        $this->assertSame(0.0, $result->getLineTax('a'));
    }

    public function testCoversItemsFirstThenShippingLikeTheQuote(): void
    {
        $result = $this->calculator->calculate(
            [
                new DocumentLine('A', 40.0, 10.0, 0.0, 0.0, 25.0, 40.0, 10.0),
                new DocumentLine(QuoteVoucherCalculator::SHIPPING_KEY, 40.0, 10.0, 0.0, 0.0, 25.0, 40.0, 10.0),
            ],
            60.0,
            12.0,
            false,
            1.0
        );

        $this->assertSame(60.0, $result->getAmount());
        $this->assertSame(50.0, $result->getLine('A')->getAmount());
        $this->assertSame(10.0, $result->getShipping()->getAmount());
        $this->assertSame(0.0, $result->getLineTax('A'));
        $this->assertSame(8.0, $result->getLineTax(QuoteVoucherCalculator::SHIPPING_KEY));
    }

    public function testNothingWhenVoucherIsUsedUp(): void
    {
        $result = $this->calculator->calculate(
            [new DocumentLine('B', 120.0, 30.0, 0.0, 0.0, 25.0, 120.0, 30.0)],
            0.0,
            0.0,
            false,
            1.0
        );

        $this->assertTrue($result->isEmpty());
        $this->assertSame(30.0, $result->getLineTax('B'));
    }

    public function testCapacityIncludesDiscountAndCompensation(): void
    {
        // Row 80 net, full VAT 20, discount 25 gross (prices incl. tax) with 5 compensation: costs 80 incl. VAT
        $line = new DocumentLine('A', 80.0, 20.0, 25.0, 5.0, 25.0, 80.0, 20.0);

        $this->assertSame(80.0, $line->getCapacity(false));
    }
}
