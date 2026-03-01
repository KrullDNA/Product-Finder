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

        // Record baseline output buffer level.  Rendering CrocoBlock listing
        // templates (especially with WooCommerce widgets like Add to Cart)
        // can flush / destroy output buffers, leaking HTML into the response
        // stream before wp_send_json_success() runs.  We start our own buffer
        // here and clean up everything before sending JSON.
        $ob_baseline = ob_get_level();
        ob_start();

        $finder_id = absint( $_POST['finder_id'] ?? 0 );
        $answers   = json_decode( stripslashes( $_POST['answers'] ?? '[]' ), true );

        if ( ! $finder_id || ! is_array( $answers ) ) {
            $this->ob_clean_to( $ob_baseline );
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
            $this->ob_clean_to( $ob_baseline );
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

        // Debug log
        $this->_debug = array();

        // If CrocoBlock listing template is set, render via JetEngine
        $html = '';
        if ( ! empty( $options['listing_template'] ) && ! empty( $top_ids ) ) {
            $this->_debug[] = 'listing_template=' . $options['listing_template'] . ', product_ids=' . implode( ',', $top_ids );
            $html = $this->render_crocoblock_listing( $top_ids, $options );
            $this->_debug[] = 'final listing_html length=' . strlen( $html );
        } else {
            $this->_debug[] = 'No listing template set or no product IDs';
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

        // Capture any stray output that leaked during rendering (e.g.
        // WooCommerce Add to Cart widget flushing / destroying output
        // buffers).  When safe_render()'s own buffer is destroyed the
        // rendered HTML ends up in the parent buffer that we started at
        // line 111.  If $html is still empty but the stray output
        // contains valid listing HTML, recover it instead of discarding.
        $stray = $this->ob_clean_to( $ob_baseline );
        if ( ! empty( $stray ) ) {
            $stray_stripped = trim( strip_tags( $stray ) );
            if ( empty( $html ) && ! empty( $stray_stripped ) ) {
                $html = $stray;
                $this->_debug[] = 'Recovered listing HTML from stray output (len=' . strlen( $stray ) . ')';
            } else {
                $this->_debug[] = 'Stray output discarded (len=' . strlen( $stray ) . '): '
                    . substr( $stray, 0, 500 );
            }
        }

        wp_send_json_success( array(
            'products'    => $products_data,
            'product_ids' => $top_ids,
            'listing_html'=> $html,
            'options'     => $options,
            'debug'       => $this->_debug,
        ) );
    }

    /* ────────── CrocoBlock listing render ─────────────────── */

    private function render_crocoblock_listing( $product_ids, $options ) {
        $listing_id   = absint( $options['listing_template'] );
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

        $this->_debug[] = 'WC ready: cart=' . ( is_null( WC()->cart ) ? 'NULL' : 'OK' )
            . ', session=' . ( is_null( WC()->session ) ? 'NULL' : 'OK' );

        // Method 1: JetEngine shortcode with query filter hook.
        // We inject product IDs via the jet-engine query filter rather than
        // encoding JSON inside a shortcode attribute (WordPress's shortcode
        // parser cannot handle escaped double quotes inside attribute values).
        if ( shortcode_exists( 'jet_engine_listing_grid' ) ) {
            $this->_debug[] = 'Method 1: shortcode + query filter hook';

            $pids = $product_ids;
            $query_filter = function ( $args ) use ( $pids ) {
                $args['post__in'] = $pids;
                $args['orderby']  = 'post__in';
                $args['order']    = 'ASC';
                return $args;
            };
            add_filter( 'jet-engine/listing/grid/posts-query-args', $query_filter, 9999 );

            $shortcode = sprintf(
                '[jet_engine_listing_grid listing_id="%d" posts_num="%d" columns="%d" columns_tablet="%d" columns_mobile="%d" post_type="product" is_archive_template="no"]',
                $listing_id,
                count( $product_ids ),
                $cols_desktop,
                $cols_tablet,
                $cols_mobile
            );

            $output = $this->capture_render( function () use ( $shortcode ) {
                return do_shortcode( $shortcode );
            } );

            remove_filter( 'jet-engine/listing/grid/posts-query-args', $query_filter, 9999 );

            $stripped = trim( strip_tags( $output ) );
            $this->_debug[] = 'Method 1 output: raw_len=' . strlen( $output )
                . ', stripped_len=' . strlen( $stripped )
                . ', first_500=' . substr( $output, 0, 500 );

            if ( ! empty( $stripped ) ) {
                $this->_debug[] = 'Method 1 SUCCESS';
                return $output;
            }
            $this->_debug[] = 'Method 1 FAILED – stripped output is empty';
        } else {
            $this->_debug[] = 'Method 1: jet_engine_listing_grid shortcode NOT found';
        }

        // Method 2: JetEngine PHP API
        if ( function_exists( 'jet_engine' ) && class_exists( 'Jet_Engine' ) ) {
            if ( isset( jet_engine()->listings ) && method_exists( jet_engine()->listings, 'get_render_instance' ) ) {
                $this->_debug[] = 'Method 2: JetEngine PHP API';

                $output = $this->capture_render( function () use ( $listing_id, $product_ids, $cols_desktop, $cols_tablet, $cols_mobile ) {
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

                $stripped = trim( strip_tags( $output ) );
                $this->_debug[] = 'Method 2 output: raw_len=' . strlen( $output )
                    . ', stripped_len=' . strlen( $stripped );

                if ( ! empty( $stripped ) ) {
                    $this->_debug[] = 'Method 2 SUCCESS';
                    return $output;
                }
                $this->_debug[] = 'Method 2 FAILED – stripped output is empty';
            } else {
                $this->_debug[] = 'Method 2: JetEngine listings API NOT available';
            }
        } else {
            $this->_debug[] = 'Method 2: JetEngine NOT loaded';
        }

        // Method 3: Manual loop with Elementor content rendering
        $this->_debug[] = 'Method 3: Manual Elementor loop';

        $output = $this->capture_render( function () use ( $listing_id, $product_ids, $cols_desktop, $cols_tablet, $cols_mobile ) {
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

        $stripped = trim( strip_tags( $output ) );
        $this->_debug[] = 'Method 3 output: raw_len=' . strlen( $output )
            . ', stripped_len=' . strlen( $stripped );

        if ( ! empty( $stripped ) ) {
            $this->_debug[] = 'Method 3 SUCCESS';
            return $output;
        }
        $this->_debug[] = 'Method 3 FAILED – ALL METHODS EXHAUSTED';
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
     * Execute a rendering callback with robust output capture.
     *
     * Uses two nested output buffers (outer safety net + inner capture)
     * so that content is still recovered when a third-party widget
     * (e.g. WooCommerce Add to Cart) flushes or destroys the inner
     * buffer.  In that scenario the rendered HTML leaks into the outer
     * buffer, which we collect here.
     *
     * @param callable $callback  Must either return or echo content.
     * @return string  Rendered HTML.
     */
    private function capture_render( callable $callback ) {
        $baseline = ob_get_level();

        ob_start(); // outer – safety net
        ob_start(); // inner – primary capture

        $returned = '';
        try {
            $returned = $callback();
        } catch ( \Throwable $e ) {
            $this->_debug[] = 'capture_render ERROR: ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine();
        }

        // Collect everything from all buffers above the baseline.
        // If the inner buffer was destroyed / flushed, its content now
        // sits in the outer buffer and we still capture it.
        $buffered = '';
        while ( ob_get_level() > $baseline ) {
            $buffered = ob_get_clean() . $buffered;
        }

        $this->_debug[] = 'capture_render: returned_len=' . strlen( $returned )
            . ', buffered_len=' . strlen( $buffered )
            . ', ob_delta=' . ( ob_get_level() - $baseline );

        // Prefer returned content; fall back to buffered output.
        if ( ! empty( $returned ) ) {
            return $returned;
        }
        return $buffered;
    }

    /**
     * Clean output buffers back to a baseline level.
     *
     * Discards all buffered content above the given level and returns it
     * as a string (useful for debug logging).
     *
     * @param int $baseline  The ob_get_level() value to return to.
     * @return string  Any stray output that was captured.
     */
    private function ob_clean_to( $baseline ) {
        $stray = '';
        while ( ob_get_level() > $baseline ) {
            $stray .= ob_get_clean();
        }
        return $stray;
    }
}

new PF_Ajax();
