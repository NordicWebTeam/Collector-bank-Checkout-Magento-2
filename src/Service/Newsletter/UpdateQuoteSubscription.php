<?php

namespace Webbhuset\CollectorCheckout\Service\Newsletter;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartRepositoryInterface;
use Webbhuset\CollectorCheckout\Data\QuoteHandler;

/**
 * Saves newsletter_subscribe additional data in quote
 */
class UpdateQuoteSubscription
{
    const SUBSCRIBE_TRUE = 'true';

    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * @var QuoteHandler
     */
    private QuoteHandler $quoteHandler;

    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $quoteRepository;

    public function __construct(
        CheckoutSession $checkoutSession,
        QuoteHandler $quoteHandler,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->quoteHandler = $quoteHandler;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param string $subscribeParam
     * @return void
     */
    public function execute(string $subscribeParam): void
    {
        $quote = $this->checkoutSession->getQuote();
        $subscribeFlag = (int)(self::SUBSCRIBE_TRUE === $subscribeParam);
        $this->quoteHandler->setNewsletterSubscribe($quote, $subscribeFlag);
        $this->quoteRepository->save($quote);
    }
}
