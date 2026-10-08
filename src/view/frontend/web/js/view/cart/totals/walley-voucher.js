/**
 * Voyado voucher row in the cart totals of the Walley checkout page.
 * The value comes from the walley_voucher total segment.
 */
define([
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/totals'
], function (Component, totals) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Webbhuset_CollectorCheckout/cart/totals/walley-voucher'
        },

        getSegment: function () {
            return totals.getSegment('walley_voucher');
        },

        getPureValue: function () {
            var segment = this.getSegment();

            return segment ? parseFloat(segment.value) : 0;
        },

        getTitle: function () {
            var segment = this.getSegment();

            return segment && segment.title ? segment.title : this.title;
        },

        getValue: function () {
            return this.getFormattedPrice(this.getPureValue());
        },

        isDisplayed: function () {
            return this.getPureValue() !== 0;
        }
    });
});
