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
		
		/* Adds listener to record CTA Variation impression */
		add_action('wp_ajax_inbound_email_record_impressions', array( __CLASS__ , 'record_impression' ) );
		add_action('wp_ajax_nopriv_inbound_email_record_impressions', array( __CLASS__ , 'record_impression' ) );
		
		/* Adds listener to record CTA variation conversions */
		add_action('wp_ajax_inbound_email_record_conversion', array( __CLASS__ , 'record_conversion' ) );
		add_action('wp_ajax_nopriv_inbound_email_record_conversion', array( __CLASS__ , 'record_conversion' ) );

		/* Adds listener to save CTA post meta */
		add_action( 'wp_ajax_nopriv_wp_wp_call_to_action_meta_save', array( __CLASS__ , 'save_meta' ) );
		add_action( 'wp_ajax_wp_wp_call_to_action_meta_save', array( __CLASS__ , 'save_meta' ) );
		
		/* Adds listener for email variation send statistics */
		add_action( 'wp_ajax_inbound_load_email_stats' , array( __CLASS__ , 'get_email_statistics' ) );
		
		/* Adds listener to send test email */
		add_action( 'wp_ajax_inbound_send_test_email' , array( __CLASS__ , 'send_test_email' ) );
	}
	
	/**
	*	Record impressions for CTA variation(s) given CTA ID(s) and variation ID(s)
	*/
	public static function record_impression() {
		global $wpdb; // this is how you get access to the database
		global $user_ID;

		$ctas = json_decode( stripslashes($_POST['ctas']) , true );
		
		foreach ( $ctas as $inbound_email_id => $vid ) {
			do_action('inbound_email_record_impression' , $inbound_email_id , $vid );
		}

		//print_r($ctas);
		header('HTTP/1.1 200 OK');
		exit;
	}
	
	
	/**
	*	Record conversion for CTA variation given CTA ID and variation ID
	*/
	public static function record_conversion() {
		global $wpdb; // this is how you get access to the database
		global $user_ID;

		$inbound_email_id = trim($_POST['inbound_email_id']);
		$variation_id = trim($_POST['variation_id']);

		do_action('inbound_email_record_conversion', $inbound_email_id, $variation_id);

		print $inbound_email_id;
		header('HTTP/1.1 200 OK');
		exit;
	}
	
	/**
	*	Saves meta pair values give cta ID, meta key, and meta value
	*/
	public static function save_meta() {
		global $wpdb;

		if ( !wp_verify_nonce( $_POST['nonce'], "inbound-email-meta-nonce")) {
			exit("Wrong nonce");
		}

		$new_meta_val = $_POST['new_meta_val'];
		$meta_id = $_POST['meta_id'];
		$post_id = mysql_real_escape_string($_POST['page_id']);

		if ($meta_id === "main_title") {
			$my_post = array();
			$my_post['ID'] = $post_id;
			$my_post['post_title'] = $new_meta_val;

			// Update the post into the database
			wp_update_post( $my_post );
		}

		if ($meta_id === "the_content") {
			$title_save = get_post_meta($post_id, "inbound-mailer-main-headline", true); // fix content from removing title
			$my_post = array();
			$my_post['ID'] = $post_id;
			$my_post['post_content'] = $new_meta_val;

			// Update the post into the database
			wp_update_post( $my_post );
			add_post_meta( $post_id, "inbound-mailer-main-headline", $title_save, true ) or update_post_meta( $post_id, "inbound-mailer-main-headline", $title_save ); // fix main headline removal
		} else {
			add_post_meta( $post_id, $meta_id, $new_meta_val, true ) or update_post_meta( $post_id, $meta_id, $new_meta_val );
		}

		header('HTTP/1.1 200 OK');
		exit;
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
		
		$mailer->send_test_email( $_REQUEST['email_address'] , $_REQUEST['email_id'] , $_REQUEST['variation_id'] ); 
		
		header('HTTP/1.1 200 OK');
		exit;
	}
}

/* Loads Inbound_Mailer_Ajax_Listeners pre init */
$Inbound_Mailer_Ajax_Listeners = new Inbound_Mailer_Ajax_Listeners();