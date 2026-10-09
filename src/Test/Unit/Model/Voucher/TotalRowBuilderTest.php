<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Test\Unit\Model\Voucher;

use PHPUnit\Framework\TestCase;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\DocumentAmounts;
use Webbhuset\CollectorCheckout\Model\Voucher\TotalRowBuilder;

class TotalRowBuilderTest extends TestCase
{
    private TotalRowBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new TotalRowBuilder();
    }

    public function testShowsGrossAmountWhenSubtotalIsShownInclTax(): void
    {
        $row = $this->builder->build($this->buildAmounts(50.0, 25.0, 10.0, 5.0), false);

        $this->assertSame(TotalRowBuilder::CODE, $row['code']);
        $this->assertSame(-50.0, $row['value']);
        $this->assertSame(-25.0, $row['base_value']);
    }

    public function testShowsNetAmountWhenSubtotalIsShownExclTax(): void
    {
        $row = $this->builder->build($this->buildAmounts(50.0, 25.0, 10.0, 5.0), true);

        $this->assertSame(-40.0, $row['value']);
        $this->assertSame(-20.0, $row['base_value']);
    }

    public function testNoRowWithoutVoucherAmounts(): void
    {
        $this->assertNull($this->builder->build(null, false));
        $this->assertNull($this->builder->build($this->buildAmounts(0.0, 0.0, 0.0, 0.0), false));
    }

    public function testPlacesRowAfterTheFirstExistingAnchor(): void
    {
        $this->assertSame('discount', $this->builder->getAnchor(['subtotal', 'shipping', 'discount', 'grand_total']));
        $this->assertSame('shipping', $this->builder->getAnchor(['subtotal', 'shipping', 'grand_total']));
        $this->assertSame('subtotal', $this->builder->getAnchor(['subtotal', 'grand_total']));
        $this->assertNull($this->builder->getAnchor(['grand_total']));
    }

    public function testPlacesRowAfterTheInclTaxRowsWhenBothAreShown(): void
    {
        $this->assertSame(
            'shipping_incl',
            $this->builder->getAnchor(['subtotal_excl', 'subtotal_incl', 'shipping', 'shipping_incl', 'tax', 'grand_total'])
        );
        $this->assertSame('subtotal_incl', $this->builder->getAnchor(['subtotal_excl', 'subtotal_incl', 'grand_total']));
    }

    private function buildAmounts(float $amount, float $baseAmount, float $tax, float $baseTax): DocumentAmounts
    {
        return new DocumentAmounts(1, 1, $amount, $baseAmount, 0.0, 0.0, [], $tax, $baseTax);
    }
}
