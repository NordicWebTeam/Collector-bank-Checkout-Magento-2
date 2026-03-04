<?php

namespace Webbhuset\CollectorCheckout\Helper;

use Magento\Quote\Model\Quote;

/**
 * Helper for shipping update response
 */
class BuildShippingUpdateResponse
{
    /**
     * @param Quote $quote
     * @return array
     */
    public function execute(Quote $quote): array
    {
        $shippingAddress = $quote->getShippingAddress();

        $data = [
            'postcode' => $shippingAddress->getPostcode(),
            'region' => $shippingAddress->getRegion(),
            'country_id' => $shippingAddress->getCountryId(),
            'shipping_method' => $shippingAddress->getShippingMethod(),
            'updated' => true
        ];

        // Description is used as Carrier Title when method is Collector Shipping
        if ($shippingAddress->getShippingMethod() === 'collectorshipping_collectorshipping') {
            $data['carrier_title'] = $shippingAddress->getShippingDescription();
            $data['shipping_method_title'] = '';
            return $data;
        }

        // Standard data structure for other methods 
        $shippingMethod = $shippingAddress->getShippingRateByCode($shippingAddress->getShippingMethod());
        if ($shippingMethod && $shippingMethod->getRateId()) {
            $data['carrier_title'] = $shippingMethod->getCarrierTitle();
            $data['shipping_method_title'] = $shippingMethod->getMethodTitle();
        }

        return $data;
    }
}
