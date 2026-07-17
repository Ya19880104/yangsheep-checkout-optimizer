/**
 * Shipping card visual proxies.
 *
 * Native WooCommerce shipping inputs remain the only submitted controls and
 * the only source of truth for checkout and logistics plugins.
 */
jQuery(function ($) {
    'use strict';

    function findNativeInput($card) {
        var packageIndex = String($card.data('package-index'));
        var methodId = String($card.data('method-id'));

        return $('#order_review input.shipping_method').filter(function () {
            var $input = $(this);
            var inputIndex = String($input.data('index'));
            var nameMatch = $input.attr('name') === 'shipping_method[' + packageIndex + ']';

            return $input.val() === methodId && (inputIndex === packageIndex || nameMatch);
        }).first();
    }

    function updateSelectedState() {
        $('.yangsheep-shipping-card').each(function () {
            var $card = $(this);
            var $input = findNativeInput($card);
            var selected = $input.length > 0 && ($input.is(':checked') || $input.attr('type') === 'hidden');

            $card.toggleClass('selected', selected);
            $card.attr('aria-checked', selected ? 'true' : 'false');
        });

        $('.yangsheep-shipping-cards-wrapper').toggle(
            $('.yangsheep-shipping-cards-container .yangsheep-shipping-card').length > 0
        );
    }

    function selectShippingCard($card) {
        var $input = findNativeInput($card);

        if (!$input.length || $input.prop('disabled')) {
            console.warn('[YS Shipping Cards] native shipping input not found');
            return;
        }

        if ($input.is(':checked') || $input.attr('type') === 'hidden') {
            updateSelectedState();
            return;
        }

        $input.prop('checked', true).trigger('change');
        updateSelectedState();
    }

    $(document).on('click', '.yangsheep-shipping-card', function () {
        selectShippingCard($(this));
    });

    $(document.body).on('change', '#order_review input.shipping_method', updateSelectedState);
    $(document.body).on('updated_checkout', updateSelectedState);

    updateSelectedState();
});
