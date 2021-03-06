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
		add_action( 'init' , array( __class__ , 'process_unsubscribe' ) , 20 );

		/* Shortcode for displaying unsubscribe page */
		add_shortcode( 'inbound-email-unsubscribe' , array( __CLASS__, 'display_unsubscribe_page' ), 1 );

	}

	/**
	 * Display unsubscribe options
	 */
	public static function display_unsubscribe_page( $atts ) {



		if ( isset( $_GET['unsubscribed'] ) ) {
			$unsubscribed_confirmation_message = apply_filters( 'inbound_mailer_unsubscribe_message' , __('Thank you!' , 'inbound-email' ) );
			return "<span class='unsubscribed-message'>". $unsubscribed_confirmation_message ."</span>";
		}

		if ( !isset( $_GET['token'] ) ) {
			return __( 'Invalid token' , 'inbound-email' );
		}



		/* get all lead lists */
		$lead_lists = Inbound_Leads::get_lead_lists_as_array();

		/* decode token */
		$params = self::decode_unsubscribe_token( $_GET['token'] );

		if ( !isset( $params['lead_id'] ) ) {
			return __( 'Oops. Something is wrong with the unsubscribe link. Are you logged in?' , 'inbound-email' );
		}

		/* Begin unsubscribe html inputs */
		$html = "<form action='?unsubscribed=true' name='unsubscribe' method='post'>";
		$html .= "<input type='hidden' name='token' value='".$_GET['token']."' >";

		/* loop through lists and show unsubscribe inputs */
		if ( isset($params['list_ids']) ) {
			foreach ($params['list_ids'] as $list_id ) {
				if ($list_id == '-1' || !$list_id ) {
					continue;
				}

				$html .= "<span class='unsubscribe-span'><label class='lead-list-label'><input type='checkbox' name='list_id[]' value='".$list_id."' class='lead-list-class'> " . $lead_lists[ $list_id ] . '</label></span>';

			}
		}

		$html .= "<span class='unsubscribe-span'><label class='lead-list-label'><input name='unsubscribe_all' type='checkbox' value='all'> " . __( 'Usubscribe from all emails' , 'inbound-email' ) .'</label></span>';
		$html .= "<div class='unsubscribe-div unsubsribe-comments'>";
		$html .= "	<span class='unsubscribe-comments-message'>". __( 'Please help us improve by letting us know why you are unsubscribing.' , 'inbound-email' ) ."</span>";
		$html .= "	<span class='unsubscribe-comments-label'>". __('Comments:' , 'inbound-email') ."<br><textarea rows='8' cols='60' name='comments'></textarea></span>";
		$html .= "</div>";
		$html .= "<span class='unsubscribe-span'><label class='unsubscribe-label'><input name='unsubscribe' type='submit' value='". __( 'Unsubscribe' , 'inbound-email' ) ."' class='inbound-button-submit inbound-submit-action'></label></span>";
		$html .= "</form>";
		return $html;

	}

	/**
	 *  Generates unsubscribe link given lead id and lists
	 *  @param ARRAY $params contains: lead_id (INT ), list_ids (MIXED), email_id (INT)
	 *  @return STRING $unsubscribe_link
	 */
	public static function generate_unsubscribe_link( $params ) {

		if (!is_admin()) {
			return __( '#unsubscribe-not-available-in-online-mode' , 'inbound-pro' );
		}

		if (isset($_GET['lead_lists']) && !is_array($_GET['lead_lists'])){
			$params['list_ids'] = explode( ',' , $_GET['lead_lists']);
		} else if (isset($params['list_ids']) && !is_array($params['list_ids'])) {
			$params['list_ids'] = explode( ',' , $params['list_ids']);
		}


		$args = array_merge( $params , $_GET );

		$token = Inbound_Mailer_Unsubscribe::encode_unsubscribe_token( $args );
		$settings = Inbound_Mailer_Settings::get_settings();

		if ( empty($settings['unsubscribe_page']) )  {
			$post = get_page_by_title( __( 'Unsubscribe' , 'inbound-email' ) );
			$settings['unsubscribe_page'] =  $post->ID;
		}

		$base_url = get_permalink( $settings['unsubscribe_page']  );

		return add_query_arg( array( 'token'=>$token ) , $base_url );

	}

	/**
	 *  Encodes data into an unsubscribe token
	 *  @param ARRAY $params contains: lead_id (INT ), list_ids (MIXED), email_id (INT)
	 *  @return INT $token
	 */
	public static function encode_unsubscribe_token( $params ) {
		;
		$json = json_encode($params);


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

		if (!isset($_POST['unsubscribe'])) {
			return;
		}

		/* decode token */
		$params = self::decode_unsubscribe_token( $_POST['token'] );

		/* add comments */
		$params['event_details']['comments'] = ( isset( $_POST['comments'] ) ) ? $_POST['comments'] : '';
		$params['event_details']['list_ids'] = $params['list_ids'];
		$params['event_details'] = json_encode( $params['event_details'] );

		/* Debug
		$params['lead_id'] = '97131'; */

		/* check if unsubscribe all is selected */
		if (isset($_POST['unsubscribe_all'])) {
			self::unsubscribe_from_all_lists( $params['lead_id'] );
			Inbound_Events::store_unsubscribe_event( $params );
		}

		/* determine if anything is selected */
		if (!isset($_POST['list_id'])) {
			return;
		}

		/* loop through lists and unsubscribe lead */
		foreach( $_POST['list_id'] as $list_id ) {
			Inbound_Leads::remove_lead_from_list( $params['lead_id'] , $list_id );
			Inbound_Mailer_Unsubscribe::add_stop_sort( $params['lead_id'] , $list_id );
			Inbound_Events::store_unsubscribe_event( $params );
		}
	}


	/**
	 *  Adds a list id to a leads unsubscribed list
	 *  @param INT $lead_id
	 *  @param INT $list_id
	 */
	public static function add_stop_sort( $lead_id , $list_id ) {
		$stop_rules = self::get_stop_sort( $lead_id );

		$stop_rules[ $list_id ] = true;

		update_post_meta( $lead_id , 'inbound_unsubscribed' , $stop_rules );
	}

	/**
	 *  Adds a list id to a leads unsubscribed list
	 *  @param INT $lead_id
	 *  @param INT $list_id
	 */
	public static function get_stop_sort( $lead_id ) {
		$stop_rules = get_post_meta( $lead_id , 'inbound_unsubscribed' , true );

		if ( !$stop_rules ) {
			$stop_rules = array();
		}

		return $stop_rules;
	}

}

$Inbound_Mailer_Unsubscribe = new Inbound_Mailer_Unsubscribe();
