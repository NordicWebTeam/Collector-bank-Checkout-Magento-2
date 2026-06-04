<?php
namespace Webbhuset\CollectorCheckout\Plugin;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class RestoreQuoteFromCookie
{
    protected $checkoutSession;
    protected $quoteRepository;
    protected $quoteCollectionFactory;
    protected $cookieManager;
    private $orderManager;
    private $configFactory;

    public function __construct(
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $quoteRepository,
        QuoteCollectionFactory $quoteCollectionFactory,
        CookieManagerInterface $cookieManager,
        \Webbhuset\CollectorCheckout\Checkout\Order\ManagerFactory $orderManager,
        \Webbhuset\CollectorCheckout\Config\ConfigFactory $configFactory,
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->cookieManager = $cookieManager;
        $this->orderManager = $orderManager;
        $this->configFactory = $configFactory;
    }

    public function beforeExecute($subject)
    {
        $publicId = $this->cookieManager->getCookie('collectorbank_public_id');
        
        if (!$publicId) {
            return null;
        }

        $successfulPublicId = (string)$this->checkoutSession->getData('collectorbank_success_public_id');
        if ($successfulPublicId && $successfulPublicId === $publicId) {
            return null;
        }

        $currentQuote = $this->checkoutSession->getQuote();

        if (!$currentQuote->getId() || !$currentQuote->getIsActive()) {
            
            $quote = $this->quoteCollectionFactory->create()
                ->addFieldToFilter('collectorbank_public_id', $publicId)
                ->setOrder('entity_id', 'DESC')
                ->getFirstItem();

            try {
                $order = $this->orderManager->create()->getOrderByPublicToken($publicId);
                $config = $this->configFactory->create(['storeId' => $quote->getStoreId()]);
                $acknowledged  = $config->getOrderStatusAcknowledged();
                if ($order->getStatus() == $acknowledged) {
                    return null;
                }
            } catch (NoSuchEntityException $e) {

            }

            if ($quote->getId()) {
                $quote->setIsActive(1)->setReservedOrderId(null);
                $this->quoteRepository->save($quote);
                
                $this->checkoutSession->replaceQuote($quote);
                $this->checkoutSession->setQuoteId($quote->getId());
                $this->checkoutSession->unsLastRealOrderId();
            }
        }

        return null;
    }
}
