<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Model\Voucher;

/**
 * Tax breakdowns after voucher VAT is deducted, see AppliedTaxesAdjuster.
 */
class AppliedTaxesResult
{
    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $itemsAppliedTaxes;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $appliedTaxes;

    /**
     * @param array<string, array<int, array<string, mixed>>> $itemsAppliedTaxes
     * @param array<string, array<string, mixed>> $appliedTaxes
     */
    public function __construct(array $itemsAppliedTaxes, array $appliedTaxes)
    {
        $this->itemsAppliedTaxes = $itemsAppliedTaxes;
        $this->appliedTaxes = $appliedTaxes;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getItemsAppliedTaxes(): array
    {
        return $this->itemsAppliedTaxes;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAppliedTaxes(): array
    {
        return $this->appliedTaxes;
    }
}
