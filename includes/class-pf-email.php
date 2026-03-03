<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle email sending for Product Finder results.
 */
class PF_Email {

    public function __construct() {
        add_action( 'wp_ajax_pf_send_results_email', array( $this, 'send_results_email' ) );
        add_action( 'wp_ajax_nopriv_pf_send_results_email', array( $this, 'send_results_email' ) );
    }

    public function send_results_email() {
        check_ajax_referer( 'pf_frontend_nonce', 'nonce' );

        $email       = sanitize_email( $_POST['email'] ?? '' );
        $finder_id   = absint( $_POST['finder_id'] ?? 0 );
        $product_ids = array_map( 'absint', (array) ( $_POST['product_ids'] ?? array() ) );

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

        $finder_title = get_the_title( $finder_id );
        $subject      = sprintf( __( 'Your %s Results', 'product-finder' ), $finder_title );

        $body = $this->build_email_body( $finder_title, $product_ids, $products_data );

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

    private function build_email_body( $finder_title, $product_ids, $products_data = array() ) {
        $html = '<div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;">';
        $html .= '<h2 style="color:#333;">' . esc_html( $finder_title ) . ' &mdash; Your Results</h2>';
        $html .= '<p style="color:#666;">' . __( 'Based on your answers, here are your recommended products:', 'product-finder' ) . '</p>';
        $html .= '<table style="width:100%;border-collapse:collapse;">';

        // If we have variation-aware products_data, use it.
        if ( ! empty( $products_data ) ) {
            foreach ( $products_data as $p ) {
                $pid          = absint( $p['id'] ?? 0 );
                $variation_id = absint( $p['variation_id'] ?? 0 );

                // Use variation product if available.
                $display_id = $variation_id ?: $pid;
                $product    = wc_get_product( $display_id );
                if ( ! $product ) {
                    $product = wc_get_product( $pid );
                }
                if ( ! $product ) {
                    continue;
                }

                $image_url = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
                $permalink = $product->get_permalink();
                $name      = ! empty( $p['name'] ) ? $p['name'] : $product->get_name();
                $price     = $product->get_price_html();

                $html .= '<tr style="border-bottom:1px solid #eee;">';
                if ( $image_url ) {
                    $html .= '<td style="padding:12px;width:80px;"><img src="' . esc_url( $image_url ) . '" width="60" height="60" style="border-radius:4px;" alt="' . esc_attr( $name ) . '"></td>';
                }
                $html .= '<td style="padding:12px;"><a href="' . esc_url( $permalink ) . '" style="color:#0073aa;text-decoration:none;font-weight:bold;">' . esc_html( $name ) . '</a><br><span style="color:#666;">' . $price . '</span></td>';
                $html .= '</tr>';
            }
        } else {
            // Fallback: simple product IDs (Cosmeceuticals mode).
            foreach ( $product_ids as $pid ) {
                $product = wc_get_product( $pid );
                if ( ! $product ) {
                    continue;
                }

                $image_url = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
                $permalink = $product->get_permalink();
                $name      = $product->get_name();
                $price     = $product->get_price_html();

                $html .= '<tr style="border-bottom:1px solid #eee;">';
                if ( $image_url ) {
                    $html .= '<td style="padding:12px;width:80px;"><img src="' . esc_url( $image_url ) . '" width="60" height="60" style="border-radius:4px;" alt="' . esc_attr( $name ) . '"></td>';
                }
                $html .= '<td style="padding:12px;"><a href="' . esc_url( $permalink ) . '" style="color:#0073aa;text-decoration:none;font-weight:bold;">' . esc_html( $name ) . '</a><br><span style="color:#666;">' . $price . '</span></td>';
                $html .= '</tr>';
            }
        }

        $html .= '</table>';
        $html .= '<p style="margin-top:24px;color:#999;font-size:12px;">' . __( 'This email was generated by Product Finder.', 'product-finder' ) . '</p>';
        $html .= '</div>';

        return $html;
    }
}

new PF_Email();
