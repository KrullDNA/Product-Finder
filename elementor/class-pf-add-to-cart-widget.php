<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Icons_Manager;

/**
 * Lightweight Add-to-Cart widget for Elementor.
 *
 * Designed to work inside CrocoBlock / JetEngine listing grids without
 * the output-buffer issues caused by WooCommerce's native Add to Cart
 * widget.  Renders a simple button that uses WC's built-in AJAX
 * add-to-cart for simple products.  For variable products, the button
 * starts disabled and activates once a variation is selected via a
 * companion swatch widget.
 */
class PF_Add_To_Cart_Widget extends Widget_Base {

    public function get_name() {
        return 'pf_add_to_cart';
    }

    public function get_title() {
        return __( 'PF Add to Cart', 'product-finder' );
    }

    public function get_icon() {
        return 'eicon-cart';
    }

    public function get_categories() {
        return array( 'product-finder' );
    }

    public function get_keywords() {
        return array( 'add to cart', 'cart', 'buy', 'woocommerce', 'product', 'listing' );
    }

    public function get_script_depends() {
        return array( 'pf-add-to-cart' );
    }

    public function get_style_depends() {
        return array( 'pf-frontend' );
    }

    /* ─────────── Controls ─────────── */

    protected function register_controls() {

        /* ── Content ── */

        $this->start_controls_section( 'section_content', array(
            'label' => __( 'Button', 'product-finder' ),
        ) );

        $this->add_control( 'button_text', array(
            'label'   => __( 'Button Text', 'product-finder' ),
            'type'    => Controls_Manager::TEXT,
            'default' => __( 'Add to Cart', 'product-finder' ),
        ) );

        $this->add_control( 'button_icon', array(
            'label'       => __( 'Icon', 'product-finder' ),
            'type'        => Controls_Manager::ICONS,
            'default'     => array(
                'value'   => 'fas fa-plus',
                'library' => 'fa-solid',
            ),
            'description' => __( 'Icon displayed after the button label.', 'product-finder' ),
        ) );

        $this->add_control( 'show_price', array(
            'label'        => __( 'Show Price', 'product-finder' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => '',
            'label_on'     => __( 'Yes', 'product-finder' ),
            'label_off'    => __( 'No', 'product-finder' ),
            'description'  => __( 'Display the product price above the button.', 'product-finder' ),
        ) );

        $this->add_control( 'show_quantity', array(
            'label'        => __( 'Show Quantity', 'product-finder' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => '',
            'label_on'     => __( 'Yes', 'product-finder' ),
            'label_off'    => __( 'No', 'product-finder' ),
            'description'  => __( 'Show a quantity input next to the button.', 'product-finder' ),
        ) );

        $this->add_responsive_control( 'align', array(
            'label'   => __( 'Alignment', 'product-finder' ),
            'type'    => Controls_Manager::CHOOSE,
            'options' => array(
                'left'   => array( 'title' => __( 'Left', 'product-finder' ),   'icon' => 'eicon-text-align-left' ),
                'center' => array( 'title' => __( 'Center', 'product-finder' ), 'icon' => 'eicon-text-align-center' ),
                'right'  => array( 'title' => __( 'Right', 'product-finder' ),  'icon' => 'eicon-text-align-right' ),
            ),
            'default'   => 'left',
            'selectors' => array(
                '{{WRAPPER}} .pf-atc-wrap' => 'text-align: {{VALUE}};',
            ),
        ) );

        $this->end_controls_section();

        /* ── Style: Button ── */

        $this->start_controls_section( 'section_style_button', array(
            'label' => __( 'Button', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'button_typography',
            'label'    => __( 'Typography', 'product-finder' ),
            'selector' => '{{WRAPPER}} .pf-atc-btn',
        ) );

        $this->start_controls_tabs( 'button_colors' );

        // Normal state
        $this->start_controls_tab( 'button_normal', array(
            'label' => __( 'Normal', 'product-finder' ),
        ) );

        $this->add_control( 'btn_color', array(
            'label'     => __( 'Text Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .pf-atc-btn' => 'color: {{VALUE}};' ),
        ) );

        $this->add_control( 'btn_bg', array(
            'label'     => __( 'Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .pf-atc-btn' => 'background-color: {{VALUE}};' ),
        ) );

        $this->end_controls_tab();

        // Hover state
        $this->start_controls_tab( 'button_hover', array(
            'label' => __( 'Hover', 'product-finder' ),
        ) );

        $this->add_control( 'btn_color_hover', array(
            'label'     => __( 'Text Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .pf-atc-btn:hover' => 'color: {{VALUE}};' ),
        ) );

        $this->add_control( 'btn_bg_hover', array(
            'label'     => __( 'Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .pf-atc-btn:hover' => 'background-color: {{VALUE}};' ),
        ) );

        $this->end_controls_tab();

        // Disabled state
        $this->start_controls_tab( 'button_disabled', array(
            'label' => __( 'Disabled', 'product-finder' ),
        ) );

        $this->add_control( 'btn_color_disabled', array(
            'label'     => __( 'Text Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .pf-atc-btn.pf-atc-btn--disabled' => 'color: {{VALUE}};' ),
        ) );

        $this->add_control( 'btn_bg_disabled', array(
            'label'     => __( 'Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .pf-atc-btn.pf-atc-btn--disabled' => 'background-color: {{VALUE}};' ),
        ) );

        $this->add_control( 'btn_opacity_disabled', array(
            'label'   => __( 'Opacity', 'product-finder' ),
            'type'    => Controls_Manager::SLIDER,
            'range'   => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ) ),
            'default' => array( 'size' => 0.5 ),
            'selectors' => array( '{{WRAPPER}} .pf-atc-btn.pf-atc-btn--disabled' => 'opacity: {{SIZE}};' ),
        ) );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control( 'btn_padding', array(
            'label'      => __( 'Padding', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em', '%' ),
            'separator'  => 'before',
            'selectors'  => array(
                '{{WRAPPER}} .pf-atc-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'btn_border_radius', array(
            'label'      => __( 'Border Radius', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-atc-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Border::get_type(), array(
            'name'     => 'btn_border',
            'selector' => '{{WRAPPER}} .pf-atc-btn',
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'btn_shadow',
            'selector' => '{{WRAPPER}} .pf-atc-btn',
        ) );

        $this->add_control( 'btn_full_width', array(
            'label'        => __( 'Full Width', 'product-finder' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => '',
            'selectors'    => array(
                '{{WRAPPER}} .pf-atc-btn' => 'width: 100%; display: block;',
            ),
        ) );

        $this->end_controls_section();

        /* ── Style: Icon ── */

        $this->start_controls_section( 'section_style_icon', array(
            'label' => __( 'Icon', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_responsive_control( 'icon_size', array(
            'label'      => __( 'Size', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', 'em' ),
            'range'      => array(
                'px' => array( 'min' => 6, 'max' => 60 ),
                'em' => array( 'min' => 0.5, 'max' => 4, 'step' => 0.1 ),
            ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-atc-btn-icon' => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .pf-atc-btn-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'icon_color', array(
            'label'     => __( 'Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-atc-btn-icon' => 'color: {{VALUE}};',
                '{{WRAPPER}} .pf-atc-btn-icon svg' => 'fill: {{VALUE}};',
            ),
        ) );

        $this->add_responsive_control( 'icon_gap', array(
            'label'      => __( 'Spacing', 'product-finder' ),
            'description'=> __( 'Gap between the label and the icon.', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', 'em' ),
            'range'      => array(
                'px' => array( 'min' => 0, 'max' => 30 ),
                'em' => array( 'min' => 0, 'max' => 2, 'step' => 0.1 ),
            ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-atc-btn-icon' => 'margin-left: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'icon_baseline_shift', array(
            'label'       => __( 'Baseline Shift', 'product-finder' ),
            'description' => __( 'Move the icon up or down to align with the text.', 'product-finder' ),
            'type'        => Controls_Manager::SLIDER,
            'size_units'  => array( 'px', 'em' ),
            'range'       => array(
                'px' => array( 'min' => -20, 'max' => 20 ),
                'em' => array( 'min' => -1, 'max' => 1, 'step' => 0.05 ),
            ),
            'selectors'   => array(
                '{{WRAPPER}} .pf-atc-btn-icon' => 'position: relative; top: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->end_controls_section();

        /* ── Style: Quantity ── */

        $this->start_controls_section( 'section_style_quantity', array(
            'label'     => __( 'Quantity Input', 'product-finder' ),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => array( 'show_quantity' => 'yes' ),
        ) );

        $this->add_responsive_control( 'qty_width', array(
            'label'      => __( 'Width', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 30, 'max' => 120 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-atc-qty' => 'width: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'qty_height', array(
            'label'      => __( 'Height', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 20, 'max' => 80 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-atc-qty' => 'height: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'qty_gap', array(
            'label'      => __( 'Gap', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-atc-inner' => 'gap: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'qty_border_color', array(
            'label'     => __( 'Border Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-atc-qty' => 'border-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'qty_border_radius', array(
            'label'      => __( 'Border Radius', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-atc-qty' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->end_controls_section();

        /* ── Style: Price ── */

        $this->start_controls_section( 'section_style_price', array(
            'label'     => __( 'Price', 'product-finder' ),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => array( 'show_price' => 'yes' ),
        ) );

        $this->add_control( 'price_color', array(
            'label'     => __( 'Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .pf-atc-price' => 'color: {{VALUE}};' ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'price_typography',
            'label'    => __( 'Typography', 'product-finder' ),
            'selector' => '{{WRAPPER}} .pf-atc-price',
        ) );

        $this->add_responsive_control( 'price_spacing', array(
            'label'      => __( 'Spacing', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-atc-price' => 'margin-bottom: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->end_controls_section();
    }

    /* ─────────── Render ─────────── */

    protected function render() {
        // Determine the current product.
        // Inside a JetEngine listing the global $post is set for each item.
        global $post, $product;

        // Editor placeholder when no product context exists.
        if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
            $this->render_editor_placeholder();
            return;
        }

        // Ensure WooCommerce's AJAX add-to-cart handler is loaded.
        wp_enqueue_script( 'wc-add-to-cart' );
        wp_enqueue_script( 'wc-add-to-cart-variation' );

        // Resolve the product object.
        $the_product = $product;
        if ( ! $the_product instanceof \WC_Product && $post ) {
            if ( function_exists( 'wc_get_product' ) ) {
                $the_product = wc_get_product( $post->ID );
            }
        }

        if ( ! $the_product instanceof \WC_Product ) {
            return; // Not in a product context – render nothing.
        }

        $settings      = $this->get_settings_for_display();
        $product_id    = $the_product->get_id();
        $product_type  = $the_product->get_type();
        $is_purchasable = $the_product->is_purchasable() && $the_product->is_in_stock();

        $is_simple     = ( 'simple' === $product_type );
        $is_variable   = ( 'variable' === $product_type );
        $button_text   = $settings['button_text'] ?: __( 'Add to Cart', 'product-finder' );

        // Build the icon HTML.
        $icon_html = '';
        if ( ! empty( $settings['button_icon']['value'] ) ) {
            ob_start();
            echo '<span class="pf-atc-btn-icon">';
            Icons_Manager::render_icon( $settings['button_icon'], array( 'aria-hidden' => 'true' ) );
            echo '</span>';
            $icon_html = ob_get_clean();
        }

        // Build CSS classes for the button.
        $btn_classes = array( 'pf-atc-btn' );
        if ( $is_simple && $is_purchasable ) {
            $btn_classes[] = 'add_to_cart_button';
            $btn_classes[] = 'ajax_add_to_cart';
        } elseif ( $is_variable && $is_purchasable ) {
            // Variable product: starts disabled until swatch selection.
            // Our own JS handles the AJAX add-to-cart for variable products
            // (WC's wc-add-to-cart.js may not have its localized params in
            // AJAX-loaded content).
            $btn_classes[] = 'pf-atc-btn--disabled';
        }

        echo '<div class="pf-atc-wrap">';

        // Optional price.
        if ( 'yes' === ( $settings['show_price'] ?? '' ) ) {
            echo '<div class="pf-atc-price">' . $the_product->get_price_html() . '</div>';
        }

        echo '<div class="pf-atc-inner">';

        // Optional quantity input (simple and variable products).
        if ( $is_purchasable && 'yes' === ( $settings['show_quantity'] ?? '' ) && ( $is_simple || $is_variable ) ) {
            $max_qty = $the_product->get_max_purchase_quantity();
            echo '<input type="number" class="pf-atc-qty" value="1" min="1" max="'
                . esc_attr( $max_qty > 0 ? $max_qty : '' )
                . '" step="1" inputmode="numeric">';
        }

        if ( $is_simple && $is_purchasable ) {
            // Simple product: AJAX add-to-cart button.
            printf(
                '<a href="%s" data-product_id="%d" data-product_sku="%s" data-quantity="1" class="%s" rel="nofollow">%s%s</a>',
                esc_url( $the_product->add_to_cart_url() ),
                $product_id,
                esc_attr( $the_product->get_sku() ),
                esc_attr( implode( ' ', $btn_classes ) ),
                esc_html( $button_text ),
                $icon_html
            );
        } elseif ( $is_variable && $is_purchasable ) {
            // Variable product: disabled until variation is selected via swatches.
            printf(
                '<a href="#" data-product_id="%d" data-product_sku="%s" data-quantity="1" data-pf-variable="1" class="%s" rel="nofollow">%s%s</a>',
                $product_id,
                esc_attr( $the_product->get_sku() ),
                esc_attr( implode( ' ', $btn_classes ) ),
                esc_html( $button_text ),
                $icon_html
            );

            // Hidden WooCommerce variation form.
            // Swatch plugins (e.g. FiF VSE) require a .variations_form with
            // data-product_variations and <select> elements to drive variation
            // selection.  Without this form, clicking a swatch cannot trigger
            // WooCommerce's found_variation event.
            $available_variations = $the_product->get_available_variations();
            $attributes           = $the_product->get_variation_attributes();

            printf(
                '<form class="variations_form cart" data-product_id="%d" data-product_variations="%s" style="position:absolute;width:0;height:0;overflow:hidden;clip:rect(0,0,0,0);">',
                $product_id,
                esc_attr( wp_json_encode( $available_variations ) )
            );
            echo '<table class="variations"><tbody>';
            foreach ( $attributes as $attribute_name => $options ) {
                $attr_key = 'attribute_' . sanitize_title( $attribute_name );
                echo '<tr><td class="value"><select name="' . esc_attr( $attr_key ) . '">';
                echo '<option value="">' . esc_html__( 'Choose an option', 'woocommerce' ) . '</option>';
                foreach ( $options as $option ) {
                    echo '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>';
                }
                echo '</select></td></tr>';
            }
            echo '</tbody></table>';
            echo '<div class="single_variation_wrap">';
            echo '<div class="woocommerce-variation single_variation"></div>';
            echo '<div class="woocommerce-variation-add-to-cart variations_button"></div>';
            echo '</div>';
            echo '</form>';
        } elseif ( $is_purchasable || 'external' === $product_type ) {
            // Grouped / external: link to product page.
            printf(
                '<a href="%s" class="%s" rel="nofollow">%s%s</a>',
                esc_url( get_permalink( $product_id ) ?: $the_product->add_to_cart_url() ),
                esc_attr( implode( ' ', $btn_classes ) ),
                esc_html( $button_text ),
                $icon_html
            );
        } else {
            // Out of stock / not purchasable.
            printf(
                '<span class="pf-atc-btn pf-atc-btn--disabled">%s%s</span>',
                esc_html__( 'Out of Stock', 'product-finder' ),
                $icon_html
            );
        }

        echo '</div>'; // .pf-atc-inner
        echo '</div>'; // .pf-atc-wrap
    }

    /**
     * Show a placeholder button in the Elementor editor.
     */
    private function render_editor_placeholder() {
        $settings  = $this->get_settings_for_display();
        $icon_html = '';
        if ( ! empty( $settings['button_icon']['value'] ) ) {
            ob_start();
            echo '<span class="pf-atc-btn-icon">';
            Icons_Manager::render_icon( $settings['button_icon'], array( 'aria-hidden' => 'true' ) );
            echo '</span>';
            $icon_html = ob_get_clean();
        }

        echo '<div class="pf-atc-wrap">';
        if ( 'yes' === ( $settings['show_price'] ?? '' ) ) {
            echo '<div class="pf-atc-price">&pound;19.99</div>';
        }
        echo '<div class="pf-atc-inner">';
        if ( 'yes' === ( $settings['show_quantity'] ?? '' ) ) {
            echo '<input type="number" class="pf-atc-qty" value="1" min="1" step="1">';
        }
        printf(
            '<a href="#" class="pf-atc-btn" onclick="return false;">%s%s</a>',
            esc_html( $settings['button_text'] ?: __( 'Add to Cart', 'product-finder' ) ),
            $icon_html
        );
        echo '</div></div>';
    }

    /**
     * Editor live preview template.
     */
    protected function content_template() {
        ?>
        <div class="pf-atc-wrap">
            <# if ( settings.show_price === 'yes' ) { #>
            <div class="pf-atc-price">&pound;19.99</div>
            <# } #>
            <div class="pf-atc-inner">
                <# if ( settings.show_quantity === 'yes' ) { #>
                <input type="number" class="pf-atc-qty" value="1" min="1" step="1">
                <# } #>
                <a href="#" class="pf-atc-btn" onclick="return false;">
                    {{{ settings.button_text || 'Add to Cart' }}}
                    <#
                    var iconHTML = elementor.helpers.renderIcon( view, settings.button_icon, { 'aria-hidden': true }, 'i', 'object' );
                    if ( iconHTML && iconHTML.value ) {
                    #>
                    <span class="pf-atc-btn-icon">{{{ iconHTML.value }}}</span>
                    <# } #>
                </a>
            </div>
        </div>
        <?php
    }
}
