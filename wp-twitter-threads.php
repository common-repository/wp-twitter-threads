<?php
/*
* Plugin name: WP TW Threads
* Description: Twitter Thread Tool : Turn Twitter Threads into WordPress Post
* Author: Xuan NGUYEN
* Author URI: https://xuxu.fr/
* Version: 1.1.0
* Text-domain: wp-twitter-threads
*/

define( 'WPTT_PLUGIN_VERSION', '1.1.0' );
define( 'WPTT_PATH', __DIR__ );
define( 'WPTT_NAMESPACE', 'LNJ' );
define( 'WPTT_SLUG', 'wp-twitter-threads' );
define( 'WPTT_SLUG_CAMELCASE', 'WPTwitterThreads' );
define( 'WPTT_URL', plugin_dir_url( __FILE__ ) );
define( 'WPTT_PLUGIN_FILE', __FILE__ );

define( 'WPTT_CONSUMER_KEY', get_option( 'wptt_consumer_key' ) );
define( 'WPTT_CONSUMER_SECRET', get_option( 'wptt_consumer_secret' ) );
define( 'WPTT_ACCESS_TOKEN', get_option( 'wptt_access_token' ) );
define( 'WPTT_ACCESS_TOKEN_SECRET', get_option( 'wptt_access_token_secret' ) );
define( 'WPTT_USER_ID', get_option( 'wptt_user_id' ) );
define( 'WPTT_SCREEN_NAME', get_option( 'wptt_screen_name' ) );

//
require "vendor/autoload.php";
//
// use Abraham\TwitterOAuth\TwitterOAuth;
// use Dusterio\LinkPreview\Client;
//
require WPTT_PATH . '/classes/wp-twitter-threads.php';

// Launch
\LNJ\WPTT::run();

// add_action( 'init', function() {
	// add_filter( 'use_block_editor_for_post', '__return_false', 10 );
// });