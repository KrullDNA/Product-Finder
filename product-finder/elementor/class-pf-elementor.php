<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the Product Finder Elementor widget.
 */
class PF_Elementor {

    public function __construct() {
        add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
        add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
        add_action( 'elementor/frontend/after_register_scripts', array( $this, 'register_frontend_scripts' ) );
    }

    public function register_frontend_scripts() {
        wp_register_script(
            'pf-add-to-cart',
            PF_PLUGIN_URL . 'frontend/js/pf-add-to-cart.js',
            array( 'jquery' ),
            PF_VERSION,
            true
        );
    }

    public function register_category( $elements_manager ) {
        $elements_manager->add_category( 'product-finder', array(
            'title' => __( 'Product Finder', 'product-finder' ),
            'icon'  => 'eicon-search',
        ) );
    }

    public function register_widget( $widgets_manager ) {
        require_once PF_PLUGIN_DIR . 'elementor/class-pf-elementor-widget.php';
        $widgets_manager->register( new PF_Elementor_Widget() );

        require_once PF_PLUGIN_DIR . 'elementor/class-pf-add-to-cart-widget.php';
        $widgets_manager->register( new PF_Add_To_Cart_Widget() );

        require_once PF_PLUGIN_DIR . 'elementor/class-pf-shade-cart-widget.php';
        $widgets_manager->register( new PF_Shade_Cart_Widget() );

        require_once PF_PLUGIN_DIR . 'elementor/class-pf-product-image-widget.php';
        $widgets_manager->register( new PF_Product_Image_Widget() );

        require_once PF_PLUGIN_DIR . 'elementor/class-pf-category-label-widget.php';
        $widgets_manager->register( new PF_Category_Label_Widget() );
    }
}

new PF_Elementor();
