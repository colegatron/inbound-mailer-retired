<?php

if ( !class_exists('Inbound_Mailer_WordPress_Leads') ) {

	class Inbound_Mailer_WordPress_Leads {

		function __construct() {
			self::load_hooks();
		}

		private function load_hooks() {
			
			add_filter('wpl_lead_activity_tabs', array( __CLASS__ , 'create_nav_tabs' ) , 10, 1);
			add_action('wpleads_after_activity_log' , array( __CLASS__ , 'show_inbound_email_click_content' ) );
			
		}
		
		/* Create New Nav Tabs in WordPress Leads - Lead UI */
		public static function create_nav_tabs( $nav_items )
		{
			$nav_items[] = array('id'=>'wpleads_lead_inbound_email_click_tab','label'=> __( 'CTA Clicks' , 'inbound-email' ) );
			return $nav_items;
		}
		
		/* Display CTA Click Content */
		public static function show_inbound_email_click_content() {
			global $post; 
			?>
			<div id="wpleads_lead_inbound_email_click_tab" class='lead-activity'>
				<h2><?php _e( 'CTA\'s Clicked' , 'inbound-email' ); ?></h2>
				<?php

				$events = get_post_meta($post->ID,'call_to_action_clicks', true);

				$events_triggered = get_post_meta( $post->ID, 'call_to_action_clicks', TRUE );


				$the_array = json_decode($events, true);
				// echo "First id : ". $the_array[1]['id'] . "!"; // Get specific value
				if ($events)
				{
					$count = 1;

					foreach($the_array as	$key=>$val)
					{
						$id = $the_array[$count]['id'];
						$title = get_the_title($id);
	
						$date_raw = new DateTime($the_array[$count]['datetime']);
	
						$date_of_conversion = $date_raw->format('F jS, Y \a\t g:ia (l)');
						$clean_date = $date_raw->format('Y-m-d H:i:s');
					
						echo '<div class="lead-timeline recent-conversion-item cta-tracking-item" data-date="'.$clean_date.'">
									<a class="lead-timeline-img" href="#non">
										<!--<i class="lead-icon-target"></i>-->
									</a>

									<div class="lead-timeline-body">
										<div class="lead-event-text">
											<p><span class="lead-item-num">'.$count.'. </span><span class="lead-helper-text">Call to Action Click: </span><a href="#">'.$title.'</a><span class="conversion-date">'.$date_of_conversion.'</span></p>
										</div>
									</div>
								</div>';

						$count++;
					}
				}
				else
				{
					_e( '<span id=\'wpl-message-none\'>No Call to Action Clicks found!</span>"' , 'inbound-email' );
				}


				?>
			</div>
			<?php
		}

	}
	
	/* Load Post Type Pre Init */
	$GLOBALS['Inbound_Mailer_WordPress_Leads'] = new Inbound_Mailer_WordPress_Leads();
}