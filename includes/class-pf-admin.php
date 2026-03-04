<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin meta boxes and save logic for Product Finder.
 */
class PF_Admin {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_product_finder', array( $this, 'save_meta' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /* ───────────────────────── Assets ───────────────────────── */

    public function enqueue_assets( $hook ) {
        $screen = get_current_screen();
        if ( ! $screen || 'product_finder' !== $screen->post_type ) {
            return;
        }

        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script( 'jquery-ui-sortable' );

        wp_enqueue_style(
            'pf-admin',
            PF_PLUGIN_URL . 'admin/css/pf-admin.css',
            array(),
            PF_VERSION
        );

        wp_enqueue_script(
            'pf-admin',
            PF_PLUGIN_URL . 'admin/js/pf-admin.js',
            array( 'jquery', 'jquery-ui-sortable', 'wp-util' ),
            PF_VERSION,
            true
        );

        // Get current finder_type for this post.
        $pf_options = array();
        if ( isset( $_GET['post'] ) ) {
            $pf_options = get_post_meta( absint( $_GET['post'] ), '_pf_options', true );
        }
        $pf_options = wp_parse_args( (array) $pf_options, array( 'finder_type' => 'cosmeceuticals' ) );

        wp_localize_script( 'pf-admin', 'pfAdmin', array(
            'ajax_url'     => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'pf_admin_nonce' ),
            'finder_type'  => $pf_options['finder_type'],
            'i18n'         => array(
                'select_image'      => __( 'Select Image', 'product-finder' ),
                'remove_image'      => __( 'Remove', 'product-finder' ),
                'use_image'         => __( 'Use this image', 'product-finder' ),
                'search_product'    => __( 'Search for a product…', 'product-finder' ),
                'confirm_del'       => __( 'Delete this item?', 'product-finder' ),
                'select_variation'  => __( '— Select variation —', 'product-finder' ),
                'loading_variations'=> __( 'Loading variations…', 'product-finder' ),
                'no_variations'     => __( 'No variations found (simple product)', 'product-finder' ),
            ),
        ) );
    }

    /* ───────────────────────── Meta Boxes ───────────────────── */

    public function add_meta_boxes() {
        add_meta_box(
            'pf_questions',
            __( 'Questions & Answers', 'product-finder' ),
            array( $this, 'render_questions_box' ),
            'product_finder',
            'normal',
            'high'
        );

        add_meta_box(
            'pf_options',
            __( 'Finder Options', 'product-finder' ),
            array( $this, 'render_options_box' ),
            'product_finder',
            'side',
            'default'
        );

        add_meta_box(
            'pf_shortcode',
            __( 'Shortcode', 'product-finder' ),
            array( $this, 'render_shortcode_box' ),
            'product_finder',
            'side',
            'default'
        );
    }

    /* ─── Shortcode box ─── */

    public function render_shortcode_box( $post ) {
        if ( 'auto-draft' === $post->post_status ) {
            echo '<p>' . esc_html__( 'Publish or save a draft first to get the shortcode.', 'product-finder' ) . '</p>';
            return;
        }
        echo '<input type="text" readonly class="widefat" value=\'[product_finder id="' . esc_attr( $post->ID ) . '"]\' onclick="this.select();" />';
    }

    /* ─── Options box ─── */

    public function render_options_box( $post ) {
        $options = get_post_meta( $post->ID, '_pf_options', true );
        $options = wp_parse_args( (array) $options, array(
            'num_results'       => 5,
            'listing_template'  => '',
            'cols_desktop'      => 3,
            'cols_tablet'       => 2,
            'cols_mobile'       => 1,
            'finder_type'       => 'cosmeceuticals',
            'enable_day_night'  => 0,
        ) );

        wp_nonce_field( 'pf_save_meta', 'pf_meta_nonce' );

        $templates = $this->get_crocoblock_templates();
        ?>
        <div class="pf-finder-type-wrap">
            <label><strong><?php esc_html_e( 'Finder Type', 'product-finder' ); ?></strong></label><br>
            <label class="pf-finder-type-option">
                <input type="radio" name="pf_options[finder_type]" value="cosmeceuticals" <?php checked( $options['finder_type'], 'cosmeceuticals' ); ?>>
                <?php esc_html_e( 'Cosmeceuticals', 'product-finder' ); ?>
                <span class="description"><?php esc_html_e( 'Simple product recommendations', 'product-finder' ); ?></span>
            </label>
            <label class="pf-finder-type-option">
                <input type="radio" name="pf_options[finder_type]" value="beauty" <?php checked( $options['finder_type'], 'beauty' ); ?>>
                <?php esc_html_e( 'Beauty', 'product-finder' ); ?>
                <span class="description"><?php esc_html_e( 'Product variation recommendations (shades, colours)', 'product-finder' ); ?></span>
            </label>
        </div>
        <div class="pf-day-night-wrap">
            <label>
                <input type="checkbox" name="pf_options[enable_day_night]" value="1" <?php checked( $options['enable_day_night'], 1 ); ?>>
                <strong><?php esc_html_e( 'Enable Day / Night Results', 'product-finder' ); ?></strong>
            </label>
            <p class="description"><?php esc_html_e( 'Split results into Day and Night tabs. A "Set" dropdown will appear on each product row.', 'product-finder' ); ?></p>
        </div>
        <hr>
        <p>
            <label><strong><?php esc_html_e( 'Number of results to show', 'product-finder' ); ?></strong></label><br>
            <input type="number" name="pf_options[num_results]" value="<?php echo esc_attr( $options['num_results'] ); ?>" min="1" max="50" class="widefat">
        </p>
        <p>
            <label><strong><?php esc_html_e( 'CrocoBlock Listing Template', 'product-finder' ); ?></strong></label><br>
            <select name="pf_options[listing_template]" class="widefat">
                <option value=""><?php esc_html_e( '— Select template —', 'product-finder' ); ?></option>
                <?php foreach ( $templates as $id => $title ) : ?>
                    <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $options['listing_template'], $id ); ?>><?php echo esc_html( $title ); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label><strong><?php esc_html_e( 'Columns – Desktop', 'product-finder' ); ?></strong></label><br>
            <input type="number" name="pf_options[cols_desktop]" value="<?php echo esc_attr( $options['cols_desktop'] ); ?>" min="1" max="6" class="widefat">
        </p>
        <p>
            <label><strong><?php esc_html_e( 'Columns – Tablet', 'product-finder' ); ?></strong></label><br>
            <input type="number" name="pf_options[cols_tablet]" value="<?php echo esc_attr( $options['cols_tablet'] ); ?>" min="1" max="6" class="widefat">
        </p>
        <p>
            <label><strong><?php esc_html_e( 'Columns – Mobile', 'product-finder' ); ?></strong></label><br>
            <input type="number" name="pf_options[cols_mobile]" value="<?php echo esc_attr( $options['cols_mobile'] ); ?>" min="1" max="6" class="widefat">
        </p>
        <?php
    }

    /* ─── Questions box ─── */

    public function render_questions_box( $post ) {
        $questions = get_post_meta( $post->ID, '_pf_questions', true );
        if ( ! is_array( $questions ) ) {
            $questions = array();
        }
        ?>
        <div id="pf-questions-wrap">
            <div id="pf-questions-list" class="pf-sortable">
                <?php
                foreach ( $questions as $qi => $question ) {
                    $this->render_question_template( $qi, $question );
                }
                ?>
            </div>
            <p><button type="button" class="button button-primary" id="pf-add-question"><?php esc_html_e( '+ Add Question', 'product-finder' ); ?></button></p>
        </div>

        <!-- Hidden template for new question -->
        <script type="text/html" id="tmpl-pf-question">
            <?php $this->render_question_template( '{{data.qi}}', array() ); ?>
        </script>

        <!-- Hidden template for new answer -->
        <script type="text/html" id="tmpl-pf-answer">
            <?php $this->render_answer_template( '{{data.qi}}', '{{data.ai}}', array() ); ?>
        </script>

        <!-- Hidden template for product row -->
        <script type="text/html" id="tmpl-pf-product-row">
            <?php $this->render_product_row_template( '{{data.qi}}', '{{data.ai}}', '{{data.pi}}', array() ); ?>
        </script>
        <?php
    }

    /* ─── Render helpers ─── */

    private function render_question_template( $qi, $question ) {
        $question = wp_parse_args( $question, array(
            'text'        => '',
            'instruction' => '',
            'multiple'    => 0,
            'answers'     => array(),
        ) );
        $name_prefix = "pf_questions[{$qi}]";
        ?>
        <div class="pf-question" data-qi="<?php echo esc_attr( $qi ); ?>">
            <div class="pf-question-header pf-drag-handle">
                <span class="pf-drag-icon dashicons dashicons-menu"></span>
                <span class="pf-question-title"><?php echo $question['text'] ? esc_html( $question['text'] ) : esc_html__( 'New Question', 'product-finder' ); ?></span>
                <span class="pf-question-toggle dashicons dashicons-arrow-down-alt2"></span>
                <button type="button" class="pf-remove-question button-link" title="<?php esc_attr_e( 'Delete Question', 'product-finder' ); ?>"><span class="dashicons dashicons-trash"></span></button>
            </div>
            <div class="pf-question-body">
                <p>
                    <label><strong><?php esc_html_e( 'Question Text', 'product-finder' ); ?></strong></label><br>
                    <input type="text" name="<?php echo esc_attr( $name_prefix ); ?>[text]" value="<?php echo esc_attr( $question['text'] ); ?>" class="widefat pf-question-text-input">
                </p>
                <p>
                    <label><strong><?php esc_html_e( 'Instruction Text', 'product-finder' ); ?></strong></label><br>
                    <input type="text" name="<?php echo esc_attr( $name_prefix ); ?>[instruction]" value="<?php echo esc_attr( $question['instruction'] ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'e.g. Select all that apply, Choose your favourite…', 'product-finder' ); ?>">
                    <span class="description"><?php esc_html_e( 'Shown below the question on the frontend. Leave blank to use the automatic hint.', 'product-finder' ); ?></span>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[multiple]" value="1" <?php checked( $question['multiple'], 1 ); ?>>
                        <?php esc_html_e( 'Allow multiple answers (checkboxes)', 'product-finder' ); ?>
                    </label>
                </p>
                <div class="pf-answers-wrap">
                    <h4><?php esc_html_e( 'Answers', 'product-finder' ); ?></h4>
                    <div class="pf-answers-list pf-sortable-answers">
                        <?php
                        if ( ! empty( $question['answers'] ) ) {
                            foreach ( $question['answers'] as $ai => $answer ) {
                                $this->render_answer_template( $qi, $ai, $answer );
                            }
                        }
                        ?>
                    </div>
                    <p><button type="button" class="button pf-add-answer"><?php esc_html_e( '+ Add Answer', 'product-finder' ); ?></button></p>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_answer_template( $qi, $ai, $answer ) {
        $answer = wp_parse_args( $answer, array(
            'text'        => '',
            'description' => '',
            'image_id'    => '',
            'products'    => array(),
        ) );
        $name_prefix = "pf_questions[{$qi}][answers][{$ai}]";
        $thumb_url   = $answer['image_id'] ? wp_get_attachment_image_url( $answer['image_id'], 'thumbnail' ) : '';
        ?>
        <div class="pf-answer" data-ai="<?php echo esc_attr( $ai ); ?>">
            <div class="pf-answer-header pf-drag-handle-answer">
                <span class="pf-drag-icon dashicons dashicons-menu"></span>
                <span class="pf-answer-label"><?php echo $answer['text'] ? esc_html( $answer['text'] ) : esc_html__( 'New Answer', 'product-finder' ); ?></span>
                <button type="button" class="pf-remove-answer button-link" title="<?php esc_attr_e( 'Delete Answer', 'product-finder' ); ?>"><span class="dashicons dashicons-trash"></span></button>
            </div>
            <div class="pf-answer-body">
                <!-- Answer text -->
                <p>
                    <label><?php esc_html_e( 'Answer Text', 'product-finder' ); ?></label><br>
                    <input type="text" name="<?php echo esc_attr( $name_prefix ); ?>[text]" value="<?php echo esc_attr( $answer['text'] ); ?>" class="widefat pf-answer-text-input">
                </p>
                <!-- Description (shown under answer text on image layout) -->
                <p>
                    <label><?php esc_html_e( 'Description', 'product-finder' ); ?></label><br>
                    <input type="text" name="<?php echo esc_attr( $name_prefix ); ?>[description]" value="<?php echo esc_attr( $answer['description'] ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Optional – displayed below the answer text on image layout', 'product-finder' ); ?>">
                </p>
                <!-- Image -->
                <div class="pf-answer-image-wrap">
                    <label><?php esc_html_e( 'Image', 'product-finder' ); ?></label><br>
                    <input type="hidden" name="<?php echo esc_attr( $name_prefix ); ?>[image_id]" value="<?php echo esc_attr( $answer['image_id'] ); ?>" class="pf-image-id">
                    <div class="pf-image-preview" <?php echo $thumb_url ? '' : 'style="display:none;"'; ?>>
                        <img src="<?php echo esc_url( $thumb_url ); ?>" alt="">
                        <button type="button" class="pf-remove-image button-link" title="<?php esc_attr_e( 'Remove Image', 'product-finder' ); ?>"><span class="dashicons dashicons-no-alt"></span> <?php esc_html_e( 'Remove', 'product-finder' ); ?></button>
                    </div>
                    <button type="button" class="button pf-select-image"><?php esc_html_e( 'Select Image', 'product-finder' ); ?></button>
                </div>
                <!-- Product search -->
                <div class="pf-answer-products-wrap">
                    <label><?php esc_html_e( 'Products', 'product-finder' ); ?></label><br>
                    <input type="text" class="widefat pf-product-search" placeholder="<?php esc_attr_e( 'Search for a product…', 'product-finder' ); ?>" autocomplete="off">
                    <div class="pf-product-search-results"></div>
                    <div class="pf-products-list">
                        <?php
                        if ( ! empty( $answer['products'] ) ) {
                            foreach ( $answer['products'] as $pi => $prod ) {
                                $this->render_product_row_template( $qi, $ai, $pi, $prod );
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_product_row_template( $qi, $ai, $pi, $prod ) {
        $prod = wp_parse_args( $prod, array(
            'id'              => '',
            'variation_id'    => '',
            'rank'            => 1,
            'result_category' => '',
            'result_set'      => 'both',
        ) );
        $name_prefix = "pf_questions[{$qi}][answers][{$ai}][products][{$pi}]";
        $product_name = $prod['id'] ? get_the_title( $prod['id'] ) : '{{data.name}}';

        // If a variation is saved, get its name for display.
        $variation_name = '';
        if ( $prod['variation_id'] && function_exists( 'wc_get_product' ) ) {
            $variation = wc_get_product( $prod['variation_id'] );
            if ( $variation && $variation->is_type( 'variation' ) ) {
                $attrs = $variation->get_attributes();
                $variation_name = implode( ', ', array_values( $attrs ) );
            }
        }
        ?>
        <div class="pf-product-row" data-pi="<?php echo esc_attr( $pi ); ?>" data-product-id="<?php echo esc_attr( $prod['id'] ); ?>">
            <input type="hidden" name="<?php echo esc_attr( $name_prefix ); ?>[id]" value="<?php echo esc_attr( $prod['id'] ); ?>" class="pf-product-id">
            <input type="hidden" name="<?php echo esc_attr( $name_prefix ); ?>[variation_id]" value="<?php echo esc_attr( $prod['variation_id'] ); ?>" class="pf-variation-id">
            <span class="pf-product-name"><?php echo esc_html( $product_name ); ?></span>
            <div class="pf-variation-picker" style="display:none;">
                <select class="pf-variation-select" data-current="<?php echo esc_attr( $prod['variation_id'] ); ?>">
                    <option value=""><?php esc_html_e( '— Select variation —', 'product-finder' ); ?></option>
                    <?php if ( $prod['variation_id'] && $variation_name ) : ?>
                        <option value="<?php echo esc_attr( $prod['variation_id'] ); ?>" selected><?php echo esc_html( $variation_name ); ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <label class="pf-product-category-label"><?php esc_html_e( 'Category:', 'product-finder' ); ?>
                <select name="<?php echo esc_attr( $name_prefix ); ?>[result_category]" class="pf-product-category">
                    <option value=""><?php esc_html_e( '— None —', 'product-finder' ); ?></option>
                    <option value="base" <?php selected( $prod['result_category'], 'base' ); ?>><?php esc_html_e( 'Base / Foundation', 'product-finder' ); ?></option>
                    <option value="concealer" <?php selected( $prod['result_category'], 'concealer' ); ?>><?php esc_html_e( 'Concealer', 'product-finder' ); ?></option>
                    <option value="lip" <?php selected( $prod['result_category'], 'lip' ); ?>><?php esc_html_e( 'Lip', 'product-finder' ); ?></option>
                    <option value="cheek" <?php selected( $prod['result_category'], 'cheek' ); ?>><?php esc_html_e( 'Cheek', 'product-finder' ); ?></option>
                    <option value="lip_cheek" <?php selected( $prod['result_category'], 'lip_cheek' ); ?>><?php esc_html_e( 'Lip & Cheek', 'product-finder' ); ?></option>
                    <option value="eye" <?php selected( $prod['result_category'], 'eye' ); ?>><?php esc_html_e( 'Eye', 'product-finder' ); ?></option>
                </select>
            </label>
            <label class="pf-product-set-label pf-product-set-picker" style="display:none;"><?php esc_html_e( 'Set:', 'product-finder' ); ?>
                <select name="<?php echo esc_attr( $name_prefix ); ?>[result_set]" class="pf-product-set">
                    <option value="both" <?php selected( $prod['result_set'], 'both' ); ?>><?php esc_html_e( 'Both', 'product-finder' ); ?></option>
                    <option value="day" <?php selected( $prod['result_set'], 'day' ); ?>><?php esc_html_e( 'Day', 'product-finder' ); ?></option>
                    <option value="night" <?php selected( $prod['result_set'], 'night' ); ?>><?php esc_html_e( 'Night', 'product-finder' ); ?></option>
                </select>
            </label>
            <label class="pf-product-rank-label"><?php esc_html_e( 'Rank:', 'product-finder' ); ?>
                <input type="number" name="<?php echo esc_attr( $name_prefix ); ?>[rank]" value="<?php echo esc_attr( $prod['rank'] ); ?>" min="1" class="pf-product-rank small-text">
            </label>
            <button type="button" class="pf-remove-product button-link" title="<?php esc_attr_e( 'Remove Product', 'product-finder' ); ?>"><span class="dashicons dashicons-trash"></span></button>
        </div>
        <?php
    }

    /* ───────────────────────── Save ─────────────────────────── */

    public function save_meta( $post_id, $post ) {
        if ( ! isset( $_POST['pf_meta_nonce'] ) || ! wp_verify_nonce( $_POST['pf_meta_nonce'], 'pf_save_meta' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save questions
        $raw_questions = $_POST['pf_questions'] ?? array();
        $questions     = $this->sanitize_questions( $raw_questions );
        update_post_meta( $post_id, '_pf_questions', $questions );

        // Save options
        $raw_options = $_POST['pf_options'] ?? array();
        $finder_type = sanitize_text_field( $raw_options['finder_type'] ?? 'cosmeceuticals' );
        if ( ! in_array( $finder_type, array( 'cosmeceuticals', 'beauty' ), true ) ) {
            $finder_type = 'cosmeceuticals';
        }
        $options     = array(
            'num_results'      => absint( $raw_options['num_results'] ?? 5 ),
            'listing_template' => sanitize_text_field( $raw_options['listing_template'] ?? '' ),
            'cols_desktop'     => absint( $raw_options['cols_desktop'] ?? 3 ),
            'cols_tablet'      => absint( $raw_options['cols_tablet'] ?? 2 ),
            'cols_mobile'      => absint( $raw_options['cols_mobile'] ?? 1 ),
            'finder_type'      => $finder_type,
            'enable_day_night' => ! empty( $raw_options['enable_day_night'] ) ? 1 : 0,
        );
        update_post_meta( $post_id, '_pf_options', $options );
    }

    private function sanitize_questions( $raw ) {
        $clean = array();
        if ( ! is_array( $raw ) ) {
            return $clean;
        }
        foreach ( $raw as $q ) {
            $question = array(
                'text'        => sanitize_text_field( $q['text'] ?? '' ),
                'instruction' => sanitize_text_field( $q['instruction'] ?? '' ),
                'multiple'    => ! empty( $q['multiple'] ) ? 1 : 0,
                'answers'     => array(),
            );
            if ( ! empty( $q['answers'] ) && is_array( $q['answers'] ) ) {
                foreach ( $q['answers'] as $a ) {
                    $answer = array(
                        'text'        => sanitize_text_field( $a['text'] ?? '' ),
                        'description' => sanitize_text_field( $a['description'] ?? '' ),
                        'image_id'    => absint( $a['image_id'] ?? 0 ),
                        'products'    => array(),
                    );
                    if ( ! empty( $a['products'] ) && is_array( $a['products'] ) ) {
                        $valid_categories = array( '', 'base', 'concealer', 'lip', 'cheek', 'lip_cheek', 'eye' );
                        foreach ( $a['products'] as $p ) {
                            $cat = sanitize_key( $p['result_category'] ?? '' );
                            if ( ! in_array( $cat, $valid_categories, true ) ) {
                                $cat = '';
                            }
                            $result_set = sanitize_key( $p['result_set'] ?? 'both' );
                            if ( ! in_array( $result_set, array( 'both', 'day', 'night' ), true ) ) {
                                $result_set = 'both';
                            }
                            $answer['products'][] = array(
                                'id'              => absint( $p['id'] ?? 0 ),
                                'variation_id'    => absint( $p['variation_id'] ?? 0 ),
                                'rank'            => absint( $p['rank'] ?? 1 ),
                                'result_category' => $cat,
                                'result_set'      => $result_set,
                            );
                        }
                    }
                    $question['answers'][] = $answer;
                }
            }
            $clean[] = $question;
        }
        return $clean;
    }

    /* ─── CrocoBlock templates ─── */

    private function get_crocoblock_templates() {
        $templates = array();

        // JetEngine listings
        $posts = get_posts( array(
            'post_type'      => 'jet-engine',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        foreach ( $posts as $p ) {
            $templates[ $p->ID ] = $p->post_title;
        }

        // Also check jet-listing type if available
        $jet_listings = get_posts( array(
            'post_type'      => 'jet-engine-booking',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        foreach ( $jet_listings as $p ) {
            $templates[ $p->ID ] = $p->post_title;
        }

        return $templates;
    }
}

new PF_Admin();
