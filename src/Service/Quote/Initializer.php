<?php declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Service\Quote;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Webbhuset\CollectorCheckout\QuoteValidator;
use Webbhuset\CollectorCheckout\Config\Config;
use Webbhuset\CollectorCheckout\QuoteComparer;
use Webbhuset\CollectorCheckout\Data\QuoteHandler;
use Webbhuset\CollectorCheckout\Adapter;
use Webbhuset\CollectorCheckout\Service\Quote\Initializer\ResultFactory;
use Webbhuset\CollectorCheckout\Service\Quote\Initializer\Result;

/**
 * Service class for validating and setting up Quote,
 * for initializing a Collector payment session
 */
class Initializer
{
    const ERROR_CODE_CURRENCY = 1;

    const ERROR_CODE_QUOTE = 2;

    /**
     * @var ResultFactory
     */
    private ResultFactory $resultFactory;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Adapter
     */
    private Adapter $collectorAdapter;

    /**
     * @var QuoteValidator
     */
    private QuoteValidator $quoteValidator;

    /**
     * @var QuoteComparer
     */
    private QuoteComparer $quoteComparer;

    /**
     * @var QuoteHandler
     */
    private QuoteHandler $quoteDataHandler;

    public function __construct(
        ResultFactory $resultFactory,
        Config $config,
        Adapter $collectorAdapter,
        QuoteValidator $quoteValidator,
        QuoteComparer $quoteComparer,
        QuoteHandler $quoteDataHandler
    ) {
        $this->resultFactory = $resultFactory;
        $this->config = $config;
        $this->collectorAdapter = $collectorAdapter;
        $this->quoteValidator = $quoteValidator;
        $this->quoteComparer = $quoteComparer;
        $this->quoteDataHandler = $quoteDataHandler;
    }

    /**
     * Init or sync. Always reinit on customer type mismatch
     *
     * @param CartInterface|Quote $quote
     * @param int|null $customerType
     * @return Result
     */
    public function execute(CartInterface $quote, ?int $customerType = null): Result
    {
        $result = $this->resultFactory->create();
        if (!$this->quoteComparer->isCurrencyMatching()) {
            $result->setError(self::ERROR_CODE_CURRENCY);
            return $result;
        }

        $quoteCheckoutErrors = $this->quoteValidator->getErrors($quote);
        if (!empty($quoteCheckoutErrors)) {
            $result->setError(self::ERROR_CODE_QUOTE);
            return $result;
        }

        $this->config->setStoreId((int)$quote->getStoreId());
        $result->setDeliveryCheckoutActive(!!$this->config->getIsDeliveryCheckoutActive());
        if ($this->requireReinit($quote, $customerType)) {
            $publicToken = $this->collectorAdapter->initWithCustomerType($quote, $customerType);
            $result->setPublicToken($publicToken);
            return $result;
        }

        $publicToken = $this->collectorAdapter->initOrSync($quote);
        $result->setPublicToken($publicToken);
        return $result;
    }

    /**
     * Check if we need to reinit payment based on customer type
     *
     * @param CartInterface|Quote $quote
     * @param integer $customerType
     * @return bool
     */
    private function requireReinit(CartInterface $quote, ?int $customerType = null): bool
    {
        $allowed = $this->config->getCustomerTypeAllowed();
        $canChangeCustomerType =
            \Webbhuset\CollectorCheckout\Config\Source\Customer\Type::BOTH_CUSTOMERS == $allowed;

        if (!$canChangeCustomerType) {
            return false;
        }

        $availableCustomerTypes = [
            \Webbhuset\CollectorCheckout\Config\Source\Customer\Type::PRIVATE_CUSTOMERS,
            \Webbhuset\CollectorCheckout\Config\Source\Customer\Type::BUSINESS_CUSTOMERS,
        ];

        if (!$customerType || !in_array($customerType, $availableCustomerTypes)) {
            return false;
        }

        $currentCustomerType = (int) $this->quoteDataHandler->getCustomerType($quote);
        return $currentCustomerType !== $customerType;
    }
}
