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

    // Verbose console logging is only active with ?pf_debug=1 in the URL.
    var PF_DEBUG = /[?&]pf_debug=1/.test(window.location.search);
    function pfLog() {
        if (PF_DEBUG && window.console) {
            console.log.apply(console, arguments);
        }
    }

    /* ── Variation event handlers ── */

    // When a swatch plugin resolves a variation, enable the button and
    // attach the variation data.
    $(document).on('found_variation', function (e, variation) {
        if (!variation || !variation.variation_id) return;

        var $target = $(e.target);

        pfLog('[PF ATC] found_variation fired, variation_id:', variation.variation_id,
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
            pfLog('[PF ATC] No listing item container found for found_variation');
            return;
        }

        var $btn = $item.find('.pf-atc-btn[data-pf-variable]');
        if (!$btn.length) {
            pfLog('[PF ATC] No pf-atc-btn found in listing item');
            return;
        }

        // Enable the button.
        pfLog('[PF ATC] Enabling button for product:', $btn.data('product_id'),
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

        pfLog('[PF ATC] Adding variable product to cart:', postData);

        $btn.removeClass('added').addClass('loading');

        // Use our custom AJAX endpoint which properly handles variable
        // products (WC's built-in ?wc-ajax=add_to_cart only supports
        // simple products).
        var ajaxUrl = (typeof pfAddToCart !== 'undefined' && pfAddToCart.ajax_url)
            ? pfAddToCart.ajax_url
            : (typeof pfFrontend !== 'undefined' && pfFrontend.ajax_url)
                ? pfFrontend.ajax_url
                : '/wp-admin/admin-ajax.php';

        var nonce = (typeof pfAddToCart !== 'undefined' && pfAddToCart.nonce)
            ? pfAddToCart.nonce
            : (typeof pfFrontend !== 'undefined' && pfFrontend.nonce)
                ? pfFrontend.nonce
                : '';

        postData.action = 'pf_add_to_cart_variable';
        postData.nonce  = nonce;

        $.post(ajaxUrl, postData, function (response) {
            $btn.removeClass('loading');

            if (!response || response.success === false) {
                console.warn('[PF ATC] Add to cart failed:', response);
                return;
            }

            $btn.addClass('added');

            // The response is from WC_AJAX::get_refreshed_fragments()
            // which returns fragments and cart_hash at the top level.
            var fragments = response.fragments || (response.data && response.data.fragments);
            var cartHash  = response.cart_hash || (response.data && response.data.cart_hash);

            // Update cart fragments (mini-cart, cart count, etc.).
            $(document.body).trigger('added_to_cart', [fragments, cartHash, $btn]);
            $(document.body).trigger('wc_fragment_refresh');

            pfLog('[PF ATC] Added to cart successfully, variation:', variationId);
        }).fail(function (jqXHR, textStatus) {
            $btn.removeClass('loading');
            console.error('[PF ATC] AJAX add to cart failed:', textStatus);
        });

        return false;
    });

    /* ── Beauty mode: add specific variation from fallback results ── */

    $(document).on('click', '.pf-atc-variation-btn', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        var $btn = $(this);

        if ($btn.hasClass('loading') || $btn.hasClass('added')) {
            return false;
        }

        var productId   = $btn.data('product_id');
        var variationId = $btn.data('variation_id');

        if (!productId || !variationId) {
            console.warn('[PF ATC] Missing product_id or variation_id on beauty button');
            return false;
        }

        var postData = {
            product_id:   productId,
            variation_id: variationId,
            quantity:     1
        };

        // Collect variation attributes from data attrs.
        var allData = $btn.data();
        $.each(allData, function (key, value) {
            if (key.indexOf('attribute_') === 0) {
                postData[key] = value;
            }
        });

        pfLog('[PF ATC] Beauty mode: adding variation to cart:', postData);

        $btn.addClass('loading').text('Adding…');

        var ajaxUrl = (typeof pfAddToCart !== 'undefined' && pfAddToCart.ajax_url)
            ? pfAddToCart.ajax_url
            : (typeof pfFrontend !== 'undefined' && pfFrontend.ajax_url)
                ? pfFrontend.ajax_url
                : '/wp-admin/admin-ajax.php';

        var nonce = (typeof pfAddToCart !== 'undefined' && pfAddToCart.nonce)
            ? pfAddToCart.nonce
            : (typeof pfFrontend !== 'undefined' && pfFrontend.nonce)
                ? pfFrontend.nonce
                : '';

        postData.action = 'pf_add_to_cart_variable';
        postData.nonce  = nonce;

        $.post(ajaxUrl, postData, function (response) {
            $btn.removeClass('loading');

            if (!response || response.success === false) {
                console.warn('[PF ATC] Beauty add to cart failed:', response);
                $btn.text(pfFrontend.i18n.add_to_cart || 'Add to Cart');
                return;
            }

            $btn.addClass('added').text('Added!');

            var fragments = response.fragments || (response.data && response.data.fragments);
            var cartHash  = response.cart_hash || (response.data && response.data.cart_hash);

            $(document.body).trigger('added_to_cart', [fragments, cartHash, $btn]);
            $(document.body).trigger('wc_fragment_refresh');

            pfLog('[PF ATC] Beauty mode: added variation', variationId, 'to cart');
        }).fail(function (jqXHR, textStatus) {
            $btn.removeClass('loading').text(pfFrontend.i18n.add_to_cart || 'Add to Cart');
            console.error('[PF ATC] Beauty AJAX add to cart failed:', textStatus);
        });

        return false;
    });

    /* ── Shade Cart widget: add specific variation to cart ── */

    $(document).on('click', '.pf-shade-atc-btn', function (e) {
        // Let WC's built-in handler manage simple products.
        if ($(this).hasClass('ajax_add_to_cart')) {
            return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();

        var $btn = $(this);

        if ($btn.hasClass('loading') || $btn.hasClass('added')) {
            return false;
        }

        var productId   = $btn.data('product_id');
        var variationId = $btn.data('variation_id');

        if (!productId || !variationId) {
            console.warn('[PF ATC] Shade Cart: missing product_id or variation_id');
            return false;
        }

        var postData = {
            product_id:   productId,
            variation_id: variationId,
            quantity:     parseInt($btn.data('quantity'), 10) || 1
        };

        // Collect variation attributes.
        var allData = $btn.data();
        $.each(allData, function (key, value) {
            if (typeof key === 'string' && key.indexOf('attribute_') === 0) {
                postData[key] = value;
            }
        });

        pfLog('[PF ATC] Shade Cart: adding to cart', postData);

        // Preserve the button inner HTML so we can restore it.
        var originalHtml = $btn.html();
        $btn.addClass('loading');

        var ajaxUrl = (typeof pfAddToCart !== 'undefined' && pfAddToCart.ajax_url)
            ? pfAddToCart.ajax_url
            : (typeof pfFrontend !== 'undefined' && pfFrontend.ajax_url)
                ? pfFrontend.ajax_url
                : '/wp-admin/admin-ajax.php';

        var nonce = (typeof pfAddToCart !== 'undefined' && pfAddToCart.nonce)
            ? pfAddToCart.nonce
            : (typeof pfFrontend !== 'undefined' && pfFrontend.nonce)
                ? pfFrontend.nonce
                : '';

        postData.action = 'pf_add_to_cart_variable';
        postData.nonce  = nonce;

        $.post(ajaxUrl, postData, function (response) {
            $btn.removeClass('loading');

            if (!response || response.success === false) {
                console.warn('[PF ATC] Shade Cart: add to cart failed', response);
                return;
            }

            $btn.addClass('added');

            var fragments = response.fragments || (response.data && response.data.fragments);
            var cartHash  = response.cart_hash || (response.data && response.data.cart_hash);

            $(document.body).trigger('added_to_cart', [fragments, cartHash, $btn]);
            $(document.body).trigger('wc_fragment_refresh');

            pfLog('[PF ATC] Shade Cart: added variation', variationId);
        }).fail(function (jqXHR, textStatus) {
            $btn.removeClass('loading');
            console.error('[PF ATC] Shade Cart: AJAX failed', textStatus);
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

    // Shade Cart quantity sync.
    $(document).on('change input', '.pf-sc-qty', function () {
        var qty = parseInt($(this).val(), 10) || 1;
        var $btn = $(this).siblings('.pf-shade-atc-btn');
        $btn.attr('data-quantity', qty);
        $btn.data('quantity', qty);
    });

})(jQuery);
