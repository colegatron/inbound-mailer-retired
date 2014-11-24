<?php

/**
*  Class helps schedule & unschedule inbound emails
*/

class Inbound_Mailer_Scheduling {

	static $settings;
	
	/**
	*  Determine batching patterns 
	*  @param INT $email_id
	*/
	public static function create_batches( $email_id ) {

		$settings = Inbound_Mailer_Scheduling::$settings;
		$variations = $settings['variations'];
		$variation_count = count($variations);
		
		$recipients = $settings['inbound_recipients']; 

		$params = array( 
			'include_lists' => $recipients,
			'return' => 'ID',
			'results_per_page' => -1,
			'orderby' => 'rand',
			'fields' => 'ids'
		);
		
		$results = Inbound_API::leads_get( $params );
		$leads = $results->posts;
		
		$chunk_size = round(count($leads) / $variation_count);
		
		$batches = array_chunk( $leads , $chunk_size);
		
		/* if the batch variation id is not already correct then change keys */
		$i = 0;
		foreach ($variations as $vid => $settings) {
			if ($vid != $i ) {
				$batches[ $vid ] = $batches[ $i ];
				unset( $batches[ $i ] );
			}
			$i++;
		}
		
		return $batches;
		
	}
	
	/** 
	*  Schedules email
	*/
	public static function schedule_email( $email_id ) {
		
		global $wpdb;
		
		/* load email settings into static variable */
		Inbound_Mailer_Scheduling::$settings = Inbound_Email_Meta::get_settings( $email_id );
		
		/* Prepare lead batches */
		$lead_batches = Inbound_Mailer_Scheduling::create_batches( $email_id );
		
		/* Set target mysql table name */
		$table_name = $wpdb->prefix . "inbound_email_queue"; 
		
		/* Prepare Schedule time */
		$timestamp = Inbound_Mailer_Scheduling::get_timestamp();
		
		/* prepare multi insert query string - limit to 1000 inserts at a time */
		foreach ($lead_batches as $vid => $leads ) {
			
			$query_values_array = array();
			$query_prefix = "INSERT INTO {$table_name} ( `email_id` , `variation_id` , `lead_id` , `type` , `status` , `datetime` )";
			$query_prefix .= "VALUES";
			
			foreach ($leads as $ID) {
				$query_values_array[] = "( {$email_id} , {$vid} , {$ID} , '".Inbound_Mailer_Scheduling::$settings['inbound_email_type']."' , 'scheduled' , '{$timestamp}')";
			}
			
			$value_batches = array_chunk( $query_values_array , 500);
			foreach ($value_batches as $values) {
				$query_values = implode( ',' , $values);
				$query = $query_prefix . $query_values;
				$wpdb->query( $query );
			}		
		}
	}
	
	/**
	*  Unscheduled email
	*/
	public static function unschedule_email( $email_id ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . "inbound_email_queue"; 
		
		$wpdb->query('delete from '.$table_name.' where status != "sent" AND email_id = "'.$email_id.'" ');
	}
	
	/**
	*  Get timestamp
	*/
	public static function get_timestamp() {
		
		$settings = Inbound_Mailer_Scheduling::$settings;
		$send_type = $settings['inbound_batch_send_nature'];

		switch( $send_type ) {
			
			/* add three minutes */
			case 'ready':
				$current = gmdate( "Y-m-d\\TG:i:s\\Z");
				$timestamp = gmdate( "Y-m-d\\TG:i:s\\Z", strtotime( $current ) + 1 * 60 );
				break;
			case 'schedule':
				$timestamp = gmdate( "Y-m-d\\TG:i:s\\Z" ,  strtotime($settings['inbound_send_datetime']) );
				break;
		
		}
		
		return $timestamp;
	}
}

