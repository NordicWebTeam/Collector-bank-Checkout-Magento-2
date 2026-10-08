<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Plugin\Voucher;

use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\VoucherRepository;

/**
 * Exposes the order's Walley vouchers as extension attribute walley_vouchers,
 * e.g. for the REST API. Read-only: the vouchers are saved by the module itself.
 */
class AddVouchersToOrder
{
    private VoucherRepository $voucherRepository;
    private OrderExtensionFactory $extensionFactory;

    public function __construct(
        VoucherRepository $voucherRepository,
        OrderExtensionFactory $extensionFactory
    ) {
        $this->voucherRepository = $voucherRepository;
        $this->extensionFactory = $extensionFactory;
    }

    public function afterGet(OrderRepositoryInterface $subject, OrderInterface $order): OrderInterface
    {
        $this->addVouchers([$order]);

        return $order;
    }

    public function afterGetList(
        OrderRepositoryInterface $subject,
        OrderSearchResultInterface $searchResult
    ): OrderSearchResultInterface {
        $this->addVouchers($searchResult->getItems());

        return $searchResult;
    }

    /**
     * @param OrderInterface[] $orders
     */
    private function addVouchers(array $orders): void
    {
        if ($orders === []) {
            return;
        }

        $orderIds = array_map(fn (OrderInterface $order): int => (int) $order->getEntityId(), array_values($orders));
        $vouchersByOrder = $this->voucherRepository->getByOrderIds($orderIds);

        foreach ($orders as $order) {
            $extension = $order->getExtensionAttributes() ?? $this->extensionFactory->create();
            $extension->setWalleyVouchers($vouchersByOrder[(int) $order->getEntityId()] ?? []);
            $order->setExtensionAttributes($extension);
        }
    }
}
