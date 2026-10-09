<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Test\Unit\Model\Voucher;

use PHPUnit\Framework\TestCase;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\VoucherRecord;
use Webbhuset\CollectorCheckout\Model\Voucher\VoucherComparison;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\AppliedVoucher;

class VoucherComparisonTest extends TestCase
{
    private VoucherComparison $comparison;

    protected function setUp(): void
    {
        $this->comparison = new VoucherComparison();
    }

    public function testNoDifferencesWhenVouchersMatch(): void
    {
        $differences = $this->comparison->getDifferences(
            [$this->buildRecord('v1', 204.4)],
            [$this->buildApplied('v1', 204.4)]
        );

        $this->assertSame([], $differences);
    }

    public function testNoDifferencesWithoutVouchers(): void
    {
        $this->assertSame([], $this->comparison->getDifferences([], []));
    }

    public function testReportsVoucherMissingAtWalley(): void
    {
        $differences = $this->comparison->getDifferences([$this->buildRecord('v1', 204.4)], []);

        $this->assertSame(['Voucher 0000v1 (v1) is on the order but no longer applied at Walley'], $differences);
    }

    public function testReportsVoucherAddedAtWalley(): void
    {
        $differences = $this->comparison->getDifferences([], [$this->buildApplied('v2', 50.0)]);

        $this->assertSame(['Voucher 0000v2 (v2) is applied at Walley but not on the order'], $differences);
    }

    public function testReportsChangedFaceAmount(): void
    {
        $differences = $this->comparison->getDifferences(
            [$this->buildRecord('v1', 204.4)],
            [$this->buildApplied('v1', 100.0)]
        );

        $this->assertSame(['Voucher 0000v1 (v1) changed amount from 204.40 to 100.00'], $differences);
    }

    private function buildRecord(string $voucherId, float $faceAmount): VoucherRecord
    {
        return new VoucherRecord(1, 2, $voucherId, '0000' . $voucherId, 'Reward vouchers', $faceAmount, $faceAmount, $faceAmount);
    }

    private function buildApplied(string $voucherId, float $amount): AppliedVoucher
    {
        return new AppliedVoucher($voucherId, '0000' . $voucherId, 'Reward vouchers', $amount);
    }
}
