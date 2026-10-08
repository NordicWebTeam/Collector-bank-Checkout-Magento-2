<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Test\Unit\Model\Voucher;

use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Webbhuset\CollectorCheckout\Model\Voucher\QuoteVoucherProvider;
use Webbhuset\CollectorCheckout\Model\Voucher\VoucherApplier;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\AppliedVoucher;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\CheckoutData;

class VoucherApplierTest extends TestCase
{
    /**
     * @var QuoteVoucherProvider&MockObject
     */
    private $provider;

    /**
     * @var Quote&MockObject
     */
    private $quote;

    private VoucherApplier $applier;

    protected function setUp(): void
    {
        $this->provider = $this->createMock(QuoteVoucherProvider::class);
        $this->quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $this->quote->setTotalsCollectedFlag(true);
        $this->applier = new VoucherApplier($this->provider);
    }

    public function testStoresNewVouchersAndMarksTotalsForRecollection(): void
    {
        $vouchers = [$this->buildVoucher('v1', 204.4)];
        $this->provider->method('getVouchers')->willReturn([]);
        $this->provider->expects($this->once())->method('saveVouchers')->with($this->quote, $vouchers);

        $this->assertTrue($this->applier->apply($this->quote, $this->buildCheckoutData($vouchers)));
        $this->assertFalse($this->quote->getTotalsCollectedFlag());
    }

    public function testDoesNothingWhenVouchersAreUnchanged(): void
    {
        $this->provider->method('getVouchers')->willReturn([$this->buildVoucher('v1', 204.4)]);
        $this->provider->expects($this->never())->method('saveVouchers');

        $changed = $this->applier->apply($this->quote, $this->buildCheckoutData([$this->buildVoucher('v1', 204.4)]));

        $this->assertFalse($changed);
        $this->assertTrue($this->quote->getTotalsCollectedFlag());
    }

    public function testReplacesVouchersWhenAmountChanged(): void
    {
        $this->provider->method('getVouchers')->willReturn([$this->buildVoucher('v1', 100.0)]);
        $this->provider->expects($this->once())->method('saveVouchers');

        $this->assertTrue($this->applier->apply($this->quote, $this->buildCheckoutData([$this->buildVoucher('v1', 204.4)])));
    }

    public function testRemovesVouchersWhenCustomerRemovedThem(): void
    {
        $this->provider->method('getVouchers')->willReturn([$this->buildVoucher('v1', 204.4)]);
        $this->provider->expects($this->once())->method('saveVouchers')->with($this->quote, []);

        $this->assertTrue($this->applier->apply($this->quote, $this->buildCheckoutData([])));
    }

    public function testDoesNothingWithoutVouchers(): void
    {
        $this->provider->method('getVouchers')->willReturn([]);
        $this->provider->expects($this->never())->method('saveVouchers');

        $this->assertFalse($this->applier->apply($this->quote, $this->buildCheckoutData([])));
    }

    /**
     * @param AppliedVoucher[] $vouchers
     * @return CheckoutData&MockObject
     */
    private function buildCheckoutData(array $vouchers)
    {
        $checkoutData = $this->createMock(CheckoutData::class);
        $checkoutData->method('getAppliedVouchers')->willReturn($vouchers);

        return $checkoutData;
    }

    private function buildVoucher(string $voucherId, float $amount): AppliedVoucher
    {
        return new AppliedVoucher($voucherId, '0000000000017', 'Reward vouchers', $amount);
    }
}
