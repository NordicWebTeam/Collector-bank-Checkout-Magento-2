<?php declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Service\Validation;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartTotalManagementInterface;
use Webbhuset\CollectorCheckout\AdapterFactory;
use Webbhuset\CollectorCheckout\Carrier\Collector;
use Webbhuset\CollectorCheckout\Checkout\Customer\ManagerFactory as CustomerManagerFactory;
use Webbhuset\CollectorCheckout\Checkout\Order\ManagerFactory as OrderManagerFactory;
use Webbhuset\CollectorCheckout\Checkout\Quote\ManagerFactory as QuoteManagerFactory;
use Webbhuset\CollectorCheckout\Config\ConfigFactory;
use Webbhuset\CollectorCheckout\Exception\QuoteNotInSyncException;
use Webbhuset\CollectorCheckout\Gateway\Config as GatewayConfig;
use Webbhuset\CollectorCheckout\Logger\Logger;
use Webbhuset\CollectorCheckout\Model\Voucher\QuoteVoucherProvider;
use Webbhuset\CollectorCheckout\Model\Voucher\VoucherApplier;
use Webbhuset\CollectorCheckout\QuoteComparerFactory;
use Webbhuset\CollectorCheckout\QuoteUpdater;
use Webbhuset\CollectorCheckout\Service\Validation\ValidationResultFactory;

/**
 * Creates Magento order after setting up all required quote data
 */
class ProcessReference
{
    /**
     * @var QuoteManagerFactory
     */
    private $quoteManagerFactory;

    /**
     * @var AdapterFactory
     */
    private $adapterFactory;

    /**
     * @var QuoteUpdater
     */
    private $quoteUpdater;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * @var CartTotalManagementInterface
     */
    private $cartTotalManagement;

    /**
     * @var QuoteComparerFactory
     */
    private $quoteComparerFactory;

    /**
     * @var OrderManagerFactory
     */
    private $orderManagerFactory;

    /**
     * @var CustomerManagerFactory
     */
    private $customerManagerFactory;

    /**
     * @var ValidationResultFactory
     */
    private ValidationResultFactory $validationResultFactory;

    /**
     * @var Logger
     */
    private $logger;

    private VoucherApplier $voucherApplier;

    private QuoteVoucherProvider $quoteVoucherProvider;

    /**
     * @param QuoteManagerFactory $quoteManagerFactory
     * @param AdapterFactory $adapterFactory
     * @param QuoteUpdater $quoteUpdater
     * @param CartRepositoryInterface $quoteRepository
     * @param ConfigFactory $configFactory
     * @param CartTotalManagementInterface $cartTotalManagement
     * @param QuoteComparerFactory $quoteComparerFactory
     * @param OrderManagerFactory $orderManagerFactory
     * @param CustomerManagerFactory $customerManagerFactory
     * @param ValidationResultFactory $validationResultFactory
     * @param Logger $logger
     * @param VoucherApplier|null $voucherApplier
     * @param QuoteVoucherProvider|null $quoteVoucherProvider
     */
    public function __construct(
        QuoteManagerFactory $quoteManagerFactory,
        AdapterFactory $adapterFactory,
        QuoteUpdater $quoteUpdater,
        CartRepositoryInterface $quoteRepository,
        ConfigFactory $configFactory,
        CartTotalManagementInterface $cartTotalManagement,
        QuoteComparerFactory $quoteComparerFactory,
        OrderManagerFactory $orderManagerFactory,
        CustomerManagerFactory $customerManagerFactory,
        ValidationResultFactory $validationResultFactory,
        Logger $logger,
        ?VoucherApplier $voucherApplier = null,
        ?QuoteVoucherProvider $quoteVoucherProvider = null
    ) {
        $this->quoteManagerFactory = $quoteManagerFactory;
        $this->adapterFactory = $adapterFactory;
        $this->quoteUpdater = $quoteUpdater;
        $this->quoteRepository = $quoteRepository;
        $this->configFactory = $configFactory;
        $this->cartTotalManagement = $cartTotalManagement;
        $this->quoteComparerFactory = $quoteComparerFactory;
        $this->orderManagerFactory = $orderManagerFactory;
        $this->customerManagerFactory = $customerManagerFactory;
        $this->validationResultFactory = $validationResultFactory;
        $this->logger = $logger;
        $this->voucherApplier = $voucherApplier
            ?? ObjectManager::getInstance()->get(VoucherApplier::class);
        $this->quoteVoucherProvider = $quoteVoucherProvider
            ?? ObjectManager::getInstance()->get(QuoteVoucherProvider::class);
    }

    /**
     * @param string $reference
     * @return ValidationResult
     */
    public function execute(string $reference): ValidationResult
    {
        $quoteId = null;
        $reservedOrderId = null;
        $validationResult = $this->validationResultFactory->create();
        $validationResult->setHttpCode(200);

        try {
            $quoteManager = $this->quoteManagerFactory->create();
            $quote = $quoteManager->getQuoteByPublicToken($reference);
            /** @var \Magento\Quote\Model\Quote $quote */

            $quoteId = $quote->getId();
            $reservedOrderId = $quote->getReservedOrderId();

            if ($quote->getPayment()->getMethod() != GatewayConfig::CHECKOUT_CODE) {
                throw new CouldNotSaveException(__('Please refresh the page and try again.'));
            }

            $checkoutData = $this->adapterFactory->create()->acquireCheckoutInformationFromQuote($quote);
            $this->quoteUpdater->setQuoteData($quote, $checkoutData);
            $this->voucherApplier->apply($quote, $checkoutData);
            $quote->setNeedsCollectorUpdate(null);
            $config = $this->configFactory->create(['storeId' => (int)$quote->getStoreId()]);
            if ($config->getIsDeliveryCheckoutActive() && !$config->getIsCustomDeliveryAdapter()) {
                $carrierCode = !$quote->isVirtual() ? Collector::GATEWAY_KEY : null;

                $this->cartTotalManagement->collectTotals(
                    $quote->getId(),
                    $quote->getPayment(),
                    $carrierCode,
                    $carrierCode
                );
                $quote = $quoteManager->getQuoteByPublicToken($reference);
            }

            if ($this->quoteVoucherProvider->getVouchers($quote)) {
                // Voucher shares only live in memory, so a loaded quote needs its totals collected
                $quote->setTotalsCollectedFlag(false)->collectTotals();
            }

            $this->quoteComparerFactory->create()->isQuoteInSync($quote, $checkoutData);

            $orderManager = $this->orderManagerFactory->create();
            $customerManager = $this->customerManagerFactory->create();

            $orderManager->removeNewOrdersByPublicToken($reference);
            $customerManager->handleCustomerOnQuote($quote);

            // Save quote, place order
            $this->quoteRepository->save($quote);
            $orderId = (string)$orderManager->createOrder($quote);
            $validationResult->setOrderReference($orderId);
        } catch (CouldNotSaveException $e) {
            $this->logger->addCritical(
                sprintf(
                    'Validation callback CouldNotSaveException. quoteId: %s orderId: %s publicToken: %s. %s',
                    $quoteId ?: 'n/a',
                    $reservedOrderId ?: 'n/a',
                    $reference,
                    $e->getMessage()
                )
            );
        } catch (NoSuchEntityException $e) {
            $this->logger->addCritical(
                sprintf(
                    'Validation callback NoSuchEntityException publicToken: %s. %s',
                    $reference,
                    $e->getMessage()
                )
            );
        } catch (QuoteNotInSyncException $e) {
            $this->logger->addCritical(
                sprintf(
                    'Cart not in sync on callback QuoteNotInSyncException publicToken: %s. %s',
                    $reference,
                    $e->getMessage()
                )
            );
        } catch (\Throwable $e) {
            $this->logger->addCritical(
                sprintf(
                    'Validation callback Unrecoverable exception publicToken: %s. %s',
                    $reference,
                    $e->getMessage()
                )
            );
        }

        if (isset($e) && $e instanceof \Throwable) {
            $validationResult->setMessage($e->getMessage());
            $validationResult->setHttpCode(404);
        }

        return $validationResult;
    }
}
