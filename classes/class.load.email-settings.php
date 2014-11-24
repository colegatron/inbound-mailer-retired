<?php

/**
 * Extension hooks and filters as well as default settings for core components
 *
 * @package	Inbouns Mailer
 * @subpackage	Extensions
*/

class Inbound_Mailer_Common_Settings {
	private static $instance;
	public $settings;

	public static function instance()
	{
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Inbound_Mailer_Common_Settings ) )
		{
			self::$instance = new Inbound_Mailer_Common_Settings;		
			self::$instance->add_common_settings();		
			self::$instance->load_settings();
		}

		return self::$instance;
	}


	/**
	*  	filters to add in core definitions to the calls to action extension definitions array 
	*/
	function add_common_settings() {
		self::add_addressing_settings();
		self::add_batch_send_settings();

	}
	
	/**
	*  Adds default mail settings
	*/
	function add_addressing_settings(){
	

		self::$instance->settings['email-settings']['inbound_subject'] =  array(
			'label' => __( 'Subject Line' , 'inbound-mailer' ),
			'description' => __( 'Subject line of the email' , 'inbound-mailer' ) ,
			'id'  => 'inbound_subject',
			'type'  => 'text',
			'default'  => '',
			'class' => '',
			'context'  => 'priority',
			'global' => true
		);
		
		self::$instance->settings['email-settings']['inbound_from_name'] =  array(
			'label' => __( 'From Name' , 'inbound-mailer' ),
			'description' => __( 'The name of the sender.' , 'inbound-mailer' ) ,
			'id'  => 'inbound_from_name',
			'type'  => 'text',
			'default'  => '',
			'class' => '',
			'context'  => 'priority',
			'global' => true
		);
		
		self::$instance->settings['email-settings']['inbound_from_email'] =  array(
			'label' => __( 'From Email' , 'inbound-mailer' ),
			'description' => __( 'The email address of the sender.' , 'inbound-mailer' ) ,
			'id'  => 'inbound_from_email',
			'type'  => 'text',
			'default'  => '',
			'class' => '',
			'context'  => 'priority',
			'global' => true
		);			

	}

	
		
	/**
	* adds batch send settings
	*/
	function add_batch_send_settings() {
		
		self::$instance->settings['batch-send-settings']['inbound_batch_send_nature'] = array(
			'id'  => 'inbound_batch_send_nature',
			'label' => __( 'Queue Setting' , 'inbound-email' ),
			'description' => __( 'Would you like to schedule this email or send it manually?' , 'inbound-email' ),
			'type'  => 'dropdown', 
			'default' => '',
			'global' => true,
			'options' => array( 
				'ready' => __( 'Manual Send' , 'inbound-mailer' ) ,
				'schedule' => __( 'Scheduled Send' , 'inbound-mailer' )
			)
		);
		
		$lead_lists = Inbound_Leads::get_lead_lists_as_array();
		
		self::$instance->settings['batch-send-settings']['inbound_recipients'] = array(
			'id'  => 'inbound_recipients',
			'label' => __( 'Select recipients' , 'inbound-email' ),
			'description' => __( 'This option provides a placeholder for the selected template data.' , 'inbound-email' ),
			'type'  => 'select2', 
			'default' => '',
			'placeholder' => __( 'Select lists to send mail to.' , 'inbound-mailer' ),
			'options' => $lead_lists,
			'global' => true
		);
		
		self::$instance->settings['batch-send-settings']['inbound_send_datetime'] = array(
			'id'  => 'inbound_send_datetime',
			'label' => __( 'Send Date/Time' , 'inbound-email' ),
			'description' => __( 'Select the date and time you would like this message to send.' , 'inbound-email' ),
			'type'  => 'datepicker', 
			'default' => '',
			'placeholder' => __( 'Select lists to send mail to.' , 'inbound-mailer' ),
			'options' => $lead_lists,
			'global' => true
		);


	}
	
	/**
	*  Makes template definitions filterable
	*/
	function load_settings() {
		self::$instance->settings = apply_filters( 'inbound_email_common_settings' , self::$instance->settings );
	}
}

/**
*  Allows quick calling of instance
*/
function Inbound_Mailer_Common_Settings() {
	return Inbound_Mailer_Common_Settings::instance();
}
