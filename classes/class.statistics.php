<?php


class Inbound_Email_Stats {
	
	/**
	*  Gets email statistics
	*  @param INT $email_id ID of email
	*  @param BOOLEAN $return false for json return true for array return
	*/
	public static function get_email_stats ( $email_id , $return = false ) {
		global $Inbound_Mailer_Variations, $post;
		
		$stats = array();
	
		
		/* get variations */
		$settings = Inbound_Email_Meta::get_settings( $email_id );
	
		/* get email type */
		$email_type = (isset($settings['email_type'])) ? $settings['email_type'] : 'batch';

		/* for band new emails set stats empty */
		if ( $email_type == 'new' || !isset($settings['statistics']) ) {
			$stats['variations'][0] =  Inbound_Email_Stats::prepare_empty_stats();
		} 
		
		/* for batch emails without stats set empty */
		else if ( !isset($settings['statistics']) && $email_type == 'batch'){					
			$stats['variations'][0] = Inbound_Email_Stats::prepare_empty_stats();
		} 
		
		/* for batch emails with stats set stats */
		else if (isset($settings['statistics']) && $email_type == 'batch' ) {

			$stats = $settings['statistics'];
		} 
		
		/* for automated emails check mandril */
		else if ($email_type == 'automated'){					
			$stats['variations'][$id] = Inbound_Email_Stats::get_mandrill_stats();
		}


		$stats = Inbound_Email_Stats::total_stats( $stats );

		if (!$return) {
			return json_encode( (object) $stats );
		} else {
			return $stats;
		}
	}
	
	public static function get_mandrill_email_statistics() {
	
	}

	/**
	*  Totals variation stats to create an aggregated statistic total_stat
	*  @param ARRAY $stats array of variations with email statics
	*  @returns ARRAY $stats array of variations with email statics and aggregated statistics
	*/
	public static function total_stats( $stats ) {
		
		$sends = 0;
		$opens = 0;
		$unopened = 0;
		$clicks = 0;
		
		foreach ($stats['variations'] as $id => $stat ) {
			$sends = $sends + $stat['sends'];
			$opens = $opens + $stat['opens'];
			$unopened = $unopened + $stat['unopened'];
			$clicks = $clicks + $stat['clicks'];
		}
		
		$stats['totals'] = array(
			'sends' => $sends,
			'opens' => $opens,
			'unopened' => $unopened,
			'clicks' => $clicks
		);
		
		return $stats;
		
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

