<?php


class Inbound_Mailer_Unsubscribe {

	/**
	*  Initialize class
	*/
	public function __construct() {

		self::load_hooks();
	}

	/**
	*  Loads hooks and filters
	*/
	public function load_hooks() {

		/* Add processing listeners  */
		add_action( 'init' , array( __class__ , 'process_unsubscribe' ) );
		
		/* Shortcode for displaying unsubscribe page */
		add_shortcode( 'inbound-email-unsubscribe', array( __CLASS__, 'display_unsubscribe_page' ), 1 );

	}

	/**
	* Display unsubscribe options
	*/
	public static function display_unsubscribe_page( $atts ) {
		
		
		if ( !isset( $_GET['token'] ) ) {
			return __( 'Invalid token' , 'inbound-email' );
		}
		
		/* get all lead lists */
		$lead_lists = Inbound_Leads::get_lead_lists_as_array(); 
		
		/* decode token */
		$unsubscribe = self::decode_unsubscribe_token( $_GET['token'] );
		
		/* Begin unsubscribe html inputs */
		$html = "<form action='' name='unsubscribe'>";
		$html .= "<input type='hidden' name='token' value='".$_GET['token']."' >";
		
		/* loop through lists and show unsubscribe inputs */
		if ( isset($unsubscribe['list_ids']) ) {
			foreach ($unsubscribe['list_ids'] as $list_id ) {
				if ($list_id == '-1') {
					continue;
				}	
				$html .= "<input type='checkbox' name='list_id[]' value='".$list_id."'> " . $lead_lists[ $list_id ];
				
			}
		}
		
		$html .= "<input name='unsubscribe_all' type='checkbox' value='all'> " . __( 'Usubscribe from all emails' , 'inbound-mailer' );
		$html .= "</form>";
		return $html;
		
	}

	/**
	*  Generates unsubscribe link given lead id and lists
	*  @param INT $lead_id ID of lead
	*  @param MIXED $list_ids array of list ids or commas delimited list of list ids
	*  @return STRING $unsubscribe_link
	*/
	public static function generate_unsubscribe_link( $lead_id = '-1' , $list_ids = '-1' ) {
		
		if (!is_array($list_ids)) {
			$list_ids = explode( ',' , $list_ids);
		}
		
		$token = Inbound_Mailer_Unsubscribe::encode_unsubscribe_token( $lead_id , $list_ids );
		$unsubscribe_page_id = Inbound_Options_API::get_option( 'inbound-email' , 'unsubscribe-page' , null);
		$base_url = get_permalink( $unsubscribe_page_id );
		
		return add_query_arg( array( 'token'=>$token ) , $base_url );
		
	}
	
	/**
	*  Encodes data into an unsubscribe token 
	*  @param INT $lead_id ID of lead
	*  @param ARRAY $list_ids array of list ids
	*  @return INT $token
	*/
	public static function encode_unsubscribe_token( $lead_id , $list_ids ) {
		$prepare = array( 'lead_id' => $lead_id , 'list_ids' => $list_ids );
		$json = json_encode($prepare);
		
		
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		$encrypted_string = 
		base64_encode( 
			trim(
				mcrypt_encrypt(
					MCRYPT_RIJNDAEL_256, substr( SECURE_AUTH_KEY , 0 , 24 )  , $json, MCRYPT_MODE_ECB, $iv
				)
			)
		);

		return  str_replace(array('+', '/'), array('-', '_'), $encrypted_string);
	}
	
	/**
	*  Decodes unsubscribe encoded reader id into a lead id
	*  @param STRING $reader_id Encoded lead id.
	*  @return ARRAY $unsubscribe array of unsubscribe data
	*/
	public static function decode_unsubscribe_token( $token ) {
		
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		$decrypted_string = 		
		trim(
			mcrypt_decrypt( 
					MCRYPT_RIJNDAEL_256 ,  substr( SECURE_AUTH_KEY , 0 , 24 )   ,  base64_decode( str_replace(array('-', '_'), array('+', '/'), $token ) ) , MCRYPT_MODE_ECB, $iv
			)
		);

		return json_decode($decrypted_string , true);
		
	}
	
	/**
	*  Unsubscribe lead from all lists
	*/
	public static function unsubscribe_from_all_lists( $lead_id = null ) {
		/* get all lead lists */
		$lead_lists = Inbound_Leads::get_lead_lists_as_array(); 
		
		foreach ( $lead_lists as $list_id => $label ) {
			Inbound_Leads::remove_lead_from_list( $lead_id , $list_id );
			Inbound_Mailer_Unsubscribe::add_stop_sort( $lead_id , $list_id );
		}
		
	}
	
	/**
	*  Adds a list id to a leads unsubscribed list
	*  @param INT $lead_id 
	*  @param INT $list_id
	*/
	public static function add_stop_sort( $lead_id , $list_id ) {
		$stop_rules = get_post_meta( $lead_id , 'inbound_unsubscribed' , true );
		
		if ( !$stop_rules ) {
			$stop_rules = array();
		}
		
		$stop_rules[ $list_id ] = true;
		
		update_post_meta( $lead_id , 'inbound_unsubscribed' , $stop_rules );
	}
	
	/**
	*  Removes a list id to a leads unsubscribed list
	*  @param INT $lead_id 
	*  @param INT $list_id
	*/
	public static function remove_stop_sort( $lead_id , $list_id ) {
		$stop_rules = get_post_meta( $lead_id , 'inbound_unsubscribed' , true );
		
		if ( !$stop_rules ) {
			return;
		}
		
		if (!isset($stop_rules[$list_id])) {
			return;
		}
		
		unset( $stop_rules[$list_id] );
		
		update_post_meta( $lead_id , 'inbound_unsubscribed' , $stop_rules );
	}

	/**
	*  Listener & unsubscribe actions 
	*/
	public static function process_unsubscribe() {
		if (isset($_POST['unsubscribe'])) {
			/* decode token */
			$unsubscribe = self::decode_unsubscribe_token( $_POST['token'] );
			
			/* determine if anything is selected */
			if (!isset($_POST['list_ids'])) {
				return;
			}
			
			/* check if unsubscribe all is selected */
			if (isset($_POST['unsubscribe_all'])) {
				self::unsubscribe_from_all_lists( $unsubscribe['lead_id'] );
			}
		}
	}
	
}

$Inbound_Mailer_Unsubscribe = new Inbound_Mailer_Unsubscribe();
