/**
 * PF Add to Cart – Variation-aware add-to-cart handler.
 *
 * Listens for WooCommerce `found_variation` / `reset_data` events fired
 * by swatch plugins and enables or disables the PF Add to Cart button
 * accordingly.  Works inside CrocoBlock / JetEngine listing grids where
 * the swatch widget and the add-to-cart widget are sibling Elementor
 * widgets within the same listing item.
 *
 * For variable products, handles the AJAX add-to-cart directly rather
 * than relying on WooCommerce's wc-add-to-cart.js, which may not have
 * its localized params available in AJAX-loaded content.
 */
(function ($) {
    'use strict';

    /* ── Variation event handlers ── */

    // When a swatch plugin resolves a variation, enable the button and
    // attach the variation data.
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
        $btn.removeClass('pf-atc-btn--disabled');

        // Store variation data on the button for our click handler.
        $btn.data('variation_id', variation.variation_id);
        $btn.data('quantity', parseInt($btn.siblings('.pf-atc-qty').val(), 10) || 1);

        // Store variation attributes so PHP handler can validate.
        if (variation.attributes) {
            $.each(variation.attributes, function (key, value) {
                $btn.data(key, value);
            });
        }
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
            .removeClass('added');

        $btn.removeData('variation_id');
    });

    /* ── Variable product: handle add-to-cart directly ── */

    $(document).on('click', '.pf-atc-btn[data-pf-variable]', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        var $btn = $(this);

        // Still disabled – do nothing.
        if ($btn.hasClass('pf-atc-btn--disabled')) {
            return false;
        }

        var variationId = $btn.data('variation_id');
        if (!variationId) {
            console.warn('[PF ATC] No variation_id on button');
            return false;
        }

        // Already in progress.
        if ($btn.hasClass('loading')) {
            return false;
        }

        var productId = $btn.data('product_id');
        var quantity   = parseInt($btn.data('quantity'), 10) || 1;

        // Collect all variation attribute data from the button.
        var postData = {
            product_id:   productId,
            variation_id: variationId,
            quantity:      quantity
        };

        var allData = $btn.data();
        $.each(allData, function (key, value) {
            if (key.indexOf('attribute_') === 0) {
                postData[key] = value;
            }
        });

        console.log('[PF ATC] Adding variable product to cart:', postData);

        $btn.removeClass('added').addClass('loading');

        // Determine the WC AJAX URL.
        var ajaxUrl;
        if (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.wc_ajax_url) {
            ajaxUrl = wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'add_to_cart');
        } else {
            // Fallback: build the URL from WP's admin-ajax or the page URL.
            var baseUrl = (typeof pfFrontend !== 'undefined' && pfFrontend.ajax_url)
                ? pfFrontend.ajax_url.replace('admin-ajax.php', '')
                : '/';
            ajaxUrl = baseUrl + '?wc-ajax=add_to_cart';
        }

        $.post(ajaxUrl, postData, function (response) {
            $btn.removeClass('loading');

            if (response && (response.error || response.success === false)) {
                console.warn('[PF ATC] Add to cart failed:', response);
                return;
            }

            $btn.addClass('added');

            // Update cart fragments (mini-cart, cart count, etc.).
            $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, $btn]);
            $(document.body).trigger('wc_fragment_refresh');

            console.log('[PF ATC] Added to cart successfully, variation:', variationId);
        }).fail(function (jqXHR, textStatus) {
            $btn.removeClass('loading');
            console.error('[PF ATC] AJAX add to cart failed:', textStatus);
        });

        return false;
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
