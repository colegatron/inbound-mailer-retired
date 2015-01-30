<?php

/**
*	This class loads miscellaneous WordPress AJAX listeners 
*/
class Inbound_Mailer_Ajax_Listeners {
	
	/**
	*	Initializes class
	*/
	public function __construct() {
		self::load_hooks();
	}

	/**
	*	Loads hooks and filters
	*/
	public static function load_hooks() {
		

		/* Adds listener to save email data */
		add_action( 'wp_ajax_save_inbound_email', array( __CLASS__ , 'save_email' ) );
		
		/* Adds listener for email variation send statistics */
		add_action( 'wp_ajax_inbound_load_email_stats' , array( __CLASS__ , 'get_email_statistics' ) );
		
		/* Adds listener to send test email */
		add_action( 'wp_ajax_inbound_send_test_email' , array( __CLASS__ , 'send_test_email' ) );
	}
	
	/**
	*	Saves meta pair values give cta ID, meta key, and meta value
	*/
	public static function save_email() {
		global $wpdb;

		if ( !isset($_POST) ) {
			return;
		}
		
		//error_log( print_r( $_POST , true ) );
		
		/* update post type */
		wp_update_post( array(
			'ID' => $_POST['post_ID'],
			'post_status' => $_POST['post_status'],
			'post_title' => $_POST['post_title'],
		));
		
		/* get current email settings */
		$email_settings = Inbound_Email_Meta::get_settings( $_POST['post_ID'] );

		/* Set the call to action variation into a session variable */
		$_SESSION[ $_POST['post_ID'] . '-variation-id'] = (isset($_POST[ 'inbvid'])) ? $_POST[ 'inbvid'] : '0';

		/* save all post vars as meta */
		foreach ($_POST as $key => $value) {
			if ( substr( $key , 0 , 8 ) == 'inbound_' ){
				$key = str_replace( 'inbound_' , '' , $key );
				$email_settings[ $key ] = $value;
			} else {
				if (self::check_whitelist( $key )) {
					$email_settings['variations'][ $_POST[ 'inbvid'] ][ $key ] = $value;
				}
			}
		}

		/* Update Settings */
		Inbound_Email_Meta::update_settings( $_POST['post_ID'] , $email_settings );

		header('HTTP/1.1 200 OK');
		exit;
	}
	
	/**
		*	Checks meta key for variation setting qualification
		*	@returns BOOLEAN $key false for skip true for save
		*/
		public static function check_whitelist( $key ) {
			/* do not save post_ related keys */
			if ( substr( $key , 0 , 5 ) == 'post_' ) {
				return false;
			}

			/* do not save hidden custom fields */
			if ( substr( $key , 0 , 1 ) == '_' ) {
				return false;
			}

			/* do not save hidden custom fields */
			if ( substr( $key , 0 , 7 ) == 'hidden_' ) {
				return false;
			}

			/* do not save hidden custom fields */
			if ( substr( $key , 0 , 4 ) == 'cur_' ) {
				return false;
			}

			/* do not save hidden custom fields */
			if ( strstr( $key , 'nonce' ) ) {
				return false;
			}

			/* do not save hidden custom fields */
			if ( in_array( $key , array('inbvid', 'email_action' , 'originalaction','action','original_publish','publish','original_post_status', 'referredby', 'meta-box-order-nonce', 'comment_status','ping_status','post_mime_type','newtag','tax_input','post_password' ,'visibility','wp-preview'	) ) ) {
				return false;
			}

			return true;
		}
	
	/**
	*  Gets JSON object containing email send statistics for each variation 
	*/
	public static function get_email_statistics() {
		
		$stats = Inbound_Email_Stats::get_email_stats( $_REQUEST['email_id'] );
		echo $stats;
		header('HTTP/1.1 200 OK');
		exit;
	}

	/**
	*  Sends test email
	*/
	public static function send_test_email() {
		$mailer = new Inbound_Mail_Daemon();
		//error_log( print_r($_REQUEST , true));
		
		$response = $mailer->send_solo_email( array( 
			'email_address' => $_REQUEST['email_address'] , 
			'email_id' => $_REQUEST['email_id'] , 
			'vid' => $_REQUEST['variation_id'] 
		));
		
		_e('Here are your send results:','inbound-pro');
		echo "\r\n";
		print_r($response);
			
		header('HTTP/1.1 200 OK');
		exit;
	}
}

/* Loads Inbound_Mailer_Ajax_Listeners pre init */
$Inbound_Mailer_Ajax_Listeners = new Inbound_Mailer_Ajax_Listeners();