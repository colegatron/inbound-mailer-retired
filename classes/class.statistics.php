<?php

/**
*  Calculates & serves Mandrill Stats
*/

class Inbound_Email_Stats {
	
	static $settings; /* email settings */
	static $results; /* results returned from mandrill */
	static $email_id; /* email id being processed */
	static $vid; /* variation id being processed */
	static $stats; /* stats array */
	
	/**
	*  Gets email statistics
	*  @param INT $email_id ID of email
	*  @param BOOLEAN $return false for json return true for array return
	*  @return JSON 
	*/
	public static function get_email_stats ( ) {
		global $Inbound_Mailer_Variations, $post;
		
		/* get historic stats */
		$stats = array();
	
		if ( !in_array( $post->post_status , array( 'sent' , 'sending', 'automated' )) ) {
			return '{}';
		}
		/* get settings */
		self::$settings = Inbound_Email_Meta::get_settings( $post->ID );

		/* get mandrill stats*/
		foreach ( self::$settings['variations'] as $vid => $variation ) {
			self::$vid = $vid;
			self::$email_id = $post->ID;
			
			$query = 'u_email_id:' .  $post->ID  . ' u_variation_id:'. self::$vid .' subaccount:' . InboundNow_Connection::get_licence_key();
			self::query_mandrill( $query );
			
			/* process data */
			self::process_mandrill_stats();
			
		}
		
		/* return empty stats if empty */
		if (!self::$stats) {
			self::$stats['variations'][0] =  Inbound_Email_Stats::prepare_empty_stats();
			Inbound_Email_Stats::prepare_totals();		
			return self::$stats ;
		}
			


		Inbound_Email_Stats::prepare_totals();
		
		return self::$stats;
	
	}
	
	/**
	*  Get Mandrill Time Series Stats
	*  @param STRING $query
	*/
	public static function query_mandrill( $query ) {
		global $post;
		
		/* get timstamps in correct timezone */
		$sent = self::get_mandrill_timestamp( self::$settings['send_datetime'] );
		$now = self::get_mandrill_timestamp( gmdate( "Y-m-d\\TG:i:s\\Z") );

		
		$mandrill = new Mandrill();
		$date_from = $sent;
		$date_to = $now;
		$tags = array();
		$senders = array();

		self::$results = $mandrill->messages->searchTimeSeries($query, $date_from, $date_to, $tags, $senders);
		
	}
	
	/**
	*  Converts gmt timestamps to correct timezones
	*  @param DATETIME $timestamp timestamp in gmt before calculating timezone
	*/
	public static function get_mandrill_timestamp( $timestamp ) {
		/* get timezone */
		$tz = explode( '-UTC' , self::$settings['timezone'] );

		$timezone = timezone_name_from_abbr($tz[0] , 60 * 60 * intval( $tz[1] ) );
		date_default_timezone_set( $timezone );
		
		$mandrill_timestamp = gmdate( "Y-m-d\\TG:i:s\\Z" ,	strtotime($timestamp) );
		
		return $mandrill_timestamp;
	}
	
	/** 
	*  build totals 
	*/
	public static function process_mandrill_stats() {

		self::$stats[ 'variations' ][ self::$vid ] = array(
			'hour' => '',
			'sent' => 0,
            'opens' => 0,
            'clicks' => 0,
            'hard_bounces' => 0,
            'soft_bounces' => 0,
            'rejects' => 0,
            'complaints' => 0,
            'unsubs' => 0,
            'unique_opens' => 0,
            'unique_clicks' => 0,
			'unopened' => 0
		);
		
		foreach ( self::$results as $key => $totals ) {
			
			/* update processed datetime */
			self::$stats[ 'variations' ][ self::$vid ][ 'hour' ] = $totals['time'];
			unset($totals['time']);
			
			/* update processed totals */
			foreach ($totals as $key => $value ) {						
				self::$stats[ 'variations' ][ self::$vid ][ $key ] = self::$stats[ 'variations' ][ self::$vid ][ $key ] + $value;
			}
			
			/* process unopened */
			self::$stats[ 'variations' ][ self::$vid ][ 'unopened' ] = $totals['sent'] - $totals['opens'];
		}
		
		/* add label */
		self::$stats[ 'variations' ][ self::$vid ][ 'label' ] =  Inbound_Mailer_Variations::vid_to_letter( self::$email_id , self::$vid );
				
		
	}

	/**
	*  Totals variation stats to create an aggregated statistic total_stat
	*  @param ARRAY $stats array of variations with email statics
	*  @returns ARRAY $stats array of variations with email statics and aggregated statistics
	*/
	public static function prepare_totals(  ) {
		
		self::$stats[ 'totals' ] = array(
			'sent' => 0,
            'opens' => 0,
            'clicks' => 0,
            'hard_bounces' => 0,
            'soft_bounces' => 0,
			'bounces' => 0,
            'rejects' => 0,
            'complaints' => 0,
            'unsubs' => 0,
            'unique_opens' => 0,
            'unique_clicks' => 0,
			'opens' => 0,
			'unopened' => 0
		);
		

		foreach (self::$stats['variations'] as $vid => $totals ) {
			
			
			self::$stats['totals']['sent'] = self::$stats['totals']['sent']  + $totals['sent'];
			self::$stats['totals']['opens'] = self::$stats['totals']['opens']  + $totals['opens'];
			self::$stats['totals']['clicks'] = self::$stats['totals']['clicks']  + $totals['clicks'];
			self::$stats['totals']['hard_bounces'] = self::$stats['totals']['hard_bounces']  + $totals['hard_bounces'];
			self::$stats['totals']['soft_bounces'] = self::$stats['totals']['soft_bounces']  + $totals['soft_bounces'];
			self::$stats['totals']['rejects'] = self::$stats['totals']['rejects']  + $totals['rejects'];
			self::$stats['totals']['complaints'] = self::$stats['totals']['complaints']  + $totals['complaints'];
			self::$stats['totals']['unsubs'] = self::$stats['totals']['unsubs']  + $totals['unsubs'];
			self::$stats['totals']['unique_opens'] = self::$stats['totals']['unique_opens']  + $totals['unique_opens'];
			self::$stats['totals']['unique_clicks'] = self::$stats['totals']['unique_clicks']  + $totals['unique_clicks'];
			
		}
		
		
		/* calculate unopened */		
		self::$stats['totals']['unopened'] = self::$stats['totals']['sent']  - self::$stats['totals']['opens'];
		
		/* calcumate total bounces */
		self::$stats['totals']['bounces'] = self::$stats['totals']['soft_bounces']  + self::$stats['totals']['hard_bounces'];
			
		
	}

	/**
	*  Returns an array of zeros for email statistics
	*/
	public static function prepare_empty_stats() {
			
		return array(
			'sends' => 0,
			'opens' => 0,
			'unopened' => 0,
			'clicks' => 0
		);		
		
	}
	
	/**
	*  Prepare dummy stats - populates an email with dummy statistics
	*/
	public static function prepare_dummy_stats( $email_id ) {
		
		$settings = Inbound_Email_Meta::get_settings( $email_id );
		
		if (!isset( $settings ) ) {
			return;
		}
		
		/* V1 */
		$settings['statistics']['variations'][0] = array(
			'label' => Inbound_Mailer_Variations::vid_to_letter( $email_id , 0 ),
			'sends' => 400,
			'opens' => 300,
			'unopened' => 100,
			'clicks' => 19
		);		
		
		/* V2 */
		$settings['statistics']['variations'][1] = array(
			'label' => Inbound_Mailer_Variations::vid_to_letter( $email_id , 1 ),
			'sends' => 400,
			'opens' => 350,
			'unopened' => 50,
			'clicks' => 28
		);		
		
		/* Totals */
		$settings['statistics']['totals'] = array(
			'sends' => 800,
			'opens' => 650,
			'unopened' => 150,
			'clicks' => 47
		);
		
		$settings = Inbound_Email_Meta::update_settings( $email_id , $settings );
	}
}

