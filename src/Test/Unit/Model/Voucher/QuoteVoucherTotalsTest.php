<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Test\Unit\Model\Voucher;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Webbhuset\CollectorCheckout\Model\Total\Quote\WalleyVoucher;
use Webbhuset\CollectorCheckout\Model\Voucher\LineShare;
use Webbhuset\CollectorCheckout\Model\Voucher\QuoteVoucherProvider;
use Webbhuset\CollectorCheckout\Model\Voucher\QuoteVoucherResult;
use Webbhuset\CollectorCheckout\Model\Voucher\QuoteVoucherTotals;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\AppliedVoucher;

class QuoteVoucherTotalsTest extends TestCase
{
    /**
     * @var QuoteVoucherProvider&MockObject
     */
    private $provider;

    private QuoteVoucherTotals $totals;

    protected function setUp(): void
    {
        $this->provider = $this->createMock(QuoteVoucherProvider::class);
        $this->totals = new QuoteVoucherTotals($this->provider);
    }

    public function testReturnsAmountOfCollectedResult(): void
    {
        $quote = $this->buildQuote($this->buildResult(50.0));
        $quote->expects($this->never())->method('collectTotals');

        $this->assertSame(50.0, $this->totals->getAmount($quote));
    }

    public function testCollectsTotalsWhenVouchersExistButResultIsMissing(): void
    {
        $address = $this->buildAddress(null);
        $quote = $this->buildQuoteWithAddress($address);
        $this->provider->method('getVouchers')->willReturn([new AppliedVoucher('v1', 'c', 'd', 50.0)]);
        $quote->expects($this->once())->method('collectTotals')->willReturnCallback(
            function () use ($address, $quote) {
                $address->setData(WalleyVoucher::RESULT_KEY, $this->buildResult(50.0));

                return $quote;
            }
        );

        $this->assertSame(50.0, $this->totals->getAmount($quote));
        $this->assertFalse((bool) $quote->getTotalsCollectedFlag());
    }

    public function testCollectedResultCollectsTotalsWhenResultIsMissing(): void
    {
        $address = $this->buildAddress(null);
        $quote = $this->buildQuoteWithAddress($address);
        $result = $this->buildResult(10.0);
        $this->provider->method('getVouchers')->willReturn([new AppliedVoucher('v1', 'c', 'd', 10.0)]);
        $quote->expects($this->once())->method('collectTotals')->willReturnCallback(
            function () use ($address, $quote, $result) {
                $address->setData(WalleyVoucher::RESULT_KEY, $result);

                return $quote;
            }
        );

        $this->assertSame($result, $this->totals->getCollectedResult($quote));
    }

    public function testDoesNotCollectTotalsWithoutVouchers(): void
    {
        $quote = $this->buildQuote(null);
        $this->provider->method('getVouchers')->willReturn([]);
        $quote->expects($this->never())->method('collectTotals');

        $this->assertSame(0.0, $this->totals->getAmount($quote));
    }

    public function testGetResultNeverCollectsTotals(): void
    {
        $quote = $this->buildQuote(null);
        $this->provider->method('getVouchers')->willReturn([new AppliedVoucher('v1', 'c', 'd', 50.0)]);
        $quote->expects($this->never())->method('collectTotals');

        $this->assertNull($this->totals->getResult($quote));
    }

    /**
     * @return Quote&MockObject
     */
    private function buildQuote(?QuoteVoucherResult $result)
    {
        return $this->buildQuoteWithAddress($this->buildAddress($result));
    }

    /**
     * @return Quote&MockObject
     */
    private function buildQuoteWithAddress(Address $address)
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isVirtual', 'getShippingAddress', 'getBillingAddress', 'collectTotals'])
            ->getMock();
        $quote->method('isVirtual')->willReturn(false);
        $quote->method('getShippingAddress')->willReturn($address);

        return $quote;
    }

    private function buildAddress(?QuoteVoucherResult $result): Address
    {
        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $address->setData(WalleyVoucher::RESULT_KEY, $result);

        return $address;
    }

    private function buildResult(float $amount): QuoteVoucherResult
    {
        return new QuoteVoucherResult(
            ['a' => new LineShare($amount, $amount, $amount, $amount)],
            LineShare::empty(),
            ['v1' => $amount],
            ['v1' => $amount]
        );
    }
}
