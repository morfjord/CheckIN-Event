<?php
/**
 * Plugin Name: CheckIN Events
 * Description: Displays CheckIN events as a custom post type. Add events by ID or import all events from your CheckIN customer account.
 * Version: 0.0.6
 * Author: Norsk Interaktiv AS, Martin Morfjord
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CHECKIN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// ─── Post Type ───────────────────────────────────────────────────────────────

add_action( 'init', function () {
    register_post_type( 'checkin_event', [
        'labels' => [
            'name'               => 'CheckIN Events',
            'singular_name'      => 'CheckIN Event',
            'add_new'            => 'Legg til nytt',
            'add_new_item'       => 'Legg til nytt CheckIN Event',
            'edit_item'          => 'Rediger CheckIN Event',
            'new_item'           => 'Nytt CheckIN Event',
            'view_item'          => 'Vis CheckIN Event',
            'search_items'       => 'Søk etter CheckIN Events',
            'not_found'          => 'Ingen events funnet',
            'not_found_in_trash' => 'Ingen events i papirkurven',
            'menu_name'          => 'CheckIN Events',
        ],
        'public'            => true,
        'has_archive'       => true,
        'show_in_menu'      => 'checkin-events',
        'menu_icon'         => 'dashicons-calendar-alt',
        'supports'          => [ 'title', 'editor', 'thumbnail' ],
        'rewrite'           => [ 'slug' => 'checkin-events' ],
        'show_in_rest'      => true,
    ] );
} );

// Disable block editor for this post type so edit_form_top works above the title
add_filter( 'use_block_editor_for_post_type', function ( $use, $post_type ) {
    if ( $post_type === 'checkin_event' ) {
        return false;
    }
    return $use;
}, 10, 2 );

// ─── Admin Menu ──────────────────────────────────────────────────────────────

add_action( 'admin_menu', function () {
    add_menu_page(
        'CheckIN Events',
        'CheckIN Events',
        'manage_options',
        'checkin-events',
        'checkin_page_events_list',
        'dashicons-calendar-alt',
        25
    );

    add_submenu_page(
        'checkin-events',
        'Alle Events',
        'Alle Events',
        'manage_options',
        'checkin-events',
        'checkin_page_events_list'
    );

    add_submenu_page(
        'checkin-events',
        'Legg til Event',
        'Legg til Event',
        'manage_options',
        'post-new.php?post_type=checkin_event'
    );

    add_submenu_page(
        'checkin-events',
        'Importer fra CheckIN',
        'Importer fra CheckIN',
        'manage_options',
        'checkin-import',
        'checkin_page_import'
    );

    add_submenu_page(
        'checkin-events',
        'Innstillinger',
        'Innstillinger',
        'manage_options',
        'checkin-settings',
        'checkin_page_settings'
    );
} );

// ─── Settings Page ────────────────────────────────────────────────────────────

function checkin_page_settings() {
    if ( isset( $_POST['checkin_settings_nonce'] ) && wp_verify_nonce( $_POST['checkin_settings_nonce'], 'checkin_save_settings' ) ) {
        update_option( 'checkin_customer_number', sanitize_text_field( $_POST['checkin_customer_number'] ?? '' ) );
        update_option( 'checkin_api_key', sanitize_text_field( $_POST['checkin_api_key'] ?? '' ) );
        update_option( 'checkin_api_base_url', esc_url_raw( $_POST['checkin_api_base_url'] ?? '' ) );
        echo '<div class="notice notice-success"><p>Innstillinger lagret.</p></div>';
    }

    $customer_number = get_option( 'checkin_customer_number', '' );
    $api_key         = get_option( 'checkin_api_key', '' );
    $api_base_url    = get_option( 'checkin_api_base_url', 'https://api.checkin.no/v1' );
    ?>
    <div class="wrap">
        <h1>CheckIN Innstillinger</h1>
        <form method="post">
            <?php wp_nonce_field( 'checkin_save_settings', 'checkin_settings_nonce' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="checkin_customer_number">Kundenummer</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="checkin_customer_number"
                            name="checkin_customer_number"
                            value="<?php echo esc_attr( $customer_number ); ?>"
                            placeholder="f.eks. 113448"
                            class="regular-text"
                        >
                        <p class="description">Ditt CheckIN kundenummer / organizer ID.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="checkin_api_key">API-nøkkel</label>
                    </th>
                    <td>
                        <input
                            type="password"
                            id="checkin_api_key"
                            name="checkin_api_key"
                            value="<?php echo esc_attr( $api_key ); ?>"
                            class="regular-text"
                        >
                        <p class="description">API-nøkkel fra CheckIN (hvis påkrevd).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="checkin_api_base_url">API Base URL</label>
                    </th>
                    <td>
                        <input
                            type="url"
                            id="checkin_api_base_url"
                            name="checkin_api_base_url"
                            value="<?php echo esc_attr( $api_base_url ); ?>"
                            class="regular-text"
                        >
                        <p class="description">Standard: <code>https://api.checkin.no/v1</code></p>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Lagre innstillinger' ); ?>
        </form>
    </div>
    <?php
}

// ─── Events List Page ─────────────────────────────────────────────────────────

function checkin_page_events_list() {
    $posts = get_posts( [
        'post_type'      => 'checkin_event',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ] );
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">CheckIN Events</h1>
        <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=checkin_event' ) ); ?>" class="page-title-action">Legg til nytt</a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=checkin-import' ) ); ?>" class="page-title-action">Importer fra CheckIN</a>
        <hr class="wp-header-end">

        <?php if ( empty( $posts ) ) : ?>
            <p>Ingen events er lagt til ennå. <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=checkin_event' ) ); ?>">Legg til et event</a> eller <a href="<?php echo esc_url( admin_url( 'admin.php?page=checkin-import' ) ); ?>">importer fra CheckIN</a>.</p>
        <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Tittel</th>
                    <th>CheckIN Event ID</th>
                    <th>Status</th>
                    <th>Shortcode</th>
                    <th>Handlinger</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $posts as $post ) :
                    $event_id = get_post_meta( $post->ID, '_checkin_event_id', true );
                ?>
                <tr>
                    <td><strong><?php echo esc_html( $post->post_title ); ?></strong></td>
                    <td><?php echo $event_id ? '<code>' . esc_html( $event_id ) . '</code>' : '<em style="color:#999">Ikke satt</em>'; ?></td>
                    <td><?php echo esc_html( get_post_status_object( $post->post_status )->label ); ?></td>
                    <td><?php if ( $event_id ) echo '<code>[checkin_event id="' . esc_html( $event_id ) . '"]</code>'; ?></td>
                    <td>
                        <a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">Rediger</a> |
                        <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" target="_blank">Vis</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
}

// ─── Import Page ──────────────────────────────────────────────────────────────

function checkin_fetch_single_event( $event_id ) {
    $api_key      = get_option( 'checkin_api_key', '' );
    $api_base_url = rtrim( get_option( 'checkin_api_base_url', 'https://api.checkin.no/v1' ), '/' );

    $transient_key = 'checkin_single_event_' . $event_id;
    $cached        = get_transient( $transient_key );
    if ( $cached !== false ) {
        return $cached;
    }

    $url  = $api_base_url . '/events/' . urlencode( $event_id );
    $args = [ 'timeout' => 15 ];

    if ( $api_key ) {
        $args['headers'] = [ 'Authorization' => 'Bearer ' . $api_key ];
    }

    $response = wp_remote_get( $url, $args );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( $code !== 200 || ! is_array( $data ) ) {
        return new WP_Error( 'api_error', 'CheckIN API svarte med kode ' . $code );
    }

    set_transient( $transient_key, $data, HOUR_IN_SECONDS );

    return $data;
}

// Extract normalized fields from a single-event API response
function checkin_parse_event_data( array $data ) {
    return [
        'description' => wp_kses_post(
            $data['description'] ?? $data['about'] ?? $data['body'] ?? $data['content'] ?? ''
        ),
        'start_date'  => sanitize_text_field(
            $data['start_date'] ?? $data['starts_at'] ?? $data['start_time'] ?? $data['date'] ?? ''
        ),
        'end_date'    => sanitize_text_field(
            $data['end_date'] ?? $data['ends_at'] ?? $data['end_time'] ?? ''
        ),
    ];
}

function checkin_fetch_events() {
    $customer_number = get_option( 'checkin_customer_number', '' );
    $api_key         = get_option( 'checkin_api_key', '' );
    $api_base_url    = rtrim( get_option( 'checkin_api_base_url', 'https://api.checkin.no/v1' ), '/' );

    if ( ! $customer_number ) {
        return new WP_Error( 'no_customer', 'Kundenummer mangler. Sett det i <a href="' . admin_url( 'admin.php?page=checkin-settings' ) . '">Innstillinger</a>.' );
    }

    $transient_key = 'checkin_events_' . md5( $customer_number . $api_key );
    $cached        = get_transient( $transient_key );
    if ( $cached !== false ) {
        return $cached;
    }

    $url  = $api_base_url . '/events?organizer_id=' . urlencode( $customer_number );
    $args = [ 'timeout' => 15 ];

    if ( $api_key ) {
        $args['headers'] = [ 'Authorization' => 'Bearer ' . $api_key ];
    }

    $response = wp_remote_get( $url, $args );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( $code !== 200 || ! is_array( $data ) ) {
        return new WP_Error( 'api_error', 'CheckIN API svarte med kode ' . $code . '. Sjekk kundenummer og API-nøkkel.' );
    }

    set_transient( $transient_key, $data, 5 * MINUTE_IN_SECONDS );

    return $data;
}

function checkin_page_import() {
    $customer_number = get_option( 'checkin_customer_number', '' );
    $message         = '';

    // Handle manual single event ID add
    if ( isset( $_POST['checkin_add_single_nonce'] ) && wp_verify_nonce( $_POST['checkin_add_single_nonce'], 'checkin_add_single' ) ) {
        $event_id    = sanitize_text_field( $_POST['single_event_id'] ?? '' );
        $event_title = sanitize_text_field( $_POST['single_event_title'] ?? '' ) ?: 'CheckIN Event ' . $event_id;

        if ( $event_id ) {
            $post_id = wp_insert_post( [
                'post_title'  => $event_title,
                'post_type'   => 'checkin_event',
                'post_status' => 'publish',
            ] );
            if ( $post_id && ! is_wp_error( $post_id ) ) {
                update_post_meta( $post_id, '_checkin_event_id', $event_id );
                $message = '<div class="notice notice-success"><p>Event <strong>' . esc_html( $event_title ) . '</strong> (ID: ' . esc_html( $event_id ) . ') ble lagt til. <a href="' . get_edit_post_link( $post_id ) . '">Rediger</a></p></div>';
            }
        }
    }

    // Handle import from API
    if ( isset( $_POST['checkin_import_nonce'] ) && wp_verify_nonce( $_POST['checkin_import_nonce'], 'checkin_import_event' ) ) {
        $event_id    = sanitize_text_field( $_POST['import_event_id'] ?? '' );
        $event_title = sanitize_text_field( $_POST['import_event_title'] ?? '' ) ?: 'CheckIN Event ' . $event_id;

        if ( $event_id ) {
            // Check if already exists
            $existing = get_posts( [
                'post_type'   => 'checkin_event',
                'post_status' => 'any',
                'meta_key'    => '_checkin_event_id',
                'meta_value'  => $event_id,
                'numberposts' => 1,
            ] );

            if ( $existing ) {
                $message = '<div class="notice notice-warning"><p>Event med ID <strong>' . esc_html( $event_id ) . '</strong> er allerede lagt til. <a href="' . get_edit_post_link( $existing[0]->ID ) . '">Se event</a></p></div>';
            } else {
                $post_id = wp_insert_post( [
                    'post_title'  => $event_title,
                    'post_type'   => 'checkin_event',
                    'post_status' => 'publish',
                ] );
                if ( $post_id && ! is_wp_error( $post_id ) ) {
                    update_post_meta( $post_id, '_checkin_event_id', $event_id );
                    $message = '<div class="notice notice-success"><p>Event <strong>' . esc_html( $event_title ) . '</strong> (ID: ' . esc_html( $event_id ) . ') ble importert. <a href="' . get_edit_post_link( $post_id ) . '">Rediger</a></p></div>';
                }
            }
        }

        // Clear cache so the list refreshes
        $api_key = get_option( 'checkin_api_key', '' );
        delete_transient( 'checkin_events_' . md5( $customer_number . $api_key ) );
    }

    // Fetch events from API
    $api_events = $customer_number ? checkin_fetch_events() : null;

    // Get already-imported event IDs
    $imported_ids = [];
    $imported_posts = get_posts( [
        'post_type'      => 'checkin_event',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'meta_key'       => '_checkin_event_id',
    ] );
    foreach ( $imported_posts as $p ) {
        $imported_ids[] = get_post_meta( $p->ID, '_checkin_event_id', true );
    }
    ?>
    <div class="wrap">
        <h1>Importer CheckIN Events</h1>
        <?php echo $message; ?>

        <?php if ( ! $customer_number ) : ?>
            <div class="notice notice-warning"><p>Sett et <a href="<?php echo esc_url( admin_url( 'admin.php?page=checkin-settings' ) ); ?>">kundenummer i innstillinger</a> for å hente events automatisk fra CheckIN.</p></div>
        <?php endif; ?>

        <!-- Manual add by ID -->
        <div class="postbox" style="max-width:600px; margin-bottom:24px;">
            <div class="postbox-header"><h2 class="hndle">Legg til event manuelt med ID</h2></div>
            <div class="inside">
                <form method="post">
                    <?php wp_nonce_field( 'checkin_add_single', 'checkin_add_single_nonce' ); ?>
                    <table class="form-table" role="presentation" style="margin-top:0;">
                        <tr>
                            <th><label for="single_event_id">CheckIN Event ID</label></th>
                            <td><input type="text" id="single_event_id" name="single_event_id" placeholder="f.eks. 123103" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="single_event_title">Tittel (valgfri)</label></th>
                            <td><input type="text" id="single_event_title" name="single_event_title" placeholder="Navn på eventet" class="regular-text"></td>
                        </tr>
                    </table>
                    <?php submit_button( 'Legg til event', 'primary', 'submit', false ); ?>
                </form>
            </div>
        </div>

        <!-- Events from API -->
        <?php if ( $customer_number ) : ?>
        <h2>Events fra CheckIN (kundenummer: <?php echo esc_html( $customer_number ); ?>)</h2>

        <?php if ( is_wp_error( $api_events ) ) : ?>
            <div class="notice notice-error"><p><?php echo wp_kses_post( $api_events->get_error_message() ); ?></p></div>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=checkin-settings' ) ); ?>" class="button">Gå til innstillinger</a></p>

        <?php elseif ( empty( $api_events ) ) : ?>
            <p>Ingen events funnet for dette kundenummeret.</p>

        <?php else : ?>
            <p>Fant <strong><?php echo count( $api_events ); ?></strong> events. Klikk «Importer» for å legge til et event på nettstedet.</p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:80px;">Event ID</th>
                        <th>Navn</th>
                        <th>Dato</th>
                        <th style="width:130px;">Status</th>
                        <th style="width:120px;">Handling</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $api_events as $event ) :
                        $eid   = esc_attr( $event['id'] ?? $event['event_id'] ?? '' );
                        $ename = esc_html( $event['name'] ?? $event['title'] ?? 'Ukjent event' );
                        $edate = esc_html( $event['date'] ?? $event['start_date'] ?? $event['starts_at'] ?? '' );
                        $already_imported = in_array( $eid, $imported_ids, true );
                    ?>
                    <tr>
                        <td><code><?php echo $eid; ?></code></td>
                        <td><?php echo $ename; ?></td>
                        <td><?php echo $edate; ?></td>
                        <td>
                            <?php if ( $already_imported ) : ?>
                                <span style="color:green;">&#10003; Importert</span>
                            <?php else : ?>
                                <span style="color:#999;">Ikke importert</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( ! $already_imported ) : ?>
                            <form method="post">
                                <?php wp_nonce_field( 'checkin_import_event', 'checkin_import_nonce' ); ?>
                                <input type="hidden" name="import_event_id" value="<?php echo $eid; ?>">
                                <input type="hidden" name="import_event_title" value="<?php echo $ename; ?>">
                                <button type="submit" class="button button-primary button-small">Importer</button>
                            </form>
                            <?php else : ?>
                                <em style="color:#999;">Allerede lagt til</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:8px;"><em>Listen caches i 5 minutter. <a href="<?php echo esc_url( admin_url( 'admin.php?page=checkin-import' ) ); ?>">Last på nytt</a> for oppdatert liste.</em></p>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

// ─── Event ID Field Above Title ───────────────────────────────────────────────

add_action( 'edit_form_top', function ( $post ) {
    if ( $post->post_type !== 'checkin_event' ) {
        return;
    }

    $event_id         = get_post_meta( $post->ID, '_checkin_event_id', true );
    $hide_embed       = get_post_meta( $post->ID, '_checkin_hide_embed', true );
    $hide_description = get_post_meta( $post->ID, '_checkin_hide_description', true );
    $hide_dates       = get_post_meta( $post->ID, '_checkin_hide_dates', true );
    $description      = get_post_meta( $post->ID, '_checkin_description', true );
    $start_date       = get_post_meta( $post->ID, '_checkin_start_date', true );
    $end_date         = get_post_meta( $post->ID, '_checkin_end_date', true );
    $last_fetched     = get_post_meta( $post->ID, '_checkin_last_fetched', true );
    wp_nonce_field( 'checkin_event_save', 'checkin_event_nonce' );

    $toggle_row = function( $name, $value, $label_on, $label_off ) {
        $checked = $value === '1';
        $bg      = $checked ? '#fff8e5' : '#edfaed';
        $border  = $checked ? '#dba617' : '#4ab866';
        $label   = $checked ? $label_off : $label_on;
        printf(
            '<div style="margin-top:8px; padding:8px 12px; background:%s; border:1px solid %s; border-radius:3px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; margin:0;">
                    <input type="checkbox" name="%s" value="1" %s style="width:15px; height:15px; margin:0;">
                    <span>%s</span>
                </label>
            </div>',
            $bg, $border, esc_attr( $name ), checked( $value, '1', false ), $label
        );
    };
    ?>
    <div style="margin: 16px 0; padding: 16px; background: #fff; border: 1px solid #c3c4c7; border-radius: 3px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">

        <div style="display:flex; align-items:flex-end; gap:10px; flex-wrap:wrap;">
            <div>
                <label for="checkin_event_id" style="font-weight:600; font-size:13px; display:block; margin-bottom:4px;">CheckIN Event ID</label>
                <input type="text" id="checkin_event_id" name="checkin_event_id" value="<?php echo esc_attr( $event_id ); ?>" placeholder="f.eks. 123103" class="regular-text">
            </div>
            <?php if ( $event_id ) : ?>
            <div>
                <input type="hidden" name="checkin_force_fetch" value="0" id="checkin_force_fetch_field">
                <button type="submit" class="button" onclick="document.getElementById('checkin_force_fetch_field').value='1';">
                    &#x21BB; <?php echo $last_fetched ? 'Oppdater fra CheckIN' : 'Hent fra CheckIN'; ?>
                </button>
                <?php if ( $last_fetched ) : ?>
                <span style="font-size:11px; color:#999; display:block; margin-top:3px;">Sist hentet: <?php echo esc_html( date_i18n( 'd.m.Y H:i', $last_fetched ) ); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <p style="margin:4px 0 0; color:#666; font-size:12px;">Finn Event ID-en på registration.checkin.no.</p>

        <?php if ( $event_id && ( $description || $start_date ) ) : ?>
        <div style="margin-top:12px; padding:12px; background:#f6f7f7; border-radius:3px; border-left:4px solid #2271b1;">
            <p style="margin:0 0 6px; font-weight:600; font-size:12px;">Data hentet fra CheckIN:</p>
            <?php if ( $start_date ) : ?>
            <p style="margin:0 0 2px; font-size:12px;"><strong>Startdato:</strong> <?php echo esc_html( checkin_format_date( $start_date ) ); ?></p>
            <?php endif; ?>
            <?php if ( $end_date ) : ?>
            <p style="margin:0 0 6px; font-size:12px;"><strong>Sluttdato:</strong> <?php echo esc_html( checkin_format_date( $end_date ) ); ?></p>
            <?php endif; ?>
            <?php if ( $description ) : ?>
            <p style="margin:0 0 2px; font-size:12px;"><strong>Om arrangementet:</strong></p>
            <div style="font-size:12px; color:#444; max-height:80px; overflow:hidden; -webkit-mask-image:linear-gradient(180deg,#000 60%,transparent);"><?php echo wp_kses_post( $description ); ?></div>
            <?php endif; ?>
        </div>
        <?php elseif ( $event_id ) : ?>
        <p style="margin-top:8px; font-size:12px; color:#999;">Ingen data hentet ennå — klikk «Hent fra CheckIN» og lagre.</p>
        <?php endif; ?>

        <div style="margin-top:10px;">
            <p style="margin:0 0 4px; font-weight:600; font-size:12px;">Automatisk visning på siden:</p>
            <?php
            $toggle_row( 'checkin_hide_embed',       $hide_embed,       '✓ Registreringswidget vises automatisk',   '✗ Registreringswidget skjult — bruk shortcode/custom field' );
            $toggle_row( 'checkin_hide_description',  $hide_description, '✓ «Om arrangementet» vises automatisk',    '✗ «Om arrangementet» skjult — bruk shortcode/custom field' );
            $toggle_row( 'checkin_hide_dates',        $hide_dates,       '✓ Dato og tid vises automatisk',           '✗ Dato og tid skjult — bruk shortcode/custom field' );
            ?>
        </div>

        <?php if ( $event_id ) : ?>
        <div style="margin-top:12px; padding:10px 12px; background:#f0f6fc; border-radius:3px; border-left:4px solid #72aee6;">
            <p style="margin:0 0 4px; font-size:12px; font-weight:600;">Shortcodes:</p>
            <code style="display:block; font-size:11px; margin-bottom:2px; user-select:all;">[checkin_event id="<?php echo esc_html( $event_id ); ?>"]</code>
            <code style="display:block; font-size:11px; margin-bottom:2px; user-select:all;">[checkin_description]</code>
            <code style="display:block; font-size:11px; margin-bottom:2px; user-select:all;">[checkin_start_date]</code>
            <code style="display:block; font-size:11px; user-select:all;">[checkin_end_date]</code>
        </div>
        <?php endif; ?>
    </div>
    <?php
} );

add_action( 'save_post_checkin_event', function ( $post_id ) {
    if (
        ! isset( $_POST['checkin_event_nonce'] ) ||
        ! wp_verify_nonce( $_POST['checkin_event_nonce'], 'checkin_event_save' ) ||
        ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
        ! current_user_can( 'edit_post', $post_id )
    ) {
        return;
    }

    $event_id = sanitize_text_field( $_POST['checkin_event_id'] ?? '' );
    update_post_meta( $post_id, '_checkin_event_id', $event_id );

    update_post_meta( $post_id, '_checkin_hide_embed',       isset( $_POST['checkin_hide_embed'] )       ? '1' : '0' );
    update_post_meta( $post_id, '_checkin_hide_description', isset( $_POST['checkin_hide_description'] ) ? '1' : '0' );
    update_post_meta( $post_id, '_checkin_hide_dates',       isset( $_POST['checkin_hide_dates'] )       ? '1' : '0' );

    // Fetch event data from API if ID is set and force_fetch requested or no data yet
    $prev_id      = get_post_meta( $post_id, '_checkin_prev_event_id', true );
    $force_fetch  = ( $_POST['checkin_force_fetch'] ?? '0' ) === '1';
    $id_changed   = $event_id && $event_id !== $prev_id;
    $has_no_data  = ! get_post_meta( $post_id, '_checkin_last_fetched', true );

    if ( $event_id && ( $force_fetch || $id_changed || $has_no_data ) ) {
        if ( $force_fetch ) {
            delete_transient( 'checkin_single_event_' . $event_id );
        }

        $api_data = checkin_fetch_single_event( $event_id );

        if ( ! is_wp_error( $api_data ) ) {
            $parsed = checkin_parse_event_data( $api_data );
            update_post_meta( $post_id, '_checkin_description', $parsed['description'] );
            update_post_meta( $post_id, '_checkin_start_date',  $parsed['start_date'] );
            update_post_meta( $post_id, '_checkin_end_date',    $parsed['end_date'] );
            update_post_meta( $post_id, '_checkin_last_fetched', time() );
        }
    }

    update_post_meta( $post_id, '_checkin_prev_event_id', $event_id );
} );

// ─── Register meta for REST / page builders ───────────────────────────────────

add_action( 'init', function () {
    register_post_meta( 'checkin_event', '_checkin_event_id', [
        'show_in_rest'  => true,
        'single'        => true,
        'type'          => 'string',
        'auth_callback' => function () {
            return current_user_can( 'edit_posts' );
        },
    ] );
} );

// ─── Frontend auto-display ────────────────────────────────────────────────────

add_filter( 'the_content', function ( $content ) {
    if ( ! is_singular( 'checkin_event' ) ) {
        return $content;
    }

    $post_id          = get_the_ID();
    $event_id         = get_post_meta( $post_id, '_checkin_event_id', true );
    $hide_embed       = get_post_meta( $post_id, '_checkin_hide_embed', true );
    $hide_description = get_post_meta( $post_id, '_checkin_hide_description', true );
    $hide_dates       = get_post_meta( $post_id, '_checkin_hide_dates', true );

    $prepend = '';
    $append  = '';

    if ( $hide_dates !== '1' ) {
        $start = get_post_meta( $post_id, '_checkin_start_date', true );
        $end   = get_post_meta( $post_id, '_checkin_end_date', true );
        if ( $start || $end ) {
            $prepend .= '<div class="checkin-dates">';
            if ( $start ) $prepend .= '<p class="checkin-start-date"><strong>Dato:</strong> ' . esc_html( checkin_format_date( $start ) ) . '</p>';
            if ( $end )   $prepend .= '<p class="checkin-end-date"><strong>Til:</strong> ' . esc_html( checkin_format_date( $end ) ) . '</p>';
            $prepend .= '</div>';
        }
    }

    if ( $hide_description !== '1' ) {
        $desc = get_post_meta( $post_id, '_checkin_description', true );
        if ( $desc ) {
            $prepend .= '<div class="checkin-description">' . wp_kses_post( $desc ) . '</div>';
        }
    }

    if ( $event_id && $hide_embed !== '1' ) {
        $append .= checkin_embed_html( $event_id );
    }

    return $prepend . $content . $append;
} );

// ─── Event data resolver ──────────────────────────────────────────────────────
// Resolves event data for a given CheckIN event ID.
// Priority: transient cache → stored post meta → live API fetch.

function checkin_get_event_data( $event_id ) {
    if ( ! $event_id ) return null;

    $transient_key = 'checkin_single_event_' . $event_id;
    $cached        = get_transient( $transient_key );
    if ( $cached !== false ) {
        return $cached;
    }

    // Try stored post meta first (fastest, no API call needed)
    $posts = get_posts( [
        'post_type'      => 'checkin_event',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'meta_key'       => '_checkin_event_id',
        'meta_value'     => $event_id,
    ] );

    if ( $posts ) {
        $pid  = $posts[0]->ID;
        $data = [
            'description' => get_post_meta( $pid, '_checkin_description', true ),
            'start_date'  => get_post_meta( $pid, '_checkin_start_date', true ),
            'end_date'    => get_post_meta( $pid, '_checkin_end_date', true ),
        ];
        // Only use stored data if it has content
        if ( $data['description'] || $data['start_date'] ) {
            set_transient( $transient_key, $data, HOUR_IN_SECONDS );
            return $data;
        }
    }

    // Fall back to live API fetch
    $api_result = checkin_fetch_single_event( $event_id );
    if ( is_wp_error( $api_result ) ) {
        return null;
    }

    $data = checkin_parse_event_data( $api_result );
    set_transient( $transient_key, $data, HOUR_IN_SECONDS );
    return $data;
}

// ─── Shortcodes ───────────────────────────────────────────────────────────────
// All shortcodes accept id="CHECKIN_EVENT_ID" to fetch data directly from API.
// Without id, they fall back to the current post's stored meta.
//
// [checkin_event id="123103"]
// [checkin_event id="123103" show="description,dates,embed"]
// [checkin_description id="123103"]
// [checkin_start_date id="123103" format="d. F Y \k\l. H:i"]
// [checkin_end_date   id="123103" format="d. F Y \k\l. H:i"]

add_shortcode( 'checkin_event', function ( $atts ) {
    $atts = shortcode_atts( [
        'id'   => '',
        'show' => 'embed', // comma-separated: description, dates, embed
    ], $atts, 'checkin_event' );

    $event_id = $atts['id'];
    if ( ! $event_id ) return '';

    $show   = array_map( 'trim', explode( ',', $atts['show'] ) );
    $output = '';

    if ( in_array( 'description', $show ) || in_array( 'dates', $show ) ) {
        $data = checkin_get_event_data( $event_id );

        if ( $data && in_array( 'dates', $show ) ) {
            $output .= '<div class="checkin-dates">';
            if ( $data['start_date'] ) $output .= '<p class="checkin-start-date"><strong>Dato:</strong> ' . esc_html( checkin_format_date( $data['start_date'] ) ) . '</p>';
            if ( $data['end_date'] )   $output .= '<p class="checkin-end-date"><strong>Til:</strong> '   . esc_html( checkin_format_date( $data['end_date'] ) ) . '</p>';
            $output .= '</div>';
        }

        if ( $data && in_array( 'description', $show ) && $data['description'] ) {
            $output .= '<div class="checkin-description">' . wp_kses_post( $data['description'] ) . '</div>';
        }
    }

    if ( in_array( 'embed', $show ) ) {
        $output .= checkin_embed_html( $event_id );
    }

    return $output;
} );

add_shortcode( 'checkin_description', function ( $atts ) {
    $atts = shortcode_atts( [ 'id' => '', 'post_id' => '' ], $atts, 'checkin_description' );

    if ( $atts['id'] ) {
        $data = checkin_get_event_data( $atts['id'] );
        $desc = $data['description'] ?? '';
    } else {
        $pid  = $atts['post_id'] ? (int) $atts['post_id'] : get_the_ID();
        $desc = get_post_meta( $pid, '_checkin_description', true );
        if ( ! $desc ) {
            $event_id = get_post_meta( $pid, '_checkin_event_id', true );
            if ( $event_id ) {
                $data = checkin_get_event_data( $event_id );
                $desc = $data['description'] ?? '';
            }
        }
    }

    return $desc ? '<div class="checkin-description">' . wp_kses_post( $desc ) . '</div>' : '';
} );

add_shortcode( 'checkin_start_date', function ( $atts ) {
    $atts = shortcode_atts( [ 'id' => '', 'post_id' => '', 'format' => '' ], $atts, 'checkin_start_date' );

    if ( $atts['id'] ) {
        $data  = checkin_get_event_data( $atts['id'] );
        $value = $data['start_date'] ?? '';
    } else {
        $pid   = $atts['post_id'] ? (int) $atts['post_id'] : get_the_ID();
        $value = get_post_meta( $pid, '_checkin_start_date', true );
        if ( ! $value ) {
            $event_id = get_post_meta( $pid, '_checkin_event_id', true );
            if ( $event_id ) {
                $data  = checkin_get_event_data( $event_id );
                $value = $data['start_date'] ?? '';
            }
        }
    }

    return $value ? esc_html( checkin_format_date( $value, $atts['format'] ) ) : '';
} );

add_shortcode( 'checkin_end_date', function ( $atts ) {
    $atts = shortcode_atts( [ 'id' => '', 'post_id' => '', 'format' => '' ], $atts, 'checkin_end_date' );

    if ( $atts['id'] ) {
        $data  = checkin_get_event_data( $atts['id'] );
        $value = $data['end_date'] ?? '';
    } else {
        $pid   = $atts['post_id'] ? (int) $atts['post_id'] : get_the_ID();
        $value = get_post_meta( $pid, '_checkin_end_date', true );
        if ( ! $value ) {
            $event_id = get_post_meta( $pid, '_checkin_event_id', true );
            if ( $event_id ) {
                $data  = checkin_get_event_data( $event_id );
                $value = $data['end_date'] ?? '';
            }
        }
    }

    return $value ? esc_html( checkin_format_date( $value, $atts['format'] ) ) : '';
} );

// ─── Date Helper ──────────────────────────────────────────────────────────────

function checkin_format_date( $date_string, $format = '' ) {
    if ( ! $date_string ) return '';
    if ( ! $format ) $format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
    $ts = strtotime( $date_string );
    return $ts ? date_i18n( $format, $ts ) : $date_string;
}

// ─── Embed Helper ─────────────────────────────────────────────────────────────

function checkin_embed_html( $event_id ) {
    return sprintf(
        '<div class="kurs-spesifikt-embed"><div id="checkin_registration"></div><script src="https://registration.checkin.no/registration.loader.js" data-event-id="%s"></script></div>',
        esc_attr( $event_id )
    );
}

// ─── Beaver Builder Themer – dynamiske felt ───────────────────────────────────

add_action( 'fl_page_data_add_properties', function () {
    if ( ! class_exists( 'FLPageData' ) ) {
        return;
    }

    // Felt 1: Råverdien til Event ID (tekst)
    FLPageData::add_post_property( 'checkin_event_id', [
        'label'  => 'CheckIN – Event ID',
        'group'  => 'posts',
        'type'   => 'string',
        'getter' => 'checkin_bb_get_event_id',
    ] );

    // Felt 2: Komplett registreringswidget som HTML
    FLPageData::add_post_property( 'checkin_registration_embed', [
        'label'  => 'CheckIN – Registreringswidget',
        'group'  => 'posts',
        'type'   => 'html',
        'getter' => 'checkin_bb_get_embed_html',
    ] );

    // Felt 3: Om arrangementet
    FLPageData::add_post_property( 'checkin_description', [
        'label'  => 'CheckIN – Om arrangementet',
        'group'  => 'posts',
        'type'   => 'html',
        'getter' => 'checkin_bb_get_description',
    ] );

    // Felt 4: Startdato
    FLPageData::add_post_property( 'checkin_start_date', [
        'label'  => 'CheckIN – Startdato',
        'group'  => 'posts',
        'type'   => 'string',
        'getter' => 'checkin_bb_get_start_date',
    ] );

    // Felt 5: Sluttdato
    FLPageData::add_post_property( 'checkin_end_date', [
        'label'  => 'CheckIN – Sluttdato',
        'group'  => 'posts',
        'type'   => 'string',
        'getter' => 'checkin_bb_get_end_date',
    ] );
} );

function checkin_bb_get_event_id( $settings ) {
    return get_post_meta( get_the_ID(), '_checkin_event_id', true );
}

function checkin_bb_get_embed_html( $settings ) {
    $event_id = get_post_meta( get_the_ID(), '_checkin_event_id', true );
    return $event_id ? checkin_embed_html( $event_id ) : '';
}

function checkin_bb_get_description( $settings ) {
    $event_id = get_post_meta( get_the_ID(), '_checkin_event_id', true );
    $data     = checkin_get_event_data( $event_id );
    return $data['description'] ?? '';
}

function checkin_bb_get_start_date( $settings ) {
    $event_id = get_post_meta( get_the_ID(), '_checkin_event_id', true );
    $data     = checkin_get_event_data( $event_id );
    return checkin_format_date( $data['start_date'] ?? '' );
}

function checkin_bb_get_end_date( $settings ) {
    $event_id = get_post_meta( get_the_ID(), '_checkin_event_id', true );
    $data     = checkin_get_event_data( $event_id );
    return checkin_format_date( $data['end_date'] ?? '' );
}
