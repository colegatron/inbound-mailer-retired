<?php

/**
*	Class helps schedule & unschedule inbound emails
*/

class Inbound_Mailer_Scheduling {

	static $settings;

	/**
	*	Determine batching patterns
	*	@param INT $email_id
	*/
	public static function create_batches( $email_id ) {

		$settings = Inbound_Mailer_Scheduling::$settings;
		$variations = $settings['variations'];
		$variation_count = count($variations);

		$recipients = $settings['recipients'];

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
			$batch_array[ $vid ] = $batches[ $i ];
			$i++;
		}


		return $batch_array;

	}

	/**
	*	Schedules email
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
				$query_values_array[] = "( {$email_id} , {$vid} , {$ID} , '".Inbound_Mailer_Scheduling::$settings['email_type']."' , 'waiting' , '{$timestamp}')";
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
	*	Unscheduled email
	*/
	public static function unschedule_email( $email_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . "inbound_email_queue";

		$wpdb->query('delete from '.$table_name.' where status != "sent" AND email_id = "'.$email_id.'" ');
	}

	/**
	*	Get timestamp given saved timezone information
	*/
	public static function get_timestamp() {

		$settings = Inbound_Mailer_Scheduling::$settings;

		$tz = explode( '-UTC' , $settings['timezone'] );

		$timezone = timezone_name_from_abbr($tz[0] , 60 * 60 * intval( $tz[1] ) );
		date_default_timezone_set( $timezone );
		$timestamp = gmdate( "Y-m-d\\TG:i:s\\Z" ,	strtotime($settings['send_datetime']) );

		return $timestamp;
	}


	/**
	*	Get's current utc timezone offset
	*/
	public static function get_current_timezone( ) {
		$gmt_offset = get_option('gmt_offset');

		$timezone = timezone_name_from_abbr( "" , $gmt_offset * 60 * 60 , 0);

		$dateTime = new DateTime();
		$dateTime->setTimeZone(new DateTimeZone( $timezone ));
		
		return array( 'abbr' => $dateTime->format('T') , 'offset' => $gmt_offset );
	}

	/**
	*	Get array of timezones
	*/
	public static function get_timezones() {
		return array(
			array('abbr'=>'BIT', 'name' => __( 'Baker Island Time' , 'inbound-mailer'	) , 'utc' => 'UTC-12'),
			array('abbr'=>'NUT', 'name' => __( 'Niue Time' , 'inbound-mailer'	) , 'utc' => 'UTC-11'),
			array('abbr'=>'SST', 'name' => __( 'Samoa Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC-11'),
			array('abbr'=>'CKT', 'name' => __( 'Cook Island Time' , 'inbound-mailer'	) , 'utc' => 'UTC-10'),
			array('abbr'=>'HAST', 'name' => __( 'Hawaii-Aleutian Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC-10'),
			array('abbr'=>'HST', 'name' => __( 'Hawaii Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC-10'),
			array('abbr'=>'TAHT', 'name' => __( 'Tahiti Time' , 'inbound-mailer'	) , 'utc' => 'UTC-10'),
			array('abbr'=>'MART', 'name' => __( 'Marquesas Islands Time' , 'inbound-mailer'	) , 'utc' => 'UTC-9:30'),
			array('abbr'=>'MIT', 'name' => __( 'Marquesas Islands Time' , 'inbound-mailer'	) , 'utc' => 'UTC-9:30'),
			array('abbr'=>'AKST', 'name' => __( 'Alaska Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC-9'),
			array('abbr'=>'GAMT', 'name' => __( 'Gambier Islands' , 'inbound-mailer'	) , 'utc' => 'UTC-9'),
			array('abbr'=>'GIT', 'name' => __( 'Gambier Island Time' , 'inbound-mailer'	) , 'utc' => 'UTC-9'),
			array('abbr'=>'HADT', 'name' => __( 'Hawaii-Aleutian Daylight Time' , 'inbound-mailer'	) , 'utc' => 'UTC-9'),
			array('abbr'=>'AKDT', 'name' => __( 'Alaska Daylight Time' , 'inbound-mailer' ) , 'utc' => 'UTC-8'),
			array('abbr'=>'CIST', 'name' => __( 'Clipperton Island Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC-8'),
			array('abbr'=>'PST', 'name' => __( 'Pacific Standard Time (North America)' , 'inbound-mailer'	) , 'utc' => 'UTC-8'),
			array('abbr'=>'MST', 'name' => __( 'Mountain Standard Time (North America)' , 'inbound-mailer'	) , 'utc' => 'UTC-7'),
			array('abbr'=>'PDT', 'name' => __( 'Pacific Daylight Time (North America)' , 'inbound-mailer'	) , 'utc' => 'UTC-7'),
			array('abbr'=>'CST', 'name' => __( 'Central Standard Time (North America)' , 'inbound-mailer'	) , 'utc' => 'UTC-6'),
			array('abbr'=>'EAST', 'name' => __( 'Easter Island Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC-6'),
			array('abbr'=>'GALT', 'name' => __( 'Galapagos Time' , 'inbound-mailer'	) , 'utc' => 'UTC-6'),
			array('abbr'=>'MDT', 'name' => __( 'Mountain Daylight Time (North America)' , 'inbound-mailer'	) , 'utc' => 'UTC-6'),
			array('abbr'=>'CDT', 'name' => __( 'Central Daylight Time (North America)' , 'inbound-mailer'	) , 'utc' => 'UTC-5'),
			array('abbr'=>'COT', 'name' => __( 'Colombia Time' , 'inbound-mailer'	) , 'utc' => 'UTC-5'),
			array('abbr'=>'CST', 'name' => __( 'Cuba Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC-5'),
			array('abbr'=>'EASST', 'name' => __( 'Easter Island Standard Summer Time' , 'inbound-mailer'	) , 'utc' => 'UTC-5'),
			array('abbr'=>'ECT', 'name' => __( 'Ecuador Time' , 'inbound-mailer'	) , 'utc' => 'UTC-5'),
			array('abbr'=>'EST', 'name' => __( 'Eastern Standard Time (North America)' , 'inbound-mailer'	) , 'utc' => 'UTC-5'),
			array('abbr'=>'PET', 'name' => __( 'Peru Time' , 'inbound-mailer'	) , 'utc' => 'UTC-5'),
			array('abbr'=>'VET', 'name' => __( 'Venezuelan Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC-4:30'),
			array('abbr'=>'AMT', 'name' => __( 'Amazon Time (Brazil)[2]' , 'inbound-mailer'	) , 'utc' => 'UTC-4'),
			array('abbr'=>'AST', 'name' => __( 'Atlantic Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC-4'),
			array('abbr'=>'BOT', 'name' => __( 'Bolivia Time' , 'inbound-mailer'	) , 'utc' => 'UTC-4'),
			array('abbr'=>'CDT', 'name' => __( 'Cuba Daylight Time[3]' , 'inbound-mailer'	) , 'utc' => 'UTC-4'),
			array('abbr'=>'CLT', 'name' => __( 'Chile Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC-4'),
			array('abbr'=>'COST', 'name' => __( 'Colombia Summer Time' , 'inbound-mailer'	) , 'utc' => 'UTC-4'),
			array('abbr'=>'ECT', 'name' => __( 'Eastern Caribbean Time (does not recognise DST)' , 'inbound-mailer'	) , 'utc' => 'UTC-4'),
			array('abbr'=>'EDT', 'name' => __( 'Eastern Daylight Time (North America)' , 'inbound-mailer'	) , 'utc' => 'UTC-4'),
			array('abbr'=>'FKT', 'name' => __( 'Falkland Islands Time' , 'inbound-mailer'	) , 'utc' => 'UTC-4'),
			array('abbr'=>'GYT', 'name' => __( 'Guyana Time' , 'inbound-mailer'	) , 'utc' => 'UTC-4'),
			array('abbr'=>'PYT', 'name' => __( 'Paraguay Time (Brazil)[7]' , 'inbound-mailer'	) , 'utc' => 'UTC-4'),
			array('abbr'=>'NST', 'name' => __( 'Newfoundland Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC-3:30'),
			array('abbr'=>'NT', 'name' => __( 'Newfoundland Time' , 'inbound-mailer'	) , 'utc' => 'UTC-3:30'),
			array('abbr'=>'ADT', 'name' => __( 'Atlantic Daylight Time' , 'inbound-mailer'	) , 'utc' => 'UTC-3'),
			array('abbr'=>'AMST', 'name' => __( 'Amazon Summer Time (Brazil)[1]' , 'inbound-mailer'	) , 'utc' => 'UTC-3'),
			array('abbr'=>'ART', 'name' => __( 'Argentina Time' , 'inbound-mailer'	) , 'utc' => 'UTC-3'),
			array('abbr'=>'BRT', 'name' => __( 'Brasilia Time' , 'inbound-mailer'	) , 'utc' => 'UTC-3'),
			array('abbr'=>'CLST', 'name' => __( 'Chile Summer Time' , 'inbound-mailer'	) , 'utc' => 'UTC-3'),
			array('abbr'=>'FKST', 'name' => __( 'Falkland Islands Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC-3'),
			array('abbr'=>'FKST', 'name' => __( 'Falkland Islands Summer Time' , 'inbound-mailer'	) , 'utc' => 'UTC-3'),
			array('abbr'=>'GFT', 'name' => __( 'French Guiana Time' , 'inbound-mailer'	) , 'utc' => 'UTC-3'),
			array('abbr'=>'PMST', 'name' => __( 'Saint Pierre and Miquelon Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC-3'),
			array('abbr'=>'PYST', 'name' => __( 'Paraguay Summer Time (Brazil)' , 'inbound-mailer'	) , 'utc' => 'UTC-3'),
			array('abbr'=>'ROTT', 'name' => __( 'Rothera Research Station Time' , 'inbound-mailer'	) , 'utc' => 'UTC-3'),
			array('abbr'=>'SRT', 'name' => __( 'Suriname Time' , 'inbound-mailer'	) , 'utc' => 'UTC-3'),
			array('abbr'=>'UYT', 'name' => __( 'Uruguay Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC-3'),
			array('abbr'=>'NDT', 'name' => __( 'Newfoundland Daylight Time' , 'inbound-mailer'	) , 'utc' => 'UTC-2:30'),
			array('abbr'=>'FNT', 'name' => __( 'Fernando de Noronha Time' , 'inbound-mailer'	) , 'utc' => 'UTC-2'),
			array('abbr'=>'GST', 'name' => __( 'South Georgia and the South Sandwich Islands' , 'inbound-mailer'	) , 'utc' => 'UTC-2'),
			array('abbr'=>'PMDT', 'name' => __( 'Saint Pierre and Miquelon Daylight time' , 'inbound-mailer'	) , 'utc' => 'UTC-2'),
			array('abbr'=>'UYST', 'name' => __( 'Uruguay Summer Time' , 'inbound-mailer'	) , 'utc' => 'UTC-2'),
			array('abbr'=>'AZOST', 'name' => __( 'Azores Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC-1'),
			array('abbr'=>'CVT', 'name' => __( 'Cape Verde Time' , 'inbound-mailer'	) , 'utc' => 'UTC-1'),
			array('abbr'=>'EGT', 'name' => __( 'Eastern Greenland Time' , 'inbound-mailer'	) , 'utc' => 'UTC-1'),
			array('abbr'=>'GMT', 'name' => __( 'Greenwich Mean Time' , 'inbound-mailer'	) , 'utc' => 'UTC'),
			array('abbr'=>'UCT', 'name' => __( 'Coordinated Universal Time' , 'inbound-mailer'	) , 'utc' => 'UTC'),
			array('abbr'=>'UTC', 'name' => __( 'Coordinated Universal Time' , 'inbound-mailer'	) , 'utc' => 'UTC'),
			array('abbr'=>'WET', 'name' => __( 'Western European Time' , 'inbound-mailer'	) , 'utc' => 'UTC'),
			array('abbr'=>'Z', 'name' => __( 'Zulu Time (Coordinated Universal Time)' , 'inbound-mailer'	) , 'utc' => 'UTC'),
			array('abbr'=>'EGST', 'name' => __( 'Eastern Greenland Summer Time' , 'inbound-mailer'	) , 'utc' => 'UTC+00'),
			array('abbr'=>'BST', 'name' => __( 'British Summer Time (British Standard Time from Feb 1968 to Oct 1971)' , 'inbound-mailer'	) , 'utc' => 'UTC+01'),
			array('abbr'=>'CET', 'name' => __( 'Central European Time' , 'inbound-mailer'	) , 'utc' => 'UTC+01'),
			array('abbr'=>'DFT', 'name' => __( 'AIX specific equivalent of Central European Time' , 'inbound-mailer'	) , 'utc' => 'UTC+01'),
			array('abbr'=>'IST', 'name' => __( 'Irish Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+01'),
			array('abbr'=>'MET', 'name' => __( 'Middle European Time Same zone as CET' , 'inbound-mailer'	) , 'utc' => 'UTC+01'),
			array('abbr'=>'WAT', 'name' => __( 'West Africa Time' , 'inbound-mailer'	) , 'utc' => 'UTC+01'),
			array('abbr'=>'WEDT', 'name' => __( 'Western European Daylight Time' , 'inbound-mailer'	) , 'utc' => 'UTC+01'),
			array('abbr'=>'WEST', 'name' => __( 'Western European Summer Time' , 'inbound-mailer'	) , 'utc' => 'UTC+01'),
			array('abbr'=>'CAT', 'name' => __( 'Central Africa Time' , 'inbound-mailer'	) , 'utc' => 'UTC+02'),
			array('abbr'=>'CEDT', 'name' => __( 'Central European Daylight Time' , 'inbound-mailer'	) , 'utc' => 'UTC+02'),
			array('abbr'=>'CEST', 'name' => __( 'Central European Summer Time (Cf. HAEC)' , 'inbound-mailer'	) , 'utc' => 'UTC+02'),
			array('abbr'=>'EET', 'name' => __( 'Eastern European Time' , 'inbound-mailer'	) , 'utc' => 'UTC+02'),
			array('abbr'=>'HAEC', 'name' => __( 'Heure Avancée d\'Europe Centrale francised name for CEST' , 'inbound-mailer'	) , 'utc' => 'UTC+02'),
			array('abbr'=>'IST', 'name' => __( 'Israel Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+02'),
			array('abbr'=>'MEST', 'name' => __( 'Middle European Saving Time Same zone as CEST' , 'inbound-mailer'	) , 'utc' => 'UTC+02'),
			array('abbr'=>'SAST', 'name' => __( 'South African Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+02'),
			array('abbr'=>'WAST', 'name' => __( 'West Africa Summer Time' , 'inbound-mailer'	) , 'utc' => 'UTC+02'),
			array('abbr'=>'AST', 'name' => __( 'Arabia Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+03'),
			array('abbr'=>'EAT', 'name' => __( 'East Africa Time' , 'inbound-mailer'	) , 'utc' => 'UTC+03'),
			array('abbr'=>'EEDT', 'name' => __( 'Eastern European Daylight Time' , 'inbound-mailer'	) , 'utc' => 'UTC+03'),
			array('abbr'=>'EEST', 'name' => __( 'Eastern European Summer Time' , 'inbound-mailer'	) , 'utc' => 'UTC+03'),
			array('abbr'=>'FET', 'name' => __( 'Further-eastern European Time' , 'inbound-mailer'	) , 'utc' => 'UTC+03'),
			array('abbr'=>'IDT', 'name' => __( 'Israel Daylight Time' , 'inbound-mailer'	) , 'utc' => 'UTC+03'),
			array('abbr'=>'IOT', 'name' => __( 'Indian Ocean Time' , 'inbound-mailer'	) , 'utc' => 'UTC+03'),
			array('abbr'=>'SYOT', 'name' => __( 'Showa Station Time' , 'inbound-mailer'	) , 'utc' => 'UTC+03'),
			array('abbr'=>'IRST', 'name' => __( 'Iran Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+03:30'),
			array('abbr'=>'AMT', 'name' => __( 'Armenia Time' , 'inbound-mailer'	) , 'utc' => 'UTC+04'),
			array('abbr'=>'AZT', 'name' => __( 'Azerbaijan Time' , 'inbound-mailer'	) , 'utc' => 'UTC+04'),
			array('abbr'=>'GET', 'name' => __( 'Georgia Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+04'),
			array('abbr'=>'GST', 'name' => __( 'Gulf Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+04'),
			array('abbr'=>'MSK', 'name' => __( 'Moscow Time' , 'inbound-mailer'	) , 'utc' => 'UTC+04'),
			array('abbr'=>'MUT', 'name' => __( 'Mauritius Time' , 'inbound-mailer'	) , 'utc' => 'UTC+04'),
			array('abbr'=>'RET', 'name' => __( 'Réunion Time' , 'inbound-mailer'	) , 'utc' => 'UTC+04'),
			array('abbr'=>'SAMT', 'name' => __( 'Samara Time' , 'inbound-mailer'	) , 'utc' => 'UTC+04'),
			array('abbr'=>'SCT', 'name' => __( 'Seychelles Time' , 'inbound-mailer'	) , 'utc' => 'UTC+04'),
			array('abbr'=>'VOLT', 'name' => __( 'Volgograd Time' , 'inbound-mailer'	) , 'utc' => 'UTC+04'),
			array('abbr'=>'AFT', 'name' => __( 'Afghanistan Time' , 'inbound-mailer'	) , 'utc' => 'UTC+04:30'),
			array('abbr'=>'AMST', 'name' => __( 'Armenia Summer Time' , 'inbound-mailer'	) , 'utc' => 'UTC+05'),
			array('abbr'=>'HMT', 'name' => __( 'Heard and McDonald Islands Time' , 'inbound-mailer'	) , 'utc' => 'UTC+05'),
			array('abbr'=>'MAWT', 'name' => __( 'Mawson Station Time' , 'inbound-mailer'	) , 'utc' => 'UTC+05'),
			array('abbr'=>'MVT', 'name' => __( 'Maldives Time' , 'inbound-mailer'	) , 'utc' => 'UTC+05'),
			array('abbr'=>'ORAT', 'name' => __( 'Oral Time' , 'inbound-mailer'	) , 'utc' => 'UTC+05'),
			array('abbr'=>'PKT', 'name' => __( 'Pakistan Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+05'),
			array('abbr'=>'TFT', 'name' => __( 'Indian/Kerguelen' , 'inbound-mailer'	) , 'utc' => 'UTC+05'),
			array('abbr'=>'TJT', 'name' => __( 'Tajikistan Time' , 'inbound-mailer'	) , 'utc' => 'UTC+05'),
			array('abbr'=>'TMT', 'name' => __( 'Turkmenistan Time' , 'inbound-mailer'	) , 'utc' => 'UTC+05'),
			array('abbr'=>'UZT', 'name' => __( 'Uzbekistan Time' , 'inbound-mailer'	) , 'utc' => 'UTC+05'),
			array('abbr'=>'IST', 'name' => __( 'Indian Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+05:30'),
			array('abbr'=>'SLST', 'name' => __( 'Sri Lanka Time' , 'inbound-mailer'	) , 'utc' => 'UTC+05:30'),
			array('abbr'=>'NPT', 'name' => __( 'Nepal Time' , 'inbound-mailer'	) , 'utc' => 'UTC+05:45'),
			array('abbr'=>'BIOT', 'name' => __( 'British Indian Ocean Time' , 'inbound-mailer'	) , 'utc' => 'UTC+06'),
			array('abbr'=>'BST', 'name' => __( 'Bangladesh Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+06'),
			array('abbr'=>'BTT', 'name' => __( 'Bhutan Time' , 'inbound-mailer'	) , 'utc' => 'UTC+06'),
			array('abbr'=>'KGT', 'name' => __( 'Kyrgyzstan time' , 'inbound-mailer'	) , 'utc' => 'UTC+06'),
			array('abbr'=>'VOST', 'name' => __( 'Vostok Station Time' , 'inbound-mailer'	) , 'utc' => 'UTC+06'),
			array('abbr'=>'YEKT', 'name' => __( 'Yekaterinburg Time' , 'inbound-mailer'	) , 'utc' => 'UTC+06'),
			array('abbr'=>'CCT', 'name' => __( 'Cocos Islands Time' , 'inbound-mailer'	) , 'utc' => 'UTC+06:30'),
			array('abbr'=>'MMT', 'name' => __( 'Myanmar Time' , 'inbound-mailer'	) , 'utc' => 'UTC+06:30'),
			array('abbr'=>'MYST', 'name' => __( 'Myanmar Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+06:30'),
			array('abbr'=>'CXT', 'name' => __( 'Christmas Island Time' , 'inbound-mailer'	) , 'utc' => 'UTC+07'),
			array('abbr'=>'DAVT', 'name' => __( 'Davis Time' , 'inbound-mailer'	) , 'utc' => 'UTC+07'),
			array('abbr'=>'HOVT', 'name' => __( 'Khovd Time' , 'inbound-mailer'	) , 'utc' => 'UTC+07'),
			array('abbr'=>'ICT', 'name' => __( 'Indochina Time' , 'inbound-mailer'	) , 'utc' => 'UTC+07'),
			array('abbr'=>'KRAT', 'name' => __( 'Krasnoyarsk Time' , 'inbound-mailer'	) , 'utc' => 'UTC+07'),
			array('abbr'=>'OMST', 'name' => __( 'Omsk Time' , 'inbound-mailer'	) , 'utc' => 'UTC+07'),
			array('abbr'=>'THA', 'name' => __( 'Thailand Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+07'),
			array('abbr'=>'ACT', 'name' => __( 'ASEAN Common Time' , 'inbound-mailer'	) , 'utc' => 'UTC+08'),
			array('abbr'=>'AWST', 'name' => __( 'Australian Western Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+08'),
			array('abbr'=>'BDT', 'name' => __( 'Brunei Time' , 'inbound-mailer'	) , 'utc' => 'UTC+08'),
			array('abbr'=>'CHOT', 'name' => __( 'Choibalsan' , 'inbound-mailer'	) , 'utc' => 'UTC+08'),
			array('abbr'=>'CIT', 'name' => __( 'Central Indonesia Time' , 'inbound-mailer'	) , 'utc' => 'UTC+08'),
			array('abbr'=>'CST', 'name' => __( 'China Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+08'),
			array('abbr'=>'CT', 'name' => __( 'China time' , 'inbound-mailer'	) , 'utc' => 'UTC+08'),
			array('abbr'=>'HKT', 'name' => __( 'Hong Kong Time' , 'inbound-mailer'	) , 'utc' => 'UTC+08'),
			array('abbr'=>'IRDT', 'name' => __( 'Iran Daylight Time' , 'inbound-mailer'	) , 'utc' => 'UTC+08'),
			array('abbr'=>'MYT', 'name' => __( 'Malaysia Time' , 'inbound-mailer'	) , 'utc' => 'UTC+08'),
			array('abbr'=>'PHT', 'name' => __( 'Philippine Time' , 'inbound-mailer'	) , 'utc' => 'UTC+08'),
			array('abbr'=>'SGT', 'name' => __( 'Singapore Time' , 'inbound-mailer'	) , 'utc' => 'UTC+08'),
			array('abbr'=>'SST', 'name' => __( 'Singapore Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+08'),
			array('abbr'=>'ULAT', 'name' => __( 'Ulaanbaatar Time' , 'inbound-mailer'	) , 'utc' => 'UTC+08'),
			array('abbr'=>'WST', 'name' => __( 'Western Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+08'),
			array('abbr'=>'CWST', 'name' => __( 'Central Western Standard Time (Australia)' , 'inbound-mailer'	) , 'utc' => 'UTC+08:45'),
			array('abbr'=>'AWDT', 'name' => __( 'Australian Western Daylight Time' , 'inbound-mailer'	) , 'utc' => 'UTC+09'),
			array('abbr'=>'EIT', 'name' => __( 'Eastern Indonesian Time' , 'inbound-mailer'	) , 'utc' => 'UTC+09'),
			array('abbr'=>'IRKT', 'name' => __( 'Irkutsk Time' , 'inbound-mailer'	) , 'utc' => 'UTC+09'),
			array('abbr'=>'JST', 'name' => __( 'Japan Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+09'),
			array('abbr'=>'KST', 'name' => __( 'Korea Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+09'),
			array('abbr'=>'TLT', 'name' => __( 'Timor Leste Time' , 'inbound-mailer'	) , 'utc' => 'UTC+09'),
			array('abbr'=>'ACST', 'name' => __( 'Australian Central Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+09:30'),
			array('abbr'=>'CST', 'name' => __( 'Central Standard Time (Australia)' , 'inbound-mailer'	) , 'utc' => 'UTC+09:30'),
			array('abbr'=>'AEST', 'name' => __( 'Australian Eastern Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+10'),
			array('abbr'=>'ChST', 'name' => __( 'Chamorro Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+10'),
			array('abbr'=>'CHUT', 'name' => __( 'Chuuk Time' , 'inbound-mailer'	) , 'utc' => 'UTC+10'),
			array('abbr'=>'DDUT', 'name' => __( 'Dumont d\'Urville Time' , 'inbound-mailer'	) , 'utc' => 'UTC+10'),
			array('abbr'=>'EST', 'name' => __( 'Eastern Standard Time (Australia)' , 'inbound-mailer'	) , 'utc' => 'UTC+10'),
			array('abbr'=>'PGT', 'name' => __( 'Papua New Guinea Time' , 'inbound-mailer'	) , 'utc' => 'UTC+10'),
			array('abbr'=>'VLAT', 'name' => __( 'Vladivostok Time' , 'inbound-mailer'	) , 'utc' => 'UTC+10'),
			array('abbr'=>'YAKT', 'name' => __( 'Yakutsk Time' , 'inbound-mailer'	) , 'utc' => 'UTC+10'),
			array('abbr'=>'ACDT', 'name' => __( 'Australian Central Daylight Time' , 'inbound-mailer'	) , 'utc' => 'UTC+10:30'),
			array('abbr'=>'CST', 'name' => __( 'Central Summer Time (Australia)' , 'inbound-mailer'	) , 'utc' => 'UTC+10:30'),
			array('abbr'=>'LHST', 'name' => __( 'Lord Howe Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+10:30'),
			array('abbr'=>'AEDT', 'name' => __( 'Australian Eastern Daylight Time' , 'inbound-mailer'	) , 'utc' => 'UTC+11'),
			array('abbr'=>'KOST', 'name' => __( 'Kosrae Time' , 'inbound-mailer'	) , 'utc' => 'UTC+11'),
			array('abbr'=>'LHST', 'name' => __( 'Lord Howe Summer Time' , 'inbound-mailer'	) , 'utc' => 'UTC+11'),
			array('abbr'=>'MIST', 'name' => __( 'Macquarie Island Station Time' , 'inbound-mailer'	) , 'utc' => 'UTC+11'),
			array('abbr'=>'NCT', 'name' => __( 'New Caledonia Time' , 'inbound-mailer'	) , 'utc' => 'UTC+11'),
			array('abbr'=>'PONT', 'name' => __( 'Pohnpei Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+11'),
			array('abbr'=>'SAKT', 'name' => __( 'Sakhalin Island time' , 'inbound-mailer'	) , 'utc' => 'UTC+11'),
			array('abbr'=>'SBT', 'name' => __( 'Solomon Islands Time' , 'inbound-mailer'	) , 'utc' => 'UTC+11'),
			array('abbr'=>'VUT', 'name' => __( 'Vanuatu Time' , 'inbound-mailer'	) , 'utc' => 'UTC+11'),
			array('abbr'=>'NFT', 'name' => __( 'Norfolk Time' , 'inbound-mailer'	) , 'utc' => 'UTC+11:30'),
			array('abbr'=>'FJT', 'name' => __( 'Fiji Time' , 'inbound-mailer'	) , 'utc' => 'UTC+12'),
			array('abbr'=>'GILT', 'name' => __( 'Gilbert Island Time' , 'inbound-mailer'	) , 'utc' => 'UTC+12'),
			array('abbr'=>'MAGT', 'name' => __( 'Magadan Time' , 'inbound-mailer'	) , 'utc' => 'UTC+12'),
			array('abbr'=>'MHT', 'name' => __( 'Marshall Islands' , 'inbound-mailer'	) , 'utc' => 'UTC+12'),
			array('abbr'=>'NZST', 'name' => __( 'New Zealand Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+12'),
			array('abbr'=>'PETT', 'name' => __( 'Kamchatka Time' , 'inbound-mailer'	) , 'utc' => 'UTC+12'),
			array('abbr'=>'TVT', 'name' => __( 'Tuvalu Time' , 'inbound-mailer'	) , 'utc' => 'UTC+12'),
			array('abbr'=>'WAKT', 'name' => __( 'Wake Island Time' , 'inbound-mailer'	) , 'utc' => 'UTC+12'),
			array('abbr'=>'CHAST', 'name' => __( 'Chatham Standard Time' , 'inbound-mailer'	) , 'utc' => 'UTC+12:45'),
			array('abbr'=>'NZDT', 'name' => __( 'New Zealand Daylight Time' , 'inbound-mailer'	) , 'utc' => 'UTC+13'),
			array('abbr'=>'PHOT', 'name' => __( 'Phoenix Island Time' , 'inbound-mailer'	) , 'utc' => 'UTC+13'),
			array('abbr'=>'TOT', 'name' => __( 'Tonga Time' , 'inbound-mailer'	) , 'utc' => 'UTC+13'),
			array('abbr'=>'CHADT', 'name' => __( 'Chatham Daylight Time' , 'inbound-mailer'	) , 'utc' => 'UTC+13:45'),
			array('abbr'=>'LINT', 'name' => __( 'Line Islands Time' , 'inbound-mailer'	) , 'utc' => 'UTC+14'),
			array('abbr'=>'TKT', 'name' => __( 'Tokelau Time' , 'inbound-mailer'	) , 'utc' => 'UTC+14'),
		);

	}

}

