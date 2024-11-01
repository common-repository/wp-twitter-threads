<p>Plugin version : <strong><?php echo WPTT_PLUGIN_VERSION;?></strong></p>

<?php
	if ( defined( 'WPTT_CONSUMER_KEY' ) && ! empty( WPTT_CONSUMER_KEY  ) && defined( 'WPTT_CONSUMER_SECRET' ) && ! empty( WPTT_CONSUMER_SECRET ) ):
?>
		<p>Twitter API KEYS: <strong>OK</strong></p>
<?php
        $check = $this::wptt_is_twitter_api_connected();
        if ( $check ):
?>
	        <p>
	            <?php echo sprintf( __( 'You are well connected to Twitter API as <strong>%s</strong>.', 'wp-twitter-threads' ), $check->screen_name );?>
	        </p>
<?php
		else:
?>
			<p>Twitter API connected: <em>NOPE</em></p>
<?php
		endif;
	else:
?>
		<p>Twitter API KEYS: <em>NOPE</em></p>
<?php

	endif;
