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
    }
}

new PF_Elementor();
