<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Lead capture log for Product Finder.
 *
 * Stores every quiz completion that submits an email address (address,
 * consent flag, answers given, recommended products) in a custom table
 * and provides a Submissions admin screen with CSV export.
 */
class PF_Leads {

    /**
     * Bump when the table schema changes – triggers dbDelta on admin_init.
     */
    const DB_VERSION = '1';

    const PER_PAGE = 20;

    public function __construct() {
        add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_pf_export_leads', array( $this, 'export_csv' ) );
    }

    /**
     * Capability required to view/export/delete submissions. Personal data,
     * so default to admins only; filterable for shop-manager setups.
     */
    public static function capability() {
        return apply_filters( 'pf_leads_capability', 'manage_options' );
    }

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'pf_leads';
    }

    /* ────────── Schema ────────── */

    public static function maybe_install() {
        if ( get_option( 'pf_leads_db_version' ) !== self::DB_VERSION ) {
            self::install();
        }
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = self::table();
        $charset = $wpdb->get_charset_collate();

        dbDelta( "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            finder_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            email VARCHAR(190) NOT NULL DEFAULT '',
            consent TINYINT(1) NOT NULL DEFAULT 0,
            answers LONGTEXT NULL,
            products LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY finder_id (finder_id),
            KEY email (email)
        ) {$charset};" );

        update_option( 'pf_leads_db_version', self::DB_VERSION );
    }

    /* ────────── Recording ────────── */

    /**
     * Insert a lead. $answers and $products should already be arrays of
     * display-ready data (see resolve_answers()).
     *
     * @return int|false Inserted row ID or false.
     */
    public static function add_lead( $finder_id, $email, $consent, $answers, $products ) {
        global $wpdb;

        $inserted = $wpdb->insert(
            self::table(),
            array(
                'finder_id'  => absint( $finder_id ),
                'email'      => sanitize_email( $email ),
                'consent'    => $consent ? 1 : 0,
                'answers'    => wp_json_encode( $answers ),
                'products'   => wp_json_encode( $products ),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%d', '%s', '%s', '%s' )
        );

        if ( ! $inserted ) {
            return false;
        }

        $lead_id = (int) $wpdb->insert_id;

        /**
         * Fires after a lead has been stored. Integration add-ons (Mailchimp,
         * Klaviyo, …) hook this to sync the lead to external services.
         *
         * @param int   $lead_id Row ID in the leads table.
         * @param array $data    finder_id, email, consent (bool), answers, products.
         */
        do_action( 'pf_lead_recorded', $lead_id, array(
            'finder_id' => absint( $finder_id ),
            'email'     => sanitize_email( $email ),
            'consent'   => (bool) $consent,
            'answers'   => $answers,
            'products'  => $products,
        ) );

        return $lead_id;
    }

    /**
     * Fetch a single lead as an array with answers/products decoded.
     * Used by integration add-ons that sync in a background cron job.
     *
     * @return array|null
     */
    public static function get_lead( $lead_id ) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', absint( $lead_id ) ), // phpcs:ignore WordPress.DB.PreparedSQL
            ARRAY_A
        );
        if ( ! $row ) {
            return null;
        }

        $row['finder_id'] = (int) $row['finder_id'];
        $row['consent']   = (bool) $row['consent'];
        $row['answers']   = json_decode( (string) $row['answers'], true ) ?: array();
        $row['products']  = json_decode( (string) $row['products'], true ) ?: array();

        return $row;
    }

    /**
     * Convert raw answer indices into readable question/answer text using
     * the finder's stored questions.
     *
     * @param int   $finder_id        Finder post ID.
     * @param array $answers          { qi => [ai, …] }
     * @param array $followup_answers { "qi_ai" => [fai, …] }
     * @return array [ [ 'question' => str, 'answers' => [str, …] ], … ]
     */
    public static function resolve_answers( $finder_id, $answers, $followup_answers ) {
        $questions = get_post_meta( $finder_id, '_pf_questions', true );
        if ( ! is_array( $questions ) ) {
            return array();
        }

        $readable = array();

        foreach ( (array) $answers as $qi => $selected ) {
            $q = $questions[ $qi ] ?? null;
            if ( ! $q ) {
                continue;
            }
            $texts = array();
            foreach ( (array) $selected as $ai ) {
                $text = $q['answers'][ $ai ]['text'] ?? '';
                if ( '' !== $text ) {
                    $texts[] = $text;
                }
            }
            $readable[] = array(
                'question' => $q['text'] ?? '',
                'answers'  => $texts,
            );
        }

        foreach ( (array) $followup_answers as $key => $selected ) {
            $parts = explode( '_', (string) $key, 2 );
            if ( 2 !== count( $parts ) ) {
                continue;
            }
            $qi = absint( $parts[0] );
            $ai = absint( $parts[1] );
            $fu = $questions[ $qi ]['answers'][ $ai ]['follow_up'] ?? null;
            if ( empty( $fu['text'] ) ) {
                continue;
            }
            $texts = array();
            foreach ( (array) $selected as $fai ) {
                $text = $fu['answers'][ $fai ]['text'] ?? '';
                if ( '' !== $text ) {
                    $texts[] = $text;
                }
            }
            $readable[] = array(
                'question' => $fu['text'],
                'answers'  => $texts,
            );
        }

        return $readable;
    }

    /* ────────── Admin screen ────────── */

    public function register_menu() {
        add_submenu_page(
            'edit.php?post_type=product_finder',
            __( 'Submissions', 'product-finder' ),
            __( 'Submissions', 'product-finder' ),
            self::capability(),
            'pf-submissions',
            array( $this, 'render_page' )
        );
    }

    /**
     * Flatten a stored answers JSON blob (or already-decoded array) into
     * "Q: A, B | Q: C" text. Public so integration add-ons can reuse it.
     */
    public static function answers_to_text( $answers_json ) {
        $rows = is_array( $answers_json ) ? $answers_json : json_decode( (string) $answers_json, true );
        if ( ! is_array( $rows ) ) {
            return '';
        }
        $out = array();
        foreach ( $rows as $row ) {
            $q = $row['question'] ?? '';
            $a = implode( ', ', (array) ( $row['answers'] ?? array() ) );
            $out[] = $q . ': ' . $a;
        }
        return implode( ' | ', $out );
    }

    /**
     * Flatten a stored products JSON blob (or already-decoded array) into
     * "Name (category), …" text. Public so integration add-ons can reuse it.
     */
    public static function products_to_text( $products_json ) {
        $rows = is_array( $products_json ) ? $products_json : json_decode( (string) $products_json, true );
        if ( ! is_array( $rows ) ) {
            return '';
        }
        $out = array();
        foreach ( $rows as $row ) {
            $name = $row['name'] ?? '';
            $cat  = $row['category'] ?? '';
            $set  = $row['set'] ?? '';
            $meta = array_filter( array( $cat, $set ) );
            $out[] = $name . ( $meta ? ' (' . implode( ', ', $meta ) . ')' : '' );
        }
        return implode( ', ', $out );
    }

    public function render_page() {
        if ( ! current_user_can( self::capability() ) ) {
            wp_die( esc_html__( 'You do not have permission to view submissions.', 'product-finder' ) );
        }

        global $wpdb;
        $table = self::table();

        // Handle single-row delete.
        if ( isset( $_GET['pf_action'], $_GET['lead'] ) && 'delete' === $_GET['pf_action'] ) {
            $lead_id = absint( $_GET['lead'] );
            check_admin_referer( 'pf_delete_lead_' . $lead_id );
            $wpdb->delete( $table, array( 'id' => $lead_id ), array( '%d' ) );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Submission deleted.', 'product-finder' ) . '</p></div>';
        }

        $finder_filter = absint( $_GET['finder'] ?? 0 );
        $paged         = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $offset        = ( $paged - 1 ) * self::PER_PAGE;

        $where  = $finder_filter ? $wpdb->prepare( 'WHERE finder_id = %d', $finder_filter ) : '';
        $total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL
        $leads  = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} {$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL
            self::PER_PAGE,
            $offset
        ) );

        $finders = get_posts( array(
            'post_type'      => 'product_finder',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        $export_url = wp_nonce_url(
            add_query_arg(
                array(
                    'action' => 'pf_export_leads',
                    'finder' => $finder_filter,
                ),
                admin_url( 'admin-post.php' )
            ),
            'pf_export_leads'
        );

        $base_url = add_query_arg(
            array(
                'post_type' => 'product_finder',
                'page'      => 'pf-submissions',
                'finder'    => $finder_filter ?: false,
            ),
            admin_url( 'edit.php' )
        );

        $total_pages = max( 1, (int) ceil( $total / self::PER_PAGE ) );
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Product Finder Submissions', 'product-finder' ); ?></h1>
            <?php if ( $total ) : ?>
                <a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'product-finder' ); ?></a>
            <?php endif; ?>
            <hr class="wp-header-end">

            <form method="get" style="margin:12px 0;">
                <input type="hidden" name="post_type" value="product_finder">
                <input type="hidden" name="page" value="pf-submissions">
                <select name="finder">
                    <option value="0"><?php esc_html_e( 'All finders', 'product-finder' ); ?></option>
                    <?php foreach ( $finders as $finder ) : ?>
                        <option value="<?php echo esc_attr( $finder->ID ); ?>" <?php selected( $finder_filter, $finder->ID ); ?>>
                            <?php echo esc_html( $finder->post_title ?: __( '(no title)', 'product-finder' ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button"><?php esc_html_e( 'Filter', 'product-finder' ); ?></button>
                <span style="margin-left:8px;color:#646970;">
                    <?php
                    /* translators: %s: number of submissions */
                    printf( esc_html( _n( '%s submission', '%s submissions', $total, 'product-finder' ) ), esc_html( number_format_i18n( $total ) ) );
                    ?>
                </span>
            </form>

            <?php if ( ! $leads ) : ?>
                <p><?php esc_html_e( 'No submissions yet. When a visitor completes a finder and enters their email, it will appear here.', 'product-finder' ); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date', 'product-finder' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'product-finder' ); ?></th>
                            <th><?php esc_html_e( 'Consent', 'product-finder' ); ?></th>
                            <th><?php esc_html_e( 'Finder', 'product-finder' ); ?></th>
                            <th><?php esc_html_e( 'Answers', 'product-finder' ); ?></th>
                            <th><?php esc_html_e( 'Recommended Products', 'product-finder' ); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $leads as $lead ) : ?>
                            <?php
                            $answers_rows  = json_decode( (string) $lead->answers, true );
                            $products_text = self::products_to_text( $lead->products );
                            $delete_url    = wp_nonce_url(
                                add_query_arg(
                                    array(
                                        'pf_action' => 'delete',
                                        'lead'      => $lead->id,
                                    ),
                                    $base_url
                                ),
                                'pf_delete_lead_' . $lead->id
                            );
                            ?>
                            <tr>
                                <td style="white-space:nowrap;"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $lead->created_at ) ); ?></td>
                                <td><a href="mailto:<?php echo esc_attr( $lead->email ); ?>"><?php echo esc_html( $lead->email ); ?></a></td>
                                <td><?php echo $lead->consent ? '<span style="color:#00a32a;">&#10003; ' . esc_html__( 'Yes', 'product-finder' ) . '</span>' : esc_html__( 'No', 'product-finder' ); ?></td>
                                <td><?php echo esc_html( get_the_title( $lead->finder_id ) ?: '#' . $lead->finder_id ); ?></td>
                                <td>
                                    <?php if ( is_array( $answers_rows ) && $answers_rows ) : ?>
                                        <details>
                                            <summary style="cursor:pointer;">
                                                <?php
                                                /* translators: %s: number of answers */
                                                printf( esc_html( _n( '%s answer', '%s answers', count( $answers_rows ), 'product-finder' ) ), esc_html( number_format_i18n( count( $answers_rows ) ) ) );
                                                ?>
                                            </summary>
                                            <ul style="margin:8px 0 0;">
                                                <?php foreach ( $answers_rows as $row ) : ?>
                                                    <li><strong><?php echo esc_html( $row['question'] ?? '' ); ?></strong><br><?php echo esc_html( implode( ', ', (array) ( $row['answers'] ?? array() ) ) ); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </details>
                                    <?php else : ?>
                                        &mdash;
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $products_text ? esc_html( $products_text ) : '&mdash;'; ?></td>
                                <td>
                                    <a href="<?php echo esc_url( $delete_url ); ?>" style="color:#b32d2e;" onclick="return confirm('<?php echo esc_js( __( 'Delete this submission?', 'product-finder' ) ); ?>');"><?php esc_html_e( 'Delete', 'product-finder' ); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ( $total_pages > 1 ) : ?>
                    <div class="tablenav bottom"><div class="tablenav-pages">
                        <?php
                        echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput
                            'base'    => add_query_arg( 'paged', '%#%', $base_url ),
                            'format'  => '',
                            'current' => $paged,
                            'total'   => $total_pages,
                        ) );
                        ?>
                    </div></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ────────── CSV export ────────── */

    public function export_csv() {
        if ( ! current_user_can( self::capability() ) ) {
            wp_die( esc_html__( 'You do not have permission to export submissions.', 'product-finder' ) );
        }
        check_admin_referer( 'pf_export_leads' );

        global $wpdb;
        $table         = self::table();
        $finder_filter = absint( $_GET['finder'] ?? 0 );
        $where         = $finder_filter ? $wpdb->prepare( 'WHERE finder_id = %d', $finder_filter ) : '';
        $leads         = $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY created_at DESC, id DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL

        $filename = 'product-finder-submissions-' . gmdate( 'Y-m-d' ) . '.csv';

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'ID', 'Date', 'Finder', 'Email', 'Consent', 'Answers', 'Recommended Products' ) );

        foreach ( $leads as $lead ) {
            fputcsv( $out, array(
                $lead->id,
                $lead->created_at,
                get_the_title( $lead->finder_id ) ?: '#' . $lead->finder_id,
                $lead->email,
                $lead->consent ? 'yes' : 'no',
                self::answers_to_text( $lead->answers ),
                self::products_to_text( $lead->products ),
            ) );
        }

        fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        exit;
    }
}

new PF_Leads();
