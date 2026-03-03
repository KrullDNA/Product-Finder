<?php
/**
 * Plugin Name: Product Finder
 * Plugin URI: https://github.com/KrullDNA/Product-Finder
 * Description: An intelligent WooCommerce Product Finder plugin with multiple-choice quiz format, product ranking, CrocoBlock listing integration, and a full Elementor widget.
 * Version: 1.1.0
 * Author: KrullDNA
 * Author URI: https://github.com/KrullDNA
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: product-finder
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PF_VERSION', '1.1.0' );
define( 'PF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Product Finder class.
 */
final class Product_Finder {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    private function includes() {
        require_once PF_PLUGIN_DIR . 'includes/class-pf-post-type.php';
        require_once PF_PLUGIN_DIR . 'includes/class-pf-admin.php';
        require_once PF_PLUGIN_DIR . 'includes/class-pf-frontend.php';
        require_once PF_PLUGIN_DIR . 'includes/class-pf-ajax.php';
        require_once PF_PLUGIN_DIR . 'includes/class-pf-email.php';

        if ( did_action( 'elementor/loaded' ) ) {
            require_once PF_PLUGIN_DIR . 'elementor/class-pf-elementor.php';
        } else {
            add_action( 'elementor/loaded', function () {
                require_once PF_PLUGIN_DIR . 'elementor/class-pf-elementor.php';
            } );
        }
    }

    private function init_hooks() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        add_action( 'init', array( $this, 'load_textdomain' ) );
    }

    public function activate() {
        PF_Post_Type::register();
        flush_rewrite_rules();
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'product-finder', false, dirname( PF_PLUGIN_BASENAME ) . '/languages/' );
    }
}

/**
 * Boot the plugin.
 */
function product_finder() {
    return Product_Finder::instance();
}

add_action( 'plugins_loaded', 'product_finder' );
