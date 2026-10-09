<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Test\Unit\Model\Voucher;

use PHPUnit\Framework\TestCase;
use Webbhuset\CollectorCheckout\Model\Voucher\MinorUnitSplitter;

class MinorUnitSplitterTest extends TestCase
{
    private MinorUnitSplitter $splitter;

    protected function setUp(): void
    {
        $this->splitter = new MinorUnitSplitter();
    }

    public function testSharesAddUpToTheAmount(): void
    {
        $this->assertSame(['a' => 334, 'b' => 333, 'c' => 333], $this->splitter->split(1000, ['a' => 1, 'b' => 1, 'c' => 1]));
    }

    public function testEqualRemaindersGoToTheEarlierKey(): void
    {
        $this->assertSame(['x' => 1, 'y' => 0], $this->splitter->split(1, ['x' => 5, 'y' => 5]));
        $this->assertSame(['y' => 1, 'x' => 0], $this->splitter->split(1, ['y' => 5, 'x' => 5]));
    }

    public function testLargestRemainderWins(): void
    {
        $this->assertSame(['a' => 6813, 'b' => 13627], $this->splitter->split(20440, ['a' => 10000, 'b' => 20000]));
    }

    public function testZeroWeightsGetNothing(): void
    {
        $this->assertSame(['a' => 0, 'b' => 0], $this->splitter->split(100, ['a' => 0, 'b' => 0]));
    }
}
