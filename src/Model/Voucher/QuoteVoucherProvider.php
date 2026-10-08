<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher;

use Magento\Quote\Model\Quote;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\VoucherRecord;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\VoucherRepository;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\AppliedVoucher;

/**
 * Vouchers stored for a quote. Totals are collected many times per request,
 * so the vouchers are cached per quote for the rest of the request.
 */
class QuoteVoucherProvider
{
    private VoucherRepository $voucherRepository;

    /**
     * @var array<int, AppliedVoucher[]>
     */
    private array $cache = [];

    public function __construct(VoucherRepository $voucherRepository)
    {
        $this->voucherRepository = $voucherRepository;
    }

    /**
     * @return AppliedVoucher[] Face values, in the order they were applied
     */
    public function getVouchers(Quote $quote): array
    {
        $quoteId = (int) $quote->getId();
        if (!$quoteId) {
            return [];
        }
        if (!isset($this->cache[$quoteId])) {
            $this->cache[$quoteId] = array_map(
                fn (VoucherRecord $record): AppliedVoucher => new AppliedVoucher(
                    $record->getVoucherId(),
                    $record->getCode(),
                    $record->getDescription(),
                    $record->getFaceAmount()
                ),
                $this->voucherRepository->getByQuoteId($quoteId)
            );
        }

        return $this->cache[$quoteId];
    }

    /**
     * Replaces the quote's vouchers. Deducted amounts are set when the order
     * is placed, see saveDeductedAmounts().
     *
     * @param AppliedVoucher[] $vouchers
     */
    public function saveVouchers(Quote $quote, array $vouchers): void
    {
        $this->save($quote, $vouchers, [], []);
    }

    /**
     * Stores the amount each voucher actually deducted from the quote.
     */
    public function saveDeductedAmounts(Quote $quote, QuoteVoucherResult $result): void
    {
        $this->save($quote, $this->getVouchers($quote), $result->getVoucherAmounts(), $result->getBaseVoucherAmounts());
    }

    /**
     * @param AppliedVoucher[] $vouchers
     * @param array<string, float> $amounts
     * @param array<string, float> $baseAmounts
     */
    private function save(Quote $quote, array $vouchers, array $amounts, array $baseAmounts): void
    {
        $quoteId = (int) $quote->getId();
        if (!$quoteId) {
            throw new \InvalidArgumentException('Vouchers can only be saved for a saved quote');
        }

        $records = array_map(
            fn (AppliedVoucher $voucher): VoucherRecord => new VoucherRecord(
                $quoteId,
                null,
                $voucher->getVoucherId(),
                $voucher->getCode(),
                $voucher->getDescription(),
                $voucher->getDiscountAmount(),
                $amounts[$voucher->getVoucherId()] ?? 0.0,
                $baseAmounts[$voucher->getVoucherId()] ?? 0.0
            ),
            $vouchers
        );
        $this->voucherRepository->replaceForQuote($quoteId, $records);
        $this->cache[$quoteId] = array_values($vouchers);
    }
}
