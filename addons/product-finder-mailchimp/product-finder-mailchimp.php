<?php
/**
 * Plugin Name: Product Finder – Mailchimp
 * Plugin URI: https://github.com/KrullDNA/Product-Finder
 * Description: Syncs Product Finder submissions (email, consent, quiz answers, recommended products) to a Mailchimp audience.
 * Version: 1.0.0
 * Author: KrullDNA
 * Author URI: https://github.com/KrullDNA
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pf-mailchimp
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: product-finder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PF_Mailchimp_Addon {

    const OPTION        = 'pf_mailchimp_settings';
    const OPTION_STATUS = 'pf_mailchimp_last_sync';
    const CRON_HOOK     = 'pf_mailchimp_sync_lead';

    public function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
    }

    public function init() {
        if ( ! class_exists( 'PF_Leads' ) ) {
            add_action( 'admin_notices', array( $this, 'missing_core_notice' ) );
            return;
        }

        add_filter( 'pf_integrations', array( $this, 'register_tab' ) );
        add_action( 'pf_lead_recorded', array( $this, 'queue_sync' ), 10, 2 );
        add_action( self::CRON_HOOK, array( $this, 'sync_lead' ) );
    }

    public function missing_core_notice() {
        echo '<div class="notice notice-error"><p>' .
            esc_html__( 'Product Finder – Mailchimp requires the Product Finder plugin to be installed and active.', 'pf-mailchimp' ) .
            '</p></div>';
    }

    public static function get_settings() {
        return wp_parse_args( (array) get_option( self::OPTION, array() ), array(
            'api_key'      => '',
            'audience_id'  => '',
            'double_optin' => 0,
            'consent_only' => 1,
            'tag'          => 'Product Finder',
        ) );
    }

    /* ────────── Queue + sync ────────── */

    /**
     * Schedule a background sync so the visitor's request isn't held up by
     * the Mailchimp API.
     */
    public function queue_sync( $lead_id, $data ) {
        $settings = self::get_settings();

        if ( ! $settings['api_key'] || ! $settings['audience_id'] ) {
            return;
        }
        if ( $settings['consent_only'] && empty( $data['consent'] ) ) {
            return;
        }

        wp_schedule_single_event( time(), self::CRON_HOOK, array( (int) $lead_id ) );
    }

    public function sync_lead( $lead_id ) {
        $settings = self::get_settings();
        $lead     = PF_Leads::get_lead( $lead_id );

        if ( ! $lead || ! is_email( $lead['email'] ) ) {
            return;
        }

        $request = self::build_member_request( $lead['email'], $settings );
        if ( ! $request ) {
            self::record_status( false, __( 'Invalid API key format (expected e.g. abc123-us21).', 'pf-mailchimp' ) );
            return;
        }

        $response = wp_remote_request( $request['url'], array(
            'method'  => $request['method'],
            'headers' => $request['headers'],
            'body'    => wp_json_encode( $request['body'] ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            self::record_status( false, $response->get_error_message() );
            return;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code >= 200 && $code < 300 ) {
            self::record_status( true, sprintf( __( 'Synced %s', 'pf-mailchimp' ), $lead['email'] ) );
        } else {
            $body   = json_decode( wp_remote_retrieve_body( $response ), true );
            $detail = is_array( $body ) ? ( $body['detail'] ?? $body['title'] ?? '' ) : '';
            self::record_status( false, 'HTTP ' . $code . ( $detail ? ' – ' . $detail : '' ) );
        }
    }

    /**
     * Build the member-upsert request. Pure, so it can be unit-tested.
     * Returns null if the API key has no datacentre suffix.
     *
     * Quiz answers/products are NOT sent – Mailchimp merge fields must be
     * pre-created per audience, so unknown fields would error. The full
     * submission stays in Product Finder → Submissions; the tag is enough
     * to segment finder leads in Mailchimp.
     *
     * @return array|null { url, method, headers, body }
     */
    public static function build_member_request( $email, $settings ) {
        $dash = strrpos( $settings['api_key'], '-' );
        if ( false === $dash ) {
            return null;
        }
        $dc = substr( $settings['api_key'], $dash + 1 );
        if ( '' === $dc ) {
            return null;
        }

        $email = strtolower( trim( $email ) );

        $body = array(
            'email_address' => $email,
            'status_if_new' => $settings['double_optin'] ? 'pending' : 'subscribed',
            'merge_fields'  => (object) array(),
        );
        if ( ! empty( $settings['tag'] ) ) {
            $body['tags'] = array( $settings['tag'] );
        }

        return array(
            'url'     => 'https://' . $dc . '.api.mailchimp.com/3.0/lists/' . rawurlencode( $settings['audience_id'] ) . '/members/' . md5( $email ),
            'method'  => 'PUT',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( 'pf:' . $settings['api_key'] ),
                'Content-Type'  => 'application/json',
            ),
            'body'    => $body,
        );
    }

    private static function record_status( $ok, $message ) {
        update_option( self::OPTION_STATUS, array(
            'time'    => time(),
            'ok'      => $ok ? 1 : 0,
            'message' => $message,
        ), false );
    }

    /* ────────── Settings tab ────────── */

    public function register_tab( $tabs ) {
        $tabs['mailchimp'] = array(
            'label'  => __( 'Mailchimp', 'pf-mailchimp' ),
            'render' => array( $this, 'render_settings' ),
            'save'   => array( $this, 'save_settings' ),
        );
        return $tabs;
    }

    public function render_settings() {
        $s      = self::get_settings();
        $status = get_option( self::OPTION_STATUS );
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="pf-mc-api-key"><?php esc_html_e( 'API Key', 'pf-mailchimp' ); ?></label></th>
                <td>
                    <input type="password" id="pf-mc-api-key" name="pf_mailchimp[api_key]" value="<?php echo esc_attr( $s['api_key'] ); ?>" class="regular-text" autocomplete="off">
                    <p class="description"><?php esc_html_e( 'Mailchimp → Account → Extras → API keys. Includes the datacentre suffix, e.g. …-us21.', 'pf-mailchimp' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="pf-mc-audience"><?php esc_html_e( 'Audience ID', 'pf-mailchimp' ); ?></label></th>
                <td>
                    <input type="text" id="pf-mc-audience" name="pf_mailchimp[audience_id]" value="<?php echo esc_attr( $s['audience_id'] ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Audience → Settings → Audience name and defaults → Audience ID.', 'pf-mailchimp' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="pf-mc-tag"><?php esc_html_e( 'Tag', 'pf-mailchimp' ); ?></label></th>
                <td>
                    <input type="text" id="pf-mc-tag" name="pf_mailchimp[tag]" value="<?php echo esc_attr( $s['tag'] ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Applied to every synced contact so you can segment them. Leave blank for no tag.', 'pf-mailchimp' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Double Opt-in', 'pf-mailchimp' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="pf_mailchimp[double_optin]" value="1" <?php checked( $s['double_optin'], 1 ); ?>>
                        <?php esc_html_e( 'New contacts must confirm via Mailchimp\'s opt-in email before being subscribed.', 'pf-mailchimp' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Consent', 'pf-mailchimp' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="pf_mailchimp[consent_only]" value="1" <?php checked( $s['consent_only'], 1 ); ?>>
                        <?php esc_html_e( 'Only sync submissions where the marketing consent checkbox was ticked (recommended).', 'pf-mailchimp' ); ?>
                    </label>
                </td>
            </tr>
            <?php if ( is_array( $status ) && ! empty( $status['time'] ) ) : ?>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Last Sync', 'pf-mailchimp' ); ?></th>
                    <td>
                        <span style="color:<?php echo $status['ok'] ? '#00a32a' : '#b32d2e'; ?>;">
                            <?php echo $status['ok'] ? '&#10003;' : '&#10007;'; ?>
                            <?php echo esc_html( $status['message'] ); ?>
                        </span>
                        <span style="color:#646970;">(<?php echo esc_html( human_time_diff( $status['time'] ) ); ?> <?php esc_html_e( 'ago', 'pf-mailchimp' ); ?>)</span>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    public function save_settings() {
        $raw = (array) ( $_POST['pf_mailchimp'] ?? array() );
        update_option( self::OPTION, array(
            'api_key'      => sanitize_text_field( $raw['api_key'] ?? '' ),
            'audience_id'  => sanitize_text_field( $raw['audience_id'] ?? '' ),
            'double_optin' => ! empty( $raw['double_optin'] ) ? 1 : 0,
            'consent_only' => ! empty( $raw['consent_only'] ) ? 1 : 0,
            'tag'          => sanitize_text_field( $raw['tag'] ?? '' ),
        ), false );
    }
}

new PF_Mailchimp_Addon();
