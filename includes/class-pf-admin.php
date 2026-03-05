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
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );

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

        add_meta_box(
            'pf_dn_styles',
            __( 'Day / Night Tab Styling', 'product-finder' ),
            array( $this, 'render_dn_styles_box' ),
            'product_finder',
            'normal',
            'default'
        );

        add_meta_box(
            'pf_email_styles',
            __( 'Email Styling', 'product-finder' ),
            array( $this, 'render_email_styles_box' ),
            'product_finder',
            'normal',
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

    /* ─── Day / Night Tab Styling box ─── */

    public function render_dn_styles_box( $post ) {
        $dn = get_post_meta( $post->ID, '_pf_dn_styles', true );
        $dn = wp_parse_args( (array) $dn, array(
            'day_label'        => 'Day',
            'night_label'      => 'Night',
            'tab_color'        => '',
            'tab_bg'           => '',
            'tab_hover_color'  => '',
            'tab_hover_bg'     => '',
            'tab_active_color' => '',
            'tab_active_bg'    => '',
            'line_color'       => '',
            'line_width'       => '',
            'active_line_color'=> '',
            'tab_padding'      => '',
            'tab_gap'          => '',
            'tab_radius'       => '',
            'tabs_margin'      => '',
            'tabs_align'       => '',
            'font_family'      => '',
            'font_size'        => '',
            'font_weight'      => '',
        ) );
        ?>
        <div class="pf-dn-styles-wrap">
            <p class="description"><?php esc_html_e( 'These styles are applied to the Day / Night result tabs on the frontend. Leave fields blank to use theme defaults.', 'product-finder' ); ?></p>

            <div class="pf-dn-grid">
                <!-- Labels -->
                <fieldset class="pf-dn-fieldset">
                    <legend><?php esc_html_e( 'Labels', 'product-finder' ); ?></legend>
                    <p>
                        <label><?php esc_html_e( 'Day Label', 'product-finder' ); ?></label><br>
                        <input type="text" name="pf_dn[day_label]" value="<?php echo esc_attr( $dn['day_label'] ); ?>" class="regular-text">
                    </p>
                    <p>
                        <label><?php esc_html_e( 'Night Label', 'product-finder' ); ?></label><br>
                        <input type="text" name="pf_dn[night_label]" value="<?php echo esc_attr( $dn['night_label'] ); ?>" class="regular-text">
                    </p>
                </fieldset>

                <!-- Normal State -->
                <fieldset class="pf-dn-fieldset">
                    <legend><?php esc_html_e( 'Normal State', 'product-finder' ); ?></legend>
                    <p>
                        <label><?php esc_html_e( 'Text Colour', 'product-finder' ); ?></label><br>
                        <input type="text" name="pf_dn[tab_color]" value="<?php echo esc_attr( $dn['tab_color'] ); ?>" class="pf-color-field" data-default-color="">
                    </p>
                    <p>
                        <label><?php esc_html_e( 'Background', 'product-finder' ); ?></label><br>
                        <input type="text" name="pf_dn[tab_bg]" value="<?php echo esc_attr( $dn['tab_bg'] ); ?>" class="pf-color-field" data-default-color="">
                    </p>
                </fieldset>

                <!-- Hover State -->
                <fieldset class="pf-dn-fieldset">
                    <legend><?php esc_html_e( 'Hover State', 'product-finder' ); ?></legend>
                    <p>
                        <label><?php esc_html_e( 'Text Colour', 'product-finder' ); ?></label><br>
                        <input type="text" name="pf_dn[tab_hover_color]" value="<?php echo esc_attr( $dn['tab_hover_color'] ); ?>" class="pf-color-field" data-default-color="">
                    </p>
                    <p>
                        <label><?php esc_html_e( 'Background', 'product-finder' ); ?></label><br>
                        <input type="text" name="pf_dn[tab_hover_bg]" value="<?php echo esc_attr( $dn['tab_hover_bg'] ); ?>" class="pf-color-field" data-default-color="">
                    </p>
                </fieldset>

                <!-- Active State -->
                <fieldset class="pf-dn-fieldset">
                    <legend><?php esc_html_e( 'Active State', 'product-finder' ); ?></legend>
                    <p>
                        <label><?php esc_html_e( 'Text Colour', 'product-finder' ); ?></label><br>
                        <input type="text" name="pf_dn[tab_active_color]" value="<?php echo esc_attr( $dn['tab_active_color'] ); ?>" class="pf-color-field" data-default-color="">
                    </p>
                    <p>
                        <label><?php esc_html_e( 'Background', 'product-finder' ); ?></label><br>
                        <input type="text" name="pf_dn[tab_active_bg]" value="<?php echo esc_attr( $dn['tab_active_bg'] ); ?>" class="pf-color-field" data-default-color="">
                    </p>
                </fieldset>

                <!-- Bottom Line -->
                <fieldset class="pf-dn-fieldset">
                    <legend><?php esc_html_e( 'Bottom Line', 'product-finder' ); ?></legend>
                    <p>
                        <label><?php esc_html_e( 'Line Colour', 'product-finder' ); ?></label><br>
                        <input type="text" name="pf_dn[line_color]" value="<?php echo esc_attr( $dn['line_color'] ); ?>" class="pf-color-field" data-default-color="">
                    </p>
                    <p>
                        <label><?php esc_html_e( 'Active Line Colour', 'product-finder' ); ?></label><br>
                        <input type="text" name="pf_dn[active_line_color]" value="<?php echo esc_attr( $dn['active_line_color'] ); ?>" class="pf-color-field" data-default-color="">
                    </p>
                    <p>
                        <label><?php esc_html_e( 'Line Thickness (px)', 'product-finder' ); ?></label><br>
                        <input type="number" name="pf_dn[line_width]" value="<?php echo esc_attr( $dn['line_width'] ); ?>" min="0" max="10" step="1" class="small-text">
                    </p>
                </fieldset>

                <!-- Typography -->
                <fieldset class="pf-dn-fieldset">
                    <legend><?php esc_html_e( 'Typography', 'product-finder' ); ?></legend>
                    <p>
                        <label><?php esc_html_e( 'Font Family', 'product-finder' ); ?></label><br>
                        <input type="text" name="pf_dn[font_family]" value="<?php echo esc_attr( $dn['font_family'] ); ?>" class="regular-text" placeholder="e.g. Apotheca, sans-serif">
                    </p>
                    <p>
                        <label><?php esc_html_e( 'Font Size (px)', 'product-finder' ); ?></label><br>
                        <input type="number" name="pf_dn[font_size]" value="<?php echo esc_attr( $dn['font_size'] ); ?>" min="0" max="100" step="1" class="small-text">
                    </p>
                    <p>
                        <label><?php esc_html_e( 'Font Weight', 'product-finder' ); ?></label><br>
                        <select name="pf_dn[font_weight]" class="widefat">
                            <option value=""><?php esc_html_e( '— Default —', 'product-finder' ); ?></option>
                            <option value="300" <?php selected( $dn['font_weight'], '300' ); ?>>300 (Light)</option>
                            <option value="400" <?php selected( $dn['font_weight'], '400' ); ?>>400 (Normal)</option>
                            <option value="500" <?php selected( $dn['font_weight'], '500' ); ?>>500 (Medium)</option>
                            <option value="600" <?php selected( $dn['font_weight'], '600' ); ?>>600 (Semi-Bold)</option>
                            <option value="700" <?php selected( $dn['font_weight'], '700' ); ?>>700 (Bold)</option>
                        </select>
                    </p>
                </fieldset>

                <!-- Spacing -->
                <fieldset class="pf-dn-fieldset">
                    <legend><?php esc_html_e( 'Spacing & Layout', 'product-finder' ); ?></legend>
                    <p>
                        <label><?php esc_html_e( 'Tab Padding (CSS shorthand)', 'product-finder' ); ?></label><br>
                        <input type="text" name="pf_dn[tab_padding]" value="<?php echo esc_attr( $dn['tab_padding'] ); ?>" class="regular-text" placeholder="e.g. 8px 16px">
                    </p>
                    <p>
                        <label><?php esc_html_e( 'Gap Between Tabs (px)', 'product-finder' ); ?></label><br>
                        <input type="number" name="pf_dn[tab_gap]" value="<?php echo esc_attr( $dn['tab_gap'] ); ?>" min="0" max="60" step="1" class="small-text">
                    </p>
                    <p>
                        <label><?php esc_html_e( 'Border Radius (CSS shorthand)', 'product-finder' ); ?></label><br>
                        <input type="text" name="pf_dn[tab_radius]" value="<?php echo esc_attr( $dn['tab_radius'] ); ?>" class="regular-text" placeholder="e.g. 4px">
                    </p>
                    <p>
                        <label><?php esc_html_e( 'Tabs Margin (CSS shorthand)', 'product-finder' ); ?></label><br>
                        <input type="text" name="pf_dn[tabs_margin]" value="<?php echo esc_attr( $dn['tabs_margin'] ); ?>" class="regular-text" placeholder="e.g. 0 0 24px 0">
                    </p>
                    <p>
                        <label><?php esc_html_e( 'Alignment', 'product-finder' ); ?></label><br>
                        <select name="pf_dn[tabs_align]" class="widefat">
                            <option value=""><?php esc_html_e( '— Default —', 'product-finder' ); ?></option>
                            <option value="flex-start" <?php selected( $dn['tabs_align'], 'flex-start' ); ?>><?php esc_html_e( 'Left', 'product-finder' ); ?></option>
                            <option value="center" <?php selected( $dn['tabs_align'], 'center' ); ?>><?php esc_html_e( 'Centre', 'product-finder' ); ?></option>
                            <option value="flex-end" <?php selected( $dn['tabs_align'], 'flex-end' ); ?>><?php esc_html_e( 'Right', 'product-finder' ); ?></option>
                        </select>
                    </p>
                </fieldset>
            </div>
        </div>
        <script>
        jQuery(function($){ $('.pf-color-field').wpColorPicker(); });
        </script>
        <?php
    }

    /* ─── Email Styling box ─── */

    public function render_email_styles_box( $post ) {
        $es = get_post_meta( $post->ID, '_pf_email_styles', true );
        $es = wp_parse_args( (array) $es, array(
            'logo_id'          => 0,
            'header_image_id'  => 0,
            'accent_color'     => '#000000',
            'heading'          => '',
            'sub_heading'      => '',
        ) );
        $logo_url = $es['logo_id'] ? wp_get_attachment_image_url( $es['logo_id'], 'medium' ) : '';
        $header_image_url = $es['header_image_id'] ? wp_get_attachment_image_url( $es['header_image_id'], 'medium' ) : '';
        ?>
        <div class="pf-email-styles-wrap">
            <p class="description"><?php esc_html_e( 'Customise the results email that gets sent to users. Leave fields blank to use defaults.', 'product-finder' ); ?></p>

            <div class="pf-dn-grid">
                <!-- Logo -->
                <fieldset class="pf-dn-fieldset">
                    <legend><?php esc_html_e( 'Logo', 'product-finder' ); ?></legend>
                    <p>
                        <input type="hidden" name="pf_email[logo_id]" value="<?php echo esc_attr( $es['logo_id'] ); ?>" class="pf-email-logo-id">
                        <div class="pf-email-logo-preview" <?php echo $logo_url ? '' : 'style="display:none;"'; ?>>
                            <img src="<?php echo esc_url( $logo_url ); ?>" alt="" style="max-width:200px;max-height:80px;height:auto;display:block;margin-bottom:6px;border:1px solid #dcdcde;border-radius:4px;">
                            <button type="button" class="button pf-email-remove-logo"><?php esc_html_e( 'Remove Logo', 'product-finder' ); ?></button>
                        </div>
                        <button type="button" class="button pf-email-select-logo"><?php esc_html_e( 'Select Logo', 'product-finder' ); ?></button>
                        <span class="description"><?php esc_html_e( 'Displayed at the top of the email.', 'product-finder' ); ?></span>
                    </p>
                </fieldset>

                <!-- Header Image (replaces heading + sub-heading text) -->
                <fieldset class="pf-dn-fieldset">
                    <legend><?php esc_html_e( 'Header Image', 'product-finder' ); ?></legend>
                    <p>
                        <input type="hidden" name="pf_email[header_image_id]" value="<?php echo esc_attr( $es['header_image_id'] ); ?>" class="pf-email-header-image-id">
                        <div class="pf-email-header-image-preview" <?php echo $header_image_url ? '' : 'style="display:none;"'; ?>>
                            <img src="<?php echo esc_url( $header_image_url ); ?>" alt="" style="max-width:400px;max-height:200px;height:auto;display:block;margin-bottom:6px;border:1px solid #dcdcde;border-radius:4px;">
                            <button type="button" class="button pf-email-remove-header-image"><?php esc_html_e( 'Remove Image', 'product-finder' ); ?></button>
                        </div>
                        <button type="button" class="button pf-email-select-header-image"><?php esc_html_e( 'Select Header Image', 'product-finder' ); ?></button>
                        <span class="description"><?php esc_html_e( 'Optional. When set, replaces the heading and sub-heading text with this image. Upload a transparent PNG for best results. The heading and sub-heading text will be used as alt text for accessibility.', 'product-finder' ); ?></span>
                    </p>
                </fieldset>

                <!-- Accent Colour -->
                <fieldset class="pf-dn-fieldset">
                    <legend><?php esc_html_e( 'Accent Colour', 'product-finder' ); ?></legend>
                    <p>
                        <label><?php esc_html_e( 'Top Strip & Button Background', 'product-finder' ); ?></label><br>
                        <input type="text" name="pf_email[accent_color]" value="<?php echo esc_attr( $es['accent_color'] ); ?>" class="pf-color-field" data-default-color="#000000">
                        <span class="description"><?php esc_html_e( 'Used for the top colour strip and "Shop the Look" button.', 'product-finder' ); ?></span>
                    </p>
                </fieldset>

                <!-- Heading -->
                <fieldset class="pf-dn-fieldset">
                    <legend><?php esc_html_e( 'Heading', 'product-finder' ); ?></legend>
                    <p>
                        <label><?php esc_html_e( 'Main Heading', 'product-finder' ); ?></label><br>
                        <input type="text" name="pf_email[heading]" value="<?php echo esc_attr( $es['heading'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Your Personalised Routine', 'product-finder' ); ?>">
                        <span class="description"><?php esc_html_e( 'The large heading displayed under the logo. Defaults to the finder title.', 'product-finder' ); ?></span>
                    </p>
                </fieldset>

                <!-- Sub Heading -->
                <fieldset class="pf-dn-fieldset">
                    <legend><?php esc_html_e( 'Sub Heading', 'product-finder' ); ?></legend>
                    <p>
                        <label><?php esc_html_e( 'Text Under Heading', 'product-finder' ); ?></label>
                        <span class="description"><?php esc_html_e( 'Smaller text shown below the main heading.', 'product-finder' ); ?></span>
                    </p>
                    <?php
                    wp_editor( $es['sub_heading'], 'pf_email_sub_heading', array(
                        'textarea_name' => 'pf_email[sub_heading]',
                        'textarea_rows' => 5,
                        'media_buttons' => false,
                        'teeny'         => true,
                        'quicktags'     => true,
                    ) );
                    ?>
                </fieldset>
            </div>
        </div>
        <script>
        jQuery(function($){
            // Re-init colour picker for any new fields.
            $('.pf-email-styles-wrap .pf-color-field').not('.wp-color-picker').wpColorPicker();

            // Logo upload.
            $(document).on('click', '.pf-email-select-logo', function(){
                var frame = wp.media({ title: 'Select Logo', button: { text: 'Use this image' }, multiple: false, library: { type: 'image' } });
                frame.on('select', function(){
                    var att = frame.state().get('selection').first().toJSON();
                    var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
                    $('.pf-email-logo-id').val(att.id);
                    $('.pf-email-logo-preview img').attr('src', url);
                    $('.pf-email-logo-preview').show();
                });
                frame.open();
            });

            $(document).on('click', '.pf-email-remove-logo', function(){
                $('.pf-email-logo-id').val('');
                $('.pf-email-logo-preview').hide();
                $('.pf-email-logo-preview img').attr('src', '');
            });

            // Header image upload.
            $(document).on('click', '.pf-email-select-header-image', function(){
                var frame = wp.media({ title: 'Select Header Image', button: { text: 'Use this image' }, multiple: false, library: { type: 'image' } });
                frame.on('select', function(){
                    var att = frame.state().get('selection').first().toJSON();
                    var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
                    $('.pf-email-header-image-id').val(att.id);
                    $('.pf-email-header-image-preview img').attr('src', url);
                    $('.pf-email-header-image-preview').show();
                });
                frame.open();
            });

            $(document).on('click', '.pf-email-remove-header-image', function(){
                $('.pf-email-header-image-id').val('');
                $('.pf-email-header-image-preview').hide();
                $('.pf-email-header-image-preview img').attr('src', '');
            });
        });
        </script>
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

        <!-- Hidden template for follow-up answer -->
        <script type="text/html" id="tmpl-pf-followup-answer">
            <?php $this->render_followup_answer_template( '{{data.qi}}', '{{data.ai}}', '{{data.fai}}', array() ); ?>
        </script>

        <!-- Hidden template for follow-up product row -->
        <script type="text/html" id="tmpl-pf-followup-product-row">
            <?php $this->render_followup_product_row_template( '{{data.qi}}', '{{data.ai}}', '{{data.fai}}', '{{data.pi}}', array() ); ?>
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
            'follow_up'   => array(),
        ) );
        $name_prefix = "pf_questions[{$qi}][answers][{$ai}]";
        $thumb_url   = $answer['image_id'] ? wp_get_attachment_image_url( $answer['image_id'], 'thumbnail' ) : '';
        $has_followup = ! empty( $answer['follow_up']['text'] ) || ! empty( $answer['follow_up']['answers'] );
        $fu_prefix    = $name_prefix . '[follow_up]';
        $fu           = wp_parse_args( (array) ( $answer['follow_up'] ?? array() ), array(
            'text'        => '',
            'instruction' => '',
            'multiple'    => 0,
            'answers'     => array(),
        ) );
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
                <!-- Follow-up question -->
                <div class="pf-followup-wrap" <?php echo $has_followup ? '' : 'style="display:none;"'; ?>>
                    <div class="pf-followup-header">
                        <span class="dashicons dashicons-admin-comments"></span>
                        <strong><?php esc_html_e( 'Follow-up Question', 'product-finder' ); ?></strong>
                        <button type="button" class="pf-remove-followup button-link" title="<?php esc_attr_e( 'Remove Follow-up', 'product-finder' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
                    </div>
                    <div class="pf-followup-body">
                        <p>
                            <label><?php esc_html_e( 'Follow-up Question Text', 'product-finder' ); ?></label><br>
                            <input type="text" name="<?php echo esc_attr( $fu_prefix ); ?>[text]" value="<?php echo esc_attr( $fu['text'] ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'e.g. What shade of brunette?', 'product-finder' ); ?>">
                        </p>
                        <p>
                            <label><?php esc_html_e( 'Instruction', 'product-finder' ); ?></label><br>
                            <input type="text" name="<?php echo esc_attr( $fu_prefix ); ?>[instruction]" value="<?php echo esc_attr( $fu['instruction'] ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Optional', 'product-finder' ); ?>">
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( $fu_prefix ); ?>[multiple]" value="1" <?php checked( $fu['multiple'], 1 ); ?>>
                                <?php esc_html_e( 'Allow multiple answers', 'product-finder' ); ?>
                            </label>
                        </p>
                        <div class="pf-followup-answers">
                            <h4><?php esc_html_e( 'Follow-up Answers', 'product-finder' ); ?></h4>
                            <div class="pf-followup-answers-list">
                                <?php
                                if ( ! empty( $fu['answers'] ) ) {
                                    foreach ( $fu['answers'] as $fai => $fa ) {
                                        $this->render_followup_answer_template( $qi, $ai, $fai, $fa );
                                    }
                                }
                                ?>
                            </div>
                            <p><button type="button" class="button pf-add-followup-answer"><?php esc_html_e( '+ Add Follow-up Answer', 'product-finder' ); ?></button></p>
                        </div>
                    </div>
                </div>
                <div class="pf-followup-add" <?php echo $has_followup ? 'style="display:none;"' : ''; ?>>
                    <button type="button" class="button pf-add-followup"><span class="dashicons dashicons-admin-comments"></span> <?php esc_html_e( 'Add Follow-up Question', 'product-finder' ); ?></button>
                    <span class="description"><?php esc_html_e( 'Show an extra question when this answer is selected', 'product-finder' ); ?></span>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_followup_answer_template( $qi, $ai, $fai, $fa ) {
        $fa = wp_parse_args( $fa, array(
            'text'        => '',
            'description' => '',
            'image_id'    => '',
            'products'    => array(),
        ) );
        $name_prefix = "pf_questions[{$qi}][answers][{$ai}][follow_up][answers][{$fai}]";
        $thumb_url   = $fa['image_id'] ? wp_get_attachment_image_url( $fa['image_id'], 'thumbnail' ) : '';
        ?>
        <div class="pf-followup-answer" data-fai="<?php echo esc_attr( $fai ); ?>">
            <div class="pf-followup-answer-header">
                <span class="pf-followup-answer-label"><?php echo $fa['text'] ? esc_html( $fa['text'] ) : esc_html__( 'New Answer', 'product-finder' ); ?></span>
                <button type="button" class="pf-remove-followup-answer button-link" title="<?php esc_attr_e( 'Delete', 'product-finder' ); ?>"><span class="dashicons dashicons-trash"></span></button>
            </div>
            <div class="pf-followup-answer-body">
                <p>
                    <label><?php esc_html_e( 'Answer Text', 'product-finder' ); ?></label><br>
                    <input type="text" name="<?php echo esc_attr( $name_prefix ); ?>[text]" value="<?php echo esc_attr( $fa['text'] ); ?>" class="widefat pf-followup-answer-text-input">
                </p>
                <p>
                    <label><?php esc_html_e( 'Description', 'product-finder' ); ?></label><br>
                    <input type="text" name="<?php echo esc_attr( $name_prefix ); ?>[description]" value="<?php echo esc_attr( $fa['description'] ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Optional', 'product-finder' ); ?>">
                </p>
                <div class="pf-answer-image-wrap">
                    <label><?php esc_html_e( 'Image', 'product-finder' ); ?></label><br>
                    <input type="hidden" name="<?php echo esc_attr( $name_prefix ); ?>[image_id]" value="<?php echo esc_attr( $fa['image_id'] ); ?>" class="pf-image-id">
                    <div class="pf-image-preview" <?php echo $thumb_url ? '' : 'style="display:none;"'; ?>>
                        <img src="<?php echo esc_url( $thumb_url ); ?>" alt="">
                        <button type="button" class="pf-remove-image button-link" title="<?php esc_attr_e( 'Remove Image', 'product-finder' ); ?>"><span class="dashicons dashicons-no-alt"></span> <?php esc_html_e( 'Remove', 'product-finder' ); ?></button>
                    </div>
                    <button type="button" class="button pf-select-image"><?php esc_html_e( 'Select Image', 'product-finder' ); ?></button>
                </div>
                <div class="pf-answer-products-wrap">
                    <label><?php esc_html_e( 'Products', 'product-finder' ); ?></label><br>
                    <input type="text" class="widefat pf-product-search" placeholder="<?php esc_attr_e( 'Search for a product…', 'product-finder' ); ?>" autocomplete="off">
                    <div class="pf-product-search-results"></div>
                    <div class="pf-products-list">
                        <?php
                        if ( ! empty( $fa['products'] ) ) {
                            foreach ( $fa['products'] as $pi => $prod ) {
                                $this->render_followup_product_row_template( $qi, $ai, $fai, $pi, $prod );
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_followup_product_row_template( $qi, $ai, $fai, $pi, $prod ) {
        $prod = wp_parse_args( $prod, array(
            'id'              => '',
            'variation_id'    => '',
            'rank'            => 1,
            'result_category' => '',
            'result_set'      => 'both',
        ) );
        $name_prefix  = "pf_questions[{$qi}][answers][{$ai}][follow_up][answers][{$fai}][products][{$pi}]";
        $product_name = $prod['id'] ? get_the_title( $prod['id'] ) : '{{data.name}}';

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
            <label class="pf-product-category-label pf-product-category-picker" style="display:none;"><?php esc_html_e( 'Category:', 'product-finder' ); ?>
                <select name="<?php echo esc_attr( $name_prefix ); ?>[result_category]" class="pf-product-category">
                    <option value=""><?php esc_html_e( '— None —', 'product-finder' ); ?></option>
                    <option value="base" data-type="beauty" <?php selected( $prod['result_category'], 'base' ); ?>><?php esc_html_e( 'Base / Foundation', 'product-finder' ); ?></option>
                    <option value="concealer" data-type="beauty" <?php selected( $prod['result_category'], 'concealer' ); ?>><?php esc_html_e( 'Concealer', 'product-finder' ); ?></option>
                    <option value="lip" data-type="beauty" <?php selected( $prod['result_category'], 'lip' ); ?>><?php esc_html_e( 'Lip', 'product-finder' ); ?></option>
                    <option value="cheek" data-type="beauty" <?php selected( $prod['result_category'], 'cheek' ); ?>><?php esc_html_e( 'Cheek', 'product-finder' ); ?></option>
                    <option value="lip_cheek" data-type="beauty" <?php selected( $prod['result_category'], 'lip_cheek' ); ?>><?php esc_html_e( 'Lip & Cheek', 'product-finder' ); ?></option>
                    <option value="eye" data-type="beauty" <?php selected( $prod['result_category'], 'eye' ); ?>><?php esc_html_e( 'Eye', 'product-finder' ); ?></option>
                    <option value="cleanser" data-type="cosmeceuticals" <?php selected( $prod['result_category'], 'cleanser' ); ?>><?php esc_html_e( 'Cleanser', 'product-finder' ); ?></option>
                    <option value="exfoliator" data-type="cosmeceuticals" <?php selected( $prod['result_category'], 'exfoliator' ); ?>><?php esc_html_e( 'Exfoliator', 'product-finder' ); ?></option>
                    <option value="moisturiser" data-type="cosmeceuticals" <?php selected( $prod['result_category'], 'moisturiser' ); ?>><?php esc_html_e( 'Moisturiser', 'product-finder' ); ?></option>
                    <option value="essential" data-type="cosmeceuticals" <?php selected( $prod['result_category'], 'essential' ); ?>><?php esc_html_e( 'Essential', 'product-finder' ); ?></option>
                    <option value="specialty" data-type="cosmeceuticals" <?php selected( $prod['result_category'], 'specialty' ); ?>><?php esc_html_e( 'Specialty', 'product-finder' ); ?></option>
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
            <label class="pf-product-category-label pf-product-category-picker" style="display:none;"><?php esc_html_e( 'Category:', 'product-finder' ); ?>
                <select name="<?php echo esc_attr( $name_prefix ); ?>[result_category]" class="pf-product-category">
                    <option value=""><?php esc_html_e( '— None —', 'product-finder' ); ?></option>
                    <option value="base" data-type="beauty" <?php selected( $prod['result_category'], 'base' ); ?>><?php esc_html_e( 'Base / Foundation', 'product-finder' ); ?></option>
                    <option value="concealer" data-type="beauty" <?php selected( $prod['result_category'], 'concealer' ); ?>><?php esc_html_e( 'Concealer', 'product-finder' ); ?></option>
                    <option value="lip" data-type="beauty" <?php selected( $prod['result_category'], 'lip' ); ?>><?php esc_html_e( 'Lip', 'product-finder' ); ?></option>
                    <option value="cheek" data-type="beauty" <?php selected( $prod['result_category'], 'cheek' ); ?>><?php esc_html_e( 'Cheek', 'product-finder' ); ?></option>
                    <option value="lip_cheek" data-type="beauty" <?php selected( $prod['result_category'], 'lip_cheek' ); ?>><?php esc_html_e( 'Lip & Cheek', 'product-finder' ); ?></option>
                    <option value="eye" data-type="beauty" <?php selected( $prod['result_category'], 'eye' ); ?>><?php esc_html_e( 'Eye', 'product-finder' ); ?></option>
                    <option value="cleanser" data-type="cosmeceuticals" <?php selected( $prod['result_category'], 'cleanser' ); ?>><?php esc_html_e( 'Cleanser', 'product-finder' ); ?></option>
                    <option value="exfoliator" data-type="cosmeceuticals" <?php selected( $prod['result_category'], 'exfoliator' ); ?>><?php esc_html_e( 'Exfoliator', 'product-finder' ); ?></option>
                    <option value="moisturiser" data-type="cosmeceuticals" <?php selected( $prod['result_category'], 'moisturiser' ); ?>><?php esc_html_e( 'Moisturiser', 'product-finder' ); ?></option>
                    <option value="essential" data-type="cosmeceuticals" <?php selected( $prod['result_category'], 'essential' ); ?>><?php esc_html_e( 'Essential', 'product-finder' ); ?></option>
                    <option value="specialty" data-type="cosmeceuticals" <?php selected( $prod['result_category'], 'specialty' ); ?>><?php esc_html_e( 'Specialty', 'product-finder' ); ?></option>
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
            'listing_template' => sanitize_text_field( $raw_options['listing_template'] ?? '' ),
            'cols_desktop'     => absint( $raw_options['cols_desktop'] ?? 3 ),
            'cols_tablet'      => absint( $raw_options['cols_tablet'] ?? 2 ),
            'cols_mobile'      => absint( $raw_options['cols_mobile'] ?? 1 ),
            'finder_type'      => $finder_type,
            'enable_day_night' => ! empty( $raw_options['enable_day_night'] ) ? 1 : 0,
        );
        update_post_meta( $post_id, '_pf_options', $options );

        // Save Day / Night tab styles
        $raw_dn     = $_POST['pf_dn'] ?? array();
        $color_keys = array(
            'tab_color', 'tab_bg', 'tab_hover_color', 'tab_hover_bg',
            'tab_active_color', 'tab_active_bg', 'line_color', 'active_line_color',
        );
        $dn_styles  = array();
        foreach ( $color_keys as $ck ) {
            $dn_styles[ $ck ] = sanitize_hex_color( $raw_dn[ $ck ] ?? '' );
        }
        $dn_styles['day_label']    = sanitize_text_field( $raw_dn['day_label'] ?? 'Day' );
        $dn_styles['night_label']  = sanitize_text_field( $raw_dn['night_label'] ?? 'Night' );
        $dn_styles['line_width']   = absint( $raw_dn['line_width'] ?? 0 );
        $dn_styles['font_family']  = sanitize_text_field( $raw_dn['font_family'] ?? '' );
        $dn_styles['font_size']    = absint( $raw_dn['font_size'] ?? 0 );
        $dn_styles['font_weight']  = sanitize_text_field( $raw_dn['font_weight'] ?? '' );
        $dn_styles['tab_padding']  = sanitize_text_field( $raw_dn['tab_padding'] ?? '' );
        $dn_styles['tab_gap']      = absint( $raw_dn['tab_gap'] ?? 0 );
        $dn_styles['tab_radius']   = sanitize_text_field( $raw_dn['tab_radius'] ?? '' );
        $dn_styles['tabs_margin']  = sanitize_text_field( $raw_dn['tabs_margin'] ?? '' );
        $dn_styles['tabs_align']   = sanitize_text_field( $raw_dn['tabs_align'] ?? '' );
        update_post_meta( $post_id, '_pf_dn_styles', $dn_styles );

        // Save Email styles
        $raw_email    = $_POST['pf_email'] ?? array();
        $email_styles = array(
            'logo_id'          => absint( $raw_email['logo_id'] ?? 0 ),
            'header_image_id'  => absint( $raw_email['header_image_id'] ?? 0 ),
            'accent_color'     => sanitize_hex_color( $raw_email['accent_color'] ?? '#000000' ) ?: '#000000',
            'heading'          => sanitize_text_field( $raw_email['heading'] ?? '' ),
            'sub_heading'      => wp_kses_post( $raw_email['sub_heading'] ?? '' ),
        );
        update_post_meta( $post_id, '_pf_email_styles', $email_styles );
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
                    // Follow-up question (optional)
                    $answer['follow_up'] = array();
                    if ( ! empty( $a['follow_up'] ) && is_array( $a['follow_up'] ) && ! empty( $a['follow_up']['text'] ) ) {
                        $fu = array(
                            'text'        => sanitize_text_field( $a['follow_up']['text'] ?? '' ),
                            'instruction' => sanitize_text_field( $a['follow_up']['instruction'] ?? '' ),
                            'multiple'    => ! empty( $a['follow_up']['multiple'] ) ? 1 : 0,
                            'answers'     => array(),
                        );
                        if ( ! empty( $a['follow_up']['answers'] ) && is_array( $a['follow_up']['answers'] ) ) {
                            foreach ( $a['follow_up']['answers'] as $fa ) {
                                $fu_answer = array(
                                    'text'        => sanitize_text_field( $fa['text'] ?? '' ),
                                    'description' => sanitize_text_field( $fa['description'] ?? '' ),
                                    'image_id'    => absint( $fa['image_id'] ?? 0 ),
                                    'products'    => array(),
                                );
                                if ( ! empty( $fa['products'] ) && is_array( $fa['products'] ) ) {
                                    foreach ( $fa['products'] as $fp ) {
                                        $fu_cat = sanitize_key( $fp['result_category'] ?? '' );
                                        if ( ! in_array( $fu_cat, $valid_categories, true ) ) {
                                            $fu_cat = '';
                                        }
                                        $fu_set = sanitize_key( $fp['result_set'] ?? 'both' );
                                        if ( ! in_array( $fu_set, array( 'both', 'day', 'night' ), true ) ) {
                                            $fu_set = 'both';
                                        }
                                        $fu_answer['products'][] = array(
                                            'id'              => absint( $fp['id'] ?? 0 ),
                                            'variation_id'    => absint( $fp['variation_id'] ?? 0 ),
                                            'rank'            => absint( $fp['rank'] ?? 1 ),
                                            'result_category' => $fu_cat,
                                            'result_set'      => $fu_set,
                                        );
                                    }
                                }
                                $fu['answers'][] = $fu_answer;
                            }
                        }
                        $answer['follow_up'] = $fu;
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
