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

        // Admin: get variations for a product
        add_action( 'wp_ajax_pf_get_variations', array( $this, 'get_variations' ) );

        // Frontend: get finder data
        add_action( 'wp_ajax_pf_get_finder', array( $this, 'get_finder' ) );
        add_action( 'wp_ajax_nopriv_pf_get_finder', array( $this, 'get_finder' ) );

        // Frontend: compute results
        add_action( 'wp_ajax_pf_compute_results', array( $this, 'compute_results' ) );
        add_action( 'wp_ajax_nopriv_pf_compute_results', array( $this, 'compute_results' ) );

        // Frontend: add variable product to cart
        add_action( 'wp_ajax_pf_add_to_cart_variable', array( $this, 'add_to_cart_variable' ) );
        add_action( 'wp_ajax_nopriv_pf_add_to_cart_variable', array( $this, 'add_to_cart_variable' ) );
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

    /* ────────── Admin: get variations for a product ────────── */

    public function get_variations() {
        check_ajax_referer( 'pf_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error();
        }

        $product_id = absint( $_GET['product_id'] ?? 0 );
        if ( ! $product_id ) {
            wp_send_json( array() );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            wp_send_json( array() );
        }

        $variations = $product->get_available_variations();
        $results    = array();

        foreach ( $variations as $var ) {
            $attrs = array();
            foreach ( $var['attributes'] as $key => $val ) {
                $taxonomy = str_replace( 'attribute_', '', $key );
                $label    = wc_attribute_label( $taxonomy );
                $term     = get_term_by( 'slug', $val, $taxonomy );
                $attrs[]  = ( $term ? $term->name : $val );
            }

            $results[] = array(
                'id'    => $var['variation_id'],
                'text'  => implode( ' / ', $attrs ),
                'price' => $var['price_html'] ?? '',
                'thumb' => $var['image']['thumb_src'] ?? '',
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
                $image_url = $a['image_id'] ? wp_get_attachment_image_url( $a['image_id'], 'large' ) : '';
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
            'finder_type'      => 'cosmeceuticals',
        ) );

        $is_beauty = ( 'beauty' === ( $options['finder_type'] ?? 'cosmeceuticals' ) );

        if ( ! is_array( $questions ) ) {
            $this->ob_clean_to( $ob_baseline );
            wp_send_json_error();
        }

        /* ── Intelligent scoring: collate ALL answers for comprehensive matching ── */

        // In Beauty mode, scoring keys are "pid:variation_id" when a variation
        // is specified, allowing different shades of the same parent product to
        // be scored independently.  In Cosmeceuticals mode the key is just pid.
        $product_scores    = array(); // key => raw score
        $product_questions = array(); // key => array of question indices
        $product_answers   = array(); // key => array of { qi, ai, question_text, answer_text }
        $key_map           = array(); // key => { pid, variation_id }
        $max_possible      = 0;
        $total_answered    = 0;

        foreach ( $answers as $qi => $selected_indices ) {
            if ( ! isset( $questions[ $qi ] ) ) {
                continue;
            }
            $q = $questions[ $qi ];
            $total_answered++;

            foreach ( (array) $selected_indices as $ai ) {
                $ai = absint( $ai );
                if ( ! isset( $q['answers'][ $ai ] ) ) {
                    continue;
                }
                $a = $q['answers'][ $ai ];

                if ( empty( $a['products'] ) ) {
                    continue;
                }

                // Find the maximum rank in this answer for inversion.
                $max_rank = 1;
                foreach ( $a['products'] as $p ) {
                    if ( (int) $p['rank'] > $max_rank ) {
                        $max_rank = (int) $p['rank'];
                    }
                }

                // Best possible score for this answer = max_rank (rank-1 inverted).
                $max_possible += $max_rank;

                foreach ( $a['products'] as $p ) {
                    $pid          = absint( $p['id'] );
                    $variation_id = absint( $p['variation_id'] ?? 0 );
                    $rank         = absint( $p['rank'] );
                    if ( ! $pid ) {
                        continue;
                    }

                    // Build the scoring key.
                    $key = ( $is_beauty && $variation_id ) ? $pid . ':' . $variation_id : (string) $pid;

                    $score = $max_rank + 1 - $rank;

                    if ( ! isset( $product_scores[ $key ] ) ) {
                        $product_scores[ $key ]    = 0;
                        $product_questions[ $key ] = array();
                        $product_answers[ $key ]   = array();
                        $key_map[ $key ]           = array(
                            'pid'          => $pid,
                            'variation_id' => $variation_id,
                        );
                    }
                    $product_scores[ $key ] += $score;

                    // Track which questions this product matched.
                    if ( ! in_array( (int) $qi, $product_questions[ $key ], true ) ) {
                        $product_questions[ $key ][] = (int) $qi;
                    }

                    // Record the answer context for the recommendation summary.
                    $product_answers[ $key ][] = array(
                        'qi'            => (int) $qi,
                        'ai'            => $ai,
                        'question_text' => $q['text'],
                        'answer_text'   => $a['text'],
                    );
                }
            }
        }

        /*
         * Composite score: reward products that match MORE of the user's
         * answered questions (breadth) on top of raw relevance (depth).
         *
         * final_score = raw_score × ( 1 + coverage_bonus )
         * coverage_bonus = questions_matched / total_answered   (0 → 1)
         *
         * This means a product that appears across 5/5 questions will rank
         * above one that scores high on only 1 question.
         */
        $composite_scores = array();
        foreach ( $product_scores as $key => $raw ) {
            $coverage = $total_answered > 0
                ? count( $product_questions[ $key ] ) / $total_answered
                : 0;
            $composite_scores[ $key ] = $raw * ( 1 + $coverage );
        }

        arsort( $composite_scores );

        // Limit results.
        $top_keys = array_slice( array_keys( $composite_scores ), 0, (int) $options['num_results'] );

        // Extract parent product IDs for CrocoBlock listing rendering.
        $top_ids = array();
        foreach ( $top_keys as $key ) {
            $pid = $key_map[ $key ]['pid'] ?? (int) $key;
            if ( ! in_array( $pid, $top_ids, true ) ) {
                $top_ids[] = $pid;
            }
        }

        // Debug log
        $this->_debug = array();
        $this->_new_styles  = array();
        $this->_new_scripts = array();

        if ( $is_beauty ) {
            $this->_debug[] = 'finder_type=beauty';
        }

        // If CrocoBlock listing template is set AND we're not in Beauty mode,
        // render via JetEngine.  In Beauty mode we always use the fallback
        // renderer since CrocoBlock listings show the parent product, not
        // the specific variation image/price we need.
        $html = '';
        if ( ! empty( $options['listing_template'] ) && ! empty( $top_ids ) && ! $is_beauty ) {
            $this->_debug[] = 'listing_template=' . $options['listing_template'] . ', product_ids=' . implode( ',', $top_ids );
            $html = $this->render_crocoblock_listing( $top_ids, $options );
            $this->_debug[] = 'final listing_html length=' . strlen( $html );
        } else if ( $is_beauty ) {
            $this->_debug[] = 'Beauty mode: using variation-aware fallback renderer';
        } else {
            $this->_debug[] = 'No listing template set or no product IDs';
        }

        // Build product data (fallback renderer and email handler both use this).
        $products_data = array();
        foreach ( $top_keys as $key ) {
            $info         = $key_map[ $key ];
            $pid          = $info['pid'];
            $variation_id = $info['variation_id'];

            $product = wc_get_product( $pid );
            if ( ! $product ) {
                continue;
            }

            // Match percentage.
            $match_pct = $max_possible > 0
                ? min( 100, round( ( $product_scores[ $key ] / $max_possible ) * 100 ) )
                : 0;

            // Deduplicate answer reasons per question.
            $reasons = array();
            $seen_q  = array();
            if ( ! empty( $product_answers[ $key ] ) ) {
                foreach ( $product_answers[ $key ] as $r ) {
                    if ( in_array( $r['qi'], $seen_q, true ) ) {
                        continue;
                    }
                    $seen_q[]  = $r['qi'];
                    $reasons[] = $r['answer_text'];
                }
            }

            $item = array(
                'id'                => $pid,
                'name'              => $product->get_name(),
                'price'             => $product->get_price_html(),
                'image'             => wp_get_attachment_image_url( $product->get_image_id(), 'large' ),
                'permalink'         => $product->get_permalink(),
                'score'             => $product_scores[ $key ],
                'match_pct'         => $match_pct,
                'questions_matched' => count( $product_questions[ $key ] ?? array() ),
                'total_questions'   => $total_answered,
                'reasons'           => $reasons,
                'variation_id'      => 0,
                'is_variable'       => false,
            );

            // In Beauty mode, overlay variation-specific data.
            if ( $is_beauty && $variation_id ) {
                $variation = wc_get_product( $variation_id );
                if ( $variation && $variation->is_type( 'variation' ) ) {
                    $var_image = wp_get_attachment_image_url( $variation->get_image_id(), 'large' );

                    // Build a descriptive name: "Parent — Shade Name"
                    $attrs      = $variation->get_attributes();
                    $attr_label = implode( ' / ', array_filter( array_values( $attrs ) ) );
                    $var_name   = $product->get_name() . ( $attr_label ? ' — ' . $attr_label : '' );

                    $item['variation_id'] = $variation_id;
                    $item['is_variable']  = true;
                    $item['name']         = $var_name;
                    $item['price']        = $variation->get_price_html();
                    $item['image']        = $var_image ?: $item['image'];
                    $item['permalink']    = $variation->get_permalink();

                    // Include variation attributes for add-to-cart.
                    $item['variation_attributes'] = array();
                    foreach ( $attrs as $attr_key => $attr_val ) {
                        $item['variation_attributes'][ 'attribute_' . $attr_key ] = $attr_val;
                    }
                }
            }

            $products_data[] = $item;
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
            'styles'      => $this->_new_styles,
            'scripts'     => $this->_new_scripts,
            'debug'       => $this->_debug,
        ) );
    }

    /* ────────── Frontend: add variable product to cart ────── */

    public function add_to_cart_variable() {
        check_ajax_referer( 'pf_frontend_nonce', 'nonce' );

        $product_id   = absint( $_POST['product_id'] ?? 0 );
        $variation_id = absint( $_POST['variation_id'] ?? 0 );
        $quantity     = empty( $_POST['quantity'] ) ? 1 : wc_stock_amount( wp_unslash( $_POST['quantity'] ) );

        if ( ! $product_id || ! $variation_id ) {
            wp_send_json_error( array( 'message' => __( 'Missing product or variation ID.', 'product-finder' ) ) );
        }

        // Collect variation attributes from POST data.
        $variations = array();
        foreach ( $_POST as $key => $value ) {
            if ( strpos( $key, 'attribute_' ) === 0 ) {
                $variations[ sanitize_title( wp_unslash( $key ) ) ] = wp_unslash( $value );
            }
        }

        // Ensure WooCommerce cart/session are loaded.
        if ( function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
        }

        $passed = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations );

        if ( $passed && false !== WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations ) ) {
            do_action( 'woocommerce_ajax_added_to_cart', $product_id );

            // Return updated cart fragments so mini-cart refreshes.
            if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
                wc_add_to_cart_message( array( $product_id => $quantity ), true );
            }

            \WC_AJAX::get_refreshed_fragments();
        } else {
            $notices = wc_get_notices( 'error' );
            $message = ! empty( $notices )
                ? wp_strip_all_tags( $notices[0]['notice'] ?? __( 'Unable to add to cart.', 'product-finder' ) )
                : __( 'Unable to add to cart.', 'product-finder' );
            wc_clear_notices();

            wp_send_json_error( array( 'message' => $message ) );
        }
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

        // Force third-party plugins to register their asset handles.
        // During AJAX, wp_enqueue_scripts doesn't fire, so handles
        // used by get_script_depends() / get_style_depends() are
        // unavailable.  Firing the hook here makes them available so
        // enqueue_widget_dependencies() can find and enqueue them.
        $this->force_register_plugin_assets();

        // Snapshot currently queued assets before rendering so we can
        // detect CSS/JS enqueued by widgets (e.g. swatch plugins) that
        // the browser hasn't loaded yet.
        $styles_before  = wp_styles()->queue;
        $scripts_before = wp_scripts()->queue;

        // Hook into WordPress's the_post action so that every time
        // JetEngine (or our manual loop) sets up a post, we also
        // initialise the WooCommerce global $product.  Without this
        // the Add to Cart widget triggers a PHP fatal because it
        // calls methods on a null $product.
        $product_setup = function ( $post ) {
            if ( 'product' === $post->post_type && function_exists( 'wc_setup_product_data' ) ) {
                wc_setup_product_data( $post );
            }
        };
        add_action( 'the_post', $product_setup );

        $html = $this->_render_listing_methods( $product_ids, $listing_id, $cols_desktop, $cols_tablet, $cols_mobile );

        remove_action( 'the_post', $product_setup );

        // Elementor widgets declare their JS/CSS dependencies via
        // get_script_depends() and get_style_depends().  During normal
        // page rendering Elementor processes these automatically, but
        // during AJAX the dependency system doesn't run.  Parse the
        // rendered HTML for widget types and explicitly enqueue their
        // declared dependencies so the asset-diff below captures them.
        $this->enqueue_widget_dependencies( $html );

        // Capture CSS/JS enqueued during rendering and collect their
        // URLs so the frontend can load them dynamically.
        $this->_new_styles  = $this->collect_asset_urls( wp_styles(),  array_diff( wp_styles()->queue,  $styles_before ) );
        $this->_new_scripts = $this->collect_asset_urls( wp_scripts(), array_diff( wp_scripts()->queue, $scripts_before ) );

        if ( ! empty( $this->_new_styles ) || ! empty( $this->_new_scripts ) ) {
            $this->_debug[] = 'Dynamic assets (from registry): ' . count( $this->_new_styles ) . ' style(s), '
                . count( $this->_new_scripts ) . ' script(s)';
        }

        // Discover third-party plugin assets directly from the
        // filesystem when the registry-based approach above fails
        // to capture them (e.g. plugin doesn't register handles
        // during AJAX at all).
        $fs_assets = $this->discover_swatch_plugin_assets( $html );
        if ( ! empty( $fs_assets['styles'] ) || ! empty( $fs_assets['scripts'] ) ) {
            // Merge, deduplicating by base URL (strip query strings).
            $existing_bases = array_map( function ( $u ) { return strtok( $u, '?' ); }, $this->_new_styles );
            foreach ( $fs_assets['styles'] as $url ) {
                if ( ! in_array( strtok( $url, '?' ), $existing_bases, true ) ) {
                    $this->_new_styles[] = $url;
                }
            }
            $existing_bases = array_map( function ( $u ) { return strtok( $u, '?' ); }, $this->_new_scripts );
            foreach ( $fs_assets['scripts'] as $url ) {
                if ( ! in_array( strtok( $url, '?' ), $existing_bases, true ) ) {
                    $this->_new_scripts[] = $url;
                }
            }
            $this->_debug[] = 'Dynamic assets (after filesystem scan): ' . count( $this->_new_styles ) . ' style(s), '
                . count( $this->_new_scripts ) . ' script(s)';
        }

        return $html;
    }

    /**
     * Try each rendering method in order and return the first success.
     */
    private function _render_listing_methods( $product_ids, $listing_id, $cols_desktop, $cols_tablet, $cols_mobile ) {

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
                    // wc_setup_product_data is called via our the_post hook.
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
     * Collect absolute URLs for newly enqueued style/script handles.
     *
     * @param WP_Styles|WP_Scripts $registry   The WordPress dependency registry.
     * @param string[]             $handles    Handles that were newly queued.
     * @return string[]  Absolute URLs (with ?ver= appended).
     */
    private function collect_asset_urls( $registry, $handles ) {
        $urls = array();
        foreach ( $handles as $handle ) {
            if ( ! isset( $registry->registered[ $handle ] ) ) {
                continue;
            }
            $dep = $registry->registered[ $handle ];
            if ( empty( $dep->src ) ) {
                continue;
            }
            $src = $dep->src;
            // Make relative URLs absolute.
            if ( 0 !== strpos( $src, 'http' ) && 0 !== strpos( $src, '//' ) ) {
                $src = site_url( $src );
            }
            if ( $dep->ver ) {
                $src = add_query_arg( 'ver', $dep->ver, $src );
            }
            $urls[] = $src;
        }
        return $urls;
    }

    /**
     * Enqueue script/style dependencies declared by Elementor widgets
     * found in the rendered listing HTML.
     *
     * During normal page rendering Elementor automatically processes
     * get_script_depends() and get_style_depends() for every widget.
     * During AJAX this doesn't happen, so swatch plugins (and other
     * widgets) never get their CSS/JS enqueued.  This method parses
     * data-widget_type attributes from the rendered HTML, looks up
     * each widget in Elementor's registry, and enqueues their declared
     * dependencies so our asset-diff picks them up.
     *
     * @param string $html  Rendered listing HTML.
     */
    private function enqueue_widget_dependencies( $html ) {
        if ( empty( $html ) || ! class_exists( '\Elementor\Plugin' ) ) {
            $this->_debug[] = 'enqueue_widget_dependencies: skipped (no html or no Elementor)';
            return;
        }

        $plugin = \Elementor\Plugin::$instance;
        if ( ! $plugin || ! isset( $plugin->widgets_manager ) ) {
            $this->_debug[] = 'enqueue_widget_dependencies: no widget manager';
            return;
        }

        $widget_manager = $plugin->widgets_manager;

        // Extract unique widget types from the rendered HTML.
        if ( ! preg_match_all( '/data-widget_type="([^"]+)"/', $html, $matches ) ) {
            $this->_debug[] = 'enqueue_widget_dependencies: no data-widget_type found in HTML';

            // Fallback: scan for known third-party widget markup and try
            // to enqueue their registered assets directly.
            $this->enqueue_known_widget_assets( $html );
            return;
        }

        $types_found = array_unique( $matches[1] );
        $this->_debug[] = 'enqueue_widget_dependencies: widget types found: ' . implode( ', ', $types_found );

        $enqueued = array();
        foreach ( $types_found as $widget_type ) {
            // widget_type is "widget_name.skin_name", we need just the name.
            $widget_name = explode( '.', $widget_type )[0];

            $widget = $widget_manager->get_widget_types( $widget_name );
            if ( ! $widget ) {
                $this->_debug[] = 'enqueue_widget_dependencies: widget "' . $widget_name . '" NOT in registry';
                continue;
            }

            // Enqueue declared script dependencies.
            if ( method_exists( $widget, 'get_script_depends' ) ) {
                $scripts = $widget->get_script_depends();
                foreach ( $scripts as $handle ) {
                    if ( wp_script_is( $handle, 'registered' ) && ! wp_script_is( $handle, 'enqueued' ) ) {
                        wp_enqueue_script( $handle );
                        $enqueued[] = 'js:' . $handle;
                    }
                }
            }

            // Enqueue declared style dependencies.
            if ( method_exists( $widget, 'get_style_depends' ) ) {
                $styles = $widget->get_style_depends();
                foreach ( $styles as $handle ) {
                    if ( wp_style_is( $handle, 'registered' ) && ! wp_style_is( $handle, 'enqueued' ) ) {
                        wp_enqueue_style( $handle );
                        $enqueued[] = 'css:' . $handle;
                    }
                }
            }
        }

        if ( ! empty( $enqueued ) ) {
            $this->_debug[] = 'Widget dependencies enqueued: ' . implode( ', ', $enqueued );
        }

        // Also check for known widget markup that might not have
        // data-widget_type (e.g. JetEngine renders without it).
        $this->enqueue_known_widget_assets( $html );
    }

    /**
     * Fallback: scan rendered HTML for known third-party widget CSS
     * class names / markup and enqueue their registered assets.
     *
     * JetEngine's PHP API rendering may strip Elementor data attributes,
     * so the data-widget_type approach doesn't always work.  This method
     * checks for recognisable markup patterns and enqueues matching
     * registered handles.
     *
     * @param string $html  Rendered listing HTML.
     */
    private function enqueue_known_widget_assets( $html ) {
        $enqueued = array();

        // FiF VSE Variation Swatches for Elementor
        if ( strpos( $html, 'fif-vse-swatches' ) !== false ) {
            // Try common handle patterns used by the FiF VSE plugin.
            $handles = array(
                'fif-vse-swatches',
                'fif-vse-frontend',
                'fif-vse',
                'fif_vse_swatches',
                'fif_vse_frontend',
                'fif_vse',
            );

            foreach ( $handles as $handle ) {
                if ( wp_script_is( $handle, 'registered' ) && ! wp_script_is( $handle, 'enqueued' ) ) {
                    wp_enqueue_script( $handle );
                    $enqueued[] = 'js:' . $handle;
                }
                if ( wp_style_is( $handle, 'registered' ) && ! wp_style_is( $handle, 'enqueued' ) ) {
                    wp_enqueue_style( $handle );
                    $enqueued[] = 'css:' . $handle;
                }
            }

            // If nothing was found with known handles, search all
            // registered scripts/styles for 'fif' or 'vse' in the handle.
            if ( empty( $enqueued ) ) {
                foreach ( wp_scripts()->registered as $handle => $dep ) {
                    if ( ( strpos( $handle, 'fif' ) !== false || strpos( $handle, 'vse' ) !== false )
                        && ! wp_script_is( $handle, 'enqueued' )
                    ) {
                        wp_enqueue_script( $handle );
                        $enqueued[] = 'js:' . $handle . ' (fuzzy)';
                    }
                }
                foreach ( wp_styles()->registered as $handle => $dep ) {
                    if ( ( strpos( $handle, 'fif' ) !== false || strpos( $handle, 'vse' ) !== false )
                        && ! wp_style_is( $handle, 'enqueued' )
                    ) {
                        wp_enqueue_style( $handle );
                        $enqueued[] = 'css:' . $handle . ' (fuzzy)';
                    }
                }
            }
        }

        if ( ! empty( $enqueued ) ) {
            $this->_debug[] = 'Known widget assets enqueued: ' . implode( ', ', $enqueued );
        }
    }

    /**
     * Force third-party plugins to register their script/style handles.
     *
     * During normal page rendering wp_enqueue_scripts fires and plugins
     * call wp_register_script / wp_register_style to make their handles
     * available.  During AJAX this hook doesn't fire, so the handles
     * returned by Elementor widget get_script_depends / get_style_depends
     * are missing from the WordPress registry.
     *
     * This method fires the relevant hooks so that plugins register
     * their handles, enabling enqueue_widget_dependencies() to work.
     */
    private function force_register_plugin_assets() {
        $scripts_before = count( wp_scripts()->registered );
        $styles_before  = count( wp_styles()->registered );

        // 1. Fire wp_enqueue_scripts if it hasn't fired yet.
        //    Buffer any stray output to prevent header / content leakage.
        if ( ! did_action( 'wp_enqueue_scripts' ) ) {
            ob_start();
            try {
                do_action( 'wp_enqueue_scripts' );
            } catch ( \Throwable $e ) {
                $this->_debug[] = 'force_register: wp_enqueue_scripts error: ' . $e->getMessage();
            }
            ob_end_clean();
        }

        // 2. Fire Elementor's frontend registration hooks.
        //    Some widgets register assets on these hooks rather than
        //    on wp_enqueue_scripts.
        if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance ) {
            $frontend = \Elementor\Plugin::$instance->frontend;
            if ( $frontend ) {
                foreach ( array( 'register_scripts', 'register_styles' ) as $method ) {
                    if ( method_exists( $frontend, $method ) ) {
                        try {
                            $frontend->$method();
                        } catch ( \Throwable $e ) {
                            // Ignore – some Elementor versions may not support these during AJAX.
                        }
                    }
                }
            }
        }

        $scripts_after = count( wp_scripts()->registered );
        $styles_after  = count( wp_styles()->registered );
        $this->_debug[] = 'force_register_plugin_assets: scripts registered '
            . $scripts_before . ' → ' . $scripts_after
            . ', styles registered ' . $styles_before . ' → ' . $styles_after;
    }

    /**
     * Discover CSS/JS files for the swatch plugin directly from the
     * filesystem.
     *
     * This is a robust fallback for when the plugin doesn't register
     * its asset handles in the WordPress script/style system during
     * AJAX requests.  We find the plugin's directory on disk, scan for
     * frontend CSS/JS files, and return their URLs so the client can
     * load them.
     *
     * @param string $html  Rendered listing HTML.
     * @return array { styles: string[], scripts: string[] }
     */
    private function discover_swatch_plugin_assets( $html ) {
        $result = array( 'styles' => array(), 'scripts' => array() );

        // Only scan when swatch markup is present in the rendered HTML.
        if ( strpos( $html, 'fif-vse-swatches' ) === false ) {
            return $result;
        }

        $plugin_dir = '';

        // Strategy 1: Use PHP reflection on the Elementor widget class.
        // This is the most reliable – we KNOW this widget exists because
        // its markup is in the HTML.
        if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance
            && isset( \Elementor\Plugin::$instance->widgets_manager ) ) {
            $widget = \Elementor\Plugin::$instance->widgets_manager
                ->get_widget_types( 'fif_vse_variation_swatches' );
            if ( $widget ) {
                try {
                    $ref  = new \ReflectionClass( $widget );
                    $file = $ref->getFileName();
                    if ( $file && defined( 'WP_PLUGIN_DIR' ) && strpos( $file, WP_PLUGIN_DIR ) === 0 ) {
                        $relative    = substr( $file, strlen( WP_PLUGIN_DIR ) + 1 );
                        $plugin_slug = explode( '/', $relative )[0];
                        $candidate   = WP_PLUGIN_DIR . '/' . $plugin_slug;
                        if ( is_dir( $candidate ) ) {
                            $plugin_dir = $candidate;
                            $this->_debug[] = 'discover_swatch: found via reflection: ' . $plugin_slug;
                        }
                    }
                } catch ( \Throwable $e ) {
                    $this->_debug[] = 'discover_swatch: reflection error: ' . $e->getMessage();
                }
            }
        }

        // Strategy 2: Search the active plugins list for FiF VSE.
        if ( ! $plugin_dir ) {
            $active = get_option( 'active_plugins', array() );
            foreach ( $active as $pf ) {
                $slug = dirname( $pf );
                if ( '.' === $slug || empty( $slug ) ) {
                    continue;
                }
                if ( stripos( $slug, 'fif' ) !== false
                    || stripos( $slug, 'vse' ) !== false
                    || stripos( $slug, 'variation-swatches' ) !== false
                    || stripos( $slug, 'variation_swatches' ) !== false
                ) {
                    $candidate = WP_PLUGIN_DIR . '/' . $slug;
                    if ( is_dir( $candidate ) ) {
                        $plugin_dir = $candidate;
                        $this->_debug[] = 'discover_swatch: found via active_plugins: ' . $slug;
                        break;
                    }
                }
            }
        }

        // Strategy 3: Glob WP_PLUGIN_DIR for matching directory names.
        if ( ! $plugin_dir && defined( 'WP_PLUGIN_DIR' ) ) {
            foreach ( array( '*fif*vse*', '*vse*swatches*', '*variation*swatches*elementor*' ) as $pattern ) {
                $dirs = glob( WP_PLUGIN_DIR . '/' . $pattern, GLOB_ONLYDIR );
                if ( ! empty( $dirs ) ) {
                    $plugin_dir = $dirs[0];
                    $this->_debug[] = 'discover_swatch: found via glob: ' . basename( $plugin_dir );
                    break;
                }
            }
        }

        if ( ! $plugin_dir || ! is_dir( $plugin_dir ) ) {
            $this->_debug[] = 'discover_swatch: plugin directory NOT found';
            return $result;
        }

        // Scan the plugin directory for frontend CSS/JS files.
        $this->scan_plugin_frontend_assets( $plugin_dir, $result );

        $this->_debug[] = 'discover_swatch: found '
            . count( $result['styles'] ) . ' CSS, '
            . count( $result['scripts'] ) . ' JS file(s)';

        return $result;
    }

    /**
     * Recursively scan a plugin directory for frontend CSS/JS files.
     *
     * Skips admin-only files, node_modules, vendor directories, and
     * source maps.  Converts filesystem paths to public URLs.
     *
     * @param string $dir     Absolute path to the plugin directory.
     * @param array  &$result { styles: string[], scripts: string[] }
     */
    private function scan_plugin_frontend_assets( $dir, &$result ) {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $plugins_url_base = plugins_url();

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
        } catch ( \Throwable $e ) {
            return;
        }

        foreach ( $iterator as $file ) {
            $path = $file->getPathname();
            $ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

            if ( 'css' !== $ext && 'js' !== $ext ) {
                continue;
            }

            // Build path relative to WP_PLUGIN_DIR.
            $relative = substr( $path, strlen( WP_PLUGIN_DIR ) );

            // Skip admin, node_modules, vendor directories.
            if ( preg_match( '#[/\\\\](admin|node_modules|vendor|build|src)[/\\\\]#i', $relative ) ) {
                continue;
            }

            // Skip source maps.
            if ( preg_match( '/\.map$/i', $relative ) ) {
                continue;
            }

            // Skip files with "admin" in the filename.
            $basename = pathinfo( $path, PATHINFO_FILENAME );
            if ( stripos( $basename, 'admin' ) !== false ) {
                continue;
            }

            $url = $plugins_url_base . $relative;

            if ( 'css' === $ext ) {
                $result['styles'][] = $url;
            } else {
                $result['scripts'][] = $url;
            }
        }
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
        } else {
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

        // Load WC frontend includes – needed for Add to Cart templates,
        // product form rendering, etc.
        if ( defined( 'WC_ABSPATH' ) ) {
            $frontend = WC_ABSPATH . 'includes/wc-template-functions.php';
            if ( ! function_exists( 'woocommerce_template_single_add_to_cart' ) && file_exists( $frontend ) ) {
                include_once $frontend;
            }

            // Template hooks (add-to-cart button, quantity selector, etc.)
            $hooks_file = WC_ABSPATH . 'includes/wc-template-hooks.php';
            if ( file_exists( $hooks_file ) ) {
                include_once $hooks_file;
            }
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
