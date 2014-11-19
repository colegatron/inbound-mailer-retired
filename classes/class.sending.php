<?php

/**
* Inbound Mail Daemon listens for and sends scheduled emails
*/
class Inbound_Mail_Daemon {

	static $table_name; /* name of the mysql table we use for querying queued emails */
	static $send_limit; /* number of emails we send during a processing job	(wp_mail only) */
	static $timestamp; /* the current date time in ISO 8601 gmdate() */
	static $dom; /* class object instance for parsing html for link modification */
	static $row; /* current mysql row object being processed */
	static $email_settings; /* settings array of the email being processed */
	static $email; /* arg array of email being processed */
	static $result; /* return result after send */
	


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
		self::$send_limit = '500';

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
		add_filter( 'inbound_heartbeat', array( __CLASS__ , 'process_mail_queue' ) );

		/* For debugging */
		add_filter( 'init', array( __CLASS__ , 'process_mail_queue' ) , 12 );

	}


	public static function process_mail_queue() {

		if ( !isset( $_GET['test'] ) ) {
			return;
		}

		/* send automation emails */
		self::send_automated_emails();

		/* send batch emails */
		self::send_batch_emails();
		exit;
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

			self::$result = self::send_email( 'mandrill' );

		}
	}

	/**
	*	Sends scheduled batch emails
	*/
	public static function send_batch_emails() {
		global $wpdb;

		/* Get results for singular email id */
		$query = "select * from ". self::$table_name ." WHERE `status` != 'sent' && `type` = 'batch' && `datetime` <	'".self::$timestamp ."' && email_id = email_id order by email_id ASC";
		$results = $wpdb->get_results( $query );

		if (!$results) {
			return;
		}

		/* Make sure we send emails as html */
		self::toggle_email_type();
		
		/* load dom parser class object */
		self::toggle_dom_parser();

		foreach( $results as $row ) {

			self::$row = $row;

			/* Get email settings if they have not been loaded yet */
			if ( !isset(self::$email_settings) ) {
				self::$email_settings = Inbound_Email_Meta::get_settings( $row->email_id );
			}

			self::$email = self::get_email();

			self::$result = self::send_email();
			
			var_dump($result);
			exit;
		}
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
	*	Prepares email data for sending
	*	@return ARRAY $email
	*/
	public static function get_email() {
		self::$email = array();
		
		self::$email['permalink'] = self::get_permalink();		
		self::$email['email_title'] = get_the_title( self::$row->email_id ); ;
		self::$email['body'] = self::get_email_body( self::$email['permalink'] );
		self::$email['send_address'] = Leads_Field_Map::get_field( self::$row->lead_id ,	'wpleads_email_address' );
		self::$email['subject'] = self::$email_settings['inbound_subject'];
		self::$email['from_name'] = self::$email_settings['inbound_from_name'];
		self::$email['from_email'] = self::$email_settings['inbound_from_email'];

		self::$email = apply_filters( 'batch_send_email' , self::$email );

		return $email;
	}


	/**
	*	Generate proper permalink
	*
	*	@return STRING $permalink
	*/
	public static function get_permalink() {

		/* get permalink */
		$permalink = get_post_permalink( self::$row->email_id	);

		/* add params */
		$permalink = add_query_arg( array( 'inbvid' => self::$row->variation_id , 'lead_id' => self::$row->lead_id ), $permalink );

		return $permalink;
	}

	/**
	*	Generate HTML for email
	*	@param STRING $permalink
	*	@return STRING
	*/
	public static function get_email_body( $permalink ) {
		$response = wp_remote_get( $permalink);
		$html = wp_remote_retrieve_body( $response );
		$body = self::rebuild_links( $html );
		echo $body;exit;
		return $body;
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
	*	Sends email
	*	@param STRING $mode toggles a wp_mail send or a mandrill send
	*/
	public static function send_email( $mode = 'wp_mail' ) {

		switch ( $mode ) {

			case 'wp_mail':
				self::$result = self::send_wp_email( );
				break;
			case 'mandrill':
				self::$result = self::send_mandrill_email();
				break;

		}

	}

	/**
	*	Sends email using wp_mail()
	*/
	public static function send_wp_email( ) {
		$headers = 'From: '. self::$email['from_name'] .' <'.self::$email['from_email'].'>' . "\r\n";
		wp_mail( self::$email['send_address'] , self::$email['subject'] , self::$email['body'] , $headers	);
	}

	/**
	*	Sends email using Inbound Now's mandrill sender
	*/
	public static function send_mandrill_email() {

	}


}

/**
*	Load Mail Daemon on init
*/
add_action('init' , function() {
	$GLOBALS['Inbound_Mail_Daemon'] = new Inbound_Mail_Daemon();
} , 2 );

