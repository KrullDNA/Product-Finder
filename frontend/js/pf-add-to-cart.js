/**
 * PF Add to Cart – Variation-aware add-to-cart handler.
 *
 * Listens for WooCommerce `found_variation` / `reset_data` events fired
 * by swatch plugins and enables or disables the PF Add to Cart button
 * accordingly.  Works inside CrocoBlock / JetEngine listing grids where
 * the swatch widget and the add-to-cart widget are sibling Elementor
 * widgets within the same listing item.
 */
(function ($) {
    'use strict';

    /* ── Variation event handlers ── */

    // When a swatch plugin resolves a variation, enable the button and
    // attach the variation data so WooCommerce's AJAX handler can use it.
    $(document).on('found_variation', function (e, variation) {
        if (!variation || !variation.variation_id) return;

        var $target = $(e.target);

        console.log('[PF ATC] found_variation fired, variation_id:', variation.variation_id,
            'target:', $target.get(0));

        // Walk up to the listing item container.
        var $item = $target.closest(
            '.jet-listing-grid__item,' +
            '.elementor-widget-wrap,' +
            '.e-con-inner,' +
            '.e-con,' +
            '.product,' +
            '.jet-listing-grid__items > div'
        );
        if (!$item.length) {
            console.log('[PF ATC] No listing item container found for found_variation');
            return;
        }

        var $btn = $item.find('.pf-atc-btn[data-pf-variable]');
        if (!$btn.length) {
            console.log('[PF ATC] No pf-atc-btn found in listing item');
            return;
        }

        // Enable the button.
        console.log('[PF ATC] Enabling button for product:', $btn.data('product_id'),
            'variation:', variation.variation_id);
        $btn
            .removeClass('pf-atc-btn--disabled')
            .addClass('add_to_cart_button ajax_add_to_cart');

        // Store variation data via jQuery .data() – WC's add-to-cart.js
        // reads data with $button.data() which uses the jQuery store.
        $btn.data('variation_id', variation.variation_id);
        $btn.data('quantity', parseInt($btn.siblings('.pf-atc-qty').val(), 10) || 1);

        // Store variation attributes so PHP handler can validate.
        if (variation.attributes) {
            $.each(variation.attributes, function (key, value) {
                $btn.data(key, value);
            });
        }

        // Update href as non-AJAX fallback.
        $btn.attr(
            'href',
            '?add-to-cart=' + $btn.data('product_id') + '&variation_id=' + variation.variation_id
        );
    });

    // When the swatch selection is cleared, disable the button again.
    $(document).on('reset_data', function (e) {
        var $target = $(e.target);

        var $item = $target.closest(
            '.jet-listing-grid__item,' +
            '.elementor-widget-wrap,' +
            '.e-con-inner,' +
            '.e-con,' +
            '.product,' +
            '.jet-listing-grid__items > div'
        );
        if (!$item.length) return;

        var $btn = $item.find('.pf-atc-btn[data-pf-variable]');
        if (!$btn.length) return;

        $btn
            .addClass('pf-atc-btn--disabled')
            .removeClass('add_to_cart_button ajax_add_to_cart added');

        $btn.removeData('variation_id');
        $btn.attr('href', '#');
    });

    /* ── Prevent clicks on disabled buttons ── */

    $(document).on('click', '.pf-atc-btn.pf-atc-btn--disabled', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
    });

    /* ── Quantity sync ── */

    $(document).on('change input', '.pf-atc-qty', function () {
        var qty = parseInt($(this).val(), 10) || 1;
        var $btn = $(this).siblings('.pf-atc-btn');
        $btn.attr('data-quantity', qty);
        $btn.data('quantity', qty);
    });

})(jQuery);
