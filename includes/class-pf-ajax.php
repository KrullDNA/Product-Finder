<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AJAX handlers for Product Finder.
 */
class PF_Ajax {

    public function __construct() {
        // Admin: product search
        add_action( 'wp_ajax_pf_search_products', array( $this, 'search_products' ) );

        // Frontend: get finder data
        add_action( 'wp_ajax_pf_get_finder', array( $this, 'get_finder' ) );
        add_action( 'wp_ajax_nopriv_pf_get_finder', array( $this, 'get_finder' ) );

        // Frontend: compute results
        add_action( 'wp_ajax_pf_compute_results', array( $this, 'compute_results' ) );
        add_action( 'wp_ajax_nopriv_pf_compute_results', array( $this, 'compute_results' ) );
    }

    /* ────────── Admin: search WooCommerce products ────────── */

    public function search_products() {
        check_ajax_referer( 'pf_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error();
        }

        $term = sanitize_text_field( $_GET['term'] ?? '' );
        if ( strlen( $term ) < 2 ) {
            wp_send_json( array() );
        }

        $products = wc_get_products( array(
            'status' => 'publish',
            's'      => $term,
            'limit'  => 20,
        ) );

        $results = array();
        foreach ( $products as $product ) {
            $results[] = array(
                'id'    => $product->get_id(),
                'text'  => $product->get_name(),
                'price' => $product->get_price_html(),
                'thumb' => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
            );
        }

        wp_send_json( $results );
    }

    /* ────────── Frontend: get finder data (questions) ──────── */

    public function get_finder() {
        check_ajax_referer( 'pf_frontend_nonce', 'nonce' );

        $finder_id = absint( $_GET['finder_id'] ?? 0 );
        if ( ! $finder_id ) {
            wp_send_json_error();
        }

        $questions = get_post_meta( $finder_id, '_pf_questions', true );
        $options   = get_post_meta( $finder_id, '_pf_options', true );

        if ( ! is_array( $questions ) ) {
            $questions = array();
        }

        // Build safe data for frontend
        $data = array();
        foreach ( $questions as $q ) {
            $q_data = array(
                'text'        => $q['text'],
                'instruction' => $q['instruction'] ?? '',
                'multiple'    => (bool) $q['multiple'],
                'answers'     => array(),
            );
            foreach ( $q['answers'] as $a ) {
                $image_url = $a['image_id'] ? wp_get_attachment_image_url( $a['image_id'], 'medium' ) : '';
                $q_data['answers'][] = array(
                    'text'  => $a['text'],
                    'image' => $image_url,
                );
            }
            $data[] = $q_data;
        }

        wp_send_json_success( array(
            'questions' => $data,
            'options'   => $options,
            'title'     => get_the_title( $finder_id ),
        ) );
    }

    /* ────────── Frontend: compute results ──────────────────── */

    public function compute_results() {
        check_ajax_referer( 'pf_frontend_nonce', 'nonce' );

        $finder_id = absint( $_POST['finder_id'] ?? 0 );
        $answers   = json_decode( stripslashes( $_POST['answers'] ?? '[]' ), true );

        if ( ! $finder_id || ! is_array( $answers ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid data.', 'product-finder' ) ) );
        }

        $questions = get_post_meta( $finder_id, '_pf_questions', true );
        $options   = get_post_meta( $finder_id, '_pf_options', true );
        $options   = wp_parse_args( (array) $options, array(
            'num_results'      => 5,
            'listing_template' => '',
            'cols_desktop'     => 3,
            'cols_tablet'      => 2,
            'cols_mobile'      => 1,
        ) );

        if ( ! is_array( $questions ) ) {
            wp_send_json_error();
        }

        // Score products: lower rank = higher score (inverted)
        $product_scores = array();

        foreach ( $answers as $qi => $selected_indices ) {
            if ( ! isset( $questions[ $qi ] ) ) {
                continue;
            }
            $q = $questions[ $qi ];

            foreach ( (array) $selected_indices as $ai ) {
                $ai = absint( $ai );
                if ( ! isset( $q['answers'][ $ai ] ) ) {
                    continue;
                }
                $a = $q['answers'][ $ai ];

                if ( empty( $a['products'] ) ) {
                    continue;
                }

                // Find the maximum rank in this answer for inversion
                $max_rank = 1;
                foreach ( $a['products'] as $p ) {
                    if ( (int) $p['rank'] > $max_rank ) {
                        $max_rank = (int) $p['rank'];
                    }
                }

                foreach ( $a['products'] as $p ) {
                    $pid  = absint( $p['id'] );
                    $rank = absint( $p['rank'] );
                    if ( ! $pid ) {
                        continue;
                    }
                    // Score = max_rank + 1 - rank (so rank 1 gets highest score)
                    $score = $max_rank + 1 - $rank;
                    if ( ! isset( $product_scores[ $pid ] ) ) {
                        $product_scores[ $pid ] = 0;
                    }
                    $product_scores[ $pid ] += $score;
                }
            }
        }

        // Sort by score descending
        arsort( $product_scores );

        // Limit results
        $top_ids = array_slice( array_keys( $product_scores ), 0, (int) $options['num_results'] );

        // If CrocoBlock listing template is set, render via JetEngine
        $html = '';
        if ( ! empty( $options['listing_template'] ) && ! empty( $top_ids ) ) {
            $html = $this->render_crocoblock_listing( $top_ids, $options );
        }

        // Build basic product data as fallback
        $products_data = array();
        foreach ( $top_ids as $pid ) {
            $product = wc_get_product( $pid );
            if ( ! $product ) {
                continue;
            }
            $products_data[] = array(
                'id'        => $pid,
                'name'      => $product->get_name(),
                'price'     => $product->get_price_html(),
                'image'     => wp_get_attachment_image_url( $product->get_image_id(), 'medium' ),
                'permalink' => $product->get_permalink(),
                'score'     => $product_scores[ $pid ],
            );
        }

        wp_send_json_success( array(
            'products'    => $products_data,
            'product_ids' => $top_ids,
            'listing_html'=> $html,
            'options'     => $options,
        ) );
    }

    /* ────────── CrocoBlock listing render ─────────────────── */

    private function render_crocoblock_listing( $product_ids, $options ) {
        $listing_id  = absint( $options['listing_template'] );
        $cols_desktop = absint( $options['cols_desktop'] ?? 3 );
        $cols_tablet  = absint( $options['cols_tablet'] ?? 2 );
        $cols_mobile  = absint( $options['cols_mobile'] ?? 1 );

        if ( ! $listing_id || empty( $product_ids ) ) {
            return '';
        }

        // Ensure WooCommerce cart, session and frontend are fully loaded.
        // Widgets like Add to Cart need the cart and session objects, which
        // may not be initialised during an AJAX request.
        $this->ensure_wc_frontend();

        // Method 1: Use the jet-engine shortcode (most reliable)
        if ( shortcode_exists( 'jet_engine_listing_grid' ) ) {
            $ids_string = implode( ',', $product_ids );
            $shortcode  = '[jet_engine_listing_grid'
                . ' listing_id="' . $listing_id . '"'
                . ' post_status="publish"'
                . ' posts_query="[{\"type\":\"posts_params\",\"posts_in\":\"' . $ids_string . '\"},{\"type\":\"order_offset\",\"order_by\":\"post__in\",\"order\":\"ASC\"}]"'
                . ' posts_num="' . count( $product_ids ) . '"'
                . ' columns="' . $cols_desktop . '"'
                . ' columns_tablet="' . $cols_tablet . '"'
                . ' columns_mobile="' . $cols_mobile . '"'
                . ' post_type="product"'
                . ' is_archive_template="no"'
                . ']';

            $output = $this->safe_render( function () use ( $shortcode ) {
                return do_shortcode( $shortcode );
            } );
            if ( ! empty( trim( strip_tags( $output ) ) ) ) {
                return $output;
            }
        }

        // Method 2: Use JetEngine PHP API if available
        if ( function_exists( 'jet_engine' ) && class_exists( 'Jet_Engine' ) ) {
            // Try the listing grid render
            if ( isset( jet_engine()->listings ) && method_exists( jet_engine()->listings, 'get_render_instance' ) ) {
                $output = $this->safe_render( function () use ( $listing_id, $product_ids, $cols_desktop, $cols_tablet, $cols_mobile ) {
                    $render = jet_engine()->listings->get_render_instance( 'listing-grid', array(
                        'listing_id'     => $listing_id,
                        'lisitng_id'     => $listing_id,
                        'posts_num'      => count( $product_ids ),
                        'columns'        => $cols_desktop,
                        'columns_tablet' => $cols_tablet,
                        'columns_mobile' => $cols_mobile,
                        'post_type'      => 'product',
                        'posts_query'    => array(
                            array(
                                'type'     => 'posts_params',
                                'posts_in' => implode( ',', $product_ids ),
                            ),
                            array(
                                'type'     => 'order_offset',
                                'order_by' => 'post__in',
                                'order'    => 'ASC',
                            ),
                        ),
                        'is_archive_template' => false,
                    ) );
                    if ( $render ) {
                        $render->render();
                    }
                    return '';
                } );
                if ( ! empty( trim( strip_tags( $output ) ) ) ) {
                    return $output;
                }
            }
        }

        // Method 3: Manual loop with Elementor content rendering
        $output = $this->safe_render( function () use ( $listing_id, $product_ids, $cols_desktop, $cols_tablet, $cols_mobile ) {
            $query = new WP_Query( array(
                'post_type'      => 'product',
                'post__in'       => $product_ids,
                'orderby'        => 'post__in',
                'posts_per_page' => count( $product_ids ),
            ) );

            if ( $query->have_posts() ) {
                echo '<div class="pf-results-grid pf-cols-d-' . esc_attr( $cols_desktop ) . ' pf-cols-t-' . esc_attr( $cols_tablet ) . ' pf-cols-m-' . esc_attr( $cols_mobile ) . '">';

                $listing_post = get_post( $listing_id );

                while ( $query->have_posts() ) {
                    $query->the_post();

                    // Ensure the WooCommerce global $product is set for this post
                    if ( function_exists( 'wc_setup_product_data' ) ) {
                        wc_setup_product_data( get_the_ID() );
                    }

                    echo '<div class="pf-result-item">';

                    if ( $listing_post ) {
                        if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->documents->get( $listing_id ) ) {
                            echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $listing_id );
                        } else {
                            echo apply_filters( 'the_content', $listing_post->post_content );
                        }
                    }

                    echo '</div>';
                }

                echo '</div>';
                wp_reset_postdata();
            }
            return '';
        } );
        if ( ! empty( trim( strip_tags( $output ) ) ) ) {
            return $output;
        }

        // If all methods failed, return empty so frontend uses fallback cards
        return '';
    }

    /**
     * Ensure WooCommerce frontend environment is fully loaded.
     *
     * During AJAX the cart, session and customer objects may not be
     * initialised yet.  Widgets such as the WooCommerce Add-to-Cart
     * button depend on these being available.
     */
    private function ensure_wc_frontend() {
        if ( ! function_exists( 'WC' ) || ! WC() ) {
            return;
        }

        // wc_load_cart() (WC 3.6+) initialises session, customer & cart.
        if ( function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
            return;
        }

        // Fallback for older WooCommerce versions.
        if ( is_null( WC()->session ) && class_exists( 'WC_Session_Handler' ) ) {
            WC()->session = new \WC_Session_Handler();
            WC()->session->init();
        }

        if ( is_null( WC()->customer ) && class_exists( 'WC_Customer' ) ) {
            WC()->customer = new \WC_Customer( get_current_user_id(), true );
        }

        if ( is_null( WC()->cart ) && class_exists( 'WC_Cart' ) ) {
            WC()->cart = new \WC_Cart();
        }
    }

    /**
     * Run a render callback inside an output buffer with error handling.
     *
     * Captures any stray PHP output (warnings, notices) that would
     * otherwise corrupt the JSON response.  Also catches Throwable
     * errors so a single broken widget does not kill the whole request.
     *
     * @param callable $callback  Must either echo or return content.
     * @return string  Rendered HTML.
     */
    private function safe_render( callable $callback ) {
        ob_start();
        try {
            $returned = $callback();
        } catch ( \Throwable $e ) {
            // Discard partial output from the failed render.
            ob_end_clean();
            return '';
        }

        $echoed = ob_get_clean();

        // If the callback returned content, prefer that; otherwise use
        // whatever was echoed into the output buffer.
        if ( ! empty( $returned ) ) {
            return $returned;
        }

        return $echoed;
    }
}

new PF_Ajax();
