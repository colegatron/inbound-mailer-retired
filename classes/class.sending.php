<?php

/**
* Inbound Mail Daemon listens for and sends scheduled emails
*/
class Inbound_Mail_Daemon {

	static $table_name; /* name of the mysql table we use for querying queued emails */
	static $send_limit; /* number of emails we send during a processing job	(wp_mail only) */
	static $timestamp; /* the current date time in ISO 8601 gmdate() */
	static $dom; /* reusable object for parsing html for link modification */
	static $row; /* current mysql row object being processed */
	static $email_settings; /* settings array of the email being processed */
	static $templates; /* array of html templates for processing */
	static $email; /* arg array of email being processed */
	static $results; /* results from sql query */
	static $response; /* return result after send */
	


	/**
	*	Initialize class
	*/
	function __construct() {

		/* Load static vars */
		self::load_static_vars();

		/* Load hooks */
		self::load_hooks();

	}

	/**
	*	Loads static variables
	*/
	public static function load_static_vars() {
		global $wpdb;

		/* Set send limit */
		self::$send_limit = 120;

		/* Set target mysql table name */
		self::$table_name = $wpdb->prefix . "inbound_email_queue";

		/* Get now timestamp */
		self::$timestamp = gmdate( "Y-m-d\\TG:i:s\\Z" );

	}

	/*
	* Load Hooks & Filters
	*/
	public static function load_hooks() {

		/* Adds 'Every Two Minutes' to System Cron */
		//add_filter( 'inbound_heartbeat', array( __CLASS__ , 'process_mail_queue' ) );

		/* For debugging */
		//add_filter( 'init', array( __CLASS__ , 'process_mail_queue' ) , 12 );

	}


	public static function process_mail_queue() {

		error_log('here');
		if ( !isset( $_GET['test'] ) && current_filter() == 'init' ) {
			return;
		}
		error_log('there');
		/* send automation emails */
		self::send_automated_emails();

		/* send batch emails */
		self::send_batch_emails();
		exit;
	}


	/**
	*	Tells WordPress to send emails as HTML
	*/
	public static function toggle_email_type() {
		add_filter( 'wp_mail_content_type', array( __CLASS__ , 'toggle_email_type_html' ) );
	}
	
	/**
	*	Set email type to html for wp_mail
	*/
	public static function toggle_email_type_html( $type ) {
		return 'text/html';
	}

	/**
	*  Loads DOMDocument class object
	*/
	public static function toggle_dom_parser() {
		self::$dom = new DOMDocument;		
	}
	
	/**
	*  Rebuild links with tracking params
	*/
	public static function rebuild_links( $html ) {
		
		@self::$dom->loadHTML($html);
		$links = self::$dom->getElementsByTagName('a');

		//Iterate over the extracted links and display their URLs
		foreach ($links as $link){
			
			$class = $link->getAttribute('class');
			$href = $link->getAttribute('href');
			
			/* Do not modify links with 'do-not-track' class */
			if ( $class == 'do-not-track' ) {
				continue;
			}
			
			/* build utm params */
			$params = array( 
				'utm_source' => self::$email['email_title'],
				'utm_medium' => 'email',
				'utm_campaign' => '',
				'lead_id' => self::$row->lead_id,
				'lead_lists' => implode( ',' , self::$email_settings['inbound_recipients'] ),
				'email_id' =>self::$row->email_id
			);	

			$new_link = add_query_arg( $params , $href );

			
			
			$html = str_replace( $href  , $new_link , $html );
			
		}
		
		return $html;
	}

	
	/**
	*	Sends scheduled automated emails
	*/
	public static function send_automated_emails() {
		global $wpdb;

		$query = "select * from ". self::$table_name ." WHERE `status` != 'sent' && `type` = 'automation' && `datetime` <	'". self::$timestamp ."' ";
		$results = $wpdb->get_results( $query );

		if (!$results) {
			return;
		}

		/* Make sure we send emails as html */
		self::toggle_email_type();

		foreach( $results as $row ) {

			self::$row = $row;

			self::$email_settings = Inbound_Email_Meta::get_settings( $row->email_id );

			self::get_email();

			self::$response = self::send_email( 'mandrill' );

		}
	}

	/**
	*	Sends scheduled batch emails
	*/
	public static function send_batch_emails() {
		global $wpdb;

		/* Get results for singular email id */
		$query = "select * from ". self::$table_name ." WHERE `status` != 'sent' && `type` = 'batch' && `datetime` <	'".self::$timestamp ."' && email_id = email_id order by email_id ASC";
		self::$results = $wpdb->get_results( $query );

		if (!self::$results) {
			return;
		}

		/* get first row of result set for determining email_id */
		self::$row = self::$results[0];
		
		/* Get email title */
		self::$email['email_title'] = get_the_title( self::$row->email_id );
		
		/* Get email settings if they have not been loaded yet */
		self::$email_settings = Inbound_Email_Meta::get_settings( self::$row->email_id );
		
		/* Build array of html content for variations */
		self::get_templates();
		
		/* Make sure we send emails as html */
		self::toggle_email_type();
		
		/* load dom parser class object */
		self::toggle_dom_parser();

		$send_count = 1;
		foreach( self::$results as $row ) {

			self::$row = $row;

			/* make sure not to try and send more than wp can handle */
			if (  $send_count > self::$send_limit ){
				return;
			}

			self::get_email();

			self::send_email();
			
			self::update_email_status( 'sent' );

			$send_count++;
		}
		
		/* mark batch email as sent */
		self::mark_email_sent();
	}
	
	/**
	*	Sends email
	*	@param STRING $mode toggles a wp_mail send or a mandrill send
	*/
	public static function send_email( $mode = 'wp_mail' ) {

		switch ( $mode ) {

			case 'wp_mail':
				self::$response = self::send_wp_email( );
				break;
			case 'mandrill':
				self::$response = self::send_mandrill_email();
				break;

		}

	}

	/**
	*	Sends email using wp_mail()
	*/
	public static function send_wp_email( ) {
		
		$headers = 'From: '. self::$email['from_name'] .' <'.self::$email['from_email'].'>' . "\r\n";
		self::$response = wp_mail( self::$email['send_address'] , self::$email['subject'] , self::$email['body'] , $headers	);

	}

	/**
	*	Sends email using Inbound Now's mandrill sender
	*/
	public static function send_mandrill_email() {

	}

	/**
	*  Updates the status of the email in the queue
	*/
	public static function update_email_status( $status ) {
		global $wpdb;
		
		$query = "update ". self::$table_name ." set `status` = '{$status}' where `id` = '".self::$row->id."'";
		$wpdb->query( $query );
		
	}

	/**
	*  Updates the post status of an email to sent
	*/
	public static function mark_email_sent( ) {
		global $wpdb;
		
		$args = array(
			'ID' => self::$row->email_id,
			'post_status' => 'sent',
		);
		
		wp_update_post( $args );
	}
	
	
	/**
	*  Gets array of raw html for each variation
	*/
	public static function get_templates() {
		
		/* setup static var as empty array */
		self::$templates = array();

		foreach ( self::$email_settings[ 'variations' ] as $vid => $variation ) {
			
			/* get permalink */
			$permalink = get_post_permalink( self::$row->email_id	);

			/* add param */
			$permalink = add_query_arg( array( 'inbvid' => $vid , 'disable_shortcodes' => true ), $permalink );;
			
			/* Stash variation template in static array */
			self::$templates[ self::$row->email_id ][ $vid ] =  self::get_variation_html( $permalink );
			
		}

	}
	
	/**
	*	Prepares email data for sending
	*	@return ARRAY $email
	*/
	public static function get_email() {

		self::$email['send_address'] = Leads_Field_Map::get_field( self::$row->lead_id ,	'wpleads_email_address' );
		self::$email['subject'] = self::$email_settings['inbound_subject'];
		self::$email['from_name'] = self::$email_settings['inbound_from_name'];
		self::$email['from_email'] = self::$email_settings['inbound_from_email'];
		self::$email['body'] = self::get_email_body();
		
	}

	/**
	*  Generates targeted email body html
	*/
	public static function get_email_body() {
		
		$html = self::$templates[ self::$row->email_id ][ self::$row->variation_id ];
		
		/* add lead id to all shortcodes before processing */
		$html = str_replace('[lead-field ' , '[lead-field lead_id="'. self::$row->lead_id .'" ' , $html );

		/* process shortcodes */
		$html = do_shortcode( $html );
		
		/* add tracking params to links */
		$html = self::rebuild_links( $html );
		
		return $html;
	
	}
	
	/**
	*	Generate HTML for email
	*	@param STRING $permalink
	*	@return STRING
	*/
	public static function get_variation_html( $permalink ) {
		$response = wp_remote_get( $permalink);
		$html = wp_remote_retrieve_body( $response );

		return $html;
	}
	

}

/**
*	Load Mail Daemon on init
*/
add_action('init' , function() {
	$GLOBALS['Inbound_Mail_Daemon'] = new Inbound_Mail_Daemon();
} , 2 );

