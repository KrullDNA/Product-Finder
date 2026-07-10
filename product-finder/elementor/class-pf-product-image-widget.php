<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

/**
 * PF Product Image – simple product image for CrocoBlock listings.
 *
 * Renders the product featured image without zoom / lightbox.
 * When inside a Product Finder results listing, automatically
 * swaps to the matched variation image.
 */
class PF_Product_Image_Widget extends Widget_Base {

    public function get_name() {
        return 'pf_product_image';
    }

    public function get_title() {
        return __( 'PF Product Image', 'product-finder' );
    }

    public function get_icon() {
        return 'eicon-image';
    }

    public function get_categories() {
        return array( 'product-finder' );
    }

    public function get_keywords() {
        return array( 'image', 'product', 'photo', 'variation', 'listing' );
    }

    public function get_style_depends() {
        return array( 'pf-frontend' );
    }

    /* ═══════════════════════════════════════
       CONTROLS
       ═══════════════════════════════════════ */

    protected function register_controls() {

        /* ── Content ── */

        $this->start_controls_section( 'section_content', array(
            'label' => __( 'Image', 'product-finder' ),
        ) );

        $this->add_control( 'image_size', array(
            'label'   => __( 'Image Size', 'product-finder' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'woocommerce_single',
            'options' => array(
                'thumbnail'            => __( 'Thumbnail', 'product-finder' ),
                'medium'               => __( 'Medium', 'product-finder' ),
                'medium_large'         => __( 'Medium Large', 'product-finder' ),
                'large'                => __( 'Large', 'product-finder' ),
                'woocommerce_single'   => __( 'WooCommerce Single', 'product-finder' ),
                'woocommerce_thumbnail'=> __( 'WooCommerce Thumbnail', 'product-finder' ),
                'full'                 => __( 'Full', 'product-finder' ),
            ),
        ) );

        $this->add_control( 'link_to_product', array(
            'label'        => __( 'Link to Product', 'product-finder' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'label_on'     => __( 'Yes', 'product-finder' ),
            'label_off'    => __( 'No', 'product-finder' ),
        ) );

        $this->add_responsive_control( 'align', array(
            'label'   => __( 'Alignment', 'product-finder' ),
            'type'    => Controls_Manager::CHOOSE,
            'options' => array(
                'left'   => array( 'title' => __( 'Left', 'product-finder' ),   'icon' => 'eicon-text-align-left' ),
                'center' => array( 'title' => __( 'Center', 'product-finder' ), 'icon' => 'eicon-text-align-center' ),
                'right'  => array( 'title' => __( 'Right', 'product-finder' ),  'icon' => 'eicon-text-align-right' ),
            ),
            'default'   => 'center',
            'selectors' => array(
                '{{WRAPPER}} .pf-pi-wrap' => 'text-align: {{VALUE}};',
            ),
        ) );

        $this->end_controls_section();

        /* ═══════════════════
           STYLE TAB
           ═══════════════════ */

        /* ── Style: Image ── */

        $this->start_controls_section( 'section_style_image', array(
            'label' => __( 'Image', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_responsive_control( 'image_width', array(
            'label'      => __( 'Width', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', '%' ),
            'range'      => array(
                'px' => array( 'min' => 50, 'max' => 1200 ),
                '%'  => array( 'min' => 10, 'max' => 100 ),
            ),
            'default'    => array( 'size' => 100, 'unit' => '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-pi-img' => 'width: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'image_max_width', array(
            'label'      => __( 'Max Width', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', '%' ),
            'range'      => array(
                'px' => array( 'min' => 50, 'max' => 1200 ),
                '%'  => array( 'min' => 10, 'max' => 100 ),
            ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-pi-img' => 'max-width: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'image_height', array(
            'label'      => __( 'Height', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', 'vh' ),
            'range'      => array(
                'px' => array( 'min' => 50, 'max' => 1000 ),
                'vh' => array( 'min' => 10, 'max' => 100 ),
            ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-pi-img' => 'height: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'image_fit', array(
            'label'   => __( 'Object Fit', 'product-finder' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'cover',
            'options' => array(
                'cover'   => __( 'Cover', 'product-finder' ),
                'contain' => __( 'Contain', 'product-finder' ),
                'fill'    => __( 'Fill', 'product-finder' ),
                'none'    => __( 'None', 'product-finder' ),
            ),
            'selectors' => array(
                '{{WRAPPER}} .pf-pi-img' => 'object-fit: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'image_border_radius', array(
            'label'      => __( 'Border Radius', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-pi-img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Border::get_type(), array(
            'name'     => 'image_border',
            'selector' => '{{WRAPPER}} .pf-pi-img',
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'image_shadow',
            'selector' => '{{WRAPPER}} .pf-pi-img',
        ) );

        $this->add_control( 'image_opacity', array(
            'label'   => __( 'Opacity', 'product-finder' ),
            'type'    => Controls_Manager::SLIDER,
            'range'   => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ) ),
            'selectors' => array(
                '{{WRAPPER}} .pf-pi-img' => 'opacity: {{SIZE}};',
            ),
        ) );

        $this->add_control( 'hover_opacity', array(
            'label'   => __( 'Hover Opacity', 'product-finder' ),
            'type'    => Controls_Manager::SLIDER,
            'range'   => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ) ),
            'selectors' => array(
                '{{WRAPPER}} .pf-pi-wrap:hover .pf-pi-img' => 'opacity: {{SIZE}};',
            ),
        ) );

        $this->end_controls_section();

        /* ── Style: Spacing ── */

        $this->start_controls_section( 'section_style_spacing', array(
            'label' => __( 'Spacing', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_responsive_control( 'image_margin', array(
            'label'      => __( 'Margin', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em', '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-pi-wrap' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
        $img_size   = $settings['image_size'] ?: 'woocommerce_single';
        $link       = 'yes' === ( $settings['link_to_product'] ?? 'yes' );

        // Determine the image to show.
        $image_id = $the_product->get_image_id();

        // Check if Product Finder matched a specific variation — use its image.
        if ( $the_product->is_type( 'variable' ) && class_exists( 'PF_Ajax' ) ) {
            $matched_vid = PF_Ajax::get_matched_variation( $product_id );
            if ( $matched_vid ) {
                $variation = wc_get_product( $matched_vid );
                if ( $variation && $variation->get_image_id() ) {
                    $image_id = $variation->get_image_id();
                }
            }
        }

        if ( ! $image_id ) {
            // Use WooCommerce placeholder.
            $image_url = wc_placeholder_img_src( $img_size );
            $img_tag   = '<img src="' . esc_url( $image_url ) . '" class="pf-pi-img" alt="' . esc_attr( $the_product->get_name() ) . '">';
        } else {
            $img_tag = wp_get_attachment_image( $image_id, $img_size, false, array(
                'class' => 'pf-pi-img',
                'alt'   => $the_product->get_name(),
            ) );
        }

        $permalink = $the_product->get_permalink();

        echo '<div class="pf-pi-wrap">';
        if ( $link && $permalink ) {
            printf( '<a href="%s" class="pf-pi-link">%s</a>', esc_url( $permalink ), $img_tag );
        } else {
            echo $img_tag;
        }
        echo '</div>';
    }

    /* ─────────── Editor placeholder ─────────── */

    private function render_editor_placeholder() {
        echo '<div class="pf-pi-wrap">';
        echo '<img src="' . esc_url( wc_placeholder_img_src( 'woocommerce_single' ) ) . '" class="pf-pi-img" alt="Product Image">';
        echo '</div>';
    }

    /* ─────────── Live preview template ─────────── */

    protected function content_template() {
        ?>
        <div class="pf-pi-wrap">
            <img src="<?php echo esc_url( wc_placeholder_img_src( 'woocommerce_single' ) ); ?>" class="pf-pi-img" alt="Product Image">
        </div>
        <?php
    }
}
