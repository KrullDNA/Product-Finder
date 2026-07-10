<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared "Integrations" settings page for Product Finder add-ons.
 *
 * Add-on plugins (Mailchimp, Klaviyo, …) register a tab via the
 * `pf_integrations` filter:
 *
 *     add_filter( 'pf_integrations', function ( $tabs ) {
 *         $tabs['mailchimp'] = array(
 *             'label'  => 'Mailchimp',
 *             'render' => $render_callback,   // echoes form fields
 *             'save'   => $save_callback,     // reads $_POST, persists options
 *         );
 *         return $tabs;
 *     } );
 *
 * The page only appears when at least one tab is registered. Nonce checks,
 * tab navigation, and the form wrapper are handled here; add-ons only
 * render fields and save their own option.
 */
class PF_Integrations {

    public function __construct() {
        // Priority 20 so it lands after the Submissions submenu.
        add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
    }

    public static function capability() {
        return apply_filters( 'pf_integrations_capability', 'manage_options' );
    }

    /**
     * @return array { slug => [ label, render, save ] }
     */
    public static function get_tabs() {
        $tabs  = apply_filters( 'pf_integrations', array() );
        $clean = array();
        foreach ( (array) $tabs as $slug => $tab ) {
            if ( ! empty( $tab['label'] ) && is_callable( $tab['render'] ?? null ) ) {
                $clean[ sanitize_key( $slug ) ] = $tab;
            }
        }
        return $clean;
    }

    public function register_menu() {
        if ( ! self::get_tabs() ) {
            return;
        }

        add_submenu_page(
            'edit.php?post_type=product_finder',
            __( 'Integrations', 'product-finder' ),
            __( 'Integrations', 'product-finder' ),
            self::capability(),
            'pf-integrations',
            array( $this, 'render_page' )
        );
    }

    public function render_page() {
        if ( ! current_user_can( self::capability() ) ) {
            wp_die( esc_html__( 'You do not have permission to manage integrations.', 'product-finder' ) );
        }

        $tabs = self::get_tabs();
        if ( ! $tabs ) {
            return;
        }

        $slugs  = array_keys( $tabs );
        $active = sanitize_key( $_GET['tab'] ?? '' );
        if ( ! isset( $tabs[ $active ] ) ) {
            $active = $slugs[0];
        }

        // Save the active tab's settings.
        if ( ! empty( $_POST['pf_integrations_save'] ) ) {
            check_admin_referer( 'pf_integrations_' . $active );
            if ( is_callable( $tabs[ $active ]['save'] ?? null ) ) {
                call_user_func( $tabs[ $active ]['save'] );
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'product-finder' ) . '</p></div>';
        }

        $base_url = admin_url( 'edit.php?post_type=product_finder&page=pf-integrations' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Product Finder Integrations', 'product-finder' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Connected services receive each submission (email, consent, quiz answers, recommended products) as it comes in.', 'product-finder' ); ?></p>

            <nav class="nav-tab-wrapper" style="margin-bottom:16px;">
                <?php foreach ( $tabs as $slug => $tab ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base_url ) ); ?>" class="nav-tab <?php echo $slug === $active ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $tab['label'] ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form method="post">
                <?php
                wp_nonce_field( 'pf_integrations_' . $active );
                echo '<input type="hidden" name="pf_integrations_save" value="1">';
                call_user_func( $tabs[ $active ]['render'] );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}

new PF_Integrations();
