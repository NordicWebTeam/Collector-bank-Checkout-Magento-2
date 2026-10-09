<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Block\Sales;

use Magento\Framework\DataObjectFactory;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Magento\Tax\Model\Config as TaxConfig;
use Webbhuset\CollectorCheckout\Model\Voucher\DocumentAmountsReader;
use Webbhuset\CollectorCheckout\Model\Voucher\TotalRowBuilder;

/**
 * Adds the "Voyado voucher" row to a sales totals block (order, invoice or
 * credit memo; admin, customer account, print and email). Magento calls
 * initTotals() on the children of the totals block.
 */
class VoucherTotal extends AbstractBlock
{
    private DocumentAmountsReader $documentAmountsReader;
    private TotalRowBuilder $totalRowBuilder;
    private TaxConfig $taxConfig;
    private DataObjectFactory $dataObjectFactory;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        DocumentAmountsReader $documentAmountsReader,
        TotalRowBuilder $totalRowBuilder,
        TaxConfig $taxConfig,
        DataObjectFactory $dataObjectFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->documentAmountsReader = $documentAmountsReader;
        $this->totalRowBuilder = $totalRowBuilder;
        $this->taxConfig = $taxConfig;
        $this->dataObjectFactory = $dataObjectFactory;
    }

    public function initTotals(): self
    {
        /** @var \Magento\Sales\Block\Order\Totals $parent */
        $parent = $this->getParentBlock();
        $source = $parent ? $parent->getSource() : null;
        if (!$source) {
            return $this;
        }

        $row = $this->totalRowBuilder->build(
            $this->documentAmountsReader->read($source),
            (bool) $this->taxConfig->displaySalesSubtotalExclTax($source->getStoreId())
        );
        if ($row === null) {
            return $this;
        }

        $total = $this->dataObjectFactory->create(['data' => $row + ['label' => __('Voyado voucher')]]);
        $parent->addTotal($total, $this->totalRowBuilder->getAnchor(array_keys($parent->getTotals())));

        return $this;
    }
}
