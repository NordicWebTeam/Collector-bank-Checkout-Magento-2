<?php
namespace Webbhuset\CollectorCheckout\Plugin;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;

class RestoreQuoteFromCookie
{
    protected $checkoutSession;
    protected $quoteRepository;
    protected $quoteCollectionFactory;
    protected $cookieManager;

    public function __construct(
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $quoteRepository,
        QuoteCollectionFactory $quoteCollectionFactory,
        CookieManagerInterface $cookieManager
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->cookieManager = $cookieManager;
    }

    public function beforeExecute($subject)
    {
        $publicId = $this->cookieManager->getCookie('collectorbank_public_id');
        
        if (!$publicId) {
            return null;
        }

        $currentQuote = $this->checkoutSession->getQuote();

        if (!$currentQuote->getId() || !$currentQuote->getIsActive()) {
            
            $quote = $this->quoteCollectionFactory->create()
                ->addFieldToFilter('collectorbank_public_id', $publicId)
                ->setOrder('entity_id', 'DESC')
                ->getFirstItem();

            if ($quote->getId()) {
                $quote->setIsActive(1);
                $this->quoteRepository->save($quote);
                
                $this->checkoutSession->replaceQuote($quote);
                $this->checkoutSession->setQuoteId($quote->getId());
                $this->checkoutSession->unsLastRealOrderId();
            }
        }

        return null;
    }
}