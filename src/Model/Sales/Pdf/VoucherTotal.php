<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Sales\Pdf;

use Magento\Sales\Model\Order\Pdf\Total\DefaultTotal;
use Magento\Tax\Helper\Data;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config as TaxConfig;
use Magento\Tax\Model\ResourceModel\Sales\Order\Tax\CollectionFactory;
use Webbhuset\CollectorCheckout\Model\Voucher\DocumentAmountsReader;
use Webbhuset\CollectorCheckout\Model\Voucher\TotalRowBuilder;

/**
 * "Voyado voucher" row in invoice and credit memo PDFs (etc/pdf.xml). The
 * amount comes from the module's tables, not from a field on the document.
 */
class VoucherTotal extends DefaultTotal
{
    private DocumentAmountsReader $documentAmountsReader;
    private TotalRowBuilder $totalRowBuilder;
    private TaxConfig $taxConfig;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Data $taxHelper,
        Calculation $taxCalculation,
        CollectionFactory $ordersFactory,
        DocumentAmountsReader $documentAmountsReader,
        TotalRowBuilder $totalRowBuilder,
        TaxConfig $taxConfig,
        array $data = []
    ) {
        parent::__construct($taxHelper, $taxCalculation, $ordersFactory, $data);
        $this->documentAmountsReader = $documentAmountsReader;
        $this->totalRowBuilder = $totalRowBuilder;
        $this->taxConfig = $taxConfig;
    }

    /**
     * @return float
     */
    public function getAmount()
    {
        $source = $this->getSource();
        if (!$source) {
            return 0.0;
        }
        $row = $this->totalRowBuilder->build(
            $this->documentAmountsReader->read($source),
            (bool) $this->taxConfig->displaySalesSubtotalExclTax($source->getStoreId())
        );

        return $row === null ? 0.0 : $row['value'];
    }
}
