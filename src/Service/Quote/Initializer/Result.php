<?php declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Service\Quote\Initializer;

use Magento\Framework\DataObject;

/**
 * DTO of quote init result
 */
class Result extends DataObject
{
    const KEY_PUBLIC_TOKEN = 'public_token';

    const KEY_DELIVERY_CHECKOUT_ACTIVE = 'delivery_checkout_active';

    const KEY_ERROR = 'error';

    /**
     * @param string $token
     * @return self
     */
    public function setPublicToken(string $token): self
    {
        return $this->setData(self::KEY_PUBLIC_TOKEN, $token);
    }

    /**
     * @return string
     */
    public function getPublicToken(): string
    {
        return (string)$this->getData(self::KEY_PUBLIC_TOKEN);
    }

    /**
     * @param bool $status
     * @return self
     */
    public function setDeliveryCheckoutActive(bool $status): self
    {
        return $this->setData(self::KEY_DELIVERY_CHECKOUT_ACTIVE, $status);
    }

    /**
     * @return bool
     */
    public function getDeliveryCheckoutActive(): bool
    {
        return !!$this->getData(self::KEY_DELIVERY_CHECKOUT_ACTIVE);
    }

    /**
     * @param int $error
     * @return self
     */
    public function setError(int $error): self
    {
        return $this->setData(self::KEY_ERROR, $error);
    }

    /**
     * @return int
     */
    public function getError(): int
    {
        return (int)$this->getData(self::KEY_ERROR);
    }
}
