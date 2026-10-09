<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Test\Unit\Plugin\Voucher;

use Magento\Sales\Api\Data\OrderExtension;
use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\VoucherRecord;
use Webbhuset\CollectorCheckout\Model\Voucher\Storage\VoucherRepository;
use Webbhuset\CollectorCheckout\Plugin\Voucher\AddVouchersToOrder;

class AddVouchersToOrderTest extends TestCase
{
    /**
     * @var VoucherRepository&MockObject
     */
    private $voucherRepository;

    /**
     * @var OrderExtensionFactory&MockObject
     */
    private $extensionFactory;

    /**
     * @var OrderRepositoryInterface&MockObject
     */
    private $orderRepository;

    private AddVouchersToOrder $plugin;

    protected function setUp(): void
    {
        $this->voucherRepository = $this->createMock(VoucherRepository::class);
        $this->extensionFactory = $this->createMock(OrderExtensionFactory::class);
        $this->extensionFactory->method('create')->willReturnCallback(fn () => new OrderExtension());
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->plugin = new AddVouchersToOrder($this->voucherRepository, $this->extensionFactory);
    }

    public function testAddsVouchersToSingleOrder(): void
    {
        $voucher = $this->buildVoucher(62);
        $order = $this->buildOrder(62, null);
        $this->voucherRepository->expects($this->once())
            ->method('getByOrderIds')
            ->with([62])
            ->willReturn([62 => [$voucher]]);

        $result = $this->plugin->afterGet($this->orderRepository, $order);

        $this->assertSame($order, $result);
        $this->assertSame([$voucher], $order->getExtensionAttributes()->getWalleyVouchers());
    }

    public function testKeepsExistingExtensionAttributes(): void
    {
        $extension = new OrderExtension();
        $order = $this->buildOrder(62, $extension);
        $this->voucherRepository->method('getByOrderIds')->willReturn([]);

        $this->plugin->afterGet($this->orderRepository, $order);

        $this->assertSame($extension, $order->getExtensionAttributes());
        $this->assertSame([], $extension->getWalleyVouchers());
    }

    public function testLoadsVouchersForOrderListInOneQuery(): void
    {
        $orderWithVoucher = $this->buildOrder(1, null);
        $orderWithoutVoucher = $this->buildOrder(2, null);
        $voucher = $this->buildVoucher(1);
        $searchResult = $this->createMock(OrderSearchResultInterface::class);
        $searchResult->method('getItems')->willReturn([$orderWithVoucher, $orderWithoutVoucher]);
        $this->voucherRepository->expects($this->once())
            ->method('getByOrderIds')
            ->with([1, 2])
            ->willReturn([1 => [$voucher]]);

        $result = $this->plugin->afterGetList($this->orderRepository, $searchResult);

        $this->assertSame($searchResult, $result);
        $this->assertSame([$voucher], $orderWithVoucher->getExtensionAttributes()->getWalleyVouchers());
        $this->assertSame([], $orderWithoutVoucher->getExtensionAttributes()->getWalleyVouchers());
    }

    public function testSkipsQueryForEmptyOrderList(): void
    {
        $searchResult = $this->createMock(OrderSearchResultInterface::class);
        $searchResult->method('getItems')->willReturn([]);
        $this->voucherRepository->expects($this->never())->method('getByOrderIds');

        $this->plugin->afterGetList($this->orderRepository, $searchResult);
    }

    /**
     * Order double that keeps the extension attributes it is given.
     *
     * @return OrderInterface&MockObject
     */
    private function buildOrder(int $orderId, ?OrderExtension $extension)
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn($orderId);
        $order->method('getExtensionAttributes')->willReturnCallback(function () use (&$extension) {
            return $extension;
        });
        $order->method('setExtensionAttributes')->willReturnCallback(
            function (OrderExtension $newExtension) use (&$extension, $order) {
                $extension = $newExtension;

                return $order;
            }
        );

        return $order;
    }

    private function buildVoucher(int $orderId): VoucherRecord
    {
        return new VoucherRecord(10, $orderId, 'v1', '0000000000017', 'Reward vouchers', 204.4, 204.4, 204.4);
    }
}
