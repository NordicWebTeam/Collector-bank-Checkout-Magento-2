<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Test\Unit\Model\Voucher;

use PHPUnit\Framework\TestCase;
use Webbhuset\CollectorCheckout\Model\Voucher\AppliedTaxesAdjuster;
use Webbhuset\CollectorCheckout\Model\Voucher\LineShare;

class AppliedTaxesAdjusterTest extends TestCase
{
    private AppliedTaxesAdjuster $adjuster;

    protected function setUp(): void
    {
        $this->adjuster = new AppliedTaxesAdjuster();
    }

    public function testReducesItemAndAddressBreakdownByVoucherVat(): void
    {
        $itemsAppliedTaxes = [
            'sequence-1' => [$this->buildAppliedTax('SE', 25.0, 20.0, 10.0, 1)],
            'shipping' => [$this->buildAppliedTax('SE', 25.0, 8.0, 4.0, null)],
        ];
        $appliedTaxes = ['SE' => $this->buildAppliedTax('SE', 25.0, 28.0, 14.0, null)];

        $result = $this->adjuster->reduce($itemsAppliedTaxes, $appliedTaxes, [
            'sequence-1' => new LineShare(25.0, 12.5, 5.0, 2.5),
            'shipping' => new LineShare(10.0, 5.0, 2.0, 1.0),
        ]);

        $this->assertSame(15.0, $result->getItemsAppliedTaxes()['sequence-1'][0]['amount']);
        $this->assertSame(7.5, $result->getItemsAppliedTaxes()['sequence-1'][0]['base_amount']);
        $this->assertSame(6.0, $result->getItemsAppliedTaxes()['shipping'][0]['amount']);
        $this->assertSame(21.0, $result->getAppliedTaxes()['SE']['amount']);
        $this->assertSame(10.5, $result->getAppliedTaxes()['SE']['base_amount']);
        $this->assertSame(1, $result->getItemsAppliedTaxes()['sequence-1'][0]['item_id']);
    }

    public function testDoesNotChangeTheInputArrays(): void
    {
        $itemsAppliedTaxes = ['sequence-1' => [$this->buildAppliedTax('SE', 25.0, 20.0, 20.0, 1)]];
        $appliedTaxes = ['SE' => $this->buildAppliedTax('SE', 25.0, 20.0, 20.0, null)];

        $this->adjuster->reduce($itemsAppliedTaxes, $appliedTaxes, [
            'sequence-1' => new LineShare(25.0, 25.0, 5.0, 5.0),
        ]);

        $this->assertSame(20.0, $itemsAppliedTaxes['sequence-1'][0]['amount']);
        $this->assertSame(20.0, $appliedTaxes['SE']['amount']);
    }

    public function testSplitsReductionByPercentWhenALineHasSeveralRates(): void
    {
        $itemsAppliedTaxes = ['sequence-1' => [
            $this->buildAppliedTax('STATE', 6.0, 6.0, 6.0, 1),
            $this->buildAppliedTax('CITY', 2.0, 2.0, 2.0, 1),
        ]];
        $appliedTaxes = [
            'STATE' => $this->buildAppliedTax('STATE', 6.0, 6.0, 6.0, null),
            'CITY' => $this->buildAppliedTax('CITY', 2.0, 2.0, 2.0, null),
        ];

        $result = $this->adjuster->reduce($itemsAppliedTaxes, $appliedTaxes, [
            'sequence-1' => new LineShare(10.8, 10.8, 0.8, 0.8),
        ]);

        $this->assertSame(5.4, $result->getItemsAppliedTaxes()['sequence-1'][0]['amount']);
        $this->assertSame(1.8, $result->getItemsAppliedTaxes()['sequence-1'][1]['amount']);
        $this->assertSame(5.4, $result->getAppliedTaxes()['STATE']['amount']);
        $this->assertSame(1.8, $result->getAppliedTaxes()['CITY']['amount']);
    }

    public function testIgnoresLinesWithoutVoucherVatOrWithoutBreakdown(): void
    {
        $itemsAppliedTaxes = ['sequence-1' => [$this->buildAppliedTax('SE', 25.0, 20.0, 20.0, 1)]];
        $appliedTaxes = ['SE' => $this->buildAppliedTax('SE', 25.0, 20.0, 20.0, null)];

        $result = $this->adjuster->reduce($itemsAppliedTaxes, $appliedTaxes, [
            'sequence-1' => LineShare::empty(),
            'sequence-9' => new LineShare(10.0, 10.0, 2.0, 2.0),
        ]);

        $this->assertSame($itemsAppliedTaxes, $result->getItemsAppliedTaxes());
        $this->assertSame($appliedTaxes, $result->getAppliedTaxes());
    }

    public function testNeverReducesBelowZero(): void
    {
        $itemsAppliedTaxes = ['sequence-1' => [$this->buildAppliedTax('SE', 25.0, 1.0, 1.0, 1)]];
        $appliedTaxes = ['SE' => $this->buildAppliedTax('SE', 25.0, 1.0, 1.0, null)];

        $result = $this->adjuster->reduce($itemsAppliedTaxes, $appliedTaxes, [
            'sequence-1' => new LineShare(25.0, 25.0, 5.0, 5.0),
        ]);

        $this->assertSame(0.0, $result->getItemsAppliedTaxes()['sequence-1'][0]['amount']);
        $this->assertSame(0.0, $result->getAppliedTaxes()['SE']['amount']);
    }

    /**
     * Same shape as CommonTaxCollector::convertAppliedTaxes()
     *
     * @return array<string, mixed>
     */
    private function buildAppliedTax(string $id, float $percent, float $amount, float $baseAmount, ?int $itemId): array
    {
        return [
            'amount' => $amount,
            'base_amount' => $baseAmount,
            'percent' => $percent,
            'id' => $id,
            'rates' => [['percent' => $percent, 'code' => $id, 'title' => $id]],
            'item_id' => $itemId,
            'item_type' => 'product',
            'associated_item_id' => null,
        ];
    }
}
