<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher;

use Magento\Quote\Model\Quote;
use Webbhuset\CollectorCheckout\Model\Total\Quote\WalleyVoucher;

/**
 * Reads the voucher result of a quote's totals collection.
 *
 * The result only lives in memory. A quote loaded in this request has its
 * voucher already included in the saved grand total, but no result until
 * totals are collected again.
 */
class QuoteVoucherTotals
{
    private QuoteVoucherProvider $provider;

    public function __construct(QuoteVoucherProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Result of the last totals collection in this request, without
     * collecting totals.
     */
    public function getResult(Quote $quote): ?QuoteVoucherResult
    {
        $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        $result = $address ? $address->getData(WalleyVoucher::RESULT_KEY) : null;

        return $result instanceof QuoteVoucherResult && !$result->isEmpty() ? $result : null;
    }

    /**
     * Result of the quote's totals collection. Collects totals first when the
     * quote has vouchers but no result in memory, e.g. a quote loaded in this
     * request whose saved grand total already includes the vouchers.
     */
    public function getCollectedResult(Quote $quote): ?QuoteVoucherResult
    {
        $result = $this->getResult($quote);
        if (!$result && $this->provider->getVouchers($quote)) {
            $quote->setTotalsCollectedFlag(false)->collectTotals();
            $result = $this->getResult($quote);
        }

        return $result;
    }

    /**
     * Amount incl. VAT deducted by vouchers, i.e. how much lower the quote
     * grand total is than the cart total in Walley. May collect totals, so
     * read this before the quote's grand total.
     */
    public function getAmount(Quote $quote): float
    {
        $result = $this->getCollectedResult($quote);

        return $result ? $result->getAmount() : 0.0;
    }
}
