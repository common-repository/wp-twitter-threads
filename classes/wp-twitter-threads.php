<?php

namespace LNJ;

class WPTT {
    private static $instance;
    private $rewrite_rules;

    /**
     * construct
     */
    public function __construct() {

        add_action( 'admin_init', [ $this, 'wptt_plugin_version' ] );
        add_action( 'admin_init', [ $this, 'wptt_settings_init' ] );
        add_action( 'admin_init', [ $this, 'wptt_add_editor_styles' ] );

        add_action( 'init', [ $this, 'wptt_add_rewrite_rules' ] );
        add_action( 'init', [ $this, 'wptt_load_textdomain' ] );

        add_action( 'init', [ $this, 'wptt_register_block' ] );

        add_action( 'admin_notices', [ $this, 'wptt_admin_notices' ] );
        add_action( 'admin_menu', [ $this, 'wptt_settings_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'wptt_enqueue_scripts_base' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'wptt_admin_enqueue_scripts' ] );

        add_action( 'wp', [ $this, 'wptt_rewrite_process' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'wptt_enqueue_scripts_base' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'wptt_index_enqueue_scripts' ] );
        add_action( 'wp_head', [ $this, 'wptt_add_scripts' ] );

        add_action( 'add_option_wptt_settings_update', [ $this, 'wptt_settings_update'], 9999 );
        add_action( 'update_option_wptt_settings_update', [ $this, 'wptt_settings_update'], 9999 );

        add_action( 'add_meta_boxes',  [ $this, 'wptt_add_meta_box'] );
        add_action( 'wp_ajax_wptt_add_thread_to_post', [ $this, 'wptt_add_thread_to_post' ] );

        add_filter( 'query_vars', [ $this, 'wptt_add_vars' ] );

        register_activation_hook( WPTT_PLUGIN_FILE, [ $this, 'wptt_install' ] );
        register_deactivation_hook( WPTT_PLUGIN_FILE, [ $this, 'wptt_uninstall' ] );

        $this->rewrite_rules = [
            [
                'regex' => '^' . WPTT_NAMESPACE . '\/' . WPTT_SLUG . '/callback/?$',
                'query' => 'index.php?wptt_rewrite=callback',
                'after' => 'top',
            ],
            [
                'regex' => '^' . WPTT_NAMESPACE . '\/' . WPTT_SLUG . '/?$',
                'query' => 'index.php?wptt_rewrite=wptt',
                'after' => 'top',
            ],
        ];
    }

    public function wptt_register_block() {
        $asset_file = include( WPTT_PATH . '/assets/js/block.asset.php' );
        wp_register_script(
            'wptt-block',
            WPTT_URL . 'assets/js/block.js',
            $asset_file['dependencies'],
            $asset_file['version']
        );

        wp_register_style(
            'wptt-block-editor-style',
            WPTT_URL . 'assets/css/block.css',
            ['wp-edit-blocks'],
            filemtime( WPTT_PATH . '/assets/css/block.css' )
        );

        wp_register_style(
            'wptt-block-front-style',
            WPTT_URL . 'assets/css/block.css',
            [],
            filemtime( WPTT_PATH . '/assets/css/block.css' )
        );

        register_block_type( 'lnj/wptt-block', array(
            'editor_script' => 'wptt-block',
            'editor_style' => 'wptt-block-editor-style',
            'style' => 'wptt-block-front-style',
        ) );
    }

    /*
     * Singleton
     */
    public static function run() {
        if ( !isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /*
     * install
     */
    public function wptt_install() {
        update_option( 'wptt_install', true );
        update_option( 'wptt_install_date', date_i18n( 'Y-m-d H:i:s' ) );
        update_option( 'wptt_uninstall_date', null );

        self::wppt_install_mu_files();

        self::wppt_add_htaccess_rules();

        //
        if ( isset( $this->rewrite_rules ) && is_array( $this->rewrite_rules ) && count( $this->rewrite_rules ) > 0 ) {
            foreach( $this->rewrite_rules as $rewrite_rule ) {
                add_rewrite_rule( $rewrite_rule['regex'], $rewrite_rule['query'], $rewrite_rule['after'] );
            }
        }
        //
        flush_rewrite_rules();
    }

    /**
     *
     */
    public function wptt_uninstall() {
        update_option( 'wptt_install', false );
        update_option( 'wptt_install_date', null );
        update_option( 'wptt_uninstall_date', date_i18n( 'Y-m-d H:i:s' ) );

        self::wppt_uninstall_mu_files();

        self::wppt_remove_htaccess_rules();
    }

    /**
     * check version and process what needed
     */
    public function wptt_plugin_version() {
        // Change each time the plugin is up :
        $wptt_plugin_version_current = get_option( 'wptt_plugin_version' );
        if (
            false === $wptt_plugin_version_current ||
            ( ! empty( $wptt_plugin_version_current ) && version_compare( $wptt_plugin_version_current, WPTT_PLUGIN_VERSION, '<' ) )
        ) {
            update_option( 'wptt_plugin_version', WPTT_PLUGIN_VERSION );

            self::wppt_install_mu_files();

            self::wppt_add_htaccess_rules();
        }
    }

    /**
     * 
     */
    public static function wppt_install_mu_files() {
        $filesystem = self::get_filesystem();

        $filename = WPMU_PLUGIN_DIR . '/wp-twitter-threads.php';

        if ( $filesystem->exists( $filename ) ) { // if exists return
            return;
        }

        $mu_plugin_path = WPTT_PATH . '/mu-plugin-template/wp-twitter-threads.php';
        $contents = $filesystem->get_contents( $mu_plugin_path );  // get mu-plugin file content

        if ( ! $filesystem->exists( WPMU_PLUGIN_DIR ) ) {  // if not exists create mu-plugins dir
            $filesystem->mkdir( WPMU_PLUGIN_DIR );
        }

        if ( ! $filesystem->exists( WPMU_PLUGIN_DIR ) ) {  // if still not exists, give up
            return;
        }

        $filesystem->put_contents( $filename, $contents );   // it's good, copy that
    }

    /**
     * 
     */
    public function wppt_uninstall_mu_files() {
        $filesystem = self::get_filesystem();

        // Remove worker
        $filename = WPMU_PLUGIN_DIR . '/wp-twitter-threads.php';
        if ( $filesystem->exists( $filename ) ) {
            $filesystem->delete( $filename );
        }
    }

    /**
     * 
     */
    public function wppt_add_htaccess_rules() {
        global $wp_rewrite;

        // Ensure get_home_path() is declared.
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $home_path = get_home_path();
        $htaccess_file = $home_path . '.htaccess';
        $new_rules = [];

        /*
         * If the file doesn't already exist check for write access to the directory
         * and whether we have some rules. Else check for write access to the file.
         */
        if ( ( ! file_exists( $htaccess_file ) && is_writable( $home_path ) && $wp_rewrite->using_mod_rewrite_permalinks() ) || is_writable( $htaccess_file ) ):
            if ( got_mod_rewrite() ):
                $new_rules[] = 'RewriteEngine On';
                $new_rules[] = 'RewriteRule ^' . WPTT_NAMESPACE . '/' . WPTT_SLUG . '/callback/? /index.php?wptt_rewrite=callback [L,QSA]';
                $new_rules[] = 'RewriteRule ^' . WPTT_NAMESPACE . '/' . WPTT_SLUG . '/? /index.php?wptt_rewrite=wptt [L,QSA]';
                
                insert_with_markers( $htaccess_file, WPTT_SLUG_CAMELCASE, $new_rules );
            endif;
        endif;
   }

    /**
     * 
     */
    public function wppt_remove_htaccess_rules() {
        global $wp_rewrite;

        $home_path = get_home_path();
        $htaccess_file = $home_path . '.htaccess';

        $filesystem = self::get_filesystem();

        $skip = false;

        if ( $filesystem->exists( $htaccess_file ) ):
            $contents_new = [];
            $contents = $filesystem->get_contents( $htaccess_file );
            $contents = preg_replace( '/\# BEGIN WPTwitterThreads(.)+\# END WPTwitterThreads/is', '', $contents );
            $filesystem->put_contents( $htaccess_file, $contents );
        endif;
   }

    /*
     * Add query vars
     */
    public function wptt_add_vars( $vars ) {
        $vars[] = 'wptt_rewrite';
        //
        return $vars;
    }

    /*
     * Maintain rewrite rules
     */
    public function wptt_add_rewrite_rules() {
        if ( isset( $this->rewrite_rules ) && is_array( $this->rewrite_rules ) && count( $this->rewrite_rules ) > 0 ) {
            foreach( $this->rewrite_rules as $rewrite_rule ) {
                add_rewrite_rule( $rewrite_rule['regex'], $rewrite_rule['query'], $rewrite_rule['after'] );
            }
        }
    }

    /*
     * Display rewrite content needed
     */
    public function wptt_rewrite_process() {
        $wptt_rewrite = get_query_var( 'wptt_rewrite' );

        http_response_code( 200 );

        if ( ! empty( $wptt_rewrite ) && 'wptt' === $wptt_rewrite ) {
            require WPTT_PATH . '/rewrites/wp-twitter-threads.php';
            exit;
        }

        if ( ! empty( $wptt_rewrite ) && 'callback' === $wptt_rewrite ) {
            require WPTT_PATH . '/rewrites/callback.php';
            exit;
        }
    }

    /*
     * load textdomain
     */
    public function wptt_load_textdomain() {
        load_plugin_textdomain( 'wp-twitter-threads', false, WPTT_PATH . '/languages' );
    }

    /*
     * enqueue scripts
     */
    public function wptt_enqueue_scripts_base() {
        wp_enqueue_style( 'wp-twitter-threads-front', WPTT_URL . 'assets/css/front.css', [], WPTT_PLUGIN_VERSION, 'all' );
    }

    public function wptt_admin_enqueue_scripts() {
        wp_enqueue_style( 'wp-twitter-threads-admin', WPTT_URL . 'assets/css/admin.css', [], WPTT_PLUGIN_VERSION, 'all' );
        wp_enqueue_script( 'wp-twitter-threads-admin', WPTT_URL . 'assets/js/admin.js', ['jquery'], WPTT_PLUGIN_VERSION, false );

        $this->wptt_enqueue_scripts_translations( 'wp-twitter-threads-admin' );
    }

    public function wptt_index_enqueue_scripts() {
        wp_enqueue_script( 'wp-twitter-threads-front', WPTT_URL . 'assets/js/front.js', ['jquery'], WPTT_PLUGIN_VERSION, false );

        $this->wptt_enqueue_scripts_translations( 'wp-twitter-threads-front' );
    }

    public function wptt_enqueue_scripts_translations( $handle ) {
        $wptt_translations = [
            'error_undefined' => __( 'Sorry, an error occured.', 'wp-twitter-threads' ),
            'processing' => __( 'Processing ...', 'wp-twitter-threads' ),
            'check' => __( 'Check', 'wp-twitter-threads' ),
            'finish' => __( 'Finish', 'wp-twitter-threads' ),
        ];
        wp_localize_script( $handle, 'wp_twitter_threads', $wptt_translations );
    }

    /*
     * add scripts in footer
     */
    public function wptt_add_scripts() {
        $current_lang = current( explode( '_', get_locale() ) );
?>
        <script>
        	var wptt_current_lang = '<?php echo $current_lang;?>';
        </script>
<?php
    }

    /*
     * Admin notices
     */
    public function wptt_admin_notices() {
        $screen = get_current_screen();
        if ( isset( $screen->parent_file ) && 'wptt-page' == $screen->parent_file ):
            if ( isset( $_GET['status'] ) && 'success' == $_GET['status'] ):
                if ( get_option( 'wptt_consumer_key' ) && get_option( 'wptt_consumer_secret' ) ):
                    add_settings_error( 'wptt-notices', 'wptt-success', __( 'Settings updated with success.', 'wp-twitter-threads' ), 'success' ); // 'error', 'success', 'warning', 'info'
                endif;
            endif;

            if ( isset( $_GET['status'] ) && 'parameters-missing' == $_GET['status'] ):
                add_settings_error( 'wptt-notices', 'wptt-success', __( 'Some parameters are missing.', 'wp-twitter-threads' ), 'error' );
            endif;
        endif;
    }

    /*
     * Admin : add settings page
     */
    public function wptt_add_editor_styles() {
        add_editor_style( WPTT_URL . 'assets/css/wptt.css' );
    }

    /*
     * Admin : add settings page
     */
    public function wptt_settings_page() {
        add_menu_page(
            __( 'WP TW Threads - settings', 'wp-twitter-threads' ),  // Page title
            __( 'WP TW Threads', 'wp-twitter-threads' ),             // Menu title
            'manage_options',                                        // Capability
            'wptt-page',                                             // Slug of setting page
            [ $this, 'wptt_settings_page_content' ],                 // Call Back function for rendering
                                                                     // icon URL
                                                                     // position
        );
    }

    /*
     *
     */
    public function wptt_settings_init() {
        add_settings_section(
            'wptt-settings-section',                                 // id of the section
            __( 'WP TW Threads - settings', 'wp-twitter-threads' ),  // title to be displayed
            '',                                                      // callback function to be called when opening section
            'wptt-page'                                              // page on which to display the section, this should be the same as the slug used in add_submenu_page()
        );
        //
        register_setting(
            'wptt-page',
            'wptt_settings_update'
        );
        register_setting(
            'wptt-page',
            'wptt_consumer_key'
        );
        register_setting(
            'wptt-page',
            'wptt_consumer_secret'
        );
        //
        $wptt_consumer_key = get_option( 'wptt_consumer_key' );
        add_settings_field(
            'wptt_consumer_key',                // id of the settings field
            'Twitter API Consumer Key',         // title
            [ $this, 'wptt_render_field' ],          // callback function
            'wptt-page',                        // page on which settings display
            'wptt-settings-section',            // section on which to show settings
            [
                'type' => 'text',
                'name' => 'wptt_consumer_key',
                'value' => $wptt_consumer_key,
            ]
        );
        //
        $wptt_consumer_secret = get_option( 'wptt_consumer_secret' );
        add_settings_field(
            'wptt_consumer_secret',
            'Twitter API Consumer Secret',
            [ $this, 'wptt_render_field' ],
            'wptt-page',
            'wptt-settings-section',
            [
                'type' => 'text',
                'name' => 'wptt_consumer_secret',
                'value' => $wptt_consumer_secret,
            ]
        );
    }

    /*
     *
     */
    public function wptt_render_field( $args ) {
        $value = ( isset( $args['value'] ) && ! empty( $args['value'] ) ) ? esc_attr( $args['value'] ) : '';
        switch( $args['type'] ) {
            default:
?>
                <input id="title" type="text" name="<?php echo esc_attr( $args['name'] ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
<?php
                break;
        }
    }

    /*
     *
     */
    public function wptt_settings_page_content() {
        // check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $oauth_callback = get_site_url() . '/' . WPTT_NAMESPACE . '/' . WPTT_SLUG . '/callback';
?>
        <div class="wrap">
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php wp_nonce_field( 'wptt-settings', 'wptt_settings' ); ?>
                <?php settings_fields('wptt-page');?>
                <?php do_settings_sections('wptt-page')?>

                <input type="hidden" id="wptt_settings_update" name="wptt_settings_update" value="<?php echo date_i18n('Ymd H:i:s');?>" />
<?php
                $check = self::wptt_is_twitter_api_connected();
                if ( $check ):
?>
                <div class="notice notice-info"><p>
                    <?php echo sprintf( __( 'You are well connected to Twitter API as <strong>%s</strong>.', 'wp-twitter-threads' ), $check->screen_name );?>
                </p></div>
<?php
                endif;
?>
                <p>
<?php                    
                    echo sprintf( __( 'To avoid a fatal error, in your Twitter API Settings, don\'t forget to put <strong>&laquo; %s &raquo;</strong> in the Callback URLs allowed, and <strong>&laquo; %s &raquo;</strong> in the Website URL field.', 'wp-twitter-threads' ), $oauth_callback, get_site_url() );
?>                    
                </p>

                <?php submit_button();?>
            </form>
        </div>
        <?php
    }

    /*
     * Update options
     */
    public function wptt_settings_update() {
        if ( isset( $_POST[ 'wptt_settings' ] ) ) {
            if ( ! wp_verify_nonce( $_POST['wptt_settings'], 'wptt-settings' ) ) {
                wp_die( __( 'Sorry, your nonce did not verify.', 'wp-twitter-threads' ) );
            }
            else {
                if ( ! empty( $_POST['wptt_consumer_key'] ) && ! empty( $_POST['wptt_consumer_secret'] ) ):
                    // force update
                    update_option( 'wptt_consumer_key', sanitize_option( 'wptt_consumer_key', $_POST['wptt_consumer_key'] ) );
                    update_option( 'wptt_consumer_secret', sanitize_option( 'wptt_consumer_secret', $_POST['wptt_consumer_secret'] ) );
                    //
                    $twitter = new \Abraham\TwitterOAuth\TwitterOAuth( $_POST['wptt_consumer_key'], $_POST['wptt_consumer_secret'] );
                    $twitter->setTimeouts(20, 30);
                    //
                    $oauth_callback = get_site_url() . '/' . WPTT_NAMESPACE . '/' . WPTT_SLUG . '/callback';
                    // request token of application
                    $request_token = $twitter->oauth(
                        'oauth/request_token', [
                            'oauth_callback' => $oauth_callback
                        ]
                    );
                    // throw exception if something gone wrong
                    if( $twitter->getLastHttpCode() != 200 ):
                        wp_die( __( 'There was a problem performing this API request.', 'wp-twitter-threads' ) );
                    else:
                        if ( ! empty( $request_token ) && is_array( $request_token ) && count( $request_token ) > 0 ):
                            update_option( 'wptt_oauth_token', sanitize_option( 'wptt_oauth_token', $request_token['oauth_token'] ) );
                            update_option( 'wptt_oauth_token_secret', sanitize_option( 'wptt_oauth_token_secret', $request_token['oauth_token_secret'] ) );
                            //
                            $url = $twitter->url(
                                'oauth/authorize', [
                                    'oauth_token' => $request_token['oauth_token']
                                ]
                            );
                            $url = esc_url( $url );
                            wp_redirect( $url );
                            exit;
                        endif;
                    endif;
                endif;
            }
        }
    }

    /*
     *
     */
    public static function wptt_is_twitter_api_connected() {
        $twitter = new \Abraham\TwitterOAuth\TwitterOAuth( WPTT_CONSUMER_KEY, WPTT_CONSUMER_SECRET, WPTT_ACCESS_TOKEN, WPTT_ACCESS_TOKEN_SECRET );
        $twitter->setTimeouts(20, 30);
        
        $check = $twitter->get( 'account/verify_credentials', [ 'include_entities' => true ] );

        if ( isset( $check->errors ) && isset( $check->errors[0]->code ) ):
            return false;
        else:
            return $check;
        endif;
    }

    /*
     *
     */
    public function wptt_add_meta_box() {
        global $post;

        if ( ( function_exists( 'has_blocks' ) && ! has_blocks( $post->ID ) ) || ! function_exists( 'has_blocks' ) ) {
            add_meta_box(
                'wptt_meta_box', 'WPTT', [ $this, 'wptt_meta_box' ], 'post', 'side', 'high'
            );
        }
    }

    /*
     *
     */
    public function wptt_meta_box() {
        global $post;

        wp_nonce_field( 'wptt-tweet-url-nonce', 'wptt_tweet_url_nonce' );
    ?>
        <label class="screen-reader-text" for="wptt_tweet_url"><?php _e( 'Type the tweet URL or its ID', 'wp-twitter-threads' );?></label>
        <input type="text" id="wptt_tweet_url" name="wptt_tweet_url" value="<?php echo esc_attr( get_post_meta( $post->ID, 'wptt_tweet_url', 1 ) );?>" placeholder="<?php _e( 'Tweet URL or its ID', 'wp-twitter-threads' );?>" />
        <input type="button" id="wptt_tweet_button" name="wptt_tweet_button" value="<?php _e( 'Retrieve', 'wp-twitter-threads' );?>" class="button" />
        <div id="wppt-loading">
            <img src="<?php echo esc_url( includes_url() . 'js/thickbox/loadingAnimation.gif' ); ?>" />
        </div>
        <div id="wptt-result"></div>
    <?php
    }

    /*
     *
     */
    public static function wptt_is_tweet_exist( $tweet, $force = null ) { // $tweet url or ID
        preg_match( '/(\/)?([0-9]+)(\?s=(.)*)?/', $tweet, $matches_urls );
        $tweet_id = ( isset( $matches_urls[2] ) && ! empty( $matches_urls[2] ) ) ? $matches_urls[2] : null;

        if ( $tweet_id ):
            //
            $twitter = new \Abraham\TwitterOAuth\TwitterOAuth( WPTT_CONSUMER_KEY, WPTT_CONSUMER_SECRET, WPTT_ACCESS_TOKEN, WPTT_ACCESS_TOKEN_SECRET );
            $twitter->setTimeouts(20, 30);

            $params = [
                'id' => $tweet_id,
                'include_entities' => true,
                'tweet_mode' => 'extended'
            ];

            $tweet = $twitter->get( 'statuses/show', $params );

            if ( $tweet ):
                if ( isset( $tweet->user->id ) && $tweet->user->id == get_option( 'wptt_user_id' ) ):
                    return $tweet;
                else:
                    if ( isset( $force ) ):
                        return $tweet;
                    else:
                        return false;
                    endif;
                endif;
            endif;
        endif;

        return false;
    }

    /*
     *
     */
    public static function wptt_get_tweets( $tweet_first, $tweet_max = null, $statuses_previous = [] ) {
        if ( ! isset( $tweet_first->id ) ):
            return $statuses_previous;
        else:
            if ( isset( $tweet_first->user->id ) && $tweet_first->user->id == get_option( 'wptt_user_id' ) ):
                $twitter = new \Abraham\TwitterOAuth\TwitterOAuth( WPTT_CONSUMER_KEY, WPTT_CONSUMER_SECRET, WPTT_ACCESS_TOKEN, WPTT_ACCESS_TOKEN_SECRET );
                $twitter->setTimeouts(20, 30);
                //
                $params = [
                    'user_id' => $tweet_first->user->id,
                    'count' => 200,
                    'include_entities' => true,
                    'tweet_mode' => 'extended',
                ];

                if ( isset( $tweet_max->id ) && ! is_null( $tweet_max->id ) ) {
                    $params['max_id'] = $tweet_max->id;
                }

                $results = $twitter->get( 'statuses/user_timeline', $params );
                if ( isset( $results->errors ) && is_array( $results->errors ) ):
                    return $statuses_previous;
                else:
                    if ( $results ):
                        if ( count( $results ) == 0 ):
                            return $statuses_previous;
                        elseif ( count( $results ) == 1 ):
                            if ( current($results)->id != $tweet_first->id ):
                                return $statuses_previous;
                            endif;
                        else:
                            $statuses = [];
                            //
                            foreach( $results as $result ):
                                if ( ! empty( $result->in_reply_to_status_id ) ):
                                    $statuses[] = $result;
                                endif;
                                //
                                if ( $result->id == $tweet_first->id ):
                                    // echo '$tweet_first->full_text = ' . $tweet_first->full_text . '<br />';
                                    $statuses_previous = array_merge( $statuses_previous, $statuses );
                                    // echo count( $statuses_previous ) . '<br />';
                                    // echo '<hr />';
                                    return $statuses_previous;
                                else:
                                    $tweet_max = $result;
                                endif;
                            endforeach;
                            //
                            $statuses_previous = array_merge( $statuses_previous, $statuses );
                            return self::wptt_get_tweets( $tweet_first, end( $results ), $statuses );
                        endif;
                    endif;
                endif;
            endif;
        endif;

        return $statuses_previous;
    }


    /*
     *
     */
    public static function wptt_process_tweets( $statuses, $tweet_first ) {
        $statuses_final = [];
        $in_reply_to_status_id = ( isset( $tweet_first->id ) ) ? $tweet_first->id : -1;

        // $statuses[] = $tweet_first;
        // foreach( $statuses as $status ) { echo '>>> ' . $status->full_text . '<br />'; }
        // echo '<hr />';

        if ( is_array( $statuses ) && count( $statuses ) > 0 ):
            $statuses = array_reverse( $statuses );
            foreach( $statuses as $status ):
                if ( ( isset( $status->in_reply_to_status_id ) && $status->in_reply_to_status_id == $in_reply_to_status_id ) ):
                    $statuses_final[] = $status;
                    $in_reply_to_status_id = $status->id;
                endif;
            endforeach;
        endif;
        array_unshift( $statuses_final, $tweet_first );

        // foreach( $statuses_final as $status ) { echo '=> ' . $status->full_text . '<br />'; }
        // echo '<hr />';

        return $statuses_final;
    }

    /*
     *
     */
    public static function wptt_parse_tweet( $status ) {
        $statuses_text = [];
        $hashtags = [];
        $images = [];
        $video = []; // video or gif (on Twitter gif are stored like mp4)
        $preview_url = '';

        preg_match_all( '@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^-_;,\.\s])?)?)@', $status->full_text, $matches_urls ); // to remove only last url

        if ( isset( $matches_urls[0] ) && is_array( $matches_urls[0] ) && count( $matches_urls[0] ) > 0 ):
            if ( count( $matches_urls[0] ) > 1 ):
                $status->full_text = str_replace( end( $matches_urls[0] ), '', $status->full_text );
                $preview_url = self::wptt_reverse_shortener( end( $matches_urls[0] ), $status );
            elseif ( count( $matches_urls[0] ) == 1 ):
                $status->full_text = str_replace( current( $matches_urls[0] ), '', $status->full_text );
                $preview_url = self::wptt_reverse_shortener( current( $matches_urls[0] ), $status );
            endif;
        endif;

        $status->full_text = preg_replace( '/(\R){2,}/', '$1$1', $status->full_text ); // replace double empty lines or more by double line
        $statuses_text[] = make_clickable( nl2br( $status->full_text ) );

        if ( isset($status->entities->hashtags) && sizeof($status->entities->hashtags) > 0 ):
            foreach ($status->entities->hashtags as $hastag):
                $hashtags[] = $hastag->text;
            endforeach;
        endif;

        if ( isset( $status->extended_entities->media ) && count( $status->extended_entities->media ) > 0 ):
            foreach( $status->extended_entities->media as $media ):
                if ( $media->type == 'photo' ):
                    $images[] = [
                        'url' => $media->media_url_https,
                        'link' => $media->media_url_https
                    ];
                elseif ( in_array( $media->type, [ 'video', 'animated_gif' ] ) ):
                    $video = [
                        'src' => current( $media->video_info->variants )->url,
                        'poster' => $media->media_url_https,
                        'autoplay' => ( 'animated_gif' == $media->type ) ? 1 : 0,
                        'playsInline' => ( 'animated_gif' == $media->type ) ? 1 : 0,
                        'loop' => ( 'animated_gif' == $media->type ) ? 1 : 0,
                    ];
                endif;
            endforeach;
        endif;

        if ( count( $images ) > 0 ):
                $statuses_text[] = [
                    'type' => 'gallery',
                    'content' => $images,
                    'linkTo' => 'media',
                ];
        endif;

        if ( count( $video ) > 0 ):
                $statuses_text[] = [
                    'type' => 'video',
                    'content' => $video,
                ];
        endif;

        if ( $status->is_quote_status ):
            $preview_url_content = self::wptt_get_preview_content( $status->quoted_status_permalink->expanded, $status->quoted_status );
            if ( $preview_url_content ):
                $statuses_text[] = $preview_url_content;
            endif;
        else:
            if ( ! empty( $preview_url ) ):
                $preview_url_content = self::wptt_get_preview_content( $preview_url );
                if ( $preview_url_content ):
                    $statuses_text[] = $preview_url_content;
                endif;
            endif;
        endif;

        return [
            'statuses_text' => $statuses_text,
            'hashtags' => $hashtags,
            'images' => $images,
            'video' => $video,
        ];
    }

    /*
     * 
     */
    public static function wptt_reverse_shortener( $url_short, $status = null ) {
        if ( isset( $status->entities->urls ) && count( $status->entities->urls ) > 0 ):
            foreach( $status->entities->urls as $url ):
                if ( $url_short == $url->url ):
                    return $url->expanded_url;
                endif;
            endforeach;
        endif;

        if ( isset( $status->entities->medias ) && count( $status->entities->medias ) > 0 ):
            foreach( $status->entities->medias as $media ):
                if ( $url_short == $media->url ):
                    return $media->expanded_url;
                endif;
            endforeach;
        endif;

        return false;
    }

    /*
     *
     */
    public static function wptt_get_preview_content( $url, $result = null ) {
        if ( ! empty( $url ) ):
            if ( ! preg_match( '/http(s)?:\/\/twitter.com/', $url, $check ) ):
                $previewClient = new \Dusterio\LinkPreview\Client( $url );
                $previews = $previewClient->getPreviews();
                $preview = $previewClient->getPreview( 'general' );
                $preview = $preview->toArray();
                if ( $preview && is_array( $preview ) && count( $preview ) ):
    
                    $preview_cover = $preview['cover'];
    
                    if ( empty( $preview_cover ) && isset( $preview['images'][0] ) && ! empty( $preview['images'][0] ) ):
                        $preview_cover = $preview['images'][0];
                    endif;
    
                    $preview_content = '<span class="wptt-preview">';
    
                    $preview_content .= '<a href="' . $url . '" class="wptt-preview-title">' . $preview['title'] . '</a>';
                    $preview_content .= '<span class="wptt-preview-description">' . $preview['description'] . '</span>';

                    if (
                        preg_match( '/youtube.com\/watch/', $url ) ||
                        preg_match( '/dailymotion.com\/video/', $url ) ||
                        preg_match( '/vimeo.com\/[0-9]+/', $url )
                    ):
                        $preview_content .= '[embed]' . $url . '[/embed]';
                    else:
                        if ( ! empty( $preview_cover ) ):
                            $preview_content .= '<span class="wptt-preview-cover"><a href="' . $url . '"><img src="' . $preview_cover . '" alt="" /></a></span>';
                        endif;
                    endif;
    
                    $preview_content .= '</span>';
    
                    return $preview_content;
                endif;
            else:
                if ( is_null( $result ) ):
                    $result = self::wptt_is_tweet_exist( $url, true );
                endif;

                if ( $result ):
                    $status_parsed = self::wptt_parse_tweet( $result );

                    $statuses_text = $status_parsed['statuses_text'];
                    $images = $status_parsed['images'];
                    $video = $status_parsed['video'];

                    $preview_content = '<span class="wptt-preview">';
                    $preview_content .= '<a href="' . $url . '" class="wptt-preview-title"><span class="wptt-twitter-avatar"><img src="' . $result->user->profile_image_url . '"></span> @' . $result->user->screen_name . '</a>';
                    if ( !empty( $result->full_text ) ):
                        $preview_content .= '<span class="wptt-preview-description">' . make_clickable( nl2br( $result->full_text ) ) . '</span>';
                    endif;
                    if ( isset( current( $images )['url'] ) ):
                        $preview_content .= '<span class="wptt-preview-cover"><a href="' . current( $images )['url'] . '"><img src="' . current( $images )['url'] . '" alt="" /></a></span>';
                    endif;
                    if ( isset( $video['src'] ) ):
                        $_autoplay = ( ! empty( $video['autoplay'] ) ) ? ' autoplay="1"' : '';
                        $_playsInline = ( ! empty( $video['playsInline'] ) ) ? ' playsInline="1"' : '';
                        $_loop = ( ! empty( $video['loop'] ) ) ? ' loop="1"' : '';
                        $preview_content .= '<span class="wptt-preview-cover"><video controls="1"'.$_autoplay.$_playsInline.$_loop.' poster="'.$video['poster'].'"><source src="'.$video['src'].'" type="video/mp4"></video></span>';
                    endif;
                    $preview_content .= '</span>';
                    return $preview_content;
                endif;
            endif;
        endif;
        return false;
    }

    /*
     *
     */
    public function wptt_add_thread_to_post() {
        $error = 1;
        $post_id = 0;
        $message = __( 'Error, parameters missing.', 'wp-twitter-threads' );
        $edit_post_link = '';
        $statuses_text = [];

        ob_start();

        // clean
        $post_id = intval( $_POST['post_id'] );
        $wptt_tweet_url = esc_url( $_POST['wptt_tweet_url'] );

        if ( $post_id > 0 ):
            // if ( ! wp_verify_nonce( $_POST['wptt_tweet_url_nonce'], 'wptt-tweet-url-nonce' ) ):
            //     $message = $message_text = __( 'Sorry, your nonce did not verify.', 'wp-twitter-threads' );
            // else:
                if ( empty( $wptt_tweet_url ) ):
                    $message = '<div class="notice notice-warning"><p>' . __( 'Please type the URL or the ID of the tweet.', 'wp-twitter-threads' ) . '</p></div>';
                else:
                    $result = self::wptt_is_tweet_exist( $wptt_tweet_url );
                    if ( $result ):
                        update_post_meta( $post_id, 'wptt_tweet_url', sanitize_meta( 'wptt_tweet_url', $wptt_tweet_url, 'post' ) );

                        $statuses = self::wptt_get_tweets( $result );
                        if ( $statuses && is_array( $statuses ) && count( $statuses ) > 0 ):
                            delete_post_meta( $post_id, 'wptt_tweet' );
                            delete_post_meta( $post_id, 'wptt_tweet_text' );

                            $statuses = self::wptt_process_tweets( $statuses, $result );

                            foreach( $statuses as $status ):
                                add_post_meta( $post_id, 'wptt_tweet', sanitize_meta( 'wptt_tweet', $status, 'post' ) );
                                add_post_meta( $post_id, 'wptt_tweet_text', sanitize_meta( 'wptt_tweet_text', $status->full_text, 'post' ) );

                                $status_parsed = self::wptt_parse_tweet( $status );

                                if (is_array( $status_parsed['statuses_text'] ) && count( $status_parsed['statuses_text'] ) > 0 ):
                                    foreach( $status_parsed['statuses_text'] as $_status_parsed ):
                                        $statuses_text[] = $_status_parsed;
                                    endforeach;
                                endif;

                                if ( is_array( $status_parsed['hashtags'] ) && count( $status_parsed['hashtags'] ) > 0 ):
                                    wp_set_post_tags( $post_id, $status_parsed['hashtags'], true );
                                endif;
                            endforeach;

                            $error = 0;
                            $edit_post_link = get_edit_post_link( $post_id, 'wp-twitter-threads' );

                            $message = '<div class="notice notice-success"><p>' . __( 'Tweets imported with success!', 'wp-twitter-threads' ) . '</p></div>';
                        else:
                            $message = '<div class="notice notice-warning"><p>' . __( 'Cannot find any tweets.', 'wp-twitter-threads' ) . '</p></div>';
                        endif;
                    else:
                        $message = '<div class="notice notice-warning"><p>' . __( 'The thread does not exist.', 'wp-twitter-threads' ) . '</p></div>';
                    endif;
                endif;
            // endif;
        endif;

        $content = ob_get_contents();
        ob_end_clean();

        $output = [
            'post_id' => $post_id,
            'error' => $error,
            'message' => $message,
            'message_text' => strip_tags( $message ),
            'content' => $content,
            'edit_post_link' => $edit_post_link,
            'statuses_text' => $statuses_text,
        ];

        wp_send_json( $output );
    }


    /*
     * Get filesystem function
     */
    public static function get_filesystem() {
        static $filesystem;
        if ( $filesystem ) {
            return $filesystem;
        }
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

        $filesystem = new \WP_Filesystem_Direct( new \stdClass() );

        if ( ! defined( 'FS_CHMOD_DIR' ) ) {
            define( 'FS_CHMOD_DIR', ( @fileperms( ABSPATH ) & 0777 | 0755 ) );
        }
        if ( ! defined( 'FS_CHMOD_FILE' ) ) {
            define( 'FS_CHMOD_FILE', ( @fileperms( ABSPATH . 'index.php' ) & 0777 | 0644 ) );
        }

        return $filesystem;
    }
}
