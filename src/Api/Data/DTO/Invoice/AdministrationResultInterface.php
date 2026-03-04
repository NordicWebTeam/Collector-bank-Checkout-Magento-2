<?php declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Api\Data\DTO\Invoice;

/**
 * Interface for DTO of invoice administration result: capture or credit operation
 */
interface AdministrationResultInterface
{
    const KEY_NEW_INVOICE_NUMBER = 'new_invoice_number';

    const KEY_TOTAL_AMOUNT = 'total_amount';

    const KEY_INVOICE_URL = 'invoice_url';

    const KEY_CORRELATION_ID = 'correlation_id';

    /**
     * @param string $identifier
     * @return void
     */
    public function setNewInvoiceNumber(string $identifier): self;

    /**
     * @return string|null
     */
    public function getNewInvoiceNumber(): ?string;

    /**
     * @param float $amount
     * @return self
     */
    public function setTotalAmount(float $amount): self;

    /**
     * @return float|null
     */
    public function getTotalAmount(): ?float;

    /**
     * @param string $url
     * @return self
     */
    public function setInvoiceUrl(string $url): self;

    /**
     * @return string|null
     */
    public function getInvoiceUrl(): ?string;

    /**
     * @param string $correlationId
     * @return self
     */
    public function setCorrelationId(string $correlationId): self;

    /**
     * @return string|null
     */
    public function getCorrelationId(): ?string;
}
