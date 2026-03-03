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
 * PF Shade Cart – Elementor Widget for CrocoBlock listing templates.
 *
 * Renders a colour swatch circle + shade label, with an add-to-cart
 * button that includes the price inline (e.g. "$115.00 | ADD TO CART +").
 * Designed to sit inside a JetEngine listing item for variable products.
 */
class PF_Shade_Cart_Widget extends Widget_Base {

    public function get_name() {
        return 'pf_shade_cart';
    }

    public function get_title() {
        return __( 'PF Shade Cart', 'product-finder' );
    }

    public function get_icon() {
        return 'eicon-circle';
    }

    public function get_categories() {
        return array( 'product-finder' );
    }

    public function get_keywords() {
        return array( 'shade', 'swatch', 'color', 'cart', 'variation', 'beauty' );
    }

    public function get_script_depends() {
        return array( 'pf-add-to-cart' );
    }

    public function get_style_depends() {
        return array( 'pf-frontend' );
    }

    /* ═══════════════════════════════════════
       CONTROLS
       ═══════════════════════════════════════ */

    protected function register_controls() {

        /* ── Content: General ── */

        $this->start_controls_section( 'section_content', array(
            'label' => __( 'Content', 'product-finder' ),
        ) );

        $this->add_control( 'shade_attribute', array(
            'label'       => __( 'Shade Attribute', 'product-finder' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'pa_shade',
            'description' => __( 'The taxonomy slug of the shade / colour attribute (e.g. pa_shade, pa_color).', 'product-finder' ),
            'label_block' => true,
        ) );

        $this->add_control( 'color_meta_key', array(
            'label'       => __( 'Colour Meta Key', 'product-finder' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'product_attribute_color',
            'description' => __( 'Term meta key where the hex colour is stored by your swatch plugin.', 'product-finder' ),
            'label_block' => true,
        ) );

        $this->add_control( 'fallback_color', array(
            'label'   => __( 'Fallback Circle Colour', 'product-finder' ),
            'type'    => Controls_Manager::COLOR,
            'default' => '#cccccc',
            'description' => __( 'Used when no swatch colour meta is found.', 'product-finder' ),
        ) );

        $this->add_control( 'button_text', array(
            'label'   => __( 'Button Text', 'product-finder' ),
            'type'    => Controls_Manager::TEXT,
            'default' => __( 'ADD TO CART', 'product-finder' ),
        ) );

        $this->add_control( 'button_icon', array(
            'label'       => __( 'Button Icon', 'product-finder' ),
            'type'        => Controls_Manager::ICONS,
            'default'     => array(
                'value'   => 'fas fa-plus',
                'library' => 'fa-solid',
            ),
        ) );

        $this->add_control( 'price_separator', array(
            'label'   => __( 'Price / Text Separator', 'product-finder' ),
            'type'    => Controls_Manager::TEXT,
            'default' => '|',
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
                '{{WRAPPER}} .pf-sc-wrap' => 'text-align: {{VALUE}};',
            ),
        ) );

        $this->end_controls_section();

        /* ═══════════════════
           STYLE TAB
           ═══════════════════ */

        /* ── Style: Shade Row ── */

        $this->start_controls_section( 'section_style_shade_row', array(
            'label' => __( 'Shade Row', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_responsive_control( 'shade_row_gap', array(
            'label'      => __( 'Gap (Circle / Label)', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', 'em' ),
            'range'      => array(
                'px' => array( 'min' => 0, 'max' => 40 ),
                'em' => array( 'min' => 0, 'max' => 3, 'step' => 0.1 ),
            ),
            'default'    => array( 'size' => 10, 'unit' => 'px' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-sc-shade-row' => 'gap: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'shade_row_margin_bottom', array(
            'label'      => __( 'Bottom Spacing', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', 'em' ),
            'range'      => array(
                'px' => array( 'min' => 0, 'max' => 60 ),
            ),
            'default'    => array( 'size' => 12, 'unit' => 'px' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-sc-shade-row' => 'margin-bottom: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->end_controls_section();

        /* ── Style: Shade Circle ── */

        $this->start_controls_section( 'section_style_circle', array(
            'label' => __( 'Shade Circle', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_responsive_control( 'circle_size', array(
            'label'      => __( 'Size', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 8, 'max' => 80 ) ),
            'default'    => array( 'size' => 24, 'unit' => 'px' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-sc-circle' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Border::get_type(), array(
            'name'     => 'circle_border',
            'selector' => '{{WRAPPER}} .pf-sc-circle',
        ) );

        $this->add_control( 'circle_border_radius', array(
            'label'      => __( 'Border Radius', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', '%' ),
            'range'      => array(
                'px' => array( 'min' => 0, 'max' => 100 ),
                '%'  => array( 'min' => 0, 'max' => 50 ),
            ),
            'default'    => array( 'size' => 50, 'unit' => '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-sc-circle' => 'border-radius: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'circle_shadow',
            'selector' => '{{WRAPPER}} .pf-sc-circle',
        ) );

        $this->end_controls_section();

        /* ── Style: Shade Label ── */

        $this->start_controls_section( 'section_style_label', array(
            'label' => __( 'Shade Label', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'label_typography',
            'selector' => '{{WRAPPER}} .pf-sc-label',
        ) );

        $this->add_control( 'label_color', array(
            'label'     => __( 'Colour', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .pf-sc-label' => 'color: {{VALUE}};' ),
        ) );

        $this->end_controls_section();

        /* ── Style: Button ── */

        $this->start_controls_section( 'section_style_button', array(
            'label' => __( 'Button', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'btn_typography',
            'selector' => '{{WRAPPER}} .pf-sc-btn',
        ) );

        $this->start_controls_tabs( 'btn_colors' );

        $this->start_controls_tab( 'btn_normal', array(
            'label' => __( 'Normal', 'product-finder' ),
        ) );
        $this->add_control( 'btn_color', array(
            'label'     => __( 'Text Colour', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .pf-sc-btn' => 'color: {{VALUE}};' ),
        ) );
        $this->add_control( 'btn_bg', array(
            'label'     => __( 'Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .pf-sc-btn' => 'background-color: {{VALUE}};' ),
        ) );
        $this->end_controls_tab();

        $this->start_controls_tab( 'btn_hover', array(
            'label' => __( 'Hover', 'product-finder' ),
        ) );
        $this->add_control( 'btn_color_hover', array(
            'label'     => __( 'Text Colour', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .pf-sc-btn:hover' => 'color: {{VALUE}};' ),
        ) );
        $this->add_control( 'btn_bg_hover', array(
            'label'     => __( 'Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .pf-sc-btn:hover' => 'background-color: {{VALUE}};' ),
        ) );
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control( 'btn_padding', array(
            'label'      => __( 'Padding', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'separator'  => 'before',
            'selectors'  => array(
                '{{WRAPPER}} .pf-sc-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'btn_border_radius', array(
            'label'      => __( 'Border Radius', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-sc-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Border::get_type(), array(
            'name'     => 'btn_border',
            'selector' => '{{WRAPPER}} .pf-sc-btn',
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'btn_shadow',
            'selector' => '{{WRAPPER}} .pf-sc-btn',
        ) );

        $this->add_control( 'btn_full_width', array(
            'label'     => __( 'Full Width', 'product-finder' ),
            'type'      => Controls_Manager::SWITCHER,
            'default'   => 'yes',
            'selectors' => array(
                '{{WRAPPER}} .pf-sc-btn' => 'width: 100%; display: flex;',
            ),
        ) );

        $this->end_controls_section();

        /* ── Style: Price (inside button) ── */

        $this->start_controls_section( 'section_style_price', array(
            'label' => __( 'Price (in Button)', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'price_typography',
            'selector' => '{{WRAPPER}} .pf-sc-btn-price',
        ) );

        $this->add_control( 'price_color', array(
            'label'     => __( 'Colour', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .pf-sc-btn-price' => 'color: {{VALUE}};' ),
        ) );

        $this->end_controls_section();

        /* ── Style: Separator ── */

        $this->start_controls_section( 'section_style_separator', array(
            'label' => __( 'Separator', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'sep_color', array(
            'label'     => __( 'Colour', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .pf-sc-btn-sep' => 'color: {{VALUE}};' ),
        ) );

        $this->add_responsive_control( 'sep_spacing', array(
            'label'      => __( 'Spacing', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', 'em' ),
            'range'      => array(
                'px' => array( 'min' => 0, 'max' => 30 ),
            ),
            'default'    => array( 'size' => 10, 'unit' => 'px' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-sc-btn-sep' => 'margin-left: {{SIZE}}{{UNIT}}; margin-right: {{SIZE}}{{UNIT}};',
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
                'px' => array( 'min' => 6, 'max' => 40 ),
            ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-sc-btn-icon'     => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .pf-sc-btn-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'icon_color', array(
            'label'     => __( 'Colour', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-sc-btn-icon'     => 'color: {{VALUE}};',
                '{{WRAPPER}} .pf-sc-btn-icon svg' => 'fill: {{VALUE}};',
            ),
        ) );

        $this->add_responsive_control( 'icon_gap', array(
            'label'      => __( 'Spacing', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', 'em' ),
            'range'      => array(
                'px' => array( 'min' => 0, 'max' => 30 ),
            ),
            'default'    => array( 'size' => 6, 'unit' => 'px' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-sc-btn-icon' => 'margin-left: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->end_controls_section();
    }

    /* ═══════════════════════════════════════
       RENDER
       ═══════════════════════════════════════ */

    protected function render() {
        global $post, $product;

        // Editor placeholder.
        if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
            $this->render_editor_placeholder();
            return;
        }

        wp_enqueue_script( 'wc-add-to-cart' );

        // Resolve the product.
        $the_product = $product;
        if ( ! $the_product instanceof \WC_Product && $post ) {
            if ( function_exists( 'wc_get_product' ) ) {
                $the_product = wc_get_product( $post->ID );
            }
        }
        if ( ! $the_product instanceof \WC_Product ) {
            return;
        }

        $settings   = $this->get_settings_for_display();
        $product_id = $the_product->get_id();
        $shade_attr = sanitize_title( $settings['shade_attribute'] ?: 'pa_shade' );
        $meta_key   = sanitize_key( $settings['color_meta_key'] ?: 'product_attribute_color' );
        $fallback   = $settings['fallback_color'] ?: '#cccccc';

        // Resolve variation data.
        $variation      = null;
        $variation_id   = 0;
        $shade_slug     = '';
        $shade_label    = '';
        $shade_hex      = $fallback;
        $price_html     = $the_product->get_price_html();
        $variation_attrs = array();

        if ( $the_product->is_type( 'variable' ) ) {
            // Check if Product Finder matched a specific variation for
            // this parent product (set during compute_results rendering).
            $matched_vid = 0;
            if ( class_exists( 'PF_Ajax' ) ) {
                $matched_vid = PF_Ajax::get_matched_variation( $product_id );
            }

            if ( $matched_vid ) {
                $variation = wc_get_product( $matched_vid );
                if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
                    $variation = null;
                }
            }

            // Fallback: pick the first in-stock variation.
            if ( ! $variation ) {
                $variations = $the_product->get_available_variations( 'objects' );
                if ( ! empty( $variations ) ) {
                    foreach ( $variations as $var ) {
                        if ( $var->is_in_stock() ) {
                            $variation = $var;
                            break;
                        }
                    }
                    if ( ! $variation ) {
                        $variation = $variations[0];
                    }
                }
            }

            if ( $variation ) {

                $variation_id   = $variation->get_id();
                $variation_attrs = $variation->get_attributes();
                $price_html     = $variation->get_price_html();

                // Extract the shade attribute value.
                if ( isset( $variation_attrs[ $shade_attr ] ) && '' !== $variation_attrs[ $shade_attr ] ) {
                    $shade_slug = $variation_attrs[ $shade_attr ];

                    // Get human-readable label.
                    if ( taxonomy_exists( $shade_attr ) ) {
                        $term = get_term_by( 'slug', $shade_slug, $shade_attr );
                        if ( $term && ! is_wp_error( $term ) ) {
                            $shade_label = $term->name;

                            // Try to get the swatch colour from term meta.
                            $color = get_term_meta( $term->term_id, $meta_key, true );
                            if ( ! $color ) {
                                // Fallback meta keys used by common swatch plugins.
                                foreach ( array( 'product_attribute_color', 'color', '_color', 'attribute_swatch_color' ) as $alt_key ) {
                                    if ( $alt_key === $meta_key ) {
                                        continue;
                                    }
                                    $color = get_term_meta( $term->term_id, $alt_key, true );
                                    if ( $color ) {
                                        break;
                                    }
                                }
                            }
                            if ( $color ) {
                                $shade_hex = $color;
                            }
                        }
                    }

                    // If no label found from taxonomy, use the raw value.
                    if ( ! $shade_label ) {
                        $shade_label = ucwords( str_replace( array( '-', '_' ), ' ', $shade_slug ) );
                    }
                }
            }
        } elseif ( $the_product->is_type( 'simple' ) ) {
            // Simple product – no shade data; only show button.
            $shade_label = '';
        }

        $is_purchasable = $the_product->is_purchasable() && $the_product->is_in_stock();
        $button_text    = $settings['button_text'] ?: __( 'ADD TO CART', 'product-finder' );
        $separator      = $settings['price_separator'] ?: '|';

        // Build icon HTML.
        $icon_html = '';
        if ( ! empty( $settings['button_icon']['value'] ) ) {
            ob_start();
            echo '<span class="pf-sc-btn-icon">';
            Icons_Manager::render_icon( $settings['button_icon'], array( 'aria-hidden' => 'true' ) );
            echo '</span>';
            $icon_html = ob_get_clean();
        }

        // ── Output ──

        echo '<div class="pf-sc-wrap">';

        // Shade row (only if we have shade data).
        if ( $shade_label ) {
            echo '<div class="pf-sc-shade-row">';
            printf(
                '<span class="pf-sc-circle" style="background-color:%s;"></span>',
                esc_attr( $shade_hex )
            );
            printf(
                '<span class="pf-sc-label">%s</span>',
                esc_html( $shade_label )
            );
            echo '</div>';
        }

        // Button.
        if ( $is_purchasable ) {
            $btn_classes = 'pf-sc-btn pf-shade-atc-btn';

            // Data attributes for AJAX add-to-cart.
            $data_attrs = sprintf(
                'data-product_id="%d" data-quantity="1"',
                $product_id
            );

            if ( $variation_id ) {
                $data_attrs .= sprintf( ' data-variation_id="%d"', $variation_id );
                // Include variation attributes so the AJAX handler can validate.
                foreach ( $variation_attrs as $attr_key => $attr_val ) {
                    $data_attrs .= sprintf(
                        ' data-attribute_%s="%s"',
                        esc_attr( sanitize_title( $attr_key ) ),
                        esc_attr( $attr_val )
                    );
                }
            } else {
                // Simple product: use WC's built-in AJAX add-to-cart.
                $btn_classes .= ' add_to_cart_button ajax_add_to_cart';
                $data_attrs  .= sprintf(
                    ' data-product_sku="%s"',
                    esc_attr( $the_product->get_sku() )
                );
            }

            printf(
                '<a href="%s" class="%s" %s rel="nofollow">',
                $variation_id ? '#' : esc_url( $the_product->add_to_cart_url() ),
                esc_attr( $btn_classes ),
                $data_attrs
            );

            // Price.
            echo '<span class="pf-sc-btn-price">' . $price_html . '</span>';
            printf( '<span class="pf-sc-btn-sep">%s</span>', esc_html( $separator ) );
            printf( '<span class="pf-sc-btn-text">%s</span>', esc_html( $button_text ) );
            echo $icon_html;

            echo '</a>';
        } else {
            // Out of stock / not purchasable.
            echo '<span class="pf-sc-btn pf-sc-btn--disabled">';
            echo '<span class="pf-sc-btn-text">' . esc_html__( 'Out of Stock', 'product-finder' ) . '</span>';
            echo '</span>';
        }

        echo '</div>';
    }

    /* ─────────── Editor placeholder ─────────── */

    private function render_editor_placeholder() {
        $settings    = $this->get_settings_for_display();
        $fallback    = $settings['fallback_color'] ?: '#cccccc';
        $button_text = $settings['button_text'] ?: __( 'ADD TO CART', 'product-finder' );
        $separator   = $settings['price_separator'] ?: '|';

        $icon_html = '';
        if ( ! empty( $settings['button_icon']['value'] ) ) {
            ob_start();
            echo '<span class="pf-sc-btn-icon">';
            Icons_Manager::render_icon( $settings['button_icon'], array( 'aria-hidden' => 'true' ) );
            echo '</span>';
            $icon_html = ob_get_clean();
        }

        echo '<div class="pf-sc-wrap">';

        echo '<div class="pf-sc-shade-row">';
        printf( '<span class="pf-sc-circle" style="background-color:%s;"></span>', esc_attr( $fallback ) );
        echo '<span class="pf-sc-label">Shade Name</span>';
        echo '</div>';

        printf(
            '<a href="#" class="pf-sc-btn" onclick="return false;"><span class="pf-sc-btn-price">&pound;115.00</span><span class="pf-sc-btn-sep">%s</span><span class="pf-sc-btn-text">%s</span>%s</a>',
            esc_html( $separator ),
            esc_html( $button_text ),
            $icon_html
        );

        echo '</div>';
    }

    /* ─────────── Live preview template ─────────── */

    protected function content_template() {
        ?>
        <#
        var fallback  = settings.fallback_color || '#cccccc';
        var btnText   = settings.button_text   || 'ADD TO CART';
        var separator = settings.price_separator || '|';
        var iconHTML  = elementor.helpers.renderIcon( view, settings.button_icon, { 'aria-hidden': true }, 'i', 'object' );
        #>
        <div class="pf-sc-wrap">
            <div class="pf-sc-shade-row">
                <span class="pf-sc-circle" style="background-color:{{{ fallback }}};"></span>
                <span class="pf-sc-label">Shade Name</span>
            </div>
            <a href="#" class="pf-sc-btn" onclick="return false;">
                <span class="pf-sc-btn-price">&pound;115.00</span>
                <span class="pf-sc-btn-sep">{{{ separator }}}</span>
                <span class="pf-sc-btn-text">{{{ btnText }}}</span>
                <# if ( iconHTML && iconHTML.value ) { #>
                <span class="pf-sc-btn-icon">{{{ iconHTML.value }}}</span>
                <# } #>
            </a>
        </div>
        <?php
    }
}
