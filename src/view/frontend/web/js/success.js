define([
    'uiComponent',
    'jquery',
    'Magento_Customer/js/customer-data',
    'mage/cookies'
], function (Element, $, customerData) {
    'use strict';
    return Element.extend({
        initialize: function (config) {
            this._super();
            if ($.mage.cookies.get('collectorbank_public_id')) {
                $.mage.cookies.clear('collectorbank_public_id');
            }
            var sections = ['cart'];

            customerData.invalidate(sections);
            customerData.reload(sections, true);
        },
    });
});
