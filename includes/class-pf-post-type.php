<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the Product Finder custom post type.
 */
class PF_Post_Type {

    public function __construct() {
        add_action( 'init', array( __CLASS__, 'register' ) );
    }

    public static function register() {
        $labels = array(
            'name'               => __( 'Product Finders', 'product-finder' ),
            'singular_name'      => __( 'Product Finder', 'product-finder' ),
            'add_new'            => __( 'Add New', 'product-finder' ),
            'add_new_item'       => __( 'Add New Product Finder', 'product-finder' ),
            'edit_item'          => __( 'Edit Product Finder', 'product-finder' ),
            'new_item'           => __( 'New Product Finder', 'product-finder' ),
            'view_item'          => __( 'View Product Finder', 'product-finder' ),
            'search_items'       => __( 'Search Product Finders', 'product-finder' ),
            'not_found'          => __( 'No product finders found', 'product-finder' ),
            'not_found_in_trash' => __( 'No product finders found in trash', 'product-finder' ),
            'menu_name'          => __( 'Product Finder', 'product-finder' ),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_position'       => 26,
            'menu_icon'           => 'dashicons-search',
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'supports'            => array( 'title' ),
            'has_archive'         => false,
            'rewrite'             => false,
            'query_var'           => false,
            'show_in_rest'        => false,
        );

        register_post_type( 'product_finder', $args );
    }
}

new PF_Post_Type();
