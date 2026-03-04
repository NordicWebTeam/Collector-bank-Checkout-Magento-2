<?php declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Service\Checkout;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * Service class for storing values across services in the checkout process
 */
class Storage
{
    /**
     * @var string|null
     */
    private ?string $publicToken = null;

    /**
     * Successfully placed order
     *
     * @var OrderInterface|null
     */
    private ?OrderInterface $successOrder = null;

    /**
     * @param string $token
     * @return void
     */
    public function setPublicToken(string $token): void
    {
        $this->publicToken = $token;
    }

    /**
     * @return string|null
     */
    public function getPublicToken(): ?string
    {
        return $this->publicToken;
    }

    /**
     * @param OrderInterface $order
     * @return void
     */
    public function setSuccessOrder(OrderInterface $order): void
    {
        $this->successOrder = $order;
    }

    /**
     * @return OrderInterface|null
     */
    public function getSuccessOrder(): ?OrderInterface
    {
        return $this->successOrder;
    }
}
