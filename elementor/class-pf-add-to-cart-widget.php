<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

/**
 * Lightweight Add-to-Cart widget for Elementor.
 *
 * Designed to work inside CrocoBlock / JetEngine listing grids without
 * the output-buffer issues caused by WooCommerce's native Add to Cart
 * widget.  Renders a simple button that uses WC's built-in AJAX
 * add-to-cart for simple products, or links to the product page for
 * variable / grouped / external products.
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

        $this->add_control( 'variable_text', array(
            'label'       => __( 'Variable Product Text', 'product-finder' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => __( 'Select Options', 'product-finder' ),
            'description' => __( 'Text shown for variable / grouped products (links to product page).', 'product-finder' ),
        ) );

        $this->add_control( 'show_price', array(
            'label'        => __( 'Show Price', 'product-finder' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => '',
            'label_on'     => __( 'Yes', 'product-finder' ),
            'label_off'    => __( 'No', 'product-finder' ),
            'description'  => __( 'Display the product price next to the button.', 'product-finder' ),
        ) );

        $this->add_control( 'show_quantity', array(
            'label'        => __( 'Show Quantity', 'product-finder' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => '',
            'label_on'     => __( 'Yes', 'product-finder' ),
            'label_off'    => __( 'No', 'product-finder' ),
            'description'  => __( 'Show a quantity input next to the button (simple products only).', 'product-finder' ),
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

        // Choose label and behaviour based on product type.
        $is_simple     = ( 'simple' === $product_type );
        $button_text   = $is_simple
            ? ( $settings['button_text'] ?: __( 'Add to Cart', 'product-finder' ) )
            : ( $settings['variable_text'] ?: __( 'Select Options', 'product-finder' ) );
        $button_url    = $is_simple ? '' : get_permalink( $product_id );

        // Build CSS classes for the button.
        // WooCommerce's add-to-cart.min.js hooks onto these classes
        // to perform AJAX add-to-cart for simple products.
        $btn_classes = array( 'pf-atc-btn', 'button' );
        if ( $is_simple && $is_purchasable ) {
            $btn_classes[] = 'add_to_cart_button';
            $btn_classes[] = 'ajax_add_to_cart';
        }
        if ( ! $is_simple ) {
            $btn_classes[] = 'product_type_' . esc_attr( $product_type );
        }

        echo '<div class="pf-atc-wrap">';

        // Optional price.
        if ( 'yes' === ( $settings['show_price'] ?? '' ) ) {
            echo '<div class="pf-atc-price">' . $the_product->get_price_html() . '</div>';
        }

        echo '<div class="pf-atc-inner">';

        // Optional quantity input (simple products only).
        if ( $is_simple && $is_purchasable && 'yes' === ( $settings['show_quantity'] ?? '' ) ) {
            echo '<input type="number" class="pf-atc-qty" value="1" min="1" max="'
                . esc_attr( $the_product->get_max_purchase_quantity() > 0 ? $the_product->get_max_purchase_quantity() : '' )
                . '" step="1" inputmode="numeric">';
        }

        if ( $is_simple && $is_purchasable ) {
            // Simple product: AJAX add-to-cart button.
            printf(
                '<a href="%s" data-product_id="%d" data-product_sku="%s" data-quantity="1" class="%s" rel="nofollow">%s</a>',
                esc_url( $the_product->add_to_cart_url() ),
                $product_id,
                esc_attr( $the_product->get_sku() ),
                esc_attr( implode( ' ', $btn_classes ) ),
                esc_html( $button_text )
            );
        } elseif ( $is_purchasable || 'external' === $product_type ) {
            // Variable / grouped / external: link to product page.
            printf(
                '<a href="%s" class="%s" rel="nofollow">%s</a>',
                esc_url( $button_url ?: $the_product->add_to_cart_url() ),
                esc_attr( implode( ' ', $btn_classes ) ),
                esc_html( $button_text )
            );
        } else {
            // Out of stock / not purchasable.
            printf(
                '<span class="pf-atc-btn pf-atc-btn--disabled">%s</span>',
                esc_html__( 'Out of Stock', 'product-finder' )
            );
        }

        echo '</div>'; // .pf-atc-inner
        echo '</div>'; // .pf-atc-wrap
    }

    /**
     * Show a placeholder button in the Elementor editor.
     */
    private function render_editor_placeholder() {
        echo '<div class="pf-atc-wrap">'
            . '<div class="pf-atc-inner">'
            . '<a href="#" class="pf-atc-btn button" onclick="return false;">'
            . esc_html__( 'Add to Cart', 'product-finder' )
            . '</a>'
            . '</div>'
            . '</div>';
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
                <a href="#" class="pf-atc-btn button" onclick="return false;">{{{ settings.button_text || 'Add to Cart' }}}</a>
            </div>
        </div>
        <?php
    }
}
