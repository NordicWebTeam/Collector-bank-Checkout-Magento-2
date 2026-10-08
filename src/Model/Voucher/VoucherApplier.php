<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher;

use Magento\Quote\Model\Quote;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\AppliedVoucher;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\CheckoutData;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Errors\ValidationError;

/**
 * Stores the vouchers applied in Walley Checkout on the quote, so the voucher
 * total collector picks them up.
 */
class VoucherApplier
{
    private QuoteVoucherProvider $provider;

    public function __construct(QuoteVoucherProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * @return bool Whether the vouchers changed; totals then need to be collected again
     * @throws ValidationError When Walley sends invalid voucher data
     */
    public function apply(Quote $quote, CheckoutData $checkoutData): bool
    {
        $vouchers = $checkoutData->getAppliedVouchers();
        if ($this->toComparable($vouchers) === $this->toComparable($this->provider->getVouchers($quote))) {
            return false;
        }

        $this->provider->saveVouchers($quote, $vouchers);
        $quote->setTotalsCollectedFlag(false);

        return true;
    }

    /**
     * @param AppliedVoucher[] $vouchers
     * @return array<int, array<string, mixed>>
     */
    private function toComparable(array $vouchers): array
    {
        return array_map(fn (AppliedVoucher $voucher): array => $voucher->toArray(), $vouchers);
    }
}
