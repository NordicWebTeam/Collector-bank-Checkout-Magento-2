<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Payment\Invoice\Rows;

class InvoiceRows
{
    private array $invoiceRows = [];

    public function addInvoiceRow(InvoiceRow $invoiceRow): void
    {
        $this->invoiceRows[] = $invoiceRow;
    }

    /**
     * @return array<int, array<string, int|float|string>>
     */
    public function getInvoiceRows(): array
    {
        $result = [];
        foreach ($this->invoiceRows as $invoiceRow) {
            $result[] = $invoiceRow->toArray();
        }

        return $result;
    }

    /**
     * @return array<int, array<string, int|float|string>>
     */
    public function toArray(): array
    {
        return $this->getInvoiceRows();
    }
}
