<?php

if (!class_exists('Inbound_Mailer_ACF')) {

	class Inbound_Mailer_ACF {

		/**
		* Initialize Inbound_Mailer_ACF Class
		*/
		public function __construct() {
			self::load_hooks();
		}


		/**
		* Load Hooks & Filters
		*/
		public static function load_hooks() {

			/* Load ACF Fields On ACF powered Email Template */
			add_filter( 'acf/location/rule_match/template_id' , array( __CLASS__ , 'load_acf_on_template' ) , 10 , 3 );

			/* Intercept load custom field value request and hijack it */
			add_filter( 'acf/load_value' , array( __CLASS__ , 'load_value' ) , 10 , 3 );

		}

		/**
		* Finds the correct value given the variation
		*
		* @param MIXED $value contains the non-variation value
		* @param INT $post_id ID of landing page being loaded
		* @param ARRAY $field wide array of data belonging to custom field (not leveraged in this method)
		*
		* @returns MIXED $new_value value mapped to variation.
		*/
		public static function load_value( $value, $post_id, $field ) {
			global $post;

			if ( !isset($post) || $post->post_type != 'inbound-email' ) {
				return $value;
			}


			$vid = Inbound_Mailer_Variations::get_current_variation_id();
			$settings = get_post_meta( $post_id , 'inbound_settings' , true);
			$variations = ( isset($settings['variations']) ) ? $settings['variations'] : null;

			if (!$variations) {
				return $value;
			}

			if ( isset( $variations[ $vid ][ 'acf' ] ) ) {
				$new_value = self::get_variation_values( $variations[ $vid ][ 'acf' ] , $field );

				/* sometimes value is an array count when new_value believes it should be an array in this case get new count */
				if (!is_array($value) && is_array($new_value)) {
					$value = count($new_value);
				} else {
					$value = $new_value;
				}

			} else {
				if ( !is_array($value) && strlen($value) && isset($field['default_value']) ) {
					$value = $field['default_value'];
				} else {
					if ($field['type'] == 'color_picker' ) {
						$value = $field['default_value'];
					}
				}
			}

			/**
			var_dump($new);
			echo "\r\n";echo "\r\n";echo "\r\n";
			/**/
			return $value;

		}


		/**
		* Searches ACF variation array and returns the correct field value given the field key
		*
		* @param ARRAY $array of custom field keys and values stored for variation
		* @param STRING $field['key'] acf form field key
		*
		* @return $feild value
		*/
		public static function get_variation_values( $array , $field ) {

			/* first check for repeater values */
			$value = self::get_repeater_values( $array , $field );

			$value = ( $value ) ? $value : self::key_search( $array , $field  ) ;

			/* color pickers seem to be special */
			if ($field['type'] == 'color_picker' ) {
				if (is_array($value)) {
					$value = $value[1];
				}
			}

			if (!is_array($value)) {

				return $value;
			}

			foreach ($array as $key => $value ){

				/* Arrays could be repeaters or any custom field with sets of multiple values */
				if ( !is_array($value) ) {
					continue;
				}

				/* Check if this array contains a repeater field layouts. If it does then return layouts, else this array is a non-repeater value set so return it */
				if ( $key === $field['key'] ) {
					$repeater_array = self::get_repeater_layouts( $value );
					return $repeater_array;
				}

			}


			return '';
		}

		/**
		*	Searches an array assumed to be a repeater field dataset and returns an array of repeater field layout definitions
		*
		*	@retuns ARRAY $fields this array will either be empty of contain repeater field layout definitions.
		*/
		public static function get_repeater_layouts( $array ) {

			$fields = array();

			foreach ($array as $key => $value) {
				if ( isset( $value['acf_fc_layout'] ) ) {
					$fields[] = $value['acf_fc_layout'];
				}
			}

			return $fields;
		}


		/**
		*	Searches an array assumed to be a repeater field dataset and returns an array of repeater field layout definitions
		*
		*	@retuns ARRAY $fields this array will either be empty of contain repeater field layout definitions.
		*/
		public static function get_repeater_values( $array , $field ) {

			/* Discover correct repeater pointer by parsing field name */
			preg_match('/(_\d_)/', $field['name'], $matches, 0);

			if (!$matches) {
				return false;
			}

			$pointer = str_replace('_' , '' , $matches[0]);
			$repeater_key = self::key_search($array, $field , true );

			return $array[$repeater_key][$pointer][$field['key']];

		}

		/**
		*	Check if current post is a landing page using an ACF powered template
		*
		*	@filter acf/location/rule_match/template_id
		*
		*	@returns BOOL declaring if current page is a landing page with an ACF template loaded or not
		*/
		public static function load_acf_on_template( $allow , $rule, $args ) {
			global $post;

			if ($post->post_type != 'inbound-email' ) {
				 return $allow;
			}

			$template =	Inbound_Mailer_Variations::get_current_template( $args['post_id'] );

			if ($template == $rule['value']) {
				return true;
			} else {
				return false;
			}
		}

		public static function load_javascript_on_admin_edit_post_page() {
				global $parent_file;

				// If we're on the edit post page.
				if ( $parent_file == 'edit.php?post_type=inbound-email' ) {
				echo "
					<script>
					jQuery('#publish').on('click', function() {
					jQuery('#publish').removeClass('disabled');
					jQuery('.spinner').show();
					jQuery('#publish').val('".__('Saving','inbound-email')."');
					});
					</script>
				";
				}
		}

		/**
		 * This is a complicated array search method for working with ACF repeater fields.
		 * @param $array
		 * @param $field
		 * @param bool|false $get_parent if get_parent is set to true to will return the parent field group key of the repeater fields
		 * @param mixed $last_key placeholder for storing the last key...
		 * @return bool|int|string
		 */
		public static function key_search($array, $field , $get_parent = false , $last_key = false) {
			$value = false;

			foreach ($array as $key => $item) {
				if ($key === $field['key'] ) {
					$value = $item;
				} else {
					if (is_array($item)) {
						$last_key = ( !is_numeric($key)) ? $key : $last_key;
						$value = self::key_search($item, $field , $get_parent , $last_key );
					}
				}

				if ($value) {
					if (!$get_parent) {
						return $value;
					} else {
						return $last_key;
					}

				}
			}

			return false;
		}

		public static function unset_key_occurance($array, $field , $get_parent = false , $last_key = false) {
			$value = false;

			foreach ($array as $key => $item) {
				if ($key === $field['key'] ) {
					$value = $item;
				} else {
					if (is_array($item)) {
						$value = self::key_search($item, $field , $get_parent , $key );
					}
				}

				if ($value) {
					if (!$get_parent) {
						return $value;
					} else {
						echo 'here'.$value;
						echo "\r\n";
						echo $last_key;exit;
						return $key;
					}

				}
			}

			return false;
		}
	}

	/**
	*	Initialize ACF Integrations
	*/
	if (!function_exists('inbound_mailer_acf_integration')) {
		add_action( 'init' , 'inbound_mailer_acf_integration' );

		function inbound_mailer_acf_integration() {
			$GLOBALS['Inbound_Mailer_ACF'] = new Inbound_Mailer_ACF();
		}
	}
}