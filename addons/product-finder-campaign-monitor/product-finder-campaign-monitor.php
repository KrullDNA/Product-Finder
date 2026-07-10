<?php
/**
 * Plugin Name: Product Finder – Campaign Monitor
 * Plugin URI: https://github.com/KrullDNA/Product-Finder
 * Description: Syncs Product Finder submissions (email, consent, quiz answers, recommended products) to a Campaign Monitor list.
 * Version: 1.0.0
 * Author: KrullDNA
 * Author URI: https://github.com/KrullDNA
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pf-campaign-monitor
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: product-finder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PF_Campaign_Monitor_Addon {

    const OPTION        = 'pf_campaign_monitor_settings';
    const OPTION_STATUS = 'pf_campaign_monitor_last_sync';
    const CRON_HOOK     = 'pf_campaign_monitor_sync_lead';

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
            esc_html__( 'Product Finder – Campaign Monitor requires the Product Finder plugin to be installed and active.', 'pf-campaign-monitor' ) .
            '</p></div>';
    }

    public static function get_settings() {
        return wp_parse_args( (array) get_option( self::OPTION, array() ), array(
            'api_key'      => '',
            'list_id'      => '',
            'consent_only' => 1,
        ) );
    }

    /* ────────── Queue + sync ────────── */

    public function queue_sync( $lead_id, $data ) {
        $settings = self::get_settings();

        if ( ! $settings['api_key'] || ! $settings['list_id'] ) {
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

        $request = self::build_subscriber_request(
            $lead['email'],
            $lead['consent'],
            get_the_title( $lead['finder_id'] ),
            PF_Leads::answers_to_text( $lead['answers'] ),
            PF_Leads::products_to_text( $lead['products'] ),
            $settings
        );

        $response = wp_remote_post( $request['url'], array(
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
            self::record_status( true, sprintf( __( 'Synced %s', 'pf-campaign-monitor' ), $lead['email'] ) );
        } else {
            $body   = json_decode( wp_remote_retrieve_body( $response ), true );
            $detail = is_array( $body ) ? ( $body['Message'] ?? '' ) : '';
            self::record_status( false, 'HTTP ' . $code . ( $detail ? ' – ' . $detail : '' ) );
        }
    }

    /**
     * Build the add-subscriber request. Pure, so it can be unit-tested.
     *
     * Quiz data is sent as the custom fields ProductFinder,
     * ProductFinderAnswers and ProductFinderProducts – Campaign Monitor
     * silently ignores custom fields that don't exist on the list, so
     * create them there to capture the data.
     *
     * @return array { url, headers, body }
     */
    public static function build_subscriber_request( $email, $consent, $finder_title, $answers_text, $products_text, $settings ) {
        return array(
            'url'     => 'https://api.createsend.com/api/v3.3/subscribers/' . rawurlencode( $settings['list_id'] ) . '.json',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $settings['api_key'] . ':x' ),
                'Content-Type'  => 'application/json',
            ),
            'body'    => array(
                'EmailAddress'   => $email,
                'Name'           => '',
                'Resubscribe'    => true,
                'ConsentToTrack' => $consent ? 'Yes' : 'Unchanged',
                'CustomFields'   => array(
                    array( 'Key' => 'ProductFinder',         'Value' => $finder_title ),
                    array( 'Key' => 'ProductFinderAnswers',  'Value' => mb_substr( $answers_text, 0, 250 ) ),
                    array( 'Key' => 'ProductFinderProducts', 'Value' => mb_substr( $products_text, 0, 250 ) ),
                ),
            ),
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
        $tabs['campaign-monitor'] = array(
            'label'  => __( 'Campaign Monitor', 'pf-campaign-monitor' ),
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
                <th scope="row"><label for="pf-cm-api-key"><?php esc_html_e( 'API Key', 'pf-campaign-monitor' ); ?></label></th>
                <td>
                    <input type="password" id="pf-cm-api-key" name="pf_campaign_monitor[api_key]" value="<?php echo esc_attr( $s['api_key'] ); ?>" class="regular-text" autocomplete="off">
                    <p class="description"><?php esc_html_e( 'Campaign Monitor → Account Settings → API keys.', 'pf-campaign-monitor' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="pf-cm-list-id"><?php esc_html_e( 'List ID', 'pf-campaign-monitor' ); ?></label></th>
                <td>
                    <input type="text" id="pf-cm-list-id" name="pf_campaign_monitor[list_id]" value="<?php echo esc_attr( $s['list_id'] ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Lists & subscribers → your list → Settings → API Subscriber List ID.', 'pf-campaign-monitor' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Consent', 'pf-campaign-monitor' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="pf_campaign_monitor[consent_only]" value="1" <?php checked( $s['consent_only'], 1 ); ?>>
                        <?php esc_html_e( 'Only sync submissions where the marketing consent checkbox was ticked (recommended).', 'pf-campaign-monitor' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Quiz Data', 'pf-campaign-monitor' ); ?></th>
                <td>
                    <p class="description">
                        <?php esc_html_e( 'To capture quiz data on the subscriber, create these text custom fields on the list: ProductFinder, ProductFinderAnswers, ProductFinderProducts. Fields that don\'t exist are silently ignored.', 'pf-campaign-monitor' ); ?>
                    </p>
                </td>
            </tr>
            <?php if ( is_array( $status ) && ! empty( $status['time'] ) ) : ?>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Last Sync', 'pf-campaign-monitor' ); ?></th>
                    <td>
                        <span style="color:<?php echo $status['ok'] ? '#00a32a' : '#b32d2e'; ?>;">
                            <?php echo $status['ok'] ? '&#10003;' : '&#10007;'; ?>
                            <?php echo esc_html( $status['message'] ); ?>
                        </span>
                        <span style="color:#646970;">(<?php echo esc_html( human_time_diff( $status['time'] ) ); ?> <?php esc_html_e( 'ago', 'pf-campaign-monitor' ); ?>)</span>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    public function save_settings() {
        $raw = (array) ( $_POST['pf_campaign_monitor'] ?? array() );
        update_option( self::OPTION, array(
            'api_key'      => sanitize_text_field( $raw['api_key'] ?? '' ),
            'list_id'      => sanitize_text_field( $raw['list_id'] ?? '' ),
            'consent_only' => ! empty( $raw['consent_only'] ) ? 1 : 0,
        ), false );
    }
}

new PF_Campaign_Monitor_Addon();
