<?php


/**
 * Metaboxes that apply to strictly to the inbound-email post type.
 *
 * @package	Inbound Mailer
 * @subpackage	Metaboxes
*/


if (!class_exists('Inbound_Mailer_Metaboxes')) {

	class Inbound_Mailer_Metaboxes {

		static $statistics;
		static $variation_stats;
		static $campaign_stats;

		function __construct() {
			self::load_hooks();
		}

		public static function load_hooks() {
			/* Add metaboxes */
			add_action('add_meta_boxes', array( __CLASS__ , 'load_metaboxes' ) );

			/* Load template selector in background */
			add_action('admin_notices', array( __CLASS__ , 'add_template_select' ) );

			/* Add Email Settings */
			add_action('edit_form_after_title', array( __CLASS__ , 'add_containers' ) , 5);

			/* Add hidden inputs */
			add_action( 'edit_form_after_title',	array( __CLASS__ , 'add_hidden_inputs' ) );

			/* Change default title placeholder */
			add_filter( 'enter_title_here', array( __CLASS__ , 'change_title_placeholder_text' ) , 10, 2 );

			/* Enqueue JS */
			add_action( 'admin_enqueue_scripts', array( __CLASS__ , 'enqueue_admin_scripts' ) );
			add_action( 'admin_print_footer_scripts', array( __CLASS__ , 'print_js_listeners' ) );

			/* Saves all all incoming POST data as meta pairs */
			add_action( 'save_post' , array( __CLASS__ , 'action_save_data' ) );

			/* changes the post status 'published' to 'unsent' */
			add_filter( 'wp_insert_post_data' , array( __CLASS__ , 'check_post_stats') );

		}

		/**
		* Loads Metaboxes
		*/
		public static function load_metaboxes() {
			global $post , $Inbound_Mailer_Variations;

			if ($post->post_type!='inbound-email') {
				return;
			}

			/* Loads Template Options */
			$template_id = $Inbound_Mailer_Variations->get_current_template( $post->ID );

			/* If new variation use historic template id */
			if ( isset($_GET['new-variation'] ) ){
				$variations = $Inbound_Mailer_Variations->get_variations( $post->ID, $vid = null );
				$vid = key($variations);
				$template_id = $Inbound_Mailer_Variations->get_current_template( $post->ID , $vid );
			}

			/* Show email administration options */
			add_meta_box(
				'email-send-actions',
				__( 'Actions', 'leads' ),
				array( __CLASS__ , 'add_email_actions' ),
				'inbound-email' ,
				'side',
				'high'
			);

			/* Show email type select toggle */
			add_meta_box(
				'email-send-type',
				__( 'Send Type', 'leads' ),
				array( __CLASS__ , 'add_email_type_select_toggle' ),
				'inbound-email' ,
				'side',
				'high'
			);

			/* Show Selected Template */
			add_meta_box(
				'email-selected-template',
				__( 'Selected Template', 'leads' ),
				array( __CLASS__ , 'add_selected_tamplate_info' ),
				'inbound-email' ,
				'side',
				'low'
			);


		}

		public static function load_statistics() {
			global $post;

			$stats = Inbound_Email_Stats::get_email_stats( $post->ID , true );

			self::$statistics = $stats;
			self::$campaign_stats = $stats['totals'];
			self::$variation_stats = $stats['variations'];
		}

		/**
		*	Load Statistics
		*/
		public static function load_graphs() {
			global $post;

			$stats_json = json_encode( (object) self::$statistics );

			?>
			<script type='text/javascript'>
				/**
				*	Statistical Graphs Class
				*/
				var Email_Graphs = ( function () {
					var stats;
					var barchart;

					var App = {
						init: function ( json ) {
							this.stats = JSON.parse(json);
							console.log( 'Statistics Loaded!');
							console.log(this.stats);
							/* Only load bar chart if sends recorded */


								console.log( 'Variation Stats:' );
								console.log( this.stats );
								Email_Graphs.load_bar_graph();
								Email_Graphs.load_circle_graphs();


						},
						load_bar_graph: function() {
							var chart = [
								{
									"key": "Opened",
									"color": "#2475a6",
									"values": []
								},{
									"key": "Unopened",
									"color": "#243BA6",
									"values": []
								},{
									"key": "Clicked",
									"color": "#9f24a6",
									"values": []
								}
							];


							for ( id in this.stats.variations ) {
								chart[0]['values'].push( { "label":	this.stats.variations[id].label , "value": this.stats.variations[id].opens } );
								chart[1]['values'].push( { "label":	this.stats.variations[id].label , "value": this.stats.variations[id].unopened } );
								chart[2]['values'].push( { "label":	this.stats.variations[id].label , "value": this.stats.variations[id].clicks } );
							}

							jQuery('.barchart').show();
							jQuery('.circle-stats').css( 'margin-top' , '25px');
							Email_Graphs.animate_bar_graph(chart);

						},
						/**
						*	Animate the graph with chart data
						*/
						animate_bar_graph: function( data ) {

							var width = jQuery('.statistics-reporting-container').parent().width();
							nv.addGraph(function() {
								var chart = nv.models.multiBarHorizontalChart()
									.x(function(d) { return d.label })
									.y(function(d) { return d.value })
									.margin({top: 30, right: 20, bottom: 40, left: 40})
									.showValues(true)				//Show bar value next to each bar.
									.tooltips(true)				 //Show tooltips on hover.
									.transitionDuration(350)
									.stacked(true)
									.showControls(false) ;		 //Allow user to switch between "Grouped" and "Stacked" mode.


								chart.yAxis
									.tickFormat(d3.format(',1'));

								d3.select('.statistics-reporting-container svg')
									.datum(data)
									.call(chart);

								nv.utils.windowResize(chart.update);
								chart.update
								return chart;
							},	function( chart ) {
								//callback
								chart.update();
							});
						},
						/**
						*	Runs graph setup and executes
						*/
						load_circle_graphs: function() {
							Email_Graphs.populate_circle_graphs();
							Email_Graphs.setup_circle_graphs();
						},
						/***
						*	Populates Circle Graphs with data
						*/
						populate_circle_graphs: function() {

							/* Set totals */
							if ( this.stats.totals.sends > 0 ) {
								jQuery('#sends-percentage').text( '100%' );
							} else {
								jQuery('#sends-percentage').text( '0%' );
							}

							jQuery('#opens-percentage').text( this.get_percentage( this.stats.totals.opens , this.stats.totals.sends ) + '%' );
							jQuery('#clicks-percentage').text( this.get_percentage( this.stats.totals.clicks , this.stats.totals.sends ) + '%' )
							jQuery('#unopened-percentage').text( this.get_percentage( this.stats.totals.unopened , this.stats.totals.sends ) + '%' )

							/* Set totals */
							jQuery('#sends-label-bottom').text(this.stats.totals.sends);
							jQuery('#opens-label-bottom').text(this.stats.totals.opens);
							jQuery('#clicks-label-bottom').text(this.stats.totals.clicks);
							jQuery('#unopened-label-bottom').text(this.stats.totals.unopened);

						},
						/**
						*	Converts totals to percentages
						*/
						get_percentage: function( count , total ) {
							if (total<1){
								return '0';
							} else {
								return ( parseInt(count) / parseInt(total) ) * 100 ;
							}
						},
						/**
						*	Sets up Circle Graphs
						*/
						setup_circle_graphs: function() {
							jQuery('.stat-group').each(function(){

								//cache some stuff
								that = jQuery(this);
								var svgObj = that.find('.svg');
								var perObj = that.find('.per');

								//establish dimensions
								var wide = that.width();
								var center = wide/2;
								var radius = wide*0.8/2;
								var start = center - radius;

								//gab the stats
								var per = perObj.text().replace("%","") / 100;

								//set up the shapes
								var svg = Snap(svgObj.get(0));
								var arc = svg.path("");
								var circle = svg.circle(wide/2, wide/2, radius);

								//initialize the circle pre-animation
								circle.attr({
									stroke: '#dbdbdb',
									fill: 'none',
									strokeWidth: 3
								});

								//empty the percentage
								perObj.text('');

								//gather everything together
								var stat = {
									center: center,
									radius: radius,
									start: start,
									svgObj: svgObj,
									per: per,
									svg: svg,
									arc: arc,
									circle: circle
								};

								//call the animation
								Email_Graphs.run_circle_charts(stat);

							});
						},
						/**
						*	Animates Graph with Circle Graph Settings
						*/
						run_circle_charts: function( stat ) {
							//establish the animation end point
							var endpoint = stat.per*360;

							//set up animation (from, to, setter)
							Snap.animate(0, endpoint, function(val) {

							//remove the previous arc
							stat.arc.remove();

							//get the current percentage
							var curPer = Math.round(val/360*100);

							//if it's maxed out
							if(curPer == 100){

								//color the circle stroke instead of the arc
								stat.circle.attr({
								stroke: "#199dab"
								});

							//otherwise animate the arc
							} else {

								//calculate the arc
								var d = val;
								var dr = d-90;
								var radians = Math.PI*(dr)/180;
								var endx = stat.center + stat.radius*Math.cos(radians);
								var endy = stat.center + stat.radius * Math.sin(radians);
								var largeArc = d>180 ? 1 : 0;
								var path = "M"+stat.center+","+stat.start+" A"+stat.radius+","+stat.radius+" 0 "+largeArc+",1 "+endx+","+endy;

								//place the arc
								stat.arc = stat.svg.path(path);

								//style the arc
								stat.arc.attr({
								stroke: '#199dab',
								fill: 'none',
								strokeWidth: 3
								});

							}

							//grow the percentage text
							stat.svgObj.prev().html(curPer +'%');

							//animation speed and easing
							}, 1500, mina.easeinout);
						}

					};

					return App;

				})();

				var json = '<?php echo $stats_json; ?>';
				Email_Graphs.init( json );
			</script>
			<?php
		}


		/**
		* Loads and hide the template selection grid
		*
		*/
		public static function add_template_select() {
			global $inbound_email_data, $post, $current_url, $Inbound_Mailer_Variations;

			$Templates = Inbound_Mailer_Load_Templates();

			if (isset($post)&&$post->post_type!='inbound-email'||!isset($post)){ return false; }

			( !strstr( $current_url, 'post-new.php')) ?	$toggle = "display:none" : $toggle = "";

			$extension_data = $Templates->definitions;
			unset($extension_data['inbound-mailer-controller']);

			if ( isset($_GET['new-variation'] ) ){
				$variations = $Inbound_Mailer_Variations->get_variations( $post->ID, $vid = null );
				$vid = key($variations);
				$template = $Inbound_Mailer_Variations->get_current_template( $post->ID , $vid );
			} else {
				$template = $Inbound_Mailer_Variations->get_current_template( $post->ID );
			}


			echo "<div class='inbound-mailer-template-selector-container' style='{$toggle}'>";
			echo "<div class='inbound-mailer-selection-heading'>";
			echo "<h1>". __( 'Select Your Email Template!' , 'inbound-email' ) ."</h1>";
			echo '<a class="button-secondary" style="display:none;" id="inbound-mailer-cancel-selection">Cancel Template Change</a>';
			echo "</div>";
				echo '<ul id="template-filter" >';
					echo '<li><a href="#" data-filter=".template-item-boxes">All</a></li>';
					$categories = array();
					foreach ( $Templates->template_categories as $cat)
					{

						$category_slug = str_replace(' ','-',$cat['value']);
						$category_slug = strtolower($category_slug);
						$cat['value'] = ucwords($cat['value']);
						if (!in_array($cat['value'],$categories))
						{
							echo '<li><a href="#" data-filter=".'.$category_slug.'">'.$cat['value'].'</a></li>';
							$categories[] = $cat['value'];
						}

					}
				echo "</ul>";
				echo '<div id="templates-container" >';

				foreach ($extension_data as $this_template=>$data)
				{
					if (!isset($data['info'])) {
						continue;
					}

					if (isset($data['info']['data_type'])&&$data['info']['data_type']!='email-template'){
						continue;
					}

					$cats = explode( ',' , $data['info']['category'] );
					foreach ($cats as $key => $cat)
					{
						$cat = trim($cat);
						$cat = str_replace(' ', '-', $cat);
						$cats[$key] = trim(strtolower($cat));
					}

					$cat_slug = implode(' ', $cats);

					$thumbnail = self::get_template_thumbnail( $this_template );

					?>
					<div id='template-item' class="<?php echo $cat_slug; ?> template-item-boxes">
						<div id="template-box">
							<div class="inbound_email_tooltip_templates" title="<?php echo $data['info']['description']; ?>"></div>
						<a class='template_select' href='#' label='<?php echo $data['info']['label']; ?>' id='<?php echo $this_template; ?>'>
							<img src="<?php echo $thumbnail; ?>" class='template-thumbnail' alt="<?php echo $data['info']['label']; ?>" id='inbound_email_<?php echo $this_template; ?>'>
						</a>

							<div id="template-title" style="text-align: center;
		font-size: 14px; padding-top: 10px;"><?php echo $data['info']['label']; ?></div>
							<!-- |<a href='#' label='<?php echo $data['info']['label']; ?>' id='<?php echo $this_template; ?>' class='template_select'>Select</a>
							<a class='thickbox <?php echo $cat_slug;?>' href='<?php echo $data['info']['demo'];?>' id='inbound_email_preview_this_template'>Preview</a> -->
						</div>
					</div>
					<?php
				}
			echo '</div>';
			echo "<div class='clear'></div>";
			echo "</div>";
		}


		/**
		*	Adds variation & email type buttons
		*/
		public static function add_containers() {
			global $post;

			if ( !$post || $post->post_type != 'inbound-email' ) {
				return;
			}

			echo '<div class="btn-toolbar " role="toolbar">';

			self::add_countdown();
			self::add_statistics();
			self::add_email_settings();
			self::add_email_send_settings();
			echo '<div class="quick-launch-container bs-callout bs-callout-clear">';
			self::add_variation_buttons();
			self::add_quick_launch_buttons();
			echo '</div>';
			self::add_preview();
			echo '</div>';
		}

		/**
		*	Adds email settings metabox container
		*/
		public static function add_email_settings() {
			global $post;

			$Inbound_Mailer_Common_Settings = Inbound_Mailer_Common_Settings();
			$email_settings = $Inbound_Mailer_Common_Settings->settings['email-settings'];

			?>
			<div class="mail-headers-container bs-callout bs-callout-clear">
				<h4><?php _e('Addressing' , 'inbound-mailer'); ?></h4>

				<?php
				self::render_settings('inbound-email' , $email_settings, $post);
				?>
			</div>
			<?php
		}

		/**
		*	Add countdown containers
		*/
		public static function add_countdown() {
			?>
			<div class="countdown-container bs-callout bs-callout-clear">
				<div class='scheduled-information-actions'>
				</div>
				<div class='scheduled-information-countdown'>
				</div>
			</div>
			<?php
		}

		/**
		*	Adds statistics container
		*/
		public static function add_statistics() {
			global $Inbound_Mailer_Variations, $post;

			
			echo '<div class="statistics-reporting-container bs-callout bs-callout-clear">';
			echo '<h4>' . __('Reporting' , 'inbound-mailer') .'</h4>';


			self::load_statistics();
			self::add_chart_bars();
			self::add_chart_totals();
			self::load_graphs();
			/* self::add_numbers_graph(); */
			echo '</div>';
		}

		/**
		*	Loads Double Horizontal Bar Chart
		*/
		public static function add_chart_bars() {
			?>
			<style>
			.dhbc svg {
				height: 200px;
				width:100%;
			}
			.barchart {
				width:100%;
				display:none;
			}
			object {
				width: 100%;
				display: block;
				height: auto;
			}
			</style>
			<div class='barchart stat-row dhbc' style='width:100%;'>
				<object><svg></svg></object>
			</div>
			<?php
		}

		/**
		*	Loads Double Horizontal Bar Chart
		*/
		/**
		*	Loads Double Horizontal Bar Chart
		*/
		public static function add_chart_totals() {

			?>
			<div class='circle-stats stat-row'>
				<div class="stat-group-container">
					<div class="stat-group">
						<div class="label-top" id='sends-label-top'><?php _e( 'Sends' , 'inbound-mailer' ); ?></div>
						<div class="per" id ='sends-percentage'>0%</div>
						<svg class="svg"></svg>
						<div class="label-bottom" id='sends-label-bottom'>0</div>
					</div>
				</div>
				<div class="stat-group-container">
					<div class="stat-group">
						<div class="label-top" id='opens-label-top'><?php _e( 'Opens' , 'inbound-mailer' ); ?></div>
						<div class="per" id='opens-percentage'>0%</div>
						<svg class="svg"></svg>
						<div class="label-bottom" id='opens-label-bottom'>0</div>
					</div>
				</div>
				<div class="stat-group-container featured">
					<div class="stat-group">
						<div class="label-top" id='clicks-label-top'><?php _e( 'Clicks' , 'inbound-mailer' ); ?></div>
						<div class="per" id='clicks-percentage'>0%</div>
						<svg class="svg"></svg>
						<div class="label-bottom" id='clicks-label-bottom'>0</div>
					</div>
				</div>
				<div class="stat-group-container">
					<div class="stat-group">
						<div class="label-top" id='unopened-label-top'>Unopened</div>
						<div class="per" id='unopened-percentage'>0%</div>
						<svg class="svg"></svg>
						<div class="label-bottom" id="unopened-label-bottom">0</div>
					</div>
				</div>
			</div>
			<?php
		}

		public static function add_numbers_graph() {
			?>
			<div class='big-number-stats'>
				<div class="statistic-container">
					<div class="stat-number-container">
						<label class="stat-number-top-label">Total Sent</label>
						<h1 class="stat-number">100%</h1>
						<label class="stat-number-bottom-label">1800</label>
					</div>
				</div>
				<div class="statistic-container">
					<div class="stat-number-container">
						<label class="stat-number-top-label">Total Opens</label>
						<h1 class="stat-number">65%</h1>
						<label class="stat-number-bottom-label">1172</label>
					</div>
				</div>
				<div class="statistic-container feature">
					<div class="stat-number-container">
						<label class="stat-number-top-label">Total Clicks</label>
						<h1 class="stat-number">7.1%</h1>
						<label class="stat-number-bottom-label">129</label>
					</div>
				</div>
				<div class="statistic-container">
					<div class="stat-number-container">
						<label class="stat-number-top-label">Total Unopened</label>
						<h1 class="stat-number">34%</h1>
						<label class="stat-number-bottom-label">628</label>
					</div>
				</div>
			</div>
			<?php
		}


		/**
		*	Adds Quick Launch Buttons
		*/
		public static function add_quick_launch_buttons() {
			global $post;

			echo '<div class="quick-launch">';
			/* Display customizer launch button */
			if ( !isset($_GET['frontend']) || $_GET['frontend'] == 'false' )	{

				$post_link = Inbound_Mailer_Variations::get_variation_permalink( $post->ID , $vid = null );

				echo "<a rel='".$post_link."' id='cta-launch-front' class='button-primary ' href='$post_link&email-customizer=on'>". __( 'Launch Visual Editor' ,'inbound-email' ) ."</a>";
				echo "&nbsp;&nbsp;";
			}

			echo '<a class="button-primary" id="inbound-mailer-change-template-button">'. __('Switch Templates' , 'inbound-email' ) .'</a>';
			echo '</div>';
		}

		/**
		* Adds variation navigation tabs to call to action edit screen
		*
		*/
		public static function add_variation_buttons() {
			global $post, $Inbound_Mailer_Variations;


			$next_available_variation_id = $Inbound_Mailer_Variations->get_next_available_variation_id( $post->ID );
			$current_variation_id = $Inbound_Mailer_Variations->get_current_variation_id();

			if ( isset($_GET['new-variation']) || isset($_GET['clone']) ) {
				$current_variation_id = $next_available_variation_id;
			}


			$variations = $Inbound_Mailer_Variations->get_variations($post->ID);


			//echo '<span class="label-vairations">'. __( 'Variations' , 'inbound-email' ) .': </span>';
			$var_id_marker = 1;
			echo '<div class="variations-menu">';
			echo ' <div class="btn-group variation-group">';
			foreach ($variations as $vid => $variation) {

				$permalink = $Inbound_Mailer_Variations->get_variation_permalink( $post->ID , $vid );
				$letter = $Inbound_Mailer_Variations->vid_to_letter( $post->ID , $vid );

				//alert (variation.new_variation);
				if ($current_variation_id==$vid&&!isset($_GET['new-variation']) || $current_variation_id==$vid && isset($_GET['clone'])) {
					$cur_class = 'selected-variation';
				} else {
					$cur_class = '';
				}
				echo '<a class="btn btn-default '.$cur_class.'" href="?post='.$post->ID.'&inbvid='.$vid.'&action=edit" id="tab-'.$vid.'" data-permalink="'.$permalink.'" target="_parent" data-toggle="tooltip" data-placement="left" title="'. __('Variations' , 'inbound-mailer') .'">'.$letter.'</a>';
			}
			echo '</div>';
			echo ' <div class="btn-group variation-group">';
			if (!isset($_GET['new-variation'])) {

				echo '<a	class="btn btn-default "	href="?post='.$post->ID.'&inbvid='.$next_available_variation_id.'&action=edit&new-variation=1"	id="tabs-add-variation" title="'.__('Add New Variation' , 'inbound-email' ).'"> <i data-code="f132" style="vertical-align:bottom;" class="dashicons dashicons-plus"></i></a>';

			} else {
				$letter = $Inbound_Mailer_Variations->vid_to_letter( $post->ID , $next_available_variation_id );
				echo '<a	class="btn btn-default selected-variation"	href="?post='.$post->ID.'&inbvid='.$next_available_variation_id.'&action=edit" id="tabs-add-variation">'.$letter.'</a>';
			}


			echo '</div>';
			echo '</div>';

		}


		/**
		*	Adds quick preview container
		*/
		public static function add_preview() {
			global $post;
			$url = get_permalink( $post->ID );

			$pass = array( 'scheduled' , 'sent' , 'sending' , 'automation' );

			if ( !in_array( $post->post_status , $pass ) ) {
				return;
			}

			?>
			<script>
			function iframeLoaded() {
					var iFrameID = document.getElementById('iframe-email-preview');
					if(iFrameID) {
						iFrameID.height = iFrameID.contentWindow.document.body.scrollHeight + "px";
					}
			}
			</script>
			<div class="preview-container bs-callout bs-callout-clear">
				<h4><?php _e('Preview' , 'inbound-mailer') ; ?></h4>

				<iframe src="<?php echo $url; ?>" id='iframe-email-preview' onload="iframeLoaded()"></iframe>
			</div>
			<?php
		}


		/**
		*	Adds email send & scheduling options
		*/
		public static function add_email_send_settings() {
			global $post;

			echo '<div class="mail-send-settings-container bs-callout bs-callout-clear">';
			echo '<h4>' . __('Send Settings' , 'inbound-mailer') . '</h4>';

			self::add_batch_send_settings();
			self::add_automation_send_settings();
			echo '</div>';
		}

		/**
		*	Adds batch send settings
		*/
		public static function add_batch_send_settings() {
			global $post;

			$Inbound_Mailer_Common_Settings = Inbound_Mailer_Common_Settings();
			$settings = $Inbound_Mailer_Common_Settings->settings['batch-send-settings'];

			?>
			<div class="send-settings batch-send-settings-container">
				<?php
				self::render_settings('inbound-email' , $settings, $post);
				?>
			</div>
			<?php
		}


		/**
		*	Adds automation send settings
		*/
		public static function add_automation_send_settings() {
			?>
			<div class="send-settings automated-send-settings-container">
				automated settings here
			</div>
			<?php
		}

		/**
		*	Display email type select metabox
		*/
		public static function add_email_type_select_toggle() {

			$email_type = self::get_email_type();

			?>
			<select class='form-control' name='inbound_email_type' id='email_type'>
				<option value='batch' <?php ($email_type == 'batch') ? print 'selected="true"' : print '' ; ?>>Batch Email</option>
				<option value='automated' <?php ($email_type == 'automated') ? print 'selected="true"' : print '' ; ?>>Automation</option>
			</select>
			<?php
		}

		/**
		*	Display email save, delete & scheduling options
		*/
		public static function add_email_actions() {

			global $post;

			$email_type = self::get_email_type();

			if ($post->post_status == 'draft' || $post->post_status == 'publish' ) {
				$post->post_status = 'unsent';
			}
			?>
			<div class='email-status'>
				Current Status:	<i><span id='email-status-display'><?php echo $post->post_status; ?></span></i>

				<div class='email-actions'>
					<a href="<?php echo get_permalink( $post->ID ); ?>" class="btn btn-info send-action action-preview" id="action-preview" target="_blank"> <?php _e('Preview' , 'inbound-mailer'); ?></a>
					<button type="button" class="btn btn-success btn-mediums send-action action-schedule" id="action-schedule"><?php _e('Schedule' , 'inbound-email' );?></button>
					<button type="button" class="btn btn-warning btn-medium send-action action-unschedule" id="action-unschedule"><?php _e('Unschedule' , 'inbound-email' );?></button>
					<button type="button" class="btn btn-success btn-medium send-action action-send" id="action-send"><?php _e('Send' , 'inbound-email' );?></button>
					<button type="button" class="btn btn-primary btn-medium send-action action-clone" id="action-clone"><?php _e('Clone this email' , 'inbound-email' );?></button>
					<button type="button" class="btn btn-danger btn-medium send-action action-cancel" id="action-cancel-sending"><?php _e('Abort Send' , 'inbound-email' );?></button>
					<button type="button" class="btn btn-warning btn-medium send-action action-unarchive" id="action-unarchive"><?php _e('Unarchive' , 'inbound-email' );?></button>
				</div>
			</div>
			<?php
		}

		/**
		*	Adds template select box
		*/
		public static function add_selected_tamplate_info() {
			global $Inbound_Mailer_Variations, $post;

			$vid = Inbound_Mailer_Variations::get_current_variation_id();
			$template = $Inbound_Mailer_Variations->get_current_template( $post->ID , $vid );

			$thumbnail = self::get_template_thumbnail( $template );
			?>
			<div class='selected-template-metabox'>
				<img src='<?php echo $thumbnail; ?>' title='<?php echo $template; ?>' id='selected-template-image'>
			</div>
			<?php
		}

		/**
		* Discovers the email type by checking the inbound_email_type taxonomy
		* @return 'batch' as default otherwise return email type.
		*/
		public static function get_email_type() {
			global $post;

			$setting = Inbound_Email_Meta::get_settings( $post->ID );
			$vid = Inbound_Mailer_Variations::get_current_variation_id();

			if ( isset( $settings['variations'][$vid]['inbound_email_type'] ) ) {
				return $settings['variations'][$vid]['inbound_email_type'];
			} else {
				return 'batch';
			}
		}

		/**
		*	Gets template thumbnail
		*/
		public static function get_template_thumbnail( $template ) {

			// Get Thumbnail
			if (file_exists(INBOUND_EMAIL_PATH.'templates/'.$template."/thumbnail.png")) {
				$thumbnail = INBOUND_EMAIL_URLPATH.'templates/'.$template."/thumbnail.png";
			} else {
				$thumbnail = INBOUND_EMAIL_UPLOADS_URLPATH.$template."/thumbnail.png";
			}

			return $thumbnail;
		}

		/**
		* Renders shortcode data for user to copy for user
		*/
		public static function add_hidden_inputs() {
			global $post, $Inbound_Mailer_Variations;

			if ( !$post || $post->post_type != 'inbound-email' ) {
				return;
			}

			/* Add hidden param for visual editor */
			if(isset($_REQUEST['frontend']) && $_REQUEST['frontend'] == 'true') {
				echo '<input type="hidden" name="frontend" id="frontend-on" value="true" />';
			}

			/* Get current variation id */
			$vid = Inbound_Mailer_Variations::get_current_variation_id();

			/* Add variation status */
			$variations_status = $Inbound_Mailer_Variations->get_variation_status( $post->ID , $vid );
			echo '<input type="hidden" name="variation_status" value = "'.$variations_status .'">';

			/* Add variation id */
			echo '<input type="hidden" name="inbvid" id="open_variation" value = "'.$vid .'">';

			/* Get selected template */
			$template_id = $Inbound_Mailer_Variations->get_current_template( $post->ID );
			echo "<input type='hidden' name='selected_template'	id='selected_template' value='".$template_id."'>";

			/* Add scheduling action */
			echo "<input type='hidden' name='email_action'	id='email_action' value='none'>";
		}


		/**
		*	Removes WordPress SEO metabox from inbound-email post type.
		*	Currently disabled. This throws admin js error.
		*
		*/
		public static function remove_wp_seo() {
			//remove_meta_box( 'wpseo_meta', 'inbound-email', 'normal' ); // change custom-post-type into the name of your custom post type
		}

		/**
		* Display CTA Settings for templates AND extensions
		*/
		public static function render_settings( $settings_key , $custom_fields , $post ) {

			global $Inbound_Mailer_Variations;

			$settings = Inbound_Email_Meta::get_settings( $post->ID );
			$variations = ( isset($settings['variations']) ) ? $settings['variations'] : null;
			$vid = Inbound_Mailer_Variations::get_current_variation_id();

			// Begin the field table and loop
			echo '<div class="form-table" id="inbound-meta">';

			foreach ($custom_fields as $field) {

				$field_id = apply_filters('inbound_email_prepare_input_id', $settings_key . "-" .$field['id'] );

				$label_class = $field['id'] . "-label";
				$type_class = " inbound-" . $field['type'];
				$type_class_row = " inbound-" . $field['type'] . "-row";
				$type_class_option = " inbound-" . $field['type'] . "-option";
				$option_class = (isset($field['class'])) ? $field['class'] : '';

				/* if setting does has a stored value then use default value */
				if ( isset( $variations[ $vid ][ $field_id ] ) ) {
					$meta = $variations[ $vid ][ $field_id ];
				}
				/* else set value to stored value */
				else {
					$meta = $field['default'];
				}

				// Remove prefixes on global => true template options
				if ( isset($field['global']) && $field['global']	) {
					$field_id = $field['id'];
					$meta =	( isset( $settings[ $field_id ] ) ) ? $settings[ $field_id ] :	$field['default'];
				}

				/* Set setting value to cloned value if clone command is enabled */
				if ( isset($_GET['clone']) ) {

					if (isset($field['global']) && $field['global'] === true) {
						$meta = get_post_meta($post->ID,	$field['id'] . '-'. $_GET['clone'] , true);
					} else {
						$meta = get_post_meta($post->ID,	$settings_key . '-' . $field['id'] . '-'. $_GET['clone'] , true);
					}
				}

				// begin a table row with
				echo '<div class="'.$field['id'].$type_class_row.' div-'.$option_class.' inbound-email-option-row inbound-meta-box-row">';
						if ($field['type'] != "description-block" && $field['type'] != "custom-css" ) {
						echo '<div id="inbound-'.$field_id.'" data-actual="'.$field_id.'" class="inbound-meta-box-label inbound-email-table-header '.$label_class.$type_class.'"><label for="'.$field_id.'">'.$field['label'].'</label></div>';
						}

						echo '<div class="inbound-email-option-td inbound-meta-box-option '.$type_class_option.'" data-field-type="'.$field['type'].'">';
						switch($field['type']) {
							case 'description-block':
								echo '<div id="'.$field_id.'" class="description-block">'.$field['description'].'</div>';
								break;
							// text
							case 'colorpicker':
								if (!$meta)
								{
									$meta = $field['default'];
								}
								$var_id = (isset($_GET['new_meta_key'])) ? "-" . $_GET['new_meta_key'] : '';
								echo '<input type="text" class="jpicker" style="background-color:#'.$meta.'" name="'.$field_id.'" id="'.$field_id.'" value="'.$meta.'" size="5" /><span class="button-primary new-save-wp-cta" data-field-type="text" id="'.$field_id.$var_id.'" style="margin-left:10px; display:none;">Update</span>
										<div class="inbound_email_tooltip tool_color" title="'.$field['description'].'"></div>';
								break;
							case 'datepicker':
								echo '<div class="jquery-date-picker inbound-datepicker" id="date-picking" data-field-type="text">
								<span class="datepair" data-language="javascript">
										<input type="text" id="date-picker-'.$settings_key.'" class="date start form-control" placeholder="' . __('Select Date','inbound-mailer') . '"/></span>
										<input id="time-picker-'.$settings_key.'" type="text" class="time time-picker form-control" placeholder =" ' . __( 'Select Time' , 'inbound-mailer' ) . '" />
										<input type="hidden" name="'.$field_id.'" id="'.$field_id.'" value="'.$meta.'" class="new-date" value="" >

									</div>';
								break;
							case 'text':
								echo '<input type="text" class="'.$option_class.' form-control" name="'.$field_id.'" id="'.$field_id.'" value="'.$meta.'" size="30" />
										<div class="inbound_email_tooltip" title="'.$field['description'].'"></div>';
								break;
							case 'number':

								echo '<input type="number" class="'.$option_class.'" name="'.$field_id.'" id="'.$field_id.'" value="'.$meta.'" size="30" />
										<div class="inbound_email_tooltip" title="'.$field['description'].'"></div>';

								break;
							// textarea
							case 'textarea':
								echo '<textarea name="'.$field_id.'" id="'.$field_id.'" cols="106" rows="6" style="width: 75%;">'.$meta.'</textarea>
										<div class="inbound_email_tooltip tool_textarea" title="'.$field['description'].'"></div>';
								break;
							// wysiwyg
							case 'wysiwyg':
								echo "<div class='iframe-options iframe-options-".$field_id."' id='".$field['id']."'>";
								wp_editor( $meta, $field_id, $settings = array( 'editor_class' => $field['id'] ) );
								echo	'<p class="description">'.$field['description'].'</p></div>';
								break;
							// media
							case 'media':
								//echo 1; exit;
								echo '<label for="upload_image" data-field-type="text">';
								echo '<input name="'.$field_id.'"	id="'.$field_id.'" type="text" size="36" name="upload_image" value="'.$meta.'" />';
								echo '<input class="upload_image_button" id="uploader_'.$field_id.'" type="button" value="Upload Image" />';
								echo '<p class="description">'.$field['description'].'</p>';
								break;
							// checkbox
							case 'checkbox':
								$i = 1;
								echo "<table class='inbound_email_check_box_table'>";
								if (!isset($meta)){$meta=array();}
								elseif (!is_array($meta)){
									$meta = array($meta);
								}
								foreach ($field['options'] as $value=>$label) {
									if ($i==5||$i==1)
									{
										echo "<tr>";
										$i=1;
									}
										echo '<td data-field-type="checkbox"><input type="checkbox" name="'.$field_id.'[]" id="'.$field_id.'" value="'.$value.'" ',in_array($value,$meta) ? ' checked="checked"' : '','/>';
										echo '<label for="'.$value.'">&nbsp;&nbsp;'.$label.'</label></td>';
									if ($i==4)
									{
										echo "</tr>";
									}
									$i++;
								}
								echo "</table>";
								echo '<div class="inbound_email_tooltip tool_checkbox" title="'.$field['description'].'"></div>';
							break;
							// radio
							case 'radio':
								foreach ($field['options'] as $value=>$label) {
									//echo $meta.":".$field_id;
									//echo "<br>";
									echo '<input type="radio" name="'.$field_id.'" id="'.$field_id.'" value="'.$value.'" ',$meta==$value ? ' checked="checked"' : '','/>';
									echo '<label for="'.$value.'">&nbsp;&nbsp;'.$label.'</label> &nbsp;&nbsp;&nbsp;&nbsp;';
								}
								echo '<div class="inbound_email_tooltip" title="'.$field['description'].'"></div>';
							break;
							// select
							case 'dropdown':
								echo '<select name="'.$field_id.'" id="'.$field_id.'" class="'.$field['id'].' form-control">';
								foreach ($field['options'] as $value=>$label) {
									echo '<option', $meta == $value ? ' selected="selected"' : '', ' value="'.$value.'">'.$label.'</option>';
								}
								echo '</select><div class="inbound_email_tooltip" title="'.$field['description'].'"></div>';
							break;
							// select
							case 'select2':
								echo '<select name="'.$field_id.'[]" id="'.$field_id.'" class="'.$field['id'].' select2 select-lists" multiple>';
								foreach ($field['options'] as $value=>$label) {
								$selected = ( in_array( $value, $meta) ) ? 'selected="true"' : '';

									echo '<option value="'.$value.'" '.$selected.' >'.$label.'</option>';
								}
								echo '</select><div class="inbound_email_tooltip" title="'.$field['description'].'"></div>';
							break;


						} //end switch
				echo '</div></div>';
			} // end foreach
			echo '</div>'; // end table
			//exit;
		}


		/**
		* Changes the default placeholder text of wp_title when cta is being created. With CTAs, wp_title is a descriptive title.
		*
		*/
		public static function change_title_placeholder_text( $text , $post ) {
			if ($post->post_type!='inbound-email') {
				return $text;
			}
			return __( 'Enter Email Description' , 'inbound-email' );
		}

		/**
		* Enqueues js
		*/
		public static function enqueue_admin_scripts() {
			$screen = get_current_screen();

			if ( !isset( $screen ) || $screen->id != 'inbound-email' || ( $screen->base != 'post' && $screen->base != 'post-new' ) ) {
				return;
			}

			wp_enqueue_style('inbound-mailer-ab-testing-admin-css', INBOUND_EMAIL_URLPATH . 'css/admin-ab-testing.css');
			wp_enqueue_script('jquery-ui-core');

			/* load BootStrap */
			wp_register_script( 'bootstrap-js' , INBOUND_EMAIL_URLPATH .'lib/BootStrap/js/bootstrap.min.js');
			wp_enqueue_script( 'bootstrap-js' );

			/* BootStrap CSS */
			wp_register_style( 'bootstrap-css' , INBOUND_EMAIL_URLPATH . 'lib/BootStrap/css/bootstrap.css');
			wp_enqueue_style( 'bootstrap-css' );

			/* Ladda.min.js - For button loading effect*/
			wp_register_script( 'ladda-js' , INBOUND_EMAIL_URLPATH .'lib/BootStrap/js/ladda.min.js');
			wp_enqueue_script( 'ladda-js' );

			wp_register_style( 'ladda-css' , INBOUND_EMAIL_URLPATH . 'lib/BootStrap/css/ladda-themeless.min.css');
			wp_enqueue_style( 'ladda-css' );

			/* D3 charting suport */
			wp_register_script( 'd3' , INBOUND_EMAIL_URLPATH .'lib/d3/d3.v3.min.js');
			wp_enqueue_script( 'd3' );

			wp_register_script( 'nvd3' , INBOUND_EMAIL_URLPATH .'lib/d3/nv.d3.js');
			wp_enqueue_script( 'nvd3' );

			wp_register_style( 'd3-css' , INBOUND_EMAIL_URLPATH . 'lib/d3/style.css');
			wp_enqueue_style( 'd3-css' );

			/* Load Snap */
			wp_register_script( 'snap' , INBOUND_EMAIL_URLPATH .'lib/snap/snap.svg.js');
			wp_enqueue_script( 'snap' );

			/* Load FontAwesome */
			wp_register_style( 'font-awesome' , INBOUND_EMAIL_URLPATH . 'lib/FontAwesome/css/font-awesome.min.css');
			wp_enqueue_style( 'font-awesome' );
		}

		/**
		* Prints js at admin footer
		*/
		public static function print_js_listeners() {
			$screen = get_current_screen();

			if ( !isset( $screen ) || $screen->id != 'inbound-email' || $screen->parent_base != 'edit' ) {
				return;
			}

			global $post;

			/* Load Settings JS Class */
			self::print_Settings_class();

			?>
			<script>
			jQuery(document).ready(function() {

				/* Initialize CPT UI default changes */
				Settings.init();

				/* Add listener to hide datepicker */
				jQuery('#inbound_batch_send_nature').on('change' , function() {
					Settings.load_batch_send_nature();
				});

				/* Fire datepicker toggle on load */
				Settings.load_batch_send_nature();

				/* Add listener to prompt sweet alert on schedule */
				jQuery('#action-schedule').on('click', function(e) {
					Settings.schedule_email();
				});

				/* Add listener to prompt sweet alert on unschedule */
				jQuery('body').on('click', '.action-unschedule' ,function(e) {
					Settings.unschedule_email();
				});

				/* Add listener to prompt sweet alert on send */
				jQuery('#action-send').on('click', function(e) {
					Settings.send_email();
				});

				/* Add listener to prompt sweet alert on send */
				jQuery('#action-clone').on('click', function(e) {
					Settings.clone_email();
				});

				/* Add listener to prompt sweet alert on send */
				jQuery('#action-cancel-sending').on('click', function(e) {
					Settings.cancel_send();
				});

				/* Add listener to prompt email send settings on send type toggle */
				jQuery('#email_type').on('change' , function() {
					Settings.load_email_type();
				});

				/* Add listener for template switching */
				jQuery('#inbound-mailer-change-template-button').live('click', function () {
					Settings.load_template_selector();
				});

				/* Add listener for template selecting 	*/
				jQuery('.template_select').click(function(){
					Settings.select_template( jQuery( this ) );
				});

				/* Fire: load correct send settings on load */
				Settings.load_email_type();

				/* Fire: Load post status toggles */
				Settings.toggle_post_status('<?php echo $post->post_status; ?>');

			});
			</script>

			<?php
		}

		/**
		*	JS class for handling UI elements
		*/
		public static function print_Settings_class() {
			global $post;
			?>
			<script>
			/**
			*	Declare hide/reveal methods for email settings
			*/
			var Settings = ( function () {

				var Init = {
					/**
					*	Initialize immediate UI modifications
					*/
					init: function() {

						/* Initiate Select2 */
						jQuery( '.select2' ).select2( { width: '300px'	});

						/* Move publsihing actions	*/
						var clone = jQuery('#major-publishing-actions');
						clone.appendTo('#email-send-actions');
						//jQuery('#submitdiv').hide();

						/* Hide screen options */
						jQuery('#show-settings-link').hide();

						/* Removes wp_content wysiwyg */
						jQuery('#postdivrich').hide();

						/* Removes Permalink edit option */
						//jQuery('#edit-slug-box').hide();

						/* Initiate variation tooltips */
						jQuery('.btn-group a').tooltip();
						jQuery('.inbound_email_tooltip').tooltip();
						jQuery('#selected-template-image').tooltip();

						/* Change 'Publish' to 'Save' */
						jQuery('#submitdiv h3 span').text('<?php _e('Save', 'inbound-email'); ?>');
					},
					/**
					*	Changes UI based on current post status
					*/
					toggle_post_status: function( post_status ) {

						switch (post_status) {
							case 'sent':
								Settings.show_graphs();
								Settings.show_preview();
								Settings.show_quick_lauch_buttons();
								Settings.show_clone_buttons();
								Settings.hide_header_settings();
								Settings.hide_email_send_settings();
								Settings.hide_template_settings();
								Settings.hide_save_buttons();
								Settings.hide_send_buttons();
								break;
							case 'sending':														
								Settings.show_preview();
								Settings.show_cancel_buttons();
								Settings.hide_quick_lauch_buttons();
								Settings.hide_header_settings();
								Settings.hide_email_send_settings();
								Settings.hide_template_settings();
								Settings.hide_save_buttons();
								Settings.show_graphs();
								break;
							case 'scheduled':
								Settings.show_countdown_container();
								Settings.show_unschedule_buttons();
								Settings.hide_header_settings();
								Settings.hide_template_settings();
								Settings.hide_email_send_settings();
								Settings.hide_save_buttons();
								Settings.update_countdown();
								break;
							case 'automated':
								jQuery('#action-preview').show();
								Settings.show_header_settings();
								Settings.show_email_send_settings();
								Settings.show_quick_lauch_buttons();
								Settings.show_template_settings();
								Settings.show_graphs();
								break;
							default: /* unsent */						
								jQuery('#action-preview').show();
								Settings.hide_graphs();
								Settings.show_header_settings();
								Settings.show_email_send_settings();
								Settings.show_quick_lauch_buttons();
								Settings.show_template_settings();
								jQuery('#post_status').val('unsent');
								break;
							
						}
					},
					show_header_settings: function() {
						jQuery('.mail-headers-container').show();
					},
					hide_header_settings: function() {
						jQuery('.mail-headers-container').hide();
					},
					show_email_send_settings: function() {
						jQuery('.mail-send-settings-container').show();
					},
					hide_email_send_settings: function() {
						jQuery('.mail-send-settings-container').hide();
					},
					show_quick_lauch_buttons: function() {
						jQuery('.quick-launch').show();
					},
					hide_quick_lauch_buttons: function() {
						jQuery('.quick-launch').hide();
					},
					show_graphs: function() {
						jQuery('.statistics-reporting-container').show();
					},
					hide_graphs: function() {
						jQuery('.statistics-reporting-container').hide();
					},
					show_graphs_barchart: function() {
						jQuery('.barchart').show();
					},
					hide_graphs_barchart: function() {
						jQuery('.barchart').hide();
					},
					show_preview: function() {
						jQuery('.preview-container').show();
					},
					hide_preview: function() {
						jQuery('.preview-container').hide();
					},
					show_template_settings: function() {
						jQuery('#postbox-container-2').show();
					},
					hide_template_settings: function() {
						jQuery('#postbox-container-2').hide();
					},
					show_countdown_container: function() {
						jQuery('.countdown-container').show();
					},
					hide_countdown_container: function() {
						jQuery('.countdown-container').hide();
					},
					show_save_buttons: function() {
						jQuery('#major-publishing-actions').show();
					},
					hide_save_buttons: function() {
						jQuery('#major-publishing-actions').hide();
					},
					show_unschedule_buttons: function() {
						jQuery('#email_type').prop('disabled', 'true');
						jQuery('.send-action').hide();
						jQuery('#action-unschedule').show();
					},
					hide_unschedule_buttons: function() {
						jQuery('#action-unschedule').hide();
					},
					show_cancel_buttons: function() {
						jQuery('#email_type').prop('disabled', 'true');
						jQuery('.send-action').hide();
						jQuery('#action-cancel-sending').show();
					},
					hide_cancel_buttons: function() {
						jQuery('#action-cancel-sending').hide();
					},
					show_send_buttons: function() {
						jQuery('#action-send').show();
					},
					hide_send_buttons: function() {
						jQuery('#action-send').hide();
					},
					show_clone_buttons: function() {
						jQuery('#action-clone').show();
					},
					hide_clone_buttons: function() {
						jQuery('#action-clone').hide();
					},
					/**
					* Populate countdown ticket
					*/
					update_countdown: function() {

						// variables for time units
						var days, hours, minutes, seconds, message;

						var target_date = new Date( jQuery('#inbound_send_datetime').val() );

						// update the tag with id "countdown" every 1 second
						setInterval(function () {

							// find the amount of "seconds" between now and target
							var current_date = new Date().getTime();
							var seconds_left = (target_date - current_date) / 1000;

							// do some time calculations
							days = parseInt(seconds_left / 86400);
							seconds_left = seconds_left % 86400;

							hours = parseInt(seconds_left / 3600);
							seconds_left = seconds_left % 3600;

							minutes = parseInt(seconds_left / 60);
							seconds = parseInt(seconds_left % 60);

							message = "<span class='send-countdown-label'><?php _e('Time until send:' , 'inbound-mailer'); ?></span>";
							// format countdown string + set tag value
							jQuery('.scheduled-information-countdown').html( message + days + "d " + hours + "h " + minutes + "m " + seconds + "s ");


						}, 1000);
					},
					/**
					*	Changes UI based on batch send type
					*/
					load_batch_send_nature: function() {
						var batch_send_type = jQuery('#inbound_batch_send_nature option:selected').val();

						jQuery('.send-action').hide();

						switch (batch_send_type) {
							case 'schedule':
								jQuery('.inbound-datepicker-row').show();
								jQuery('#action-schedule').show();
								break;
							default:
								jQuery('.inbound-datepicker-row').hide();
								jQuery('#action-send').show();
								break;
						}
					},
					load_email_type: function() {
						var send_nature = jQuery('#email_type option:selected').val();
						jQuery('.send-settings').hide();

						switch (send_nature) {
							case 'automated':
								jQuery('.automated-send-settings-container').show();
								jQuery('.send-action').hide();
								jQuery('#post_status').val('automated');
								jQuery('#email-status-display').text('<?php _e('Automated' , 'inbound-mailer'); ?>');
								break;
							default:
								Settings.load_batch_send_nature();
								jQuery('.batch-send-settings-container').show();
								break;
						}
					},

					/**
					*	Checks to make sure all necessary email fields are populated correctly
					*/
					validate_fields: function() {

						/* checks if scheduled email is scheduled into future */
						if( ! Settings.validate_headers() ){
							return false;
						}

						/* checks if lists are selected */
						if( ! Settings.validate_recipients() ){
							return false;
						}

						/* checks if scheduled email is scheduled into future */
						if( ! Settings.validate_schedule_date() ){
							return false;
						}

						return true;
					},
					validate_headers: function() {
						if ( !jQuery('#inbound_subject').val() ) {
							swal("<?php _e('Email requires subject to send.' , 'inbound-mailer'); ?>");
							return false;
						}
						if ( !jQuery('#inbound_from_name').val() ) {
							swal("<?php _e('Email requires from name to send.' , 'inbound-mailer'); ?>");
							return false;
						}
						if ( !jQuery('#inbound_from_email').val() ) {
							swal("<?php _e('Email requires from email address to send.' , 'inbound-mailer'); ?>");
							return false;
						}

						return true;
					},
					/**
					*	Checks to make sure lead lists are selected for sending
					*/
					validate_recipients: function() {
						var selectedRecipients = jQuery('#inbound_recipients').val();

						if ( selectedRecipients ) {
							return true;
						} else {
							swal("<?php _e('Please set email recipients.' , 'inbound-mailer'); ?>");
							return false;
						}
					},
					/**
					*	When being scheduled checks and make sure schedule date is set as future date
					*/
					validate_schedule_date: function () {

						/* Check if scheduling if not validate true */
						if ( jQuery('#inbound_batch_send_nature').val() != 'schedule' ) {
							return true;
						}


						/* check if date is set into future and throw warning if not */
						var selectedDate = jQuery('#inbound_send_datetime').val();

						/* build a date string for current datetime */
						var date = new Date();
						var y = date.getFullYear();
						var mo = date.getMonth() + 1;
						var d = date.getDate();
						var h = date.getHours();
						var mi = date.getMinutes();
						var current = y + '-' + mo + '-' + d + ' ' + h + ':' + mi;

						/* compare dates and return fase if selected date is located in past */
						if ( new Date(selectedDate) < new Date(current) ) {
							swal("<?php _e('Please select a date/time in the future.' , 'inbound-mailer'); ?>");
							return false;
						} else {
							return true;
						}
					},
					unschedule_email: function() {

						/* Throw confirmation for scheduling */
						swal({
							title: "<?php _e( 'Are you sure?' , 'inbound-mailer' ); ?>",
							text: "<?php _e( 'Are you sure you want to unschedule this email?' , 'inbound-mailer' ); ?>",
							type: "info",
							showCancelButton: true,
							confirmButtonColor: "#449d44",
							confirmButtonText: "<?php _e( 'Yes, unschedule it!' , 'inbound-mailer' ); ?>",
							closeOnConfirm: false
						}, function(){
							swal( {
								title: "<?php _e('Please wait' , 'inbound-mailer' ); ?>",
								text: "<?php _e('We are unscheduling your email right now.' , 'inbound-mailer' ); ?>",
								imageUrl: '<?php echo INBOUND_EMAIL_URLPATH; ?>/images/loading_colorful.gif'
							} );

							jQuery('#email_action').val('unschedule');
							jQuery('#post_status').val('unsent');
							jQuery('#post').submit();
						});

					},
					/**
					*	Validates and Schedules Email
					*/
					schedule_email: function() {
						if ( ! Settings.validate_fields() ){
							return false;
						}

						/* Throw confirmation for scheduling */
						swal({
							title: "<?php _e( 'Are you sure?' , 'inbound-mailer' ); ?>",
							text: "<?php _e( 'Are you sure you want to schedule this email?' , 'inbound-mailer' ); ?>",
							type: "info",
							showCancelButton: true,
							confirmButtonColor: "#449d44",
							confirmButtonText: "<?php _e( 'Yes, schedule it!' , 'inbound-mailer' ); ?>",
							closeOnConfirm: false
						}, function(){
							swal( {
								title: "<?php _e('Please wait' , 'inbound-mailer' ); ?>",
								text: "<?php _e('We are scheduling your email now.' , 'inbound-mailer' ); ?>",
								imageUrl: '<?php echo INBOUND_EMAIL_URLPATH; ?>/images/loading_colorful.gif'
							} );
							
							jQuery('#email_action').val('schedule');
							jQuery('#post_status').val('scheduled');
							jQuery('#post').submit();
						});

					},
					/**
					*	Validates and Schedules Email Immediately
					*/
					clone_email: function() {
						
						/* Throw confirmation for scheduling */
						swal({
							title: "Are you sure?",
							text: "<?php _e( 'Are you sure you want to clone this email?' , 'inbound-mailer' ); ?>",
							type: "info",
							showCancelButton: true,
							confirmButtonColor: "#2ea2cc",
							confirmButtonText: "<?php _e( 'Yes, clone it!' , 'inbound-mailer' ); ?>",
							closeOnConfirm: false
						}, function(){


							swal( {
								title: "<?php _e('Please wait' , 'inbound-mailer' ); ?>",
								text: "<?php _e('We are cloning your email now.' , 'inbound-mailer' ); ?>",
								imageUrl: '<?php echo INBOUND_EMAIL_URLPATH; ?>/images/loading_colorful.gif'
							} );

							/* redirect page */
							var redirect_url = "<?php echo admin_url('admin.php?action=inbound_email_clone_post&post=' . $post->ID ); ?>";
							window.location = redirect_url;
						});

					},
					/**
					*	Validates and Schedules Email Immediately
					*/
					send_email: function() {
						if ( ! Settings.validate_fields() ){
							return false;
						}

						/* Throw confirmation for scheduling */
						swal({
							title: "Are you sure?",
							text: "<?php _e( 'Are you sure you want to begin sending this email?' , 'inbound-mailer' ); ?>",
							type: "info",
							showCancelButton: true,
							confirmButtonColor: "#449d44",
							confirmButtonText: "<?php _e( 'Yes, send it!' , 'inbound-mailer' ); ?>",
							closeOnConfirm: false
						}, function(){


							swal( {
								title: "<?php _e('Please wait' , 'inbound-mailer' ); ?>",
								text: "<?php _e('We are scheduling your email now.' , 'inbound-mailer' ); ?>",
								imageUrl: '<?php echo INBOUND_EMAIL_URLPATH; ?>/images/loading_colorful.gif'
							} );

							jQuery('#email_action').val('schedule');
							jQuery('#post_status').val('sending');
							jQuery('#post').submit();
						});

					},
					/**
					*	Validates and Schedules Email Immediately
					*/
					cancel_send: function() {
						if ( ! Settings.validate_fields() ){
							return false;
						}

						/* Throw confirmation for scheduling */
						swal({
							title: "Are you sure?",
							text: "<?php _e( 'Cancelling this email will remove all unsent emails from our send queue. Cancel now to stop sending.' , 'inbound-mailer' ); ?>",
							type: "warning",
							showCancelButton: true,
							confirmButtonColor: "#d9534f",
							confirmButtonText: "<?php _e( 'Yes, cancel it now!' , 'inbound-mailer' ); ?>",
							closeOnConfirm: false
						}, function(){


							swal( {
								title: "<?php _e('Please wait' , 'inbound-mailer' ); ?>",
								text: "<?php _e('We are cancelling your email now.' , 'inbound-mailer' ); ?>",
								imageUrl: '<?php echo INBOUND_EMAIL_URLPATH; ?>/images/loading_colorful.gif'
							} );

							jQuery('#email_action').val('unschedule');
							jQuery('#post_status').val('cancelled');
							jQuery('#post').submit();
						});

					},
					/**
					*	Select template
					*/
					select_template: function( element ) {

						var template = element.attr('id');
						var label = element.attr('label');

						/* Throw confirmation for switching templates */
						swal({
							title: "Are you sure?",
							text: "<?php _e( 'Are you sure you want to select this template?' , 'inbound-mailer' ); ?>",
							type: "info",
							showCancelButton: true,
							confirmButtonColor: "#449d44",
							confirmButtonText: "<?php _e( 'Yes' , 'inbound-mailer' ); ?>",
							closeOnConfirm: false,
							closeOnCancel: false
						}, function(	){

								swal( {
									title: "<?php _e('Please wait' , 'inbound-mailer' ); ?>",
									text: "<?php _e('We are setting up your email now.' , 'inbound-mailer' ); ?>",
									imageUrl: '<?php echo INBOUND_EMAIL_URLPATH; ?>/images/loading_colorful.gif'

								}, function() {

								});

								jQuery('#selected_template').val(template);
								jQuery('#post').submit();

						});
					},
					/**
					*	Loads template selection box
					*/
					load_template_selector: function() {

						jQuery(".wrap").fadeOut(500,function(){

							jQuery(".inbound-mailer-template-selector-container").fadeIn(500, function(){
								jQuery(".currently_selected").show();
								jQuery('#inbound-mailer-cancel-selection').show();
							});

						});
					}

				}

				return Init;

			})();
			</script>
			<?php

		}

		/**
		* Updates call to action variation data on post save
		*
		* @param INT $inbound_email_id of call to action id
		*
		*/
		public static function action_save_data( $inbound_email_id ) {
			global $post;
			unset($_POST['post_content']);

			if ( wp_is_post_revision( $inbound_email_id ) ) {
				return;
			}

			if ( !isset($_POST['post_type']) || $_POST['post_type'] != 'inbound-email' ) {
				return;
			}

			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
				return;
			}

			$email_settings = Inbound_Email_Meta::get_settings( $post->ID );

			/* Set the call to action variation into a session variable */
			$_SESSION[ $post->ID . '-variation-id'] = (isset($_POST[ 'inbvid'])) ? $_POST[ 'inbvid'] : '0';

			/* save all post vars as meta */
			foreach ($_POST as $key => $value) {
				if ( substr( $key , 0 , 8 ) == 'inbound_' ){
					$email_settings[ $key ] = $value;
				} else {
					if (self::check_whitelist( $key )) {
						$email_settings['variations'][ $_POST[ 'inbvid'] ][ $key ] = $value;
					}
				}
			}

			/* Update Settings */
			Inbound_Email_Meta::update_settings( $post->ID , $email_settings );

			/* Perform scheduling */
			Inbound_Mailer_Metaboxes::action_processing();
		}

		/**
		*	Schedule email
		*/
		public static function action_processing() {
			
			global $post; 
			

			switch( $_POST['email_action'] ) {
			
				case 'unschedule':
					Inbound_Mailer_Scheduling::unschedule_email( $post->ID );
					break;
				case 'schedule': 
					Inbound_Mailer_Scheduling::schedule_email( $post->ID );
					break;
					
				
			}
			
		}

		/**
		*	Checks meta key for variation setting qualification
		*	@returns BOOLEAN $key false for skip true for save
		*/
		public static function check_whitelist( $key ) {
			/* do not save post_ related keys */
			if ( substr( $key , 0 , 5 ) == 'post_' ) {
				return false;
			}

			/* do not save hidden custom fields */
			if ( substr( $key , 0 , 1 ) == '_' ) {
				return false;
			}

			/* do not save hidden custom fields */
			if ( substr( $key , 0 , 7 ) == 'hidden_' ) {
				return false;
			}

			/* do not save hidden custom fields */
			if ( substr( $key , 0 , 4 ) == 'cur_' ) {
				return false;
			}

			/* do not save hidden custom fields */
			if ( strstr( $key , 'nonce' ) ) {
				return false;
			}

			/* do not save hidden custom fields */
			if ( in_array( $key , array('inbvid', 'email_action' , 'originalaction','action','original_publish','publish','original_post_status', 'referredby', 'meta-box-order-nonce', 'comment_status','ping_status','post_mime_type','newtag','tax_input','post_password' ,'visibility','wp-preview'	) ) ) {
				return false;
			}

			return true;
		}

		/**
		*  
		*/
		public static function check_post_stats( $data ) {
			if ( $data['post_type']!='inbound-email' ) {
				return $data;
			}
			
			if ( $data['post_status']=='publish') {
				 $data['post_status'] = 'unsent';
			}
			
			return $data;
		}

	}

	$GLOBALS['Inbound_Mailer_Metaboxes'] = new Inbound_Mailer_Metaboxes;
}


