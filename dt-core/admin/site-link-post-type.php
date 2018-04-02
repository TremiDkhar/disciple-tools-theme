<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

/**
 * Site_Link_System Post Type Class
 *
 * All functionality pertaining to project update post types in Site_Link_System.
 * @class Site_Link_System
 *
 * @version 0.1.8
 *
 * @since   0.1.7 Moved to post type
 * @since   0.1.8 Added key_select, readonly
 */
if ( ! class_exists( 'Site_Link_System' ) ) {

    // @codingStandardsIgnoreLine
    class Site_Link_System
    {

        /*****************************************************************************************************************
         * PRIMARY INTEGRATION SECTION
         *
         * The next section has the main functions intended for integration into other systems
         * @variable $token
         * (This defines the prefix for the site keys used through the entire system.)
         *
         * @function get_site_keys()
         *          (This can be called to get all the site keys installed in the system.)
         * @function create_transfer_token_for_site( $site_key )
         *           (This gets the one hour transfer token to be passed with a REST request.
         *           It requires the key for the link record that the token is to be made for.)
         * @function verify_transfer_token( $transfer_token )
         *           (This tests the transfer token against the registered sites and returns a true or false response.)
         * @function add_cors_sites()
         *           (This is meant to be added into REST registrations that intend to pass data between sites. This
         *           modifies the Cross-Origin-Resource-Sharing policy to allow these transfers to approved sites.)
         *****************************************************************************************************************/

        /**
         * SET PREFIX FOR SYSTEM
         * This public token sets the prefix throughout the system and allows the system. Changing this could
         * potentially let you refactor and implement this system again under a different namespace.
         *
         * @since 1.0
         * @var string
         */
        public static $token = 'site_link_system';

        /**
         * GET THE ARRAY OF SITE KEYS
         *
         * @since 1.4
         * @return array Returns array of site keys, or empty array.
         */
        public static function get_site_keys()
        {
            $prefix = self::$token;
            $keys = get_option( $prefix . '_api_keys', [] );

            return $keys;
        }

        /**
         * CREATE A TRANSFER TOKEN FOR A SITE
         * This method encrypts with md5 and the GMT date. So every day, this encryption will change. Using this method
         * requires that both of the servers have their timezone in Settings > General > Timezone correctly set.
         *
         * @since 1.0
         * @note  Key changes every hour
         *
         * @param $site_key string This is the key to the site array stored in options.
         *
         * @return string Returns transfer token for the two sites specified in the site1 and site2 fields.
         */
        public static function create_transfer_token_for_site( $site_key )
        {
            return md5( $site_key . current_time( 'Y-m-dH', 1 ) );
        }

        /**
         * VERIFY A TRANSFER TOKEN FROM A CONNECTED SITE REST REQUEST
         *
         * @since 1.0
         *
         * @param $transfer_token
         *
         * @return bool
         */
        public static function verify_transfer_token( $transfer_token ): bool
        {
            if ( ! empty( $transfer_token ) ) {
                $id_decrypted = self::decrypt_transfer_token( $transfer_token );
                if ( $id_decrypted ) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }

        /**
         * ENABLE CROSS-ORIGIN-RESOURCE-SHARING (CORS) FOR LINKED SITES ONLY
         * This function can be added to other REST registrations, in addition to the transfer token, this limits the
         * approved list of Cross Origin requests to those that are linked through the Site Link System.
         * NOTE: This is by no means fool proof security measure, since request origins can be falsified, but only acts as
         * another layer of the larger security strategy and increases compatibility with browser requests cross origin.
         *
         * @since 1.4
         */
        public static function add_cors_sites()
        {
            /**
             * Cross Origin Resource Sharing (CORS)
             * This allows the javascript requests to cross domains to get access to resources. This is normally
             * disabled to prevent hacking and XSS attacts. In order to link sites and pass contacts and other data
             * this function checks the requesting URL against the approved list of URLs, and if there is a match it adds
             * permission for CORS for that domain into the header.
             *
             * @link https://enable-cors.org/
             * @link https://github.com/WP-API/WP-API/issues/144
             * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
             * @link https://stackoverflow.com/questions/8719276/cors-with-php-headers
             */
            /**
             * @link https://gist.github.com/miya0001/d6508b9ba52df5aedc78fca186ff6088
             */

            $keys = self::get_site_keys();

            if ( empty( $keys ) ) {
                return;
            }

            $approved_urls = [];
            foreach ( $keys as $key => $value ) {
                $approved_urls[] = 'https://' . self::get_non_local_site( $value['site1'], $value['site2'] );
            }

            $request_header = get_http_origin();

            foreach ( $approved_urls as $approved_url ) {
                if ( $request_header == $approved_url ) {
                    add_filter( 'rest_pre_serve_request', function( $value ) {
                        header( 'Access-Control-Allow-Origin: ' . get_http_origin() );
                        header( 'Access-Control-Allow-Methods: GET, POST, HEAD, OPTIONS' );
                        header( 'Access-Control-Allow-Credentials: true' );
                        header( 'Access-Control-Expose-Headers: Link', false );

                        return $value;
                    } );
                }
            }
        }



        /************************************************************************************************************
         * ADMIN INTERFACE SECTION
         *
         * This section contains the ui for the admin interface. It has two implementations: multiple or single.
         * - Multiple allows for multiple connections to be generated and added.
         * - Single manages a single site link to a home site. It cannot create a site link, but only enter the link info
         * from another website.
         * These metaboxes can be implemented through a static call to the class.
         * For example: Site_Link_System::metabox_multiple_link()
         ************************************************************************************************************/

        public function register_post_type()
        {
            register_post_type( $this->post_type, /* (http://codex.wordpress.org/Function_Reference/register_post_type) */
                // let's now add all the options for this post type
                [
                'labels'              => [
                'name'               => $this->plural, /* This is the Title of the Group */
                'singular_name'      => $this->singular, /* This is the individual type */
                'all_items'          => __( 'All' ) . ' ' . $this->plural, /* the all items menu item */
                'add_new'            => __( 'Add New' ), /* The add new menu item */
                'add_new_item'       => __( 'Add New' ) . ' ' . $this->singular, /* Add New Display Title */
                'edit'               => __( 'Edit' ), /* Edit Dialog */
                'edit_item'          => __( 'Edit' ) . ' ' . $this->singular, /* Edit Display Title */
                'new_item'           => __( 'New' ) . ' ' . $this->singular, /* New Display Title */
                'view_item'          => __( 'View' ) . ' ' . $this->singular, /* View Display Title */
                'search_items'       => __( 'Search' ) . ' ' . $this->plural, /* Search Custom Type Title */
                'not_found'          => __( 'Nothing found in the Database.' ), /* This displays if there are no entries yet */
                'not_found_in_trash' => __( 'Nothing found in Trash' ), /* This displays if there is nothing in the trash */
                'parent_item_colon'  => ''
                ], /* end of arrays */
                'public'              => false,
                'publicly_queryable'  => false,
                'exclude_from_search' => true,
                'show_ui'             => true,
                'query_var'           => true,
                'menu_position'       => $this->menu_position, /* this is what order you want it to appear in on the left hand side menu */
                'menu_icon'           => 'dashicons-admin-links', /* the icon for the custom post type menu. uses built-in dashicons (CSS class name) */
                'rewrite'             => [
                'slug' => $this->post_type,
                'with_front' => false
                ], /* you can specify its url slug */
                'has_archive'         => false, /* you can rename the slug here */
                'capability_type'     => 'post',
                'hierarchical'        => false,
                /* the next one is important, it tells what's enabled in the post editor */
                'supports'            => [ 'title' ]
                ] /* end of options */
            ); /* end of register post type */
        }

        public function register_custom_columns( $column_name )
        {
            global $post;

            switch ( $column_name ) {
                case 'linked':
                    if ( $this->is_key_locked( $post->ID ) ) {
                        ?>

                        <span >
                        <?php esc_html_e( 'Status:' ) ?>
                            <strong>
                            <span id="<?php echo esc_attr( md5( $post->ID ) ); ?>-status">
                                <?php esc_html_e( 'Checking Status' ) ?>
                            </span>
                        </strong>
                    </span>
                        <script>
                            jQuery(document).ready(function () {
                                check_link_status('<?php echo esc_attr( self::create_transfer_token_for_site( $this->get_site_key_by_id( $post->ID ) ) ); ?>', '<?php echo esc_attr( $this->get_non_local_site_by_id( $post->ID ) ); ?>', '<?php echo esc_attr( md5( $post->ID ) ); ?>');
                            })
                        </script>
                        <?php
                    } else {
                        echo '<span >Unfinished Configuration</span>';
                    }
                    break;

                default:
                    break;
            }
        }

        public function register_custom_column_headings( $defaults )
        {

            $new_columns = array( 'linked' => __( 'Linked' ) );

            $last_item = [];

            if ( count( $defaults ) > 2 ) {
                $last_item = array_slice( $defaults, -1 );

                array_pop( $defaults );
            }
            $defaults = array_merge( $defaults, $new_columns );

            if ( is_array( $last_item ) && 0 < count( $last_item ) ) {
                foreach ( $last_item as $k => $v ) {
                    $defaults[ $k ] = $v;
                    break;
                }
            }

            return $defaults;
        }

        public function post_type_updated_messages( $messages )
        {
            global $post;

            $messages[ $this->post_type ] = [
            0  => '', // Unused. Messages start at index 1.
            1  => sprintf(
                '%1$s updated.',
                $this->singular
            ),
            2  => 'Site Link updated.',
            3  => 'Site Link deleted.',
            4  => sprintf( '%s updated.', $this->singular ),
            /* translators: %s: date and time of the revision */
            5  => isset( $_GET['revision'] ) ? sprintf( '%1$s restored to revision from %2$s', $this->singular, wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
            6  => sprintf( '%1$s published. %3$s%2$s%4$s', $this->singular, strtolower( $this->singular ), '', '' ),
            7  => sprintf( '%s saved.', $this->singular ),
            8  => sprintf( '%1$s submitted. %2$s%3$s%4$s', $this->singular, strtolower( $this->singular ), '', '' ),
            9  => sprintf(
                '%1$s scheduled for: %1$s. %2$s%2$s%3$6$s',
                $this->singular,
                strtolower( $this->singular ),
                // translators: Publish box date format, see http://php.net/date
                '<strong>' . date_i18n( __( 'M j, Y @ G:i' ),
                strtotime( $post->post_date ) ) . '</strong>',
                '',
                ''
            ),
            10 => sprintf( '%1$s draft updated. %2$s%3$s%4$s', $this->singular, strtolower( $this->singular ), '', '' ),
            ];

            return $messages;
        }

        public function meta_box_setup()
        {
            add_meta_box( $this->post_type . '_details', 'Manage Site Link', [ $this, 'meta_box_load_management_box' ], $this->post_type, 'normal', 'high' );
            add_meta_box( $this->post_type . '_instructions', 'Configuration Notes', [ $this, 'meta_box_configuration_box' ], $this->post_type, 'normal', 'high' );
        }

        public function meta_box_content( $section = 'info' )
        {
            global $post_id;
            $fields = get_post_custom( $post_id );
            $field_data = $this->meta_box_custom_fields_settings();

            echo '<input type="hidden" name="' . esc_attr( $this->post_type ) . '_noonce" id="' . esc_attr( $this->post_type ) . '_noonce" value="' . esc_attr( wp_create_nonce( esc_attr( $this->post_type ) . '_noonce_action' ) ) . '" />';

            if ( 0 < count( $field_data ) ) {
                echo '<table class="form-table">' . "\n";
                echo '<tbody>' . "\n";

                foreach ( $field_data as $k => $v ) {

                    if ( $v['section'] == $section ) {

                        $data = $v['default'];
                        if ( isset( $fields[ $k ] ) && isset( $fields[ $k ][0] ) ) {
                            $data = $fields[ $k ][0];
                        }

                        $type = $v['type'];

                        switch ( $type ) {

                            case 'url':
                                if ( $this->is_key_locked( $post_id ) ) {
                                    echo '<tr valign="top"><th scope="row"><label for="' . esc_attr( $k ) . '">' . esc_html( $v['name'] ) . '</label></th>
                                    <td>' . esc_attr( $data );
                                    echo '</td><tr/>' . "\n";
                                }
                                else {
                                    echo '<tr valign="top"><th scope="row"><label for="' . esc_attr( $k ) . '">' . esc_html( $v['name'] ) . '</label></th>
                                    <td><input name="' . esc_attr( $k ) . '" type="text" id="' . esc_attr( $k ) . '" class="regular-text" value="' . esc_attr( $data ) . '" /> <a onclick="jQuery(\'#' . esc_attr( $k ) . '\').val( window.location.hostname );">add this site</a>' . "\n";
                                    echo '<p class="description">' . esc_html( $v['description'] ) . '</p>' . "\n";
                                    echo '</td><tr/>' . "\n";
                                }

                                break;
                            case 'token':
                                if ( $this->is_key_locked( $post_id ) ) {
                                    echo '<tr valign="top"><th scope="row">' . esc_html( $v['name'] ) . '</th>
                                    <td style="-ms-word-break: break-all;
                                                 word-break: break-all;
                                                 word-break: break-word;
                                                 -webkit-hyphens: none;
                                                 -moz-hyphens: none;
                                                 -ms-hyphens: none;
                                                 hyphens: none;">' . esc_attr( $data ) . '</td><tr/>';
                                }
                                else {
                                    $data = self::generate_token();

                                    echo '<tr valign="top"><th scope="row"><label for="' . esc_attr( $k ) . '">' . esc_html( $v['name'] ) . '</label></th>
                                    <td><input name="' . esc_attr( $k ) . '" type="text" id="' . esc_attr( $k ) . '" class="regular-text" value="' . esc_attr( $data ) . '" /> <a style="" onclick="jQuery(\'#'.esc_attr( $k ).'\').val(\'\');">clear</a>' . "\n";
                                    echo '<p class="description">' . esc_html( $v['description'] ) . '</p>' . "\n";
                                    echo '</td><tr/>' . "\n";
                                }

                                break;

                            case 'text':
                                echo '<tr valign="top"><th scope="row"><label for="' . esc_attr( $k ) . '">' . esc_html( $v['name'] ) . '</label></th>
                                    <td><input name="' . esc_attr( $k ) . '" type="text" id="' . esc_attr( $k ) . '" class="regular-text" value="' . esc_attr( $data ) . '" />' . "\n";
                                echo '<p class="description">' . esc_html( $v['description'] ) . '</p>' . "\n";
                                echo '</td><tr/>' . "\n";

                                break;
                            case 'readonly':
                                echo '<tr valign="top"><th scope="row"><label for="' . esc_attr( $k ) . '">' . esc_html( $v['name'] ) . '</label></th>
                                    <td>' . esc_attr( $data );
                                echo '<input name="' . esc_attr( $k ) . '" type="hidden" id="' . esc_attr( $k ) . '" value="' . esc_attr( $data ) . '" /> ';
                                echo '<p class="description">' . esc_html( $v['description'] ) . '</p>' . "\n";
                                echo '</td><tr/>' . "\n";

                                break;

                            case 'select':
                                echo '<tr valign="top"><th scope="row">
                                <label for="' . esc_attr( $k ) . '">' . esc_html( $v['name'] ) . '</label></th>
                                <td>
                                <select name="' . esc_attr( $k ) . '" id="' . esc_attr( $k ) . '" class="regular-text">';
                                // Iterate the options
                                foreach ( $v['default'] as $vv ) {
                                    echo '<option value="' . esc_attr( $vv ) . '" ';
                                    if ( $vv == $data ) {
                                        echo 'selected';
                                    }
                                    echo '>' . esc_html( $vv ) . '</option>';
                                }
                                echo '</select>' . "\n";
                                echo '<p class="description">' . esc_html( $v['description'] ) . '</p>' . "\n";
                                echo '</td><tr/>' . "\n";
                                break;
                            case 'key_select':
                                echo '<tr valign="top"><th scope="row">
                                <label for="' . esc_attr( $k ) . '">' . esc_attr( $v['name'] ) . '</label></th>
                                <td>
                                <select name="' . esc_attr( $k ) . '" id="' . esc_attr( $k ) . '" class="regular-text">';
                                // Iterate the options
                                foreach ( $v['default'] as $kk => $vv ) {
                                    echo '<option value="' . esc_attr( $kk ) . '" ';
                                    if ( $kk == $data ) {
                                        echo 'selected';
                                    }
                                    echo '>' . esc_attr( $vv ) . '</option>';
                                }
                                echo '</select>' . "\n";
                                echo '<p class="description">' . esc_attr( $v['description'] ) . '</p>' . "\n";
                                echo '</td><tr/>' . "\n";
                                break;

                            default:
                                break;
                        }
                    }
                }
                echo '</tbody>' . "\n";
                echo '</table>' . "\n";
            }
        }

        public function meta_box_save( $post_id )
        {

            // Verify
            if ( get_post_type() != $this->post_type ) {
                return $post_id;
            }

            $key = $this->post_type . '_noonce';
            if ( isset( $_POST[ $key ] ) && ! wp_verify_nonce( sanitize_key( $_POST[ $key ] ), esc_attr( $this->post_type ) . '_noonce_action' ) ) {
                return $post_id;
            }

            if ( isset( $_POST['post_type'] ) && 'page' == sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) ) {
                if ( ! current_user_can( 'edit_page', $post_id ) ) {
                    return $post_id;
                }
            } else {
                if ( ! current_user_can( 'edit_post', $post_id ) ) {
                    return $post_id;
                }
            }

            if ( isset( $_GET['action'] ) ) {
                if ( $_GET['action'] == 'trash' || $_GET['action'] == 'untrash' || $_GET['action'] == 'delete' ) {
                    return $post_id;
                }
            }

            if ( isset( $_POST['reset-site'] ) ) {
                if ( current_user_can( 'edit_page', $post_id ) ) {
                    delete_post_meta( $post_id, 'url' );
                    delete_post_meta( $post_id, 'site1' );
                    delete_post_meta( $post_id, 'site2' );
                    delete_post_meta( $post_id, 'site_key' );

                    return $post_id;
                }
            }

            $field_data = $this->meta_box_custom_fields_settings();
            $fields = array_keys( $field_data );

            foreach ( $fields as $f ) {
                if ( ! isset( $_POST[ $f ] ) ) {
                    continue;
                }

                ${$f} = strip_tags( trim( sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) ) );

                // Escape and confirm format of the URL fields.
                if ( 'url' == $field_data[ $f ]['type'] ) {
                    if ( strpos( ${$f}, 'http' ) !== false || strpos( ${$f}, '//' ) !== false || strpos( ${$f}, '/' ) !== false ){
                        ${$f} = parse_url( ${$f}, PHP_URL_HOST );
                    }
                }

                if ( get_post_meta( $post_id, $f ) == '' ) {
                    add_post_meta( $post_id, $f, ${$f}, true );
                } elseif ( ${$f} != get_post_meta( $post_id, $f, true ) ) {
                    update_post_meta( $post_id, $f, ${$f} );
                } elseif ( ${$f} == '' ) {
                    delete_post_meta( $post_id, $f, get_post_meta( $post_id, $f, true ) );
                }
            }

            $this->build_cached_option(); // rebuilds cache for options

            return $post_id;
        }

        public function meta_box_custom_fields_settings()
        {
            $fields = [];

            // Public Info

            $fields['token'] = [
            'name'        => 'Token',
            'description' => 'If you have a token from another site, just clear token above and replace it.',
            'type'        => 'token',
            'default'     => self::generate_token(),
            'section'     => 'site',
            ];

            $fields['site1'] = [
            'name'        => 'Site 1',
            'description' => 'Use just the host name. Example: www.website.com',
            'type'        => 'url',
            'default'     => '',
            'section'     => 'site',
            ];

            $fields['site2'] = [
            'name'        => 'Site 2',
            'description' => 'Use just the host name. Example: www.website.com',
            'type'        => 'url',
            'default'     => '',
            'section'     => 'site',
            ];

            // @codingStandardsIgnoreLine
            return apply_filters( 'site_link_fields_settings', $fields );
        }

        public function meta_box_load_management_box()
        {
            global $pagenow, $post_id;

            $this->build_cached_option(); // rests cached version of the site_link_details

            // check if new
            if ( 'page-new.php' == $pagenow /* check if this is the post-new page */ ) {
                echo 'First save the record';
            } else {
                if ( $this->is_key_locked( $post_id, true ) /* check if key has been created and linked */ ) {

                    $this->meta_box_content( 'site' );

                    $site_key = get_post_meta( $post_id, 'site_key', true );

                    /**
                     * Verification link section
                     */
                    ?>
                    <hr>

                    <table width="100%">
                        <tr>
                            <td>
                                <span style="float:right">
                                    <?php esc_html_e( 'Status:' ) ?>
                                    <strong>
                                        <span id="<?php echo esc_attr( md5( $post_id ) ); ?>-status">
                                            <?php esc_html_e( 'Checking Status' ) ?>
                                        </span>
                                    </strong>
                                </span>
                                <p>
                                    <a class="button" onclick="jQuery('#reset-confirmation').toggle();" name="reset">Delete Current Site Configuration</a>
                                </p>
                                <p id="reset-confirmation" style="display:none;">
                                    <strong class="fail-red">Are you sure? This will permanently destroy the site token.</strong><br>
                                    <button class="button button-primary" type="submit" name="reset-site">Delete Site Configuration</button>
                                    <br>
                                </p>
                            </td>
                        </tr>
                        <tr id="<?php echo esc_attr( md5( $post_id ) ); ?>-message" style="display:none;">
                            <td>
                                <strong><?php esc_attr_e( 'Consider Checking:' ) ?></strong>
                                <ol>
                                    <li>
                                        <?php echo sprintf( esc_attr__( 'Check if the target site is setup with identical configuration information.' ), esc_attr( current_time( 'Y-m-dH', 1 ) ) ); ?>
                                    </li>
                                    <li>
                                        <?php echo esc_attr__( 'Check if HTTPS/SSL is enabled on both sites. Due to the transfer of data between these sites, SSL encryption is required for both sites to protect the data exchange.' ); ?>
                                    </li>
                                    <li>
                                        <?php echo esc_attr__( 'Check if the server timestamps are identical. Mismatched server times will cause decryption key failures. Your server timestamp' ); ?>
                                        :
                                        <span class="info-color"><strong><?php echo esc_attr( current_time( 'Y-m-dH', 1 ) ) ?></strong></span>
                                    </li>
                                </ol>
                            </td>
                        </tr>
                        <script>
                            jQuery(document).ready(function () {
                                check_link_status('<?php echo esc_attr( self::create_transfer_token_for_site( $site_key ) ); ?>', '<?php echo esc_attr( $this->get_non_local_site_by_id( $post_id ) ); ?>', '<?php echo esc_attr( md5( $post_id ) ); ?>');
                            })
                        </script>
                    </table>
                    <?php

                } else {
                    $this->meta_box_content( 'site' );
                }
            }
        }

        public function meta_box_configuration_box() {
            ?>
            <!-- Footer Information -->
            <p><?php esc_attr_e( 'Current Site' ) ?>: <span
                        class="info-color"><?php echo esc_html( self::get_current_site_base_url() ); ?></span></p>
            <p class="text-small"><?php esc_attr_e( 'Timestamp' ) ?>: <span
                        class="info-color"><?php echo esc_attr( current_time( 'Y-m-dH', 1 ) ) ?></span>
                <em>( <?php esc_attr_e( 'Compare this number to linked site. It should be identical.' ) ?> )</em></p>
            <?php
        }

        public static function admin_notice( $notice, $type )
        {
            echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>';
            echo esc_html( $notice );
            echo '</p></div>';
        }

        public function scripts()
        {
            echo "<script type='text/javascript'>
            
        function check_link_status( transfer_token, url, id ) {
            
        let linked = '" . esc_attr__( 'Linked' ) . "';
        let not_linked = '" . esc_attr__( 'Not Linked' ) . "';
        let not_found = '" . esc_attr__( 'Failed to connect with the URL provided.' ) . "';
        
        return jQuery.ajax({
            type: 'POST',
            data: JSON.stringify({ \"transfer_token\": transfer_token } ),
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            url: 'https://' + url + '/wp-json/dt-public/v1/sites/site_link_check',
        })
            .done(function (data) {
                if( data ) {
                    jQuery('#' + id + '-status').html( linked ).attr('class', 'success-green')
                } else {
                    jQuery('#' + id + '-status').html( not_linked ).attr('class', 'fail-red');
                    jQuery('#' + id + '-message').show();
                }
            })
            .fail(function (err) {
                jQuery( document ).ajaxError(function( event, request, settings ) {
                     if( request.status === 0 ) {
                        jQuery('#' + id + '-status').html( not_found ).attr('class', 'fail-red')
                     } else {
                        jQuery('#' + id + '-status').html( JSON.stringify( request.statusText ) ).attr('class', 'fail-red')
                     }
                });
            });
        }
        </script>";
            echo "<style>
                .success-green { color: limegreen;}
                .fail-red { color: red;}
                .info-color { color: steelblue; }
                .button-like-link-left { 
                    float: left; 
                    background: none !important;
                    color: inherit;
                    border: none;
                    padding: 0 !important;
                    font: inherit;
                    /*border is optional*/
                    cursor: pointer;
                    }
            </style>";
        }

        public function enter_title_here( $title )
        {
            if ( get_post_type() == $this->post_type ) {
                $title = __( 'Enter the title here' );
            }

            return $title;
        }

        public function is_key_locked( $post_id, $admin_notice = false ) : bool {
            if ( ! $post_id ) {
                return false;
            }
            $token = get_post_meta( $post_id, 'token', true );
            $site1 = get_post_meta( $post_id, 'site1', true );
            $site2 = get_post_meta( $post_id, 'site2', true );
            $site_key = get_post_meta( $post_id, 'site_key', true );

            if ( ! $token ) {
                return false;
            }
            if ( ! $site1 ) {
                return false;
            }
            if ( ! $site2 ) {
                return false;
            }

            $local_site = self::verify_one_site_is_local( $site1, $site2 );
            if ( ! $local_site ) {
                if ( $admin_notice ) {
                    self::admin_notice( 'Local site not found in submission. Either Site1 or Site2 must be this current website', 'error' );
                }
                return false;
            }

            if ( $site1 == $site2 ) {
                if ( $admin_notice ) {
                    self::admin_notice( 'Sites1 and Site2 cannot be the same site.', 'error' );
                }
                return false;
            }

            if ( ! $site_key ) {
                $id = add_post_meta( $post_id, 'site_key', self::generate_key( $token, $site1, $site2 ), true );
                if ( ! $id ) {
                    if ( $admin_notice ) {
                        self::admin_notice( 'Failed to create the site_key.', 'error' );
                    }
                    return false;
                }
            }
            return true;
        }

        public function build_cached_option() {
            global $wpdb;

            $results = $wpdb->get_results(  "
                SELECT
                  ID as id,
                  post.post_title as label,
                  meta2.meta_value as token,
                  meta3.meta_value as site1,
                  meta4.meta_value as site2,
                  meta1.meta_value as site_key
                FROM $wpdb->posts as post
                  JOIN $wpdb->postmeta as meta1 ON post.ID=meta1.post_id AND meta1.meta_key = 'site_key'
                  JOIN $wpdb->postmeta as meta2 ON post.ID=meta2.post_id AND meta2.meta_key = 'token'
                  JOIN $wpdb->postmeta as meta3 ON post.ID=meta3.post_id AND meta3.meta_key = 'site1'
                  JOIN $wpdb->postmeta as meta4 ON post.ID=meta4.post_id AND meta4.meta_key = 'site2'
                WHERE post.post_status = 'publish'
            ", ARRAY_A  );

            $site_keys = [];
            foreach ( $results as $result ) {
                $site_keys[$result['site_key']] = [
                'id'    => $result['id'],
                'label' => $result['label'],
                'token' => $result['token'],
                'site1' => $result['site1'],
                'site2' => $result['site2'],
                ];
            }

            update_option( self::$token . '_api_keys', $site_keys );
            return $site_keys;
        }

        public function get_site_key_by_id( $post_id ) {
            return get_post_meta( $post_id, 'site_key', true );
        }

        public function get_token_by_id( $post_id ) {
            return get_post_meta( $post_id, 'token', true );
        }

        public function get_non_local_site_by_id( $post_id ) {
            $site1 = get_post_meta( $post_id, 'site1', true );
            $site2 = get_post_meta( $post_id, 'site2', true );
            return self::get_non_local_site( $site1, $site2 );
        }

        public static function get_non_local_site( $site1, $site2 )
        {
            $local_site = self::get_current_site_base_url();
            if ( $local_site == $site1 ) {
                return $site2;
            } else {
                return $site1;
            }
        }


        /**
         * Checks if at least one of the sites begin submitted is the local site. This prevents trying to build a link
         * between two other sites.
         *
         * @param $site1
         * @param $site2
         *
         * @return bool
         */
        public static function verify_one_site_is_local( $site1, $site2 )
        {
            $local_site = self::get_current_site_base_url();
            if ( $local_site == $site1 ) {
                return true;
            }
            if ( $local_site == $site2 ) {
                return true;
            }

            return false;
        }

        /****************************************************************************************************************
         * REST ENDPOINT
         *
         * This REST endpoint supports the status verification on the edit and post pages.
         ****************************************************************************************************************/

        /**
         * Rest Registration for Site Link Check javascript
         */
        public function add_api_routes()
        {
            $version = '1';
            $namespace = 'dt-public/v' . $version;

            register_rest_route(
                $namespace, '/sites/site_link_check', [
                [
                'methods'  => WP_REST_Server::CREATABLE,
                'callback' => [ $this, 'site_link_check' ],
                ],
                ]
            );

            // Enable cross origin resource requests (CORS) for approved sites.
            self::add_cors_sites();
        }

        /**
         * Verify site is linked
         *
         * @param  WP_REST_Request $request
         *
         * @return string|WP_Error|array The contact on success
         */
        public function site_link_check( WP_REST_Request $request )
        {
            $params = $request->get_params();

            if ( isset( $params['transfer_token'] ) ) {
                $status = self::verify_transfer_token( $params['transfer_token'] );
                if ( $status ) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return new WP_Error( "site_check_error", "Malformed request", [ 'status' => 400 ] );
            }
        }

        /****************************************************************************************************************
         * MISCELLANEOUS SUPPORT FUNCTIONS
         ****************************************************************************************************************/

        /**
         * Generates the site key based on the token, site1, and site2 value.
         * This guarantees that the key is unique between the two sites.
         *
         * @param $token
         * @param $site1
         * @param $site2
         *
         * @return string
         */
        public static function generate_key( $token, $site1, $site2 )
        {
            return md5( $token . $site1 . $site2 );
        }

        public static function decrypt_transfer_token( $transfer_token )
        {

            $keys = self::get_site_keys();

            if ( empty( $keys ) ) {
                return false;
            }

            foreach ( $keys as $key => $array ) {
                if ( md5( $key . current_time( 'Y-m-dH', 1 ) ) == $transfer_token ) {
                    return $key;
                }
            }

            return false;
        }

        public static function filter_for_target_site( $value )
        {
            $local_site = self::get_current_site_base_url();
            if ( $local_site == $value['site1'] ) {
                return $value['site2'];
            } else {
                return $value['site1'];
            }
        }

        public static function filter_url( $url )
        {
            $url = sanitize_text_field( wp_unslash( $url ) );
            $url = str_replace( 'http://', '', $url );
            $url = trim( str_replace( 'https://', '', $url ) );

            return $url;
        }

        public static function generate_token( $length = 32 )
        {
            return bin2hex( random_bytes( $length ) );
        }

        protected static function get_current_site_base_url()
        {
            $url = str_replace( 'http://', '', home_url() );
            $url = str_replace( 'https://', '', $url );

            return trim( $url );
        }

        private function flush_rewrite_rules()
        {
            $this->register_post_type();
            flush_rewrite_rules();
        }

        public static function activation()
        {
            Site_Link_System::instance()->flush_rewrite_rules();
        }

        public static function deactivate()
        {
            $prefix = self::$token;
            delete_option( $prefix . '_api_keys' );
        }

        /**
         * Variables and Singleton
         */
        public $post_type;
        public $singular;
        public $plural;
        public $menu_position;
        public $dashicon;
        private static $_instance = null;
        public static function instance()
        {
            if ( is_null( self::$_instance ) ) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        /**
         * Site_Link_System constructor.
         *
         * @param int    $menu_position
         * @param string $dashicon
         */
        public function __construct(
        $menu_position = 5,
        $dashicon = 'dashicons-admin-links'
        )
        {
            $this->post_type = self::$token;
            $this->singular = 'Site Link';
            $this->plural = 'Site Links';
            $this->menu_position = $menu_position;
            $this->dashicon = $dashicon;

            add_action( 'init', [ $this, 'register_post_type' ] );
            add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );

            if ( is_admin() ) {
                global $pagenow;

                add_action( 'admin_head', [ $this, 'scripts' ], 20 );
                add_action( 'admin_menu', [ $this, 'meta_box_setup' ], 20 );
                add_action( 'save_post', [ $this, 'meta_box_save' ] );
                add_filter( 'enter_title_here', [ $this, 'enter_title_here' ] );
                add_filter( 'post_updated_messages', [ $this, 'post_type_updated_messages' ] );

                if ( $pagenow == 'edit.php' && isset( $_GET['post_type'] ) ) {
                    $pt = sanitize_text_field( wp_unslash( $_GET['post_type'] ) );
                    if ( $pt === $this->post_type ) {
                        add_filter( 'manage_edit-' . $this->post_type . '_columns', [ $this, 'register_custom_column_headings' ], 10, 1 );
                        add_action( 'manage_posts_custom_column', [ $this, 'register_custom_columns' ], 10, 2 );
                    }
                }
            }
        } // End __construct()

    } // End Class
}