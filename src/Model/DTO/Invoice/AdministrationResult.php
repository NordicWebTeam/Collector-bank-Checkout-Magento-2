<?php declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\DTO\Invoice;

use Magento\Framework\DataObject;
use Webbhuset\CollectorCheckout\Api\Data\DTO\Invoice\AdministrationResultInterface;

/**
 * DTO of invoice administration result
 */
class AdministrationResult extends DataObject implements AdministrationResultInterface
{
    /**
     * @param string $identifier
     * @return AdministrationResultInterface
     */
    public function setNewInvoiceNumber(string $identifier): AdministrationResultInterface
    {
        return $this->setData(self::KEY_NEW_INVOICE_NUMBER, $identifier);
    }

    /**
     * @return string|null
     */
    public function getNewInvoiceNumber(): ?string
    {
        $data = (string)$this->getData(self::KEY_NEW_INVOICE_NUMBER);
        return ($data) ? $data : null;
    }

    /**
     * @param float $amount
     * @return AdministrationResultInterface
     */
    public function setTotalAmount(float $amount): AdministrationResultInterface
    {
        return $this->setData(self::KEY_TOTAL_AMOUNT, $amount);
    }

    /**
     * @return float|null
     */
    public function getTotalAmount(): ?float
    {
        $data = (float)$this->getData(self::KEY_TOTAL_AMOUNT);
        return ($data) ? $data : null;
    }

    /**
     * @param string $url
     * @return AdministrationResultInterface
     */
    public function setInvoiceUrl(string $url): AdministrationResultInterface
    {
        return $this->setData(self::KEY_INVOICE_URL, $url);
    }

    /**
     * @return string|null
     */
    public function getInvoiceUrl(): ?string
    {
        $data = (string)$this->getData(self::KEY_INVOICE_URL);
        return ($data) ? $data : null;
    }

    /**
     * @param string $correlationId
     * @return AdministrationResultInterface
     */
    public function setCorrelationId(string $correlationId): AdministrationResultInterface
    {
        return $this->setData(self::KEY_CORRELATION_ID, $correlationId);
    }

    /**
     * @return string|null
     */
    public function getCorrelationId(): ?string
    {
        $data = (string)$this->getData(self::KEY_CORRELATION_ID);
        return ($data) ? $data : null;
    }
}
