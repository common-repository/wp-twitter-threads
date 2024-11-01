<?php
    $url_settings = admin_url( 'admin.php?page=wptt-page' );

	if ( is_user_logged_in() && isset( $_GET['oauth_token'] ) && isset( $_GET['oauth_verifier'] ) ):
		if ( get_option( 'wptt_consumer_key' ) && get_option( 'wptt_consumer_secret' ) && get_option( 'wptt_oauth_token' ) && get_option( 'wptt_oauth_token_secret' ) ):

			$connection = new \Abraham\TwitterOAuth\TwitterOAuth(
				get_option( 'wptt_consumer_key' ),
				get_option( 'wptt_consumer_secret' ),
				get_option( 'wptt_oauth_token' ),
				get_option( 'wptt_oauth_token_secret' )
			);

			// request user token
			$oauth_verifier = filter_input( INPUT_GET, 'oauth_verifier' );

			$token = $connection->oauth(
				'oauth/access_token', [
					'oauth_verifier' => $oauth_verifier
				]
			);

			if ( $token ):
                update_option( 'wptt_access_token', sanitize_option( 'wptt_access_token', $token['oauth_token'] ) );
                update_option( 'wptt_access_token_secret', sanitize_option( 'wptt_access_token_secret', $token['oauth_token_secret'] ) );
                update_option( 'wptt_user_id', sanitize_option( 'wptt_user_id', $token['user_id'] ) );
                update_option( 'wptt_screen_name', sanitize_option( 'wptt_screen_name', $token['screen_name'] ) );
            else:
            	update_option( 'wptt_access_token', null );
                update_option( 'wptt_access_token_secret', null );
                update_option( 'wptt_user_id', null );
                update_option( 'wptt_screen_name', null );
			endif;

	        $url_settings = add_query_arg( 'status', 'success', $url_settings );
			wp_redirect( $url_settings );
			exit;
		else:
	        $url_settings = add_query_arg( 'status', 'parameters-missing', $url_settings );
			wp_redirect( $url_settings );
			exit;
		endif;
	else:
		wp_die( __( 'hello you, what\'s up ?', 'wp-twitter-threads' ) );
	endif;