<?php

namespace Webbhuset\CollectorCheckout\Service\Validation;

use Magento\Framework\DataObject;

/**
 * Data model for result of reference Validation
 */
class ValidationResult extends DataObject
{
    const KEY_HTTP_CODE = 'http_code';

    const KEY_MESSAGE = 'message';

    const KEY_ORDER_REFERENCE = 'order_reference';

    public function setHttpCode(int $code): self
    {
        return $this->setData(self::KEY_HTTP_CODE, $code);
    }

    /**
     * @return int
     */
    public function getHttpCode(): int
    {
        return (int)$this->getData(self::KEY_HTTP_CODE);
    }

    /**
     * Set message describing an unsuccessful validation result
     *
     * @param string $message
     * @return self
     */
    public function setMessage(string $message): self
    {
        return $this->setData(self::KEY_MESSAGE, $message);
    }

    /**
     * Get message describing unsuccessful validation
     *
     * @return string
     */
    public function getMessage(): string
    {
        return (string)$this->getData(self::KEY_MESSAGE);
    }

    /**
     * Set order reference in a successful validation
     *
     * @param string $reference
     * @return self
     */
    public function setOrderReference(string $reference): self
    {
        return $this->setData(self::KEY_ORDER_REFERENCE, $reference);
    }

    /**
     * Get order reference of successful validation
     *
     * @return string
     */
    public function getOrderReference(): string
    {
        return (string)$this->getData(self::KEY_ORDER_REFERENCE);
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return in_array($this->getHttpCode(), [200, 201, 202]);
    }
}
