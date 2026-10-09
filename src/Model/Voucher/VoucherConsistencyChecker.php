<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Model\Order;
use Webbhuset\CollectorCheckout\Logger\Logger;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\VoucherRepository;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\CheckoutData;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Errors\ValidationError;

/**
 * Checks, when Walley notifies that a purchase is completed, that the vouchers
 * stored on the order are still the ones applied at Walley. A difference is
 * logged and added as order comment for manual follow-up.
 */
class VoucherConsistencyChecker
{
    private VoucherRepository $voucherRepository;
    private VoucherComparison $comparison;
    private OrderStatusHistoryRepositoryInterface $historyRepository;
    private Logger $logger;

    public function __construct(
        VoucherRepository $voucherRepository,
        VoucherComparison $comparison,
        OrderStatusHistoryRepositoryInterface $historyRepository,
        Logger $logger
    ) {
        $this->voucherRepository = $voucherRepository;
        $this->comparison = $comparison;
        $this->historyRepository = $historyRepository;
        $this->logger = $logger;
    }

    /**
     * Never throws, so the notification callback is not interrupted.
     */
    public function check(OrderInterface $order, CheckoutData $checkoutData): void
    {
        try {
            $differences = $this->getDifferences($order, $checkoutData);
            if (!$differences) {
                return;
            }

            $message = 'Walley voucher mismatch: ' . implode('; ', $differences);
            $this->logger->addCritical(sprintf('%s. Order %s', $message, $order->getIncrementId()));
            if ($order instanceof Order) {
                $this->historyRepository->save($order->addCommentToStatusHistory(__($message)));
            }
        } catch (\Throwable $e) {
            $this->logger->addCritical(sprintf(
                'Walley voucher check failed for order %s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));
        }
    }

    /**
     * @return string[]
     */
    private function getDifferences(OrderInterface $order, CheckoutData $checkoutData): array
    {
        $stored = $this->voucherRepository->getByOrderId((int) $order->getEntityId());
        try {
            $applied = $checkoutData->getAppliedVouchers();
        } catch (ValidationError $e) {
            return ['invalid voucher data from Walley: ' . $e->getMessage()];
        }

        return $this->comparison->getDifferences($stored, $applied);
    }
}
