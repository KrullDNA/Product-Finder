<?php
/**
 * Plugin Name: Product Finder – Klaviyo
 * Plugin URI: https://github.com/KrullDNA/Product-Finder
 * Description: Syncs Product Finder submissions (email, consent, quiz answers, recommended products) to Klaviyo and subscribes them to a list.
 * Version: 1.0.0
 * Author: KrullDNA
 * Author URI: https://github.com/KrullDNA
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pf-klaviyo
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: product-finder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PF_Klaviyo_Addon {

    const OPTION        = 'pf_klaviyo_settings';
    const OPTION_STATUS = 'pf_klaviyo_last_sync';
    const CRON_HOOK     = 'pf_klaviyo_sync_lead';

    /**
     * Klaviyo API revision (their APIs are date-versioned).
     */
    const API_REVISION = '2024-10-15';

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
            esc_html__( 'Product Finder – Klaviyo requires the Product Finder plugin to be installed and active.', 'pf-klaviyo' ) .
            '</p></div>';
    }

    public static function get_settings() {
        return wp_parse_args( (array) get_option( self::OPTION, array() ), array(
            'api_key'      => '', // Private key (pk_…)
            'list_id'      => '',
            'consent_only' => 1,
        ) );
    }

    /* ────────── Queue + sync ────────── */

    public function queue_sync( $lead_id, $data ) {
        $settings = self::get_settings();

        if ( ! $settings['api_key'] ) {
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

        // 1. Upsert the profile with the quiz data as custom properties.
        $profile = self::build_profile_request(
            $lead['email'],
            get_the_title( $lead['finder_id'] ),
            PF_Leads::answers_to_text( $lead['answers'] ),
            PF_Leads::products_to_text( $lead['products'] ),
            $settings
        );

        $response = wp_remote_post( $profile['url'], array(
            'headers' => $profile['headers'],
            'body'    => wp_json_encode( $profile['body'] ),
            'timeout' => 15,
        ) );

        if ( ! $this->response_ok( $response, $lead['email'] ) ) {
            return;
        }

        // 2. Subscribe to the configured list (skipped when no list is set,
        //    or when the visitor didn't give marketing consent).
        if ( ! $settings['list_id'] || ! $lead['consent'] ) {
            self::record_status( true, sprintf( __( 'Synced %s (profile only)', 'pf-klaviyo' ), $lead['email'] ) );
            return;
        }

        $subscribe = self::build_subscribe_request( $lead['email'], $settings );

        $response = wp_remote_post( $subscribe['url'], array(
            'headers' => $subscribe['headers'],
            'body'    => wp_json_encode( $subscribe['body'] ),
            'timeout' => 15,
        ) );

        if ( $this->response_ok( $response, $lead['email'] ) ) {
            self::record_status( true, sprintf( __( 'Synced and subscribed %s', 'pf-klaviyo' ), $lead['email'] ) );
        }
    }

    /**
     * Check a Klaviyo response; records an error status and returns false
     * on failure.
     */
    private function response_ok( $response, $email ) {
        if ( is_wp_error( $response ) ) {
            self::record_status( false, $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code >= 200 && $code < 300 ) {
            return true;
        }

        $body   = json_decode( wp_remote_retrieve_body( $response ), true );
        $detail = '';
        if ( is_array( $body ) && ! empty( $body['errors'][0] ) ) {
            $detail = $body['errors'][0]['detail'] ?? $body['errors'][0]['title'] ?? '';
        }
        self::record_status( false, 'HTTP ' . $code . ( $detail ? ' – ' . $detail : '' ) );
        return false;
    }

    /**
     * Build the profile-import (upsert) request. Pure, so it can be
     * unit-tested. Quiz data goes into custom profile properties.
     *
     * @return array { url, headers, body }
     */
    public static function build_profile_request( $email, $finder_title, $answers_text, $products_text, $settings ) {
        return array(
            'url'     => 'https://a.klaviyo.com/api/profile-import/',
            'headers' => self::headers( $settings ),
            'body'    => array(
                'data' => array(
                    'type'       => 'profile',
                    'attributes' => array(
                        'email'      => $email,
                        'properties' => array(
                            'Product Finder'          => $finder_title,
                            'Product Finder Answers'  => $answers_text,
                            'Product Finder Products' => $products_text,
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * Build the list-subscribe request (bulk subscribe job with a single
     * profile). Pure, so it can be unit-tested.
     *
     * @return array { url, headers, body }
     */
    public static function build_subscribe_request( $email, $settings ) {
        return array(
            'url'     => 'https://a.klaviyo.com/api/profile-subscription-bulk-create-jobs/',
            'headers' => self::headers( $settings ),
            'body'    => array(
                'data' => array(
                    'type'       => 'profile-subscription-bulk-create-job',
                    'attributes' => array(
                        'profiles' => array(
                            'data' => array(
                                array(
                                    'type'       => 'profile',
                                    'attributes' => array(
                                        'email'         => $email,
                                        'subscriptions' => array(
                                            'email' => array(
                                                'marketing' => array( 'consent' => 'SUBSCRIBED' ),
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                    'relationships' => array(
                        'list' => array(
                            'data' => array(
                                'type' => 'list',
                                'id'   => $settings['list_id'],
                            ),
                        ),
                    ),
                ),
            ),
        );
    }

    private static function headers( $settings ) {
        return array(
            'Authorization' => 'Klaviyo-API-Key ' . $settings['api_key'],
            'Content-Type'  => 'application/json',
            'revision'      => self::API_REVISION,
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
        $tabs['klaviyo'] = array(
            'label'  => __( 'Klaviyo', 'pf-klaviyo' ),
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
                <th scope="row"><label for="pf-kl-api-key"><?php esc_html_e( 'Private API Key', 'pf-klaviyo' ); ?></label></th>
                <td>
                    <input type="password" id="pf-kl-api-key" name="pf_klaviyo[api_key]" value="<?php echo esc_attr( $s['api_key'] ); ?>" class="regular-text" autocomplete="off">
                    <p class="description"><?php esc_html_e( 'Klaviyo → Settings → API keys → Create Private API Key (starts with pk_). Needs Profiles and Subscriptions write access.', 'pf-klaviyo' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="pf-kl-list-id"><?php esc_html_e( 'List ID', 'pf-klaviyo' ); ?></label></th>
                <td>
                    <input type="text" id="pf-kl-list-id" name="pf_klaviyo[list_id]" value="<?php echo esc_attr( $s['list_id'] ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Optional. Lists & Segments → your list → the ID is in the URL. Consented leads are subscribed to this list; without it (or without consent) only the profile is created.', 'pf-klaviyo' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Consent', 'pf-klaviyo' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="pf_klaviyo[consent_only]" value="1" <?php checked( $s['consent_only'], 1 ); ?>>
                        <?php esc_html_e( 'Only sync submissions where the marketing consent checkbox was ticked (recommended). Regardless of this setting, leads are never subscribed to the list without consent.', 'pf-klaviyo' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Quiz Data', 'pf-klaviyo' ); ?></th>
                <td>
                    <p class="description"><?php esc_html_e( 'The finder name, answers and recommended products are stored as the profile properties "Product Finder", "Product Finder Answers" and "Product Finder Products" – usable in segments and flows.', 'pf-klaviyo' ); ?></p>
                </td>
            </tr>
            <?php if ( is_array( $status ) && ! empty( $status['time'] ) ) : ?>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Last Sync', 'pf-klaviyo' ); ?></th>
                    <td>
                        <span style="color:<?php echo $status['ok'] ? '#00a32a' : '#b32d2e'; ?>;">
                            <?php echo $status['ok'] ? '&#10003;' : '&#10007;'; ?>
                            <?php echo esc_html( $status['message'] ); ?>
                        </span>
                        <span style="color:#646970;">(<?php echo esc_html( human_time_diff( $status['time'] ) ); ?> <?php esc_html_e( 'ago', 'pf-klaviyo' ); ?>)</span>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    public function save_settings() {
        $raw = (array) ( $_POST['pf_klaviyo'] ?? array() );
        update_option( self::OPTION, array(
            'api_key'      => sanitize_text_field( $raw['api_key'] ?? '' ),
            'list_id'      => sanitize_text_field( $raw['list_id'] ?? '' ),
            'consent_only' => ! empty( $raw['consent_only'] ) ? 1 : 0,
        ), false );
    }
}

new PF_Klaviyo_Addon();
