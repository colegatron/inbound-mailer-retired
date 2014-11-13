<?php

/**
*  Class helps schedule & unschedule inbound emails
*/

class Inbound_Mailer_Scheduling {

	static $settings;
	
	/**
	*  Determine batching patterns 
	*/
	public static function create_batches() {
	
	}
	
	/** 
	*  Schedules email
	*/
	public static function schedule_email( $email_id ) {
		
		Inbound_Mailer_Scheduling::$settings = Inbound_Email_Meta::get_settings( $email_id );
		$batches = Inbound_Mailer_Scheduling::create_batches();
	}
	
	/**
	*  Unscheduled email
	*/
	public static function unschedule_email( $email_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . "inbound_email_queue"; 
		
		$wpdb->query('delete from '.$table_name.' where status != "sent" AND email_id = "'.$email_id.'" ');
	}
}

