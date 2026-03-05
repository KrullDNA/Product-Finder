<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle email sending and session-based results URLs for Product Finder.
 */
class PF_Email {

    public function __construct() {
        add_action( 'wp_ajax_pf_send_results_email', array( $this, 'send_results_email' ) );
        add_action( 'wp_ajax_nopriv_pf_send_results_email', array( $this, 'send_results_email' ) );

        // Save results to a session for shareable URL.
        add_action( 'wp_ajax_pf_save_results_session', array( $this, 'save_results_session' ) );
        add_action( 'wp_ajax_nopriv_pf_save_results_session', array( $this, 'save_results_session' ) );
    }

    /**
     * Generate a unique session token and store results in a transient.
     */
    public function save_results_session() {
        check_ajax_referer( 'pf_frontend_nonce', 'nonce' );

        $finder_id = absint( $_POST['finder_id'] ?? 0 );
        $answers   = $_POST['answers'] ?? '[]';
        $followup  = $_POST['followup_answers'] ?? '{}';

        if ( ! $finder_id ) {
            wp_send_json_error( array( 'message' => 'Invalid finder.' ) );
        }

        $token = wp_generate_password( 16, false );

        $session_data = array(
            'finder_id'        => $finder_id,
            'answers'          => $answers,
            'followup_answers' => $followup,
            'created'          => time(),
        );

        // Store for 30 days.
        set_transient( 'pf_results_' . $token, $session_data, 30 * DAY_IN_SECONDS );

        wp_send_json_success( array( 'token' => $token ) );
    }

    /**
     * Get stored session data by token.
     */
    public static function get_session_data( $token ) {
        $token = preg_replace( '/[^a-zA-Z0-9]/', '', $token );
        return get_transient( 'pf_results_' . $token );
    }

    public function send_results_email() {
        check_ajax_referer( 'pf_frontend_nonce', 'nonce' );

        $email       = sanitize_email( $_POST['email'] ?? '' );
        $finder_id   = absint( $_POST['finder_id'] ?? 0 );
        $product_ids = array_map( 'absint', (array) ( $_POST['product_ids'] ?? array() ) );
        $results_url = esc_url_raw( $_POST['results_url'] ?? '' );

        if ( ! is_email( $email ) || empty( $product_ids ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid email or no products found.', 'product-finder' ) ) );
        }

        // Check if this is a Beauty finder with variation data.
        $options = get_post_meta( $finder_id, '_pf_options', true );
        $options = wp_parse_args( (array) $options, array( 'finder_type' => 'cosmeceuticals' ) );

        // Accept optional products_data (variation-aware) from the frontend.
        $products_data = array();
        if ( ! empty( $_POST['products_data'] ) ) {
            $raw = json_decode( stripslashes( $_POST['products_data'] ), true );
            if ( is_array( $raw ) ) {
                $products_data = $raw;
            }
        }

        // Day/Night mode data.
        $day_night      = ! empty( $_POST['day_night'] );
        $day_products   = array();
        $night_products = array();
        if ( $day_night ) {
            if ( ! empty( $_POST['day_products'] ) ) {
                $raw = json_decode( stripslashes( $_POST['day_products'] ), true );
                if ( is_array( $raw ) ) {
                    $day_products = $raw;
                }
            }
            if ( ! empty( $_POST['night_products'] ) ) {
                $raw = json_decode( stripslashes( $_POST['night_products'] ), true );
                if ( is_array( $raw ) ) {
                    $night_products = $raw;
                }
            }
        }

        // Get email style settings.
        $email_styles = get_post_meta( $finder_id, '_pf_email_styles', true );
        $email_styles = wp_parse_args( (array) $email_styles, array(
            'logo_id'      => 0,
            'accent_color' => '#000000',
            'heading'      => '',
            'sub_heading'  => '',
        ) );

        $finder_title = get_the_title( $finder_id );
        $subject      = sprintf( __( 'Your %s Results', 'product-finder' ), $finder_title );

        $body = $this->build_email_body( $finder_id, $finder_title, $product_ids, $products_data, $results_url, $email_styles, $day_night, $day_products, $night_products );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
        );

        $sent = wp_mail( $email, $subject, $body, $headers );

        if ( $sent ) {
            wp_send_json_success( array( 'message' => __( 'Results sent to your email!', 'product-finder' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to send email. Please try again.', 'product-finder' ) ) );
        }
    }

    /**
     * Map category slug to display label.
     */
    private function get_category_label( $category ) {
        $map = array(
            'base'      => 'BASE',
            'concealer' => 'CONCEAL',
            'lip'       => 'LIP',
            'cheek'     => 'CHEEK',
            'lip_cheek' => 'LIP + CHEEK',
            'eye'       => 'EYE',
            'cleanser'    => 'CLEANSER',
            'exfoliator'  => 'EXFOLIATOR',
            'moisturiser' => 'MOISTURISER',
            'essential'   => 'ESSENTIAL',
            'specialty'   => 'SPECIALTY',
        );
        return $map[ $category ] ?? '';
    }

    /**
     * Build a single product card row for the email.
     */
    private function build_product_card( $product, $name, $category_label, $results_url = '' ) {
        $image_url = wp_get_attachment_image_url( $product->get_image_id(), 'medium' );
        $permalink = $product->get_permalink();
        $price     = $product->get_price_html();

        // Get product type / subtitle if available.
        $short_desc = $product->get_short_description();
        $type_text  = '';
        if ( $short_desc ) {
            // Use the first line of short description as subtitle.
            $type_text = wp_strip_all_tags( strtok( $short_desc, "\n" ) );
            if ( strlen( $type_text ) > 60 ) {
                $type_text = substr( $type_text, 0, 57 ) . '...';
            }
        }

        // Get variation swatch info.
        $swatch_html = '';
        $swatch_label = '';
        if ( $product->is_type( 'variation' ) ) {
            $attrs = $product->get_attributes();
            $swatch_label = implode( ', ', array_values( $attrs ) );

            // Try to get the swatch colour.
            $parent = wc_get_product( $product->get_parent_id() );
            if ( $parent ) {
                $color_attr = '';
                foreach ( $attrs as $attr_name => $attr_val ) {
                    $term = get_term_by( 'slug', $attr_val, $attr_name );
                    if ( $term ) {
                        $color = get_term_meta( $term->term_id, 'product_attribute_color', true );
                        if ( ! $color ) {
                            $color = get_term_meta( $term->term_id, '_fif_vse_color', true );
                        }
                        if ( $color ) {
                            $color_attr = $color;
                            break;
                        }
                    }
                }
                if ( $color_attr ) {
                    $swatch_html = '<td style="width:28px;vertical-align:middle;padding-right:8px;"><div style="width:24px;height:24px;border-radius:50%;background:' . esc_attr( $color_attr ) . ';border:1px solid rgba(0,0,0,0.1);"></div></td>';
                }
            }
        }

        $html = '<tr><td style="padding:16px 0;">';
        $html .= '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f8f8f8;border-radius:8px;">';
        $html .= '<tr>';

        // Category label (rotated) - left column.
        if ( $category_label ) {
            $html .= '<td width="40" style="vertical-align:top;text-align:center;padding:16px 0 16px 8px;">';
            $html .= '<div style="writing-mode:vertical-rl;transform:rotate(180deg);-webkit-transform:rotate(180deg);-ms-writing-mode:tb-rl;font-size:20px;font-weight:700;letter-spacing:0.15em;text-transform:uppercase;color:#000;white-space:nowrap;line-height:1;display:inline-block;">';
            $html .= esc_html( $category_label );
            $html .= '</div>';
            $html .= '</td>';
        }

        // Product image - centre.
        $html .= '<td width="180" style="vertical-align:middle;padding:16px;">';
        if ( $image_url ) {
            $html .= '<a href="' . esc_url( $permalink ) . '" style="text-decoration:none;"><img src="' . esc_url( $image_url ) . '" width="160" height="160" style="border-radius:6px;display:block;object-fit:cover;" alt="' . esc_attr( $name ) . '"></a>';
        }
        $html .= '</td>';

        // Product info - right.
        $html .= '<td style="vertical-align:middle;padding:16px 16px 16px 0;">';
        $html .= '<div style="font-size:18px;font-weight:400;color:#000;margin-bottom:2px;font-style:italic;">' . esc_html( $name ) . '</div>';
        if ( $type_text ) {
            $html .= '<div style="font-size:13px;font-weight:700;color:#000;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px;">' . esc_html( $type_text ) . '</div>';
        }
        $html .= '<div style="font-size:16px;font-weight:700;color:#000;margin-bottom:12px;">' . wp_strip_all_tags( $price ) . '</div>';

        // Swatch row.
        if ( $swatch_html || $swatch_label ) {
            $html .= '<table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:12px;"><tr>';
            $html .= $swatch_html;
            if ( $swatch_label ) {
                $html .= '<td style="vertical-align:middle;font-size:14px;color:#333;">' . esc_html( $swatch_label ) . '</td>';
            }
            $html .= '</tr></table>';
        }

        // Shop Now button (links to results page or product).
        $button_url = $results_url ? $results_url : $permalink;
        $html .= '<table cellpadding="0" cellspacing="0" border="0"><tr>';
        $html .= '<td style="vertical-align:middle;">';
        $html .= '<a href="' . esc_url( $button_url ) . '" style="display:inline-block;background:#000000;color:#fff;text-decoration:none;padding:10px 24px;font-size:13px;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;">';
        $html .= esc_html__( 'SHOP NOW', 'product-finder' );
        $html .= '</a>';
        $html .= '</td>';
        $html .= '</tr></table>';

        $html .= '</td>';
        $html .= '</tr></table>';
        $html .= '</td></tr>';

        return $html;
    }

    private function build_email_body( $finder_id, $finder_title, $product_ids, $products_data = array(), $results_url = '', $email_styles = array(), $day_night = false, $day_products = array(), $night_products = array() ) {
        $accent   = ! empty( $email_styles['accent_color'] ) ? $email_styles['accent_color'] : '#000000';
        $logo_url = '';
        if ( ! empty( $email_styles['logo_id'] ) ) {
            $logo_url = wp_get_attachment_image_url( absint( $email_styles['logo_id'] ), 'medium' );
        }
        $heading     = ! empty( $email_styles['heading'] ) ? $email_styles['heading'] : $finder_title . ' — Your Results';
        $sub_heading = ! empty( $email_styles['sub_heading'] ) ? $email_styles['sub_heading'] : __( 'Based on your answers, here are your recommended products:', 'product-finder' );

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html .= '<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">';
        $html .= '</head><body style="margin:0;padding:0;background:#ffffff;font-family:\'Montserrat\',Verdana,Arial,Helvetica,sans-serif;">';

        // Top accent strip.
        $html .= '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>';
        $html .= '<td style="background:' . esc_attr( $accent ) . ';height:6px;font-size:0;line-height:0;">&nbsp;</td>';
        $html .= '</tr></table>';

        // Container.
        $html .= '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" style="padding:0 16px;"><table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">';

        // Logo.
        if ( $logo_url ) {
            $html .= '<tr><td style="text-align:center;padding:30px 0 16px;">';
            $html .= '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $finder_title ) . '" style="max-width:200px;max-height:80px;height:auto;" />';
            $html .= '</td></tr>';
        }

        // Heading.
        $html .= '<tr><td style="text-align:center;padding:16px 0 6px;">';
        $html .= '<h1 style="margin:0;font-size:24px;font-weight:700;color:#000;">' . esc_html( $heading ) . '</h1>';
        $html .= '</td></tr>';

        // Sub heading.
        $html .= '<tr><td style="text-align:center;padding:0 0 24px;">';
        $html .= '<div style="margin:0;font-size:14px;color:#666;line-height:1.5;">' . wp_kses_post( $sub_heading ) . '</div>';
        $html .= '</td></tr>';

        // Shop the Look button.
        if ( $results_url ) {
            $html .= '<tr><td style="text-align:center;padding:0 0 30px;">';
            $html .= '<a href="' . esc_url( $results_url ) . '" style="display:inline-block;background:' . esc_attr( $accent ) . ';color:#000000;text-decoration:none;padding:14px 36px;font-size:14px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;">';
            $html .= esc_html__( 'SHOP THE LOOK', 'product-finder' );
            $html .= '</a>';
            $html .= '</td></tr>';
        }

        // Helper to render a group of product cards.
        $render_group = function ( $group_data, $fallback_ids = array() ) use ( $results_url ) {
            $out = '';
            if ( ! empty( $group_data ) ) {
                foreach ( $group_data as $p ) {
                    $pid          = absint( $p['id'] ?? 0 );
                    $variation_id = absint( $p['variation_id'] ?? 0 );

                    $display_id = $variation_id ?: $pid;
                    $product    = wc_get_product( $display_id );
                    if ( ! $product ) {
                        $product = wc_get_product( $pid );
                    }
                    if ( ! $product ) {
                        continue;
                    }

                    $name     = ! empty( $p['name'] ) ? $p['name'] : $product->get_name();
                    $category = $p['result_category'] ?? '';
                    $cat_label = $this->get_category_label( $category );

                    $out .= $this->build_product_card( $product, $name, $cat_label, $results_url );
                }
            } else {
                foreach ( $fallback_ids as $pid ) {
                    $product = wc_get_product( $pid );
                    if ( ! $product ) {
                        continue;
                    }
                    $out .= $this->build_product_card( $product, $product->get_name(), '', $results_url );
                }
            }
            return $out;
        };

        // Helper to render a Day/Night lozenge header.
        $render_lozenge = function ( $label ) use ( $accent ) {
            $out = '<tr><td style="text-align:center;padding:24px 0 8px;">';
            $out .= '<span style="display:inline-block;background:' . esc_attr( $accent ) . ';color:#000000;padding:8px 28px;font-size:13px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;border-radius:20px;">';
            $out .= esc_html( $label );
            $out .= '</span>';
            $out .= '</td></tr>';
            return $out;
        };

        // Product cards.
        $html .= '<tr><td>';
        $html .= '<table width="100%" cellpadding="0" cellspacing="0" border="0">';

        if ( $day_night && ( ! empty( $day_products ) || ! empty( $night_products ) ) ) {
            // Day group.
            if ( ! empty( $day_products ) ) {
                $html .= $render_lozenge( __( 'Day', 'product-finder' ) );
                $html .= $render_group( $day_products );
            }
            // Night group.
            if ( ! empty( $night_products ) ) {
                $html .= $render_lozenge( __( 'Night', 'product-finder' ) );
                $html .= $render_group( $night_products );
            }
        } else {
            $html .= $render_group( $products_data, $product_ids );
        }

        $html .= '</table>';
        $html .= '</td></tr>';

        // Bottom Shop Now button.
        if ( $results_url ) {
            $html .= '<tr><td style="text-align:center;padding:30px 0;">';
            $html .= '<a href="' . esc_url( $results_url ) . '" style="display:inline-block;background:' . esc_attr( $accent ) . ';color:#000000;text-decoration:none;padding:14px 36px;font-size:14px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;">';
            $html .= esc_html__( 'SHOP NOW', 'product-finder' );
            $html .= '</a>';
            $html .= '</td></tr>';
        }

        // Footer.
        $html .= '<tr><td style="text-align:center;padding:20px 0 30px;border-top:1px solid #e0e0e0;">';
        $html .= '<p style="margin:0;font-size:12px;color:#999;">' . __( 'This email was generated by Product Finder.', 'product-finder' ) . '</p>';
        $html .= '</td></tr>';

        // Close container.
        $html .= '</table></td></tr></table>';
        $html .= '</body></html>';

        return $html;
    }
}

new PF_Email();
