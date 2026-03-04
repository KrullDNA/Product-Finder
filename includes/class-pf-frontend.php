<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frontend shortcode and asset loading for Product Finder.
 */
class PF_Frontend {

    public function __construct() {
        add_shortcode( 'product_finder', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
    }

    public function register_assets() {
        wp_register_style(
            'pf-frontend',
            PF_PLUGIN_URL . 'frontend/css/pf-frontend.css',
            array(),
            PF_VERSION
        );

        wp_register_script(
            'pf-frontend',
            PF_PLUGIN_URL . 'frontend/js/pf-frontend.js',
            array( 'jquery' ),
            PF_VERSION,
            true
        );

        wp_register_script(
            'pf-add-to-cart',
            PF_PLUGIN_URL . 'frontend/js/pf-add-to-cart.js',
            array( 'jquery' ),
            PF_VERSION,
            true
        );

        wp_localize_script( 'pf-add-to-cart', 'pfAddToCart', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'pf_frontend_nonce' ),
        ) );
    }

    public function render_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'id'               => 0,
            'loading_heading'  => '',
            'results_heading'  => '',
        ), $atts, 'product_finder' );

        $finder_id = absint( $atts['id'] );
        if ( ! $finder_id ) {
            return '<p>' . esc_html__( 'Product Finder: invalid ID.', 'product-finder' ) . '</p>';
        }

        $questions = get_post_meta( $finder_id, '_pf_questions', true );
        if ( ! is_array( $questions ) || empty( $questions ) ) {
            return '<p>' . esc_html__( 'Product Finder: no questions configured.', 'product-finder' ) . '</p>';
        }

        $options = get_post_meta( $finder_id, '_pf_options', true );
        $options = wp_parse_args( (array) $options, array(
            'num_results'      => 5,
            'listing_template' => '',
            'cols_desktop'     => 3,
            'cols_tablet'      => 2,
            'cols_mobile'      => 1,
        ) );

        wp_enqueue_style( 'pf-frontend' );
        wp_enqueue_script( 'pf-frontend' );
        wp_enqueue_script( 'pf-add-to-cart' );

        // Pre-load WooCommerce variation scripts – results may contain
        // variable products with swatch widgets that need these.
        if ( function_exists( 'WC' ) ) {
            wp_enqueue_script( 'wc-add-to-cart' );
            wp_enqueue_script( 'wc-add-to-cart-variation' );
        }

        wp_localize_script( 'pf-frontend', 'pfFrontend', array(
            'ajax_url'  => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'pf_frontend_nonce' ),
            'finder_id' => $finder_id,
            'i18n'      => array(
                'next'         => __( 'Continue', 'product-finder' ),
                'back'         => __( 'Back', 'product-finder' ),
                'skip_email'   => __( 'Skip & View Results', 'product-finder' ),
                'send_results' => __( 'Send Results', 'product-finder' ),
                'view_results' => __( 'View Results', 'product-finder' ),
                'loading'      => __( 'Finding your perfect products…', 'product-finder' ),
                'email_label'  => __( 'Get your results sent to your inbox', 'product-finder' ),
                'email_placeholder' => __( 'Enter your email address', 'product-finder' ),
                'email_success'=> __( 'Results sent!', 'product-finder' ),
                'email_fail'   => __( 'Failed to send. Please try again.', 'product-finder' ),
                'your_results' => __( 'Your Recommended Products', 'product-finder' ),
                'start_over'   => __( 'Start Over', 'product-finder' ),
                'complete'     => __( 'Complete', 'product-finder' ),
                'add_to_cart'  => __( 'Add to Cart', 'product-finder' ),
                'tab_day'      => __( 'Day', 'product-finder' ),
                'tab_night'    => __( 'Night', 'product-finder' ),
            ),
        ) );

        // Build inline data for the finder so we don't need an extra AJAX call
        $inline_data = array();
        foreach ( $questions as $q ) {
            $q_data = array(
                'text'        => $q['text'],
                'instruction' => $q['instruction'] ?? '',
                'multiple'    => (bool) $q['multiple'],
                'answers'     => array(),
            );
            foreach ( $q['answers'] as $a ) {
                $image_url = ! empty( $a['image_id'] ) ? wp_get_attachment_image_url( $a['image_id'], 'large' ) : '';
                $q_data['answers'][] = array(
                    'text'        => $a['text'],
                    'description' => $a['description'] ?? '',
                    'image'       => $image_url,
                );
            }
            $inline_data[] = $q_data;
        }

        ob_start();
        ?>
        <div class="pf-finder" id="pf-finder-<?php echo esc_attr( $finder_id ); ?>" data-finder-id="<?php echo esc_attr( $finder_id ); ?>" data-questions="<?php echo esc_attr( wp_json_encode( $inline_data ) ); ?>" data-options="<?php echo esc_attr( wp_json_encode( $options ) ); ?>"<?php
            if ( ! empty( $atts['loading_heading'] ) ) {
                echo ' data-loading-heading="' . esc_attr( $atts['loading_heading'] ) . '"';
            }
            if ( ! empty( $atts['results_heading'] ) ) {
                echo ' data-results-heading="' . esc_attr( $atts['results_heading'] ) . '"';
            }
        ?>>

            <!-- Progress bar -->
            <div class="pf-progress-bar-wrap">
                <div class="pf-progress-bar">
                    <div class="pf-progress-fill" style="width:0%"></div>
                </div>
                <span class="pf-progress-text">0%</span>
            </div>

            <!-- Questions container -->
            <div class="pf-questions-container"></div>

            <!-- Email capture screen -->
            <div class="pf-email-screen" style="display:none;">
                <div class="pf-email-inner">
                    <h3 class="pf-email-title"></h3>
                    <p class="pf-email-desc"></p>
                    <div class="pf-email-form">
                        <input type="email" class="pf-email-input" placeholder="">
                        <button type="button" class="pf-btn pf-btn-primary pf-send-email"></button>
                    </div>
                    <button type="button" class="pf-btn pf-btn-link pf-skip-email"></button>
                    <div class="pf-email-message" style="display:none;"></div>
                </div>
            </div>

            <!-- Loading screen -->
            <div class="pf-loading-screen" style="display:none;">
                <div class="pf-loading-inner">
                    <svg class="pf-loading-icon" viewBox="0 0 50 50" width="60" height="60">
                        <circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-dasharray="90, 150" stroke-dashoffset="0"/>
                    </svg>
                    <p class="pf-loading-text"></p>
                </div>
            </div>

            <!-- Results screen -->
            <div class="pf-results-screen" style="display:none;">
                <h3 class="pf-results-title"></h3>
                <div class="pf-results-container"></div>
                <div class="pf-results-actions">
                    <button type="button" class="pf-btn pf-btn-secondary pf-start-over"></button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

new PF_Frontend();
