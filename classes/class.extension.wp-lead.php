<?php

/**
*  Adds events section to a Lead's Activity tab
*/
class Inbound_Mailer_WordPress_Leads {

	static $click_events;
	
	/**
	*  Initiate class
	*/
	function __construct() {
		self::load_hooks();
	}

	/**
	*  Loads hooks and filters
	*/
	private function load_hooks() {
		
		add_filter('wpl_lead_activity_tabs', array( __CLASS__ , 'create_nav_tabs' ) , 10, 1);
		add_action('wpleads_after_activity_log' , array( __CLASS__ , 'show_inbound_email_click_content' ) );
		
	}
	
	/**
	*  	Create New Nav Tabs in WordPress Leads - Lead UI
	*/
	public static function create_nav_tabs( $nav_items ) {
		global $post;
		
		self::$click_events = Inbound_Email_Tracking::get_click_events( $post->ID );
		
		$nav_items[] = array(
			'id'=>'wpleads_lead_inbound_email_click_tab',
			'label'=> __( 'Email Clicks' , 'inbound-email' ),
			'count' => count( self::$click_events )
		);
		
		return $nav_items;
	}
	
	/* Display CTA Click Content */
	public static function show_inbound_email_click_content() {
		global $post; 
		?>
		<div id="wpleads_lead_inbound_email_click_tab" class='lead-activity'>
			<h2><?php _e( 'Email\'s Clicked' , 'inbound-email' ); ?></h2>
			<?php

			if ( self::$click_events ) {
				$count = 1;

				foreach( self::$click_events as $key=>$event) {
					$id = $event['id'];
					$title = get_the_title($id);

					$date_raw = new DateTime($event['datetime']);

					$date_of_conversion = $date_raw->format('F jS, Y \a\t g:ia (l)');
					$clean_date = $date_raw->format('Y-m-d H:i:s');
				
					echo '<div class="lead-timeline recent-conversion-item cta-tracking-item" data-date="'.$clean_date.'">
								<a class="lead-timeline-img" href="#non">
									<!--<i class="lead-icon-target"></i>-->
								</a>

								<div class="lead-timeline-body">
									<div class="lead-event-text">
										<p><span class="lead-item-num">'.$count.'. </span><span class="lead-helper-text">'.__('Email Clickthrough' , 'inbound-email' ).': </span><a href="#">'.$title.'</a><span class="conversion-date">'.$date_of_conversion.'</span></p>
									</div>
								</div>
							</div>';

					$count++;
				}
			}
			else
			{
				echo '<span id=\'wpl-message-none\'>'. __( 'No Email Clickthroughs!' , 'inbound-email' ) .'</span>';
			}


			?>
		</div>
		<?php
	}

}

/* Load Post Type Pre Init */
$GLOBALS['Inbound_Mailer_WordPress_Leads'] = new Inbound_Mailer_WordPress_Leads();
