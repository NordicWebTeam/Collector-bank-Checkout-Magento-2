<?php

namespace Webbhuset\CollectorCheckout\Plugin;

/**
 * Class SetNeedsUpdateOnItemChange
 *
 * @package Webbhuset\CollectorCheckout\Plugin
 */
class SetNeedsUpdateOnItemChange
{
    /**
     * Plugin function to set a flag that collector bank needs update if items has been removed
     *
     * @param \Magento\Checkout\Model\Cart $subject
     * @param                              $result
     * @return mixed
     */
    public function afterRemoveItem(
        \Magento\Checkout\Model\Cart $subject,
        $result
    ) {
        $subject->getQuote()->setNeedsCollectorUpdate(true);

        return $result;
    }

    /**
     * Plugin function to set a flag that collector bank needs update if items has been updated
     *
     * @param \Magento\Checkout\Model\Cart $subject
     * @param                              $result
     * @return mixed
     */
    public function afterUpdateItems(
        \Magento\Checkout\Model\Cart $subject,
        $result
    ) {
        $subject->getQuote()->setNeedsCollectorUpdate(true);

        return $result;
    }

    public function afterSave(
        \Magento\Checkout\Model\Cart $subject,
        $result
    ) {
        $subject->getQuote()->setNeedsCollectorUpdate(true);

        return $result;
    }
}
