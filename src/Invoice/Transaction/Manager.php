<?php

namespace Webbhuset\CollectorCheckout\Invoice\Transaction;

use Webbhuset\CollectorCheckout\Api\Data\DTO\Invoice\AdministrationResultInterface;

class Manager
{
    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $transactionFactory;
    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * Manager constructor.
     *
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Framework\DB\TransactionFactory    $transactionFactory
     */
    public function __construct(
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\TransactionFactory $transactionFactory
    ) {
        $this->invoiceService        = $invoiceService;
        $this->transactionFactory    = $transactionFactory;
    }

    protected $counter = 0;

    /**
     * Adds a transaction to the order
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param string                                 $type
     * @param bool                                   $status
     * @param ?AdministrationResultInterface        $administrationResult
     */
    public function addTransaction(
        \Magento\Sales\Api\Data\OrderInterface $order,
        string $type,
        bool $status = false,
        ?AdministrationResultInterface $administrationResult = null
    ) {
        $payment = $order->getPayment();
        $txnId = $order->getIncrementId() . "-{$type}";
        $parentTransId = $payment->getLastTransId();
        $paymentData = $payment->getAdditionalInformation();

        if (null !== $administrationResult) {
            $correlationId = $administrationResult->getCorrelationId();
            $paymentData['invoice_url'] = $administrationResult->getInvoiceUrl();
            $paymentData['amount_to_pay'] = $administrationResult->getTotalAmount();
            if (null !== $correlationId) {
                $txnId = $correlationId;
                $paymentData['purchase_identifier'] = $correlationId;
            }
        }
        $payment->setTransactionId($txnId)
            ->setIsTransactionClosed($status)
            ->setTransactionAdditionalInfo(
                \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,
                array_filter($paymentData)
            );
        $transaction = $payment->addTransaction($type, null, true);
        if ($parentTransId) {
            $transaction->setParentTxnId($parentTransId);
        }
        $transaction->save();
        $transaction->setDataChanges(false);
        $payment->unsTransactions();
        $payment->save();
    }

    /**
     * Adds an invoice to the transaction / order
     *
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     * @throws \Exception
     */
    public function addInvoiceTransaction(
        \Magento\Sales\Model\Order\Invoice $invoice
    ) {
        $transaction = $this->transactionFactory->create()
            ->addObject($invoice)
            ->addObject($invoice->getOrder());

        $transaction->save();
    }
}
