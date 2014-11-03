<?php

/**
 * Extension hooks and filters as well as default settings for core components
 *
 * @package	Inbouns Mailer
 * @subpackage	Extensions
*/
if( !class_exists('Inbound_Mailer_Load_Extensions') ) {

	class Inbound_Mailer_Load_Extensions {
		private static $instance;
		public $definitions;
		public $template_categories;

		public static function instance()
		{
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Inbound_Mailer_Load_Extensions ) )
			{
				self::$instance = new Inbound_Mailer_Load_Extensions;

				/* if frontend load transient data - this data will update on every wp-admin call so you can use an admin call as a cache clear */
				if ( !is_admin() )
				{
					self::$instance->template_definitions = get_transient('inbound_email_template_definitions');

					if ( self::$instance->template_definitions ) {
						return self::$instance;
					}
				}

				self::$instance->include_template_files();
				self::$instance->add_core_definitions();
				self::$instance->load_definitions();
				self::$instance->read_template_categories();
			}

			return self::$instance;
		}

		function include_template_files()
		{
			/* load templates from wp-content/plugins/inbound-email/templates/ */

			$core_templates = self::$instance->get_core_templates();

			foreach ($core_templates as $name)
			{
				if ($name != ".svn"){
					include_once(INBOUND_EMAIL_PATH."templates/$name/config.php");
				}
			}

			/* load templates from uploads folder */
			$uploaded_templates = self::$instance->get_uploaded_templates();

			foreach ($uploaded_templates as $name) {
				include_once( INBOUND_EMAIL_UPLOADS_PATH."$name/config.php");
			}

			/* parse template markup */
			foreach ($inbound_email_data as $key => $data) {
				if (isset($data['markup'])) {
					$parsed = self::$instance->parse_markup($data['markup']);
					$inbound_email_data[$key]['css-template'] = $parsed['css-template'];
					$inbound_email_data[$key]['html-template'] = $parsed['html-template'];
				}
			}

			self::$instance->template_definitions = $inbound_email_data;


		}

		function parse_markup($markup)
		{
			if(strstr($markup,'</style>'))
			{
				$pieces = explode('</style>' , $markup);
				$parsed['css-template'] = strip_tags($pieces[0]);
				$parsed['html-template'] = $pieces[1];
			}
			else
			{
				$parsed['css-template'] = "";
				$parsed['html-template'] = $markup;
			}

			return $parsed;
		}

		function get_core_templates()
		{
			$core_templates = array();
			$template_path = INBOUND_EMAIL_PATH."templates/" ;
			$results = scandir($template_path);

			//scan through templates directory and pull in name paths
			foreach ($results as $name) {
				if ($name === '.' or $name === '..' or $name === '__MACOSX') continue;

				if (is_dir($template_path . '/' . $name)) {
					$core_templates[] = $name;
				}
			}

			return $core_templates;
		}

		function get_uploaded_templates()
		{
			//scan through templates directory and pull in name paths
			$uploaded_templates = array();

			if (!is_dir( INBOUND_EMAIL_UPLOADS_PATH ))
			{
				wp_mkdir_p( INBOUND_EMAIL_UPLOADS_PATH );
			}

			$templates = scandir( INBOUND_EMAIL_UPLOADS_PATH );


			//scan through templates directory and pull in name paths
			foreach ($templates as $name) {
				if ($name === '.' or $name === '..' or $name === '__MACOSX') continue;

				if ( is_dir( INBOUND_EMAIL_UPLOADS_PATH . '/' . $name ) ) {
					$uploaded_templates[] = $name;
				}
			}

			return $uploaded_templates;
		}

		/* collects & loads extension array data */
		function load_definitions()
		{
			$inbound_email_data = self::$instance->template_definitions;
			self::$instance->definitions = apply_filters( 'inbound_email_extension_data' , $inbound_email_data);
			set_transient('inbound_email_extension_definitions' , $inbound_email_data ,  60*60*24 );
		}

		/* filters to add in core definitions to the calls to action extension definitions array */
		function add_core_definitions()
		{
			add_filter('save_post' , array( $this , 'store_template_data_as_transient') , 1  );	
			add_filter('inbound_email_extension_data' , array( $this , 'add_addressing_settings') , 1  );
			add_filter('inbound_email_extension_data' , array( $this , 'add_batch_send_settings') , 1  );

		}

		function store_template_data_as_transient( $post_id )
		{
			global $post;

			if (!isset($post)) {
				return;
			}
			
			if ($post->post_type=='revision' ||  'trash' == get_post_status( $post_id )) {
				return;
			}

			if ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )||( isset($_POST['post_type']) && $_POST['post_type']=='revision' )) {
				return;
			}

			if ($post->post_type=='inbound-email') {
				set_transient('inbound_email_template_definitions' , self::$instance->template_definitions  , 60*60*24 );
			}
		}
	
		
		/**
		*  Adds default mail settings
		*/
		function add_addressing_settings($inbound_email_data){
		

			$inbound_email_data['email-settings']['inbound_subject'] =  array(
				'label' => __( 'Subject Line' , 'inbound-mailer' ),
				'description' => __( 'Subject line of the email' , 'inbound-mailer' ) ,
				'id'  => 'inbound_subject',
				'type'  => 'text',
				'default'  => '',
				'class' => '',
				'context'  => 'priority',
				'global' => true
			);
			
			$inbound_email_data['email-settings']['inbound_from_name'] =  array(
				'label' => __( 'From Name' , 'inbound-mailer' ),
				'description' => __( 'The name of the sender.' , 'inbound-mailer' ) ,
				'id'  => 'inbound_from_name',
				'type'  => 'text',
				'default'  => '',
				'class' => '',
				'context'  => 'priority',
				'global' => true
			);
			
			$inbound_email_data['email-settings']['inbound_from_email'] =  array(
				'label' => __( 'From Email' , 'inbound-mailer' ),
				'description' => __( 'The email address of the sender.' , 'inbound-mailer' ) ,
				'id'  => 'inbound_from_email',
				'type'  => 'text',
				'default'  => '',
				'class' => '',
				'context'  => 'priority',
				'global' => true
			);			

			return $inbound_email_data;
		}

		
			
		/**
		* adds batch send settings
		*/
		function add_batch_send_settings($data) {
			
			$data['batch-send-settings']['inbound_batch_send_nature'] = array(
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
			
			$data['batch-send-settings']['inbound_recipients'] = array(
				'id'  => 'inbound_recipients',
				'label' => __( 'Select recipients' , 'inbound-email' ),
				'description' => __( 'This option provides a placeholder for the selected template data.' , 'inbound-email' ),
				'type'  => 'select2', 
				'default' => '',
				'placeholder' => __( 'Select lists to send mail to.' , 'inbound-mailer' ),
				'options' => $lead_lists,
				'global' => true
			);
			
			$data['batch-send-settings']['inbound_send_datetime'] = array(
				'id'  => 'inbound_send_datetime',
				'label' => __( 'Send Date/Time' , 'inbound-email' ),
				'description' => __( 'Select the date and time you would like this message to send.' , 'inbound-email' ),
				'type'  => 'datepicker', 
				'default' => '',
				'placeholder' => __( 'Select lists to send mail to.' , 'inbound-mailer' ),
				'options' => $lead_lists,
				'global' => true
			);

			return $data;

		}
		


		function read_template_categories()
		{

			$template_cats = array();

			if ( !isset(self::$instance->definitions ) ) {
				return;
			}

			//print_r($extension_data);
			foreach (self::$instance->definitions as $key=>$val)
			{

				if (strstr($key,'inbound-mailer') || !isset($val['info']['category']))
					continue;

				/* allot for older lp_data model */
				if (isset($val['category']))
				{
					$cats = $val['category'];
				}
				else
				{
					if (isset($val['info']['category']))
					{
						$cats = $val['info']['category'];
					}
				}

				$cats = explode(',',$cats);

				foreach ($cats as $cat_value)
				{
					$cat_value = trim($cat_value);
					$name = str_replace(array('-','_'),' ',$cat_value);
					$name = ucwords($name);

					if (!isset($template_cats[$cat_value]))
					{
						$template_cats[$cat_value]['count'] = 1;
					}
					else
					{
						$template_cats[$cat_value]['count']++;
					}

					$template_cats[$cat_value]['value'] = $cat_value;
					$template_cats[$cat_value]['label'] = "$name";
				}
			}

			self::$instance->template_categories = $template_cats;
		}
	}


	function Inbound_Mailer_Load_Extensions()
	{
		return Inbound_Mailer_Load_Extensions::instance();
	}
}

