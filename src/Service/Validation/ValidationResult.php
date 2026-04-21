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
     * @param string $message
     * @return self
     */
    public function setMessage(string $message): self
    {
        return $this->setData(self::KEY_MESSAGE, $message);
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return (string)$this->getData(self::KEY_MESSAGE);
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return in_array($this->getHttpCode(), [200, 201, 202]);
    }
}
