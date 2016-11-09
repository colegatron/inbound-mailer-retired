<?php

if ( !class_exists('Inbound_Mailer_Post_Type') ) {

	class Inbound_Mailer_Post_Type {

        static $stats;

		function __construct() {
			self::load_hooks();
		}

		public static function load_hooks() {

			add_action('admin_init', array( __CLASS__ ,	'rebuild_permalinks' ) );
			add_action('admin_enqueue_scripts', array( __CLASS__ ,	'check_if_scheduled_emails_sent' ) );
			add_action('init', array( __CLASS__ , 'register_post_type' ) , 1	);
			add_action('init', array( __CLASS__ , 'register_tag_taxonomy' ) , 1 );
			add_action('init', array( __CLASS__ , 'register_post_status' ) , 11 );

			/* Load Admin Only Hooks */
			if (is_admin()) {

                /* control priority of post status */
                add_filter( 'views_edit-inbound-email' , array( __CLASS__ , 'filter_post_status_priority' ));

				/* Adds support for newly added post status */
				add_action( 'admin_footer-post.php' , array( __CLASS__ , 'add_post_status' ) );

				/* Register Columns */
				add_filter( 'manage_inbound-email_posts_columns' , array( __CLASS__ , 'register_columns') );

				/* Prepare Column Data */
				add_action( "manage_posts_custom_column", array( __CLASS__ , 'prepare_column_data' ) , 10, 2 );

				/* setup column sorting */
				add_filter( "manage_edit-inbound-email_sortable_columns", array( __CLASS__ , 'define_sortable_columns' ));
				add_action( 'posts_clauses', array( __CLASS__ , 'process_column_sorting' ) , 1 , 2 );

				/* Filter Row Actions */
				add_filter( 'post_row_actions' , array( __CLASS__ , 'filter_row_actions' ) , 10 , 2 );

				/* Add Category Filter */
				add_action( 'restrict_manage_posts' , array( __CLASS__ ,'add_category_taxonomy_filter' ));

				/* Remove 'tags' & 'categories' from menu */
				add_filter( 'admin_footer' , array( __CLASS__ , 'apply_js' ) );

				/* Cache statistics data */
				add_filter( 'admin_footer' , array( __CLASS__ , 'cache_data' ) );

			}
		}


        /**
		*	Rebuilds permalinks after activation
		*/
		public static function rebuild_permalinks() {
			$activation_check = get_option('inbound_email_activate_rewrite_check',0);

			if ($activation_check) {
				global $wp_rewrite;
				$wp_rewrite->flush_rules();
				update_option( 'inbound_email_activate_rewrite_check', '0');
			}
		}

		/**
		*	Registers inbound-email post type
		*/
		public static function register_post_type() {

			if ( post_type_exists( 'inbound-email' ) ) {
				return;
			}

			$email_path = apply_filters( 'inbound_email_preview_path' , 'mail' );

			$labels = array(
				'name' => __('Emails', 'inbound-email' ),
				'singular_name' => __('Email Campaigns', 'inbound-email' ),
				'add_new' => __('Add New', 'inbound-email' ),
				'add_new_item' => __('Add New Campaign' , 'inbound-email' ),
				'edit_item' => __(' ' , 'inbound-email' ),
				'new_item' => __('New Campaign' , 'inbound-email' ),
				'view_item' => __('View Email' , 'inbound-email' ),
				'search_items' => __('Search Email Campaigns' , 'inbound-email' ),
				'not_found' =>	__('Nothing found' , 'inbound-email' ),
				'not_found_in_trash' => __('Nothing found in Trash' , 'inbound-email' ),
				'parent_item_colon' => ''
			);

			$args = array(
				'labels' => $labels,
				'public' => true,
				'publicly_queryable' => true,
				'show_ui' => true,
				'query_var' => true,
				'menu_icon' => '',
				'rewrite' => array("slug" => $email_path),
				'capability_type' => 'post',
				'hierarchical' => false,
				'menu_position' => 34,
				'show_in_nav_menus'	=> false,
				'supports' => array()
			);

			register_post_type( 'inbound-email' , $args );

		}

		/**
		*	Register Tag Taxonomy
		*/
		public static function register_tag_taxonomy() {

			register_taxonomy('inbound_email_tag','inbound-email', array(
					'hierarchical' => false,
					'label' => __( 'Tags' , 'inbound-email' ),
					'singular_label' => __( 'Tag' , 'inbound-email' ),
					'show_ui' => true,
					'show_in_nav_menus'	=> false,
					'query_var' => true,
					"rewrite" => false,
					'add_new_item' => __('Tag email' , 'inbound-pro' )
			));

		}

		/**
		*  	Register Columns
		*/
		public static function register_columns( $cols ) {

			self::$stats = get_transient( 'inbound-email-stats-cache');
			if (!is_array(self::$stats)) {
				self::$stats = array();
			}

			$cols = array(
				"cb" => "<input type=\"checkbox\" />",
				"inbound_email_thumbnail" => __( 'Preview' , 'inbound-email' ),
				"title" => __( 'Title' , 'inbound-email' ),
				"inbound_email_type" => __( 'type' , 'inbound-email' ),
				"inbound_email_status" => __( 'status' , 'inbound-email' ),
				"inbound_email_stats" => __( 'stats' , 'inbound-email' )

			);

			return $cols;

		}



		/**
		*  	Prepare Column Data
		*/
		public static function prepare_column_data( $column , $post_id ) {
			global $post, $Inbound_Mailer_Variations;

			if ($post->post_type !='inbound-email') {
				return $column;
			}

			switch ($column) {

				case "ID":
					echo $post->ID;
					break;
                case "inbound_email_thumbnail":
					$permalink = get_permalink($post->ID);
					$local = array('127.0.0.1', "::1");
					if(!in_array($_SERVER['REMOTE_ADDR'], $local)){
						$thumbnail = 'http://s.wordpress.com/mshots/v1/' . urlencode(esc_url($permalink)) . '?w=140';
					} else {
						$template = $Inbound_Mailer_Variations->get_current_template($post->ID);
						$thumbnail = Inbound_Mailer_Metaboxes::get_template_thumbnail($template);
					}

                    echo "<a title='". __('Click to Preview' , 'inbound-pro' ) ."' class='thickbox' href='".add_query_arg( array( 'TB_iframe' => 'true' , 'width'=>640 , 'height' => 763 ) , $permalink )."' target='_blank'><img src='".$thumbnail."' style='max-width:100%;' title='".__('Click to Preview' , 'inbound-pro') ."'></a>";
                    break;
				case "inbound_email_type":
					$email_type = Inbound_Mailer_Metaboxes::get_email_type($post->ID);
					echo $email_type;
					break;
                case "inbound_email_status":
					echo $post->post_status;
					break;
                case "inbound_email_stats":
					?>
						<div class="email-stats-container" style="background-color:#ffffff;">
							<table class="email-stats-table">
								<tr>
									<td>
										<div class="td-col-sends" data-email-id="<?php echo $post->ID; ?>" data-email-status="<?php echo $post->post_status; ?>"><img src="<?php echo INBOUND_EMAIL_URLPATH; ?>assets/images/ajax_progress.gif" class="col-ajax-spinner" style="margin-top:3px;"></div>
									</td>
									<td>
										<div class="email-square" style="width: 10px;height: 10px; border-radius: 2px;margin-top:5px;	background: green;"></div>
									</td>
									<td>
									<?php echo __( 'Sends' ,'inbound-pro' ); ?>
									</td>
								</tr>
								<tr>
									<td>
										<div class="td-col-opens" data-email-id="<?php echo $post->ID; ?>" data-email-status="<?php echo $post->post_status; ?>"><img src="<?php echo INBOUND_EMAIL_URLPATH; ?>assets/images/ajax_progress.gif" class="col-ajax-spinner" style="margin-top:3px;"></div>
									</td>
									<td>
										<div class="email-square" style="width: 10px;height: 10px; border-radius: 2px;margin-top:5px;	background: cornflowerblue;"></div>
									</td>
									<td>
									<?php echo __( 'Opens' ,'inbound-pro' ); ?>
									</td>
								</tr>
								<tr>
									<td>
										<div class="td-col-clicks" data-email-id="<?php echo $post->ID; ?>" data-email-status="<?php echo $post->post_status; ?>"><img src="<?php echo INBOUND_EMAIL_URLPATH; ?>assets/images/ajax_progress.gif" class="col-ajax-spinner" style="margin-top:3px;"></div>
									</td>
									<td>
										<div class="email-square" style="width: 10px;height: 10px; border-radius: 2px;margin-top:5px;	background: violet;"></div>
									</td>
									<td>
										<?php echo __( 'Clicks' ,'inbound-pro' ); ?>
									</td>
								</tr>
								<tr>
									<td>
										<div class="td-col-unsubs" data-email-id="<?php echo $post->ID; ?>" data-email-status="<?php echo $post->post_status; ?>"><img src="<?php echo INBOUND_EMAIL_URLPATH; ?>assets/images/ajax_progress.gif" class="col-ajax-spinner" style="margin-top:3px;"></div>
									</td>
									<td>
										<div class="email-square" style="width: 10px;height: 10px; border-radius: 2px;margin-top:5px;	background: #000;"></div>
									</td>
									<td>
										<?php echo __( 'Unsubscribes' ,'inbound-pro' ); ?>
									</td>
								</tr>
								<tr>
									<td>
										<div class="td-col-mutes" data-email-id="<?php echo $post->ID; ?>" data-email-status="<?php echo $post->post_status; ?>"><img src="<?php echo INBOUND_EMAIL_URLPATH; ?>assets/images/ajax_progress.gif" class="col-ajax-spinner" style="margin-top:3px;"></div>
									</td>
									<td>
										<div class="email-square" style="width: 10px;height: 10px; border-radius: 2px;margin-top:5px;	background: #000;"></div>
									</td>
									<td>
										<?php echo __( 'Mutes' ,'inbound-pro' ); ?>
									</td>
								</tr>
							</table>
						</div>

					<?php
					break;

			}
		}

		public static function load_email_stats( $email_id ) {
			global $inobund_settings;
		    if ( isset(self::$stats[$email_id]) ) {
		        return self::$stats[$email_id];
            }

			switch ($inbound_settings['inbound-mailer']['mail-service']) {
				case 'mandrill' :
					self::$stats[$email_id] = Inbound_Mandrill_Stats::get_email_timeseries_stats();
					break;
				case 'sparkpost' :
					self::$stats[$email_id] = Inbound_SparkPost_Stats::get_email_timeseries_stats();
					break;
			}

            return self::$stats[$email_id];
        }

		public static function check_if_scheduled_emails_sent() {
			$screen = get_current_screen();


			if (isset($screen) && $screen->id == 'edit-inbound-email' ) {
				$emails = get_posts( array(
					'post_type' => 'inbound-email',
					'post_status' => 'scheduled',
					'posts_per_page' => -1
				));

				if (!$emails) {
					return;
				}

				/* if scheduled email's send time is here then mark sent and refresh */
				$updated = false;
				foreach ($emails as $email) {
					$settings = Inbound_Email_Meta::get_settings($email->ID);
					$wordpress_date_time =  date_i18n('Y-m-d G:i:s');
					$today = new DateTime($wordpress_date_time);
					$schedule_date = new DateTime($settings['send_datetime']);
					$interval = $today->diff($schedule_date);

					if ( $interval->format('%R') == '-' ) {
						$args = array(
							'ID' => $email->ID,
							'post_status' => 'sent',
						);

						wp_update_post( $args );

						$updated = true;
					}
				}

				/* if any email's statuses were updated then refresh page */
				if ($updated) {
					header('Location: ' . admin_url('edit.php?post_type=inbound-email'));
					exit;
				}

			}
		}

		/**
		 * Defines sortable columns
		 * @param $columns
		 * @return mixed
		 */
		public static function define_sortable_columns($columns) {

			$columns['inbound_email_type'] = 'inbound_email_type';
			$columns['inbound_email_status'] = 'inbound_email_status';

			return $columns;
		}

		public static function process_column_sorting(  $pieces, $query ) {

			global $wpdb, $table_prefix;

			if (!isset($_GET['post_type']) || $_GET['post_type'] != 'inbound-email') {
				return $pieces;
			}

			if ( $query->is_main_query() && ( $orderby = $query->get( 'orderby' ) ) ) {
				$order = strtoupper( $query->get( 'order' ) );

				if ( ! in_array( $order, array( 'ASC', 'DESC' ) ) ) {
					$order = 'ASC';
				}

				switch( $orderby ) {

					case 'inbound_email_type':

						$pieces[ 'orderby' ] = " {$wpdb->posts}.post_status ";

						break;


				}
			} else {
				$pieces[ 'orderby' ] = " post_modified  DESC , " . $pieces[ 'orderby' ];
			}


			return $pieces;
		}

		/**
		*	Registers all post status types related to the inbound-email cpt
		*	@adds post_status unsent
		*	@adds post_status sent
		*	@adds post_status sending
		*	@adds post_status scheduled
		*	@adds post_status automated
		*/
		public static function register_post_status() {

			/* unsent */
			register_post_status( 'unsent', array(
				'label'	=> __( 'Unsent', 'inbound-email' ),
				'public' => true,
				'show_in_admin_all_list' => true,
				'show_in_admin_status_list' => true,
				'label_count' => _n_noop( 'Unsent <span class="count">(%s)</span>', 'Unsent <span class="count">(%s)</span>' )
			));


			/* sent */
			register_post_status( 'sent', array(
				'label'	=> __( 'Sent', 'inbound-email' ),
				'public' => !is_admin(),
				'show_in_admin_all_list' => false,
				'show_in_admin_status_list' => true,
				'label_count' => _n_noop( 'Sent <span class="count">(%s)</span>', 'Sent <span class="count">(%s)</span>' )
			));


			/* cancelled */
			register_post_status( 'cancelled', array(
				'label'	=> __( 'Cancelled', 'inbound-email' ),
				'public' => !is_admin(),
				'show_in_admin_all_list' => false,
				'show_in_admin_status_list' => true,
				'label_count' => _n_noop( 'Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>' )
			));

			/* automated */
			register_post_status( 'sending', array(
				'label'	=> __( 'Sending', 'inbound-email' ),
				'public' => true,
				'show_in_admin_all_list' => false,
				'show_in_admin_status_list' => true,
				'label_count' => _n_noop( 'Sending <span class="count">(%s)</span>', 'Sending <span class="count">(%s)</span>' )
			));

			/* scheduled */
			register_post_status( 'scheduled', array(
				'label'	=> __( 'Scheduled', 'inbound-email' ),
				'public' => !is_admin(),
				'show_in_admin_all_list' => true,
				'show_in_admin_status_list' => true,
				'label_count' => _n_noop( 'Scheduled <span class="count">(%s)</span>', 'Scheduled <span class="count">(%s)</span>' )
			));

			/* automated */
			register_post_status( 'automated', array(
				'label'	=> __( 'Automated', 'inbound-pro' ),
				'public' => !is_admin(),
				'show_in_admin_all_list' => false,
				'show_in_admin_status_list' => true,
				'label_count' => _n_noop( 'Automated <span class="count">(%s)</span>', 'Automated <span class="count">(%s)</span>' )
			));
      
            /* direct_email */
			$public = (current_user_can('administrator') && !is_admin()) ? true : false;
			register_post_status( 'direct_email', array(
				'label'	=> __( 'Direct Emails', 'inbound-pro' ),
				'public' => $public,
				'show_in_admin_all_list' => false,
				'show_in_admin_status_list' => false,
				'label_count' => _n_noop( 'Direct Emails <span class="count">(%s)</span>', 'Direct Emails <span class="count">(%s)</span>' )
			));
		}

		/**
		*	Adds dropdown support for added post status
		*/
		public static function add_post_status() {
			global $post;

			if($post->post_type == 'inbound-email'){

				$statuses = array(
					'automated' => __( 'Automated' , 'inbound-pro' ) ,
					'unsent' => __( 'Unsent' , 'inbound-pro' ) ,
					'sent' => __( 'Sent' , 'inbound-pro' ) ,
					'cancelled' => __( 'Cancelled' , 'inbound-pro' ) ,
					'scheduled' => __( 'Scheduled' , 'inbound-pro'),
					'sending' => __( 'Sending' , 'inbound-pro'),
                    'direct_email' => __( 'Direct Emails' , 'inbound-pro' ) ,
				);

				echo '<script type="text/javascript">';
				echo 'jQuery(document).ready(function(){';
				foreach ( $statuses as $status => $label ) {

					$complete = '';
					$this_label = '';

					if ( $post->post_status == $status ) {
						$complete = ' selected=\"selected\"';
					}

					echo '
						jQuery("select#post_status").append("<option value=\"'.$status.'\" '.$complete.'>'.$label.'</option>");
					';
				}
				echo '});';
				echo '</script>';
			}
		}

		/**
		*	Add admin js that removes menu items
		*/
		public static function apply_js() {
			global $post;

			if (!isset($post) || $post->post_type!='inbound-email') {
				return;
			}
			?>
			<script type='text/javascript'>

			jQuery( document ).ready( function() {
				var i = 0;
				jQuery('#menu-posts-inbound-email li').each( function() {
					if ( i==3  ) {
						jQuery(this).hide();
					}
					i++;
				});

				/* hide visibility toggle */
				jQuery('.misc-pub-visibility').hide();

				/* hide scheduling toggle */
				jQuery('.misc-pub-curtime').hide();

				/* Add post status to quick edit */
				(function($){
					jQuery( "select[name=_status]" ).each(
							function () {
								var value = $( this ).val();

								jQuery("option[value=pending]", this).after("<option value='sent'>Sent</option>");
								jQuery("option[value=pending]", this).after("<option value='unsent'>Unsent</option>");
								jQuery("option[value=pending]", this).after("<option value='automted'>Automated</option>");

							}
					);
				})(jQuery);
			});

			</script>
			<?php
		}

		/**
		*  	Define Row Actions
		*/
		public static function filter_row_actions( $actions , $post ) {

			if ($post->post_type=='inbound-email') {
				//unset($actions['inline hide-if-no-js']);
				$actions['clear'] = '';
			}

			return $actions;

		}


        /**
         * rebuild priority of post status links
         * @param ARRAY $links
         * @return ARRAY $new_links
         */
        public static function filter_post_status_priority( $links ) {
            //$new_links['all'] = $links['unsent'];

            if (isset($links['draft'])) {
                $new_links['draft'] =  $links['draft'];
            }
            if (isset($links['pending'])) {
                $new_links['pending'] =  $links['pending'];
            }
            if (isset($links['sent'])) {
                $new_links['sent'] =  $links['sent'];
            }
            if (isset($links['unsent'])) {
                $new_links['unsent'] =  $links['unsent'];
            }
            if (isset($links['automated'])) {
                $new_links['automated'] =  $links['automated'];
            }
            if (isset($links['scheduled'])) {
                $new_links['scheduled'] =  $links['scheduled'];
            }
            if (isset($links['direct_email'])) {
                $new_links['direct_email'] =  $links['direct_email'];
            }
            if (isset($links['trash'])) {
                $new_links['trash'] =  $links['trash'];
            }

            return $new_links;
        }

		/**
		*  	Adds ability to filter email templates by custom post type
		*/
		public static function add_category_taxonomy_filter() {
			global $post_type;

			if ($post_type === "inbound-email") {
			$post_types = get_post_types( array( '_builtin' => false ) );
			if ( in_array( $post_type, $post_types ) ) {
				$filters = get_object_taxonomies( $post_type );

				foreach ( $filters as $tax_slug ) {
					$tax_obj = get_taxonomy( $tax_slug );
					(isset($_GET[$tax_slug])) ? $current = sanitize_text_field($_GET[$tax_slug]) : $current = 0;
					wp_dropdown_categories( array(
						'show_option_all' => __('Show All '.$tax_obj->label ),
						'taxonomy' 		=> $tax_slug,
						'name' 			=> $tax_obj->name,
						'orderby' 		=> 'name',
						'selected' 		=> $current,
						'hierarchical' 		=> $tax_obj->hierarchical,
						'show_count' 		=> false,
						'hide_empty' 		=> true
					) );
					}
				}
			}
		}


		/**
		*  	Clears stats of all CTAs
		*/
		public static function clear_all_inbound_email_stats() {
			$ctas = get_posts( array(
				'post_type' => 'inbound-email',
				'posts_per_page' => -1
			));


			foreach ($ctas as $cta) {
				Inbound_Mailer_Post_Type::clear_inbound_email_stats( $cta->ID );
			}
		}

		/**
		*	Tells the 'email-templates' menu item to sit as a submenu in the 'inbound-email' parent menu
		*/
		public static function set_email_template_menu_location() {
			return 'inbound-email';
		}

		/**
		*	Tells the 'email-templates' label to be 'Templates' instead of 'Email Templates'
		*/
		public static function set_email_template_labels( $labels ) {
			$labels['name'] = __('Templates', 'inbound-emails');
			$labels['singular_name'] = __('Templates', 'inbound-emails');

			return $labels;
		}


		/**
		*  Get Automation Emails
		*  @param STRING $return_type OBEJCT or ARRAY
		*/
		public static function get_automation_emails_as( $return_type = 'OBJECT' ) {

			//self::register_post_type();
			self::register_post_status();

			$emails = get_posts( array(
				'numberposts' => -1 ,
				'post_status' => 'automated',
				'post_type' => 'inbound-email'
			));

			//print_r($emails);

			if ( $return_type == 'OBJECT' ) {
				return $emails;
			}

			$array = array();
			foreach ( $emails as $email ) {
				$array[ $email->ID ] = $email->post_title;
			}

			return $array;
		}

		public static function cache_data() {
			if (!get_transient('inbound-email-stats-cache')) {

			}
		}
	}

	/* Load Post Type Pre Init */
	new Inbound_Mailer_Post_Type();
}
