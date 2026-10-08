<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Test\Unit\Service\Sdk\Checkout\Checkout;

use PHPUnit\Framework\TestCase;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\AppliedVoucherParser;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Errors\ValidationError;

class AppliedVoucherParserTest extends TestCase
{
    private AppliedVoucherParser $parser;

    protected function setUp(): void
    {
        $this->parser = new AppliedVoucherParser();
    }

    public function testReturnsEmptyListWhenAppliedVouchersIsMissing(): void
    {
        $this->assertSame([], $this->parser->fromResponseData(['cart' => []]));
    }

    public function testReturnsEmptyListWhenAppliedVouchersIsEmpty(): void
    {
        $this->assertSame([], $this->parser->fromResponseData(['appliedVouchers' => []]));
    }

    public function testParsesVoucherFromWalleyPayload(): void
    {
        $vouchers = $this->parser->fromResponseData([
            'appliedVouchers' => [$this->buildVoucherData()],
        ]);

        $this->assertCount(1, $vouchers);
        $this->assertSame('70f8e58d-852c-4e6e-9215-0688e6716bf8', $vouchers[0]->getVoucherId());
        $this->assertSame('0000000000017', $vouchers[0]->getCode());
        $this->assertSame('Generate reward vouchers 19/03/2026 10:00', $vouchers[0]->getDescription());
        $this->assertSame(204.4, $vouchers[0]->getDiscountAmount());
    }

    public function testParsesSeveralVouchersInOrder(): void
    {
        $vouchers = $this->parser->fromResponseData([
            'appliedVouchers' => [
                $this->buildVoucherData(['voucherId' => 'b5dac7a4', 'code' => '0000000000086']),
                $this->buildVoucherData(['voucherId' => '4511b9b9', 'code' => '0000000000130']),
            ],
        ]);

        $this->assertSame(['0000000000086', '0000000000130'], [
            $vouchers[0]->getCode(),
            $vouchers[1]->getCode(),
        ]);
    }

    public function testAcceptsNumericStringAmountAndMissingDescription(): void
    {
        $data = $this->buildVoucherData(['discountAmount' => '50.5']);
        unset($data['description']);

        $vouchers = $this->parser->fromResponseData(['appliedVouchers' => [$data]]);

        $this->assertSame(50.5, $vouchers[0]->getDiscountAmount());
        $this->assertSame('', $vouchers[0]->getDescription());
    }

    public function testToArrayReturnsWalleyFieldNames(): void
    {
        $vouchers = $this->parser->fromResponseData(['appliedVouchers' => [$this->buildVoucherData()]]);

        $this->assertSame([
            'voucherId' => '70f8e58d-852c-4e6e-9215-0688e6716bf8',
            'code' => '0000000000017',
            'description' => 'Generate reward vouchers 19/03/2026 10:00',
            'discountAmount' => 204.4,
        ], $vouchers[0]->toArray());
    }

    /**
     * @dataProvider invalidVoucherProvider
     * @param array<string, mixed> $override
     */
    public function testThrowsOnInvalidVoucher(array $override, string $expectedMessage): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->parser->fromResponseData(['appliedVouchers' => [$this->buildVoucherData($override)]]);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidVoucherProvider(): array
    {
        return [
            'missing voucherId' => [['voucherId' => ''], 'voucherId'],
            'missing code' => [['code' => null], 'code'],
            'zero amount' => [['discountAmount' => 0], 'discountAmount'],
            'negative amount' => [['discountAmount' => -10], 'discountAmount'],
            'non numeric amount' => [['discountAmount' => 'abc'], 'discountAmount'],
        ];
    }

    public function testThrowsWhenAppliedVouchersIsNotAList(): void
    {
        $this->expectException(ValidationError::class);

        $this->parser->fromResponseData(['appliedVouchers' => 'invalid']);
    }

    public function testThrowsWhenEntryIsNotAnObject(): void
    {
        $this->expectException(ValidationError::class);

        $this->parser->fromResponseData(['appliedVouchers' => ['invalid']]);
    }

    public function testThrowsOnDuplicateVoucherId(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Duplicate');

        $this->parser->fromResponseData([
            'appliedVouchers' => [$this->buildVoucherData(), $this->buildVoucherData()],
        ]);
    }

    /**
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function buildVoucherData(array $override = []): array
    {
        return array_merge([
            'voucherId' => '70f8e58d-852c-4e6e-9215-0688e6716bf8',
            'code' => '0000000000017',
            'description' => 'Generate reward vouchers 19/03/2026 10:00',
            'discountAmount' => 204.4,
        ], $override);
    }
}
