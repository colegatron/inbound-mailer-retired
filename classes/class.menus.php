<?php

if ( !class_exists('Inbound_Mailer_Menus') ) {

/**
*  Loads admin sub-menus and performs misc menu related functions
*/
class Inbound_Mailer_Menus {

	/**
	*  Initializes class
	*/
	public function __construct() {
		self::load_hooks();
	}
	
	/**
	*  Loads hooks and filters
	*/
	public static function load_hooks() {
		add_action('admin_menu', array( __CLASS__ , 'add_sub_menus' ) );
	}
	
	/**
	*  Adds sub-menus to 'Inbound Email Component'
	*/
	public static function add_sub_menus() {
		if ( !current_user_can('manage_options')) {
			return;
		}
	
		add_submenu_page('edit.php?post_type=inbound-email', __( 'Templates' , 'inbound-email' ) , __( 'Templates' , 'inbound-email' ) , 'manage_options', 'inbound_email_manage_templates', array( 'Inbound_Mailer_Template_Manager' , 'display_management_page' ) );

		add_submenu_page('edit.php?post_type=inbound-email', __( 'Settings' , 'inbound-email' ) , __( 'Settings' , 'inbound-email') , 'manage_options', 'inbound_email_global_settings', array( 'Inbound_Mailer_Global_Settings' , 'display_global_settings' ) );

	}
	
}

/** 
*  Loads Class Pre-Init 
*/
$Inbound_Mailer_Menus = new Inbound_Mailer_Menus();

}