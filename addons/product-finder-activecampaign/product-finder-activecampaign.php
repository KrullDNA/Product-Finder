<?php
/**
 * Plugin Name: Product Finder – ActiveCampaign
 * Plugin URI: https://github.com/KrullDNA/Product-Finder
 * Description: Syncs Product Finder submissions (email, consent) to ActiveCampaign and adds them to a list.
 * Version: 1.0.0
 * Author: KrullDNA
 * Author URI: https://github.com/KrullDNA
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pf-activecampaign
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: product-finder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PF_ActiveCampaign_Addon {

    const OPTION        = 'pf_activecampaign_settings';
    const OPTION_STATUS = 'pf_activecampaign_last_sync';
    const CRON_HOOK     = 'pf_activecampaign_sync_lead';

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
            esc_html__( 'Product Finder – ActiveCampaign requires the Product Finder plugin to be installed and active.', 'pf-activecampaign' ) .
            '</p></div>';
    }

    public static function get_settings() {
        return wp_parse_args( (array) get_option( self::OPTION, array() ), array(
            'api_url'      => '', // e.g. https://youraccount.api-us1.com
            'api_key'      => '',
            'list_id'      => '',
            'consent_only' => 1,
        ) );
    }

    /* ────────── Queue + sync ────────── */

    public function queue_sync( $lead_id, $data ) {
        $settings = self::get_settings();

        if ( ! $settings['api_url'] || ! $settings['api_key'] ) {
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

        // 1. Upsert the contact.
        $contact  = self::build_contact_request( $lead['email'], $settings );
        $response = wp_remote_post( $contact['url'], array(
            'headers' => $contact['headers'],
            'body'    => wp_json_encode( $contact['body'] ),
            'timeout' => 15,
        ) );

        $contact_id = $this->extract_contact_id( $response );
        if ( ! $contact_id ) {
            return; // extract_contact_id already recorded the error.
        }

        // 2. Add the contact to the configured list (optional).
        if ( ! $settings['list_id'] ) {
            self::record_status( true, sprintf( __( 'Synced %s (contact only)', 'pf-activecampaign' ), $lead['email'] ) );
            return;
        }

        $list     = self::build_list_request( $contact_id, $settings );
        $response = wp_remote_post( $list['url'], array(
            'headers' => $list['headers'],
            'body'    => wp_json_encode( $list['body'] ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            self::record_status( false, $response->get_error_message() );
            return;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code >= 200 && $code < 300 ) {
            self::record_status( true, sprintf( __( 'Synced and added %s to list', 'pf-activecampaign' ), $lead['email'] ) );
        } else {
            self::record_status( false, 'HTTP ' . $code . ' – ' . __( 'contact created but adding to list failed. Check the List ID.', 'pf-activecampaign' ) );
        }
    }

    /**
     * Pull the contact ID out of a contact/sync response; records an error
     * status and returns 0 on failure.
     */
    private function extract_contact_id( $response ) {
        if ( is_wp_error( $response ) ) {
            self::record_status( false, $response->get_error_message() );
            return 0;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 && ! empty( $body['contact']['id'] ) ) {
            return (int) $body['contact']['id'];
        }

        $detail = '';
        if ( is_array( $body ) ) {
            $detail = $body['message'] ?? ( $body['errors'][0]['title'] ?? '' );
        }
        self::record_status( false, 'HTTP ' . $code . ( $detail ? ' – ' . $detail : '' ) );
        return 0;
    }

    /**
     * Build the contact-sync (upsert) request. Pure, so it can be
     * unit-tested.
     *
     * Quiz data is not sent – ActiveCampaign custom fields require
     * pre-created field IDs. The full submission stays in
     * Product Finder → Submissions.
     *
     * @return array { url, headers, body }
     */
    public static function build_contact_request( $email, $settings ) {
        return array(
            'url'     => rtrim( $settings['api_url'], '/' ) . '/api/3/contact/sync',
            'headers' => self::headers( $settings ),
            'body'    => array(
                'contact' => array( 'email' => $email ),
            ),
        );
    }

    /**
     * Build the add-to-list request. Pure, so it can be unit-tested.
     *
     * @return array { url, headers, body }
     */
    public static function build_list_request( $contact_id, $settings ) {
        return array(
            'url'     => rtrim( $settings['api_url'], '/' ) . '/api/3/contactLists',
            'headers' => self::headers( $settings ),
            'body'    => array(
                'contactList' => array(
                    'list'    => (int) $settings['list_id'],
                    'contact' => (int) $contact_id,
                    'status'  => 1, // active/subscribed
                ),
            ),
        );
    }

    private static function headers( $settings ) {
        return array(
            'Api-Token'    => $settings['api_key'],
            'Content-Type' => 'application/json',
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
        $tabs['activecampaign'] = array(
            'label'  => __( 'ActiveCampaign', 'pf-activecampaign' ),
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
                <th scope="row"><label for="pf-ac-api-url"><?php esc_html_e( 'API URL', 'pf-activecampaign' ); ?></label></th>
                <td>
                    <input type="url" id="pf-ac-api-url" name="pf_activecampaign[api_url]" value="<?php echo esc_attr( $s['api_url'] ); ?>" class="regular-text" placeholder="https://youraccount.api-us1.com">
                    <p class="description"><?php esc_html_e( 'ActiveCampaign → Settings → Developer → API Access → URL.', 'pf-activecampaign' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="pf-ac-api-key"><?php esc_html_e( 'API Key', 'pf-activecampaign' ); ?></label></th>
                <td>
                    <input type="password" id="pf-ac-api-key" name="pf_activecampaign[api_key]" value="<?php echo esc_attr( $s['api_key'] ); ?>" class="regular-text" autocomplete="off">
                    <p class="description"><?php esc_html_e( 'ActiveCampaign → Settings → Developer → API Access → Key.', 'pf-activecampaign' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="pf-ac-list-id"><?php esc_html_e( 'List ID', 'pf-activecampaign' ); ?></label></th>
                <td>
                    <input type="text" id="pf-ac-list-id" name="pf_activecampaign[list_id]" value="<?php echo esc_attr( $s['list_id'] ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Optional. Lists → your list → the number in the URL. Without it, contacts are created but not added to a list.', 'pf-activecampaign' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Consent', 'pf-activecampaign' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="pf_activecampaign[consent_only]" value="1" <?php checked( $s['consent_only'], 1 ); ?>>
                        <?php esc_html_e( 'Only sync submissions where the marketing consent checkbox was ticked (recommended).', 'pf-activecampaign' ); ?>
                    </label>
                </td>
            </tr>
            <?php if ( is_array( $status ) && ! empty( $status['time'] ) ) : ?>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Last Sync', 'pf-activecampaign' ); ?></th>
                    <td>
                        <span style="color:<?php echo $status['ok'] ? '#00a32a' : '#b32d2e'; ?>;">
                            <?php echo $status['ok'] ? '&#10003;' : '&#10007;'; ?>
                            <?php echo esc_html( $status['message'] ); ?>
                        </span>
                        <span style="color:#646970;">(<?php echo esc_html( human_time_diff( $status['time'] ) ); ?> <?php esc_html_e( 'ago', 'pf-activecampaign' ); ?>)</span>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    public function save_settings() {
        $raw = (array) ( $_POST['pf_activecampaign'] ?? array() );
        update_option( self::OPTION, array(
            'api_url'      => esc_url_raw( $raw['api_url'] ?? '' ),
            'api_key'      => sanitize_text_field( $raw['api_key'] ?? '' ),
            'list_id'      => sanitize_text_field( $raw['list_id'] ?? '' ),
            'consent_only' => ! empty( $raw['consent_only'] ) ? 1 : 0,
        ), false );
    }
}

new PF_ActiveCampaign_Addon();
