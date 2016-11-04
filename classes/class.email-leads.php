<?php
if (!class_exists('Inbound_Mailer_Direct_Email_Leads')) {

    class Inbound_Mailer_Direct_Email_Leads {

        /**
         * Inbound_Mailer_Direct_Email_Leads constructor.
         */
        public function __construct() {
            self::add_hooks();
        }

        /**
         *
         */
        public static function add_hooks() {
            /*do actions and filters here!*/

            /*Add the "Email Lead" tab to the edit lead list of tabs*/
            add_filter('wpl_lead_tabs', array(__CLASS__, 'add_direct_email_tab'), 10, 1);

            /*Add the contents to the "Email Lead" tab*/
            add_action('wpl_print_lead_tab_sections', array(__CLASS__, 'add_direct_email_tab_contents'));

            /*Add ajax listener for populating the address headers when a premade template is selected*/
            add_action('wp_ajax_get_addressing_settings', array(__CLASS__, 'get_addressing_settings'));

            /*Add ajax listener for sending the email*/
            add_action('wp_ajax_send_email_to_lead', array(__CLASS__, 'send_email_to_lead'));

        }

        /**
         * @param $tabs
         * @return mixed
         */
        public static function add_direct_email_tab($tabs) {
            $args = array(
                'id' => 'wpleads_lead_tab_direct_email',
                'label' => __('Email Lead', 'inbound-pro')
            );

            array_push($tabs, $args);

            return $tabs;
        }

        /**
         *
         */
        public static function add_direct_email_tab_contents() {
            global $post;

            $email_settings = new Inbound_Email_Meta;
            $metaboxes = new Inbound_Mailer_Metaboxes;

            /* Enqueue Sweet Alert support  */
            wp_enqueue_script('sweet-alert-js', INBOUND_EMAIL_URLPATH . 'assets/libraries/SweetAlert/sweet-alert.js');
            wp_enqueue_style('sweet-alert-css', INBOUND_EMAIL_URLPATH . 'assets/libraries/SweetAlert/sweet-alert.css');


            /*get the email address we're sending to*/
            $recipient_email_addr = Leads_Field_Map::get_field($post->ID, 'wpleads_email_address');

            /*get all the "automated" emails*/
            $email_templates = get_posts(array(
                'numberposts' => -1,
                'post_status' => 'automated',
                'post_type' => 'inbound-email',
            ));

            /*get the current user*/	
			$user = wp_get_current_user();
            
            /*put the email ids and names in an array for use in the email dropdown selector*/
            $template_id_and_name;
            foreach ($email_templates as $email_template) {

                $template_id_and_name[$email_template->ID] = $email_template->post_title;
            }


            /*these are the fields in the "Email Lead" tab*/
            $custom_fields = array(
                'subject' => array(
                    'description' => __('Subject line of the email. This field is variation dependant!', 'inbound-pro'),
                    'label' => __('Subject Line', 'inbound-pro'),
                    'id' => 'subject',
                    'type' => 'text',
                    'default' => '',
                    'class' => 'direct_email_lead_field',
                ),
                'from_name' => array(
                    'label' => __('From Name', 'inbound-pro'),
                    'description' => __('The name of the sender. This field is variation dependant!', 'inbound-pro'),
                    'id' => 'from_name',
                    'type' => 'text',
                    'default' => $user->display_name,
                    'class' => 'direct_email_lead_field',
                ),
                'from_email' => array(
                    'label' => __('From Email', 'inbound-pro'),
                    'description' => __('The email address of the sender. This field is variation dependant!', 'inbound-pro'),
                    'id' => 'from_email',
                    'type' => 'text',
                    'default' => $user->user_email,
                    'class' => 'direct_email_lead_field',
                ),
                'reply_email' => array(
                    'label' => __('Reply Email', 'inbound-pro'),
                    'description' => __('The email address recipients can reply to. This field is variation dependant!', 'inbound-pro'),
                    'id' => 'reply_email',
                    'type' => 'text',
                    'default' => $user->user_email,
                    'class' => 'direct_email_lead_field',
                ),
                'recipient_email_address' => array(
                    'label' => __('Recipient Email Address', 'inbound-pro'),
                    'description' => __('The email address of the recipient.', 'inbound-pro'),
                    'id' => 'recipient_email_address',
                    'type' => 'text',
                    'default' => $recipient_email_addr,
                    'class' => '',
                ),
                'use_premade_template' => array(
                    'label' => __('Use a premade email template?', 'inbound-pro'),
                    'description' => __('Use this to choose whether to send a custom or a premade email', 'inbound-pro'),
                    'id' => 'premade_template_chooser',
                    'type' => 'dropdown',
                    'default' => '0',
                    'class' => 'premade_template_chooser',
                    'options' => array('0' => 'No', '1' => 'Yes'),
                ),
                'email_message_box' => array(
                    'label' => __('Email Message', 'inbound-pro'),
                    'description' => __('Use this editor to create a short custom email messages', 'inbound-pro'),
                    'id' => 'email_message_box',
                    'type' => 'wysiwyg',
                    'default' => __('Email content goes in here. You may want to send yourself one to see how it looks.', 'inbound-pro'),
                    'class' => 'email_message_box',
                    'disable_variants' => '1',
                ),
                'premade_email_templates' => array(
                    'label' => __('Select a premade email', 'inbound-pro'),
                    'description' => __('Use this to select which premade email to use.', 'inbound-pro'),
                    'id' => 'premade_template_selector',
                    'type' => 'dropdown',
                    'default' => '0',
                    'class' => 'premade_template_selector',
                    'options' => $template_id_and_name,
                ),
                'email_variation' => array(
                    'label' => __('Select a varation', 'inbound-pro'),
                    'description' => __('Use this to select which variation of the premade email to use.', 'inbound-pro'),
                    'id' => 'email_variation_selector',
                    'type' => 'dropdown',
                    'default' => '0',
                    'class' => 'email_variation_selector',
                    'options' => array('0' => 'A'),
                ),
                'footer_address' => array(
                    'label' => __('Footer Address', 'inbound-pro'),
                    'description' => __('In order to be complaint with CAN-SPAM Act please enter a valid address.', 'inbound-pro'),
                    'id' => 'footer_address',
                    'type' => 'text',
                    'default' => '',
                    'class' => 'email_footer_address',
                ),
            ); ?>


            <div class="lead-profile-section" id="wpleads_lead_tab_direct_email">
                <?php
                Inbound_Mailer_Metaboxes::render_settings('inbound-email', $custom_fields, $post); ?>

                <button id="send-email-button" type="button" style="padding:15px;">
                    <?php _e('Send Email' , 'inbound-pro'); ?>
                    <i class="fa fa-envelope" aria-hidden="true"></i>
                </button>
            </div>
            </div>


            <script>
                jQuery(document).ready(function () {
                    var variationSettings;
                    console.log(variationSettings.post_id);

                    /*page load actions*/
                    jQuery('.premade_template_selector').css('display', 'none');
                    jQuery('.email_variation_selector').css('visibility', 'hidden');
                    jQuery('.inbound-tooltip').css('display', 'none');
                    jQuery('#footer_address').attr('placeholder', "<?php _e('In order to be complaint with CAN-SPAM Act please enter a valid address.', 'inbound-pro') ?>");
                    jQuery('.open-marketing-button-popup.inbound-marketing-button.button').css('display', 'none');
                    
                    jQuery('#premade_template_chooser').on('change', function () {
                        if (jQuery('#premade_template_chooser').val() == 1) {
                            jQuery('div.email_message_box.inbound-wysiwyg-row.div-email_message_box.inbound-email-option-row.inbound-meta-box-row').css('display', 'none');
                            jQuery('.premade_template_selector').css('display', 'block');
                            jQuery('.email_variation_selector').css('display', 'block');
                            jQuery('#footer_address').css('display', 'none');
                            jQuery('.direct_email_lead_field, .div-direct_email_lead_field').css('display', 'none');
                        } else {
                            jQuery('div.email_message_box.inbound-wysiwyg-row.div-email_message_box.inbound-email-option-row.inbound-meta-box-row').css('display', 'block');
                            jQuery('.premade_template_selector').css('display', 'none');
                            jQuery('#footer_address').css('display', 'block');
                            jQuery('.email_variation_selector').css('display', 'none');
                            jQuery('.direct_email_lead_field, .div-direct_email_lead_field').css('display', 'block');
                        }

                    });

                    jQuery('#premade_template_selector').on('change', function () {
                        var id = jQuery('#premade_template_selector').val();
                        console.log(id);
                        jQuery.ajax({
                            type: 'POST',
                            url: ajaxurl,
                            data: {
                                action: 'get_addressing_settings',
                                email_id: id,

                            },
                            success: function (response) {
                                response = JSON.parse(response);
                                //					console.log(response);
                                variationSettings = response.variations;
                                //					console.log(variationSettings);

                                jQuery('#email_variation_selector').find('option').remove();
                                if (variationSettings.length > 1) {
                                    var alphabetObject = {
                                        0: 'A', 1: 'B', 2: 'C', 3: 'D', 4: 'E',
                                        5: 'F', 6: 'G', 7: 'H', 8: 'I', 9: 'J',
                                        10: 'K', 11: 'L', 12: 'M', 13: 'N', 14: 'O',
                                        15: 'P', 16: 'Q', 17: 'R', 18: 'S', 19: 'T',
                                        20: 'U', 21: 'V', 22: 'W', 23: 'X', 24: 'Y',
                                        25: 'Z',
                                    }
                                    console.log(variationSettings.length);

                                    for (var index = 0; index < variationSettings.length; index++) {
                                        jQuery('#email_variation_selector').append('<option value="' + index + '">' + alphabetObject[index] + '</option>');
                                    }

                                    jQuery('.email_variation_selector').css('visibility', 'visible');

                                } else {
                                    jQuery('#email_variation_selector').append('<option value="0">A</option>');
                                    jQuery('.email_variation_selector').css('visibility', 'hidden');
                                }

                            },
                            error: function (MLHttpRequest, textStatus, errorThrown) {
                                alert("<?php _e('Ajax not enabled', 'inbound-pro'); ?>");
                            },
                        });

                    });

                    /*Send the email*/
                    jQuery('#send-email-button').on('click', function () {
                        var postId = <?php echo $post->ID; ?>;
                        var userId = <?php echo $user->ID; ?>;
                        var subject = jQuery('#subject').val();
                        var fromName = jQuery('#from_name').val();
                        var fromEmail = jQuery('#from_email').val();
                        var replyEmail = jQuery('#reply_email').val();
                        var emailContent = get_tinymce_content();
                        var recipientEmail = jQuery('#recipient_email_address').val();
                        var usePremadeTemplate = jQuery('#premade_template_chooser').val();
                        var isPremadeTemplate = jQuery('#premade_template_chooser').val();
                        var premadeEmailId = jQuery('#premade_template_selector').val();
                        var variationSelected = jQuery('#email_variation_selector').val();
                        var footerAddress = jQuery('#footer_address').val();

                        swal({
                            title: "<?php _e('Please wait', 'inbound-pro'); ?>",
                            text: "<?php _e('We are sending a your email now.', 'inbound-pro'); ?>",
                            imageUrl: '<?php echo INBOUND_EMAIL_URLPATH; ?>/assets/images/loading_colorful.gif'
                        });


                        jQuery.ajax({
                            type: 'POST',
                            url: ajaxurl,
                            data: {
                                action: 'send_email_to_lead',
                                post_id: postId,
                                user_id: userId,
                                subject: subject,
                                from_name: fromName,
                                from_email: fromEmail,
                                reply_email: replyEmail,
                                email_content: emailContent,
                                recipient_email: recipientEmail,
                                use_premade_template: usePremadeTemplate,
                                is_premade_template: isPremadeTemplate,
                                premade_email_id: premadeEmailId,
                                variation_selected: variationSelected,
                                footer_address: footerAddress,

                            },

                            success: function (response) {
                                response = JSON.parse(response);
                                console.log(response);

                                /**error check**/
                                /*if it's a basic error, like a field isn't filled in*/
                                if (response.basic_error) {
                                    swal({
                                        title: response.title,
                                        text: response.basic_error,
                                        type: 'error',
                                    });
                                    return false;
                                }
                                /*if it's a system error, like some data wasn't supplied*/
                                if (response.system_error) {
                                    swal({
                                        title: response.title,
                                        text: response.system_error,
                                        type: 'error',
                                    });
                                    return false;
                                }


                                /*...no errors? THEN SUCCESS!*/
                                if (response.success) {
                                    swal({
                                        title: response.title,
                                        text: response.success,
                                        type: 'success',
                                    });
                                    return false;
                                }

                                alert(response);
                                jQuery('.confirm').click();


                            },
                            error: function (MLHttpRequest, textStatus, errorThrown) {
                                alert("<?php _e('Ajax not enabled', 'inbound-pro'); ?>");
                            },


                        });
                    });


                    function get_tinymce_content() {
                        if (jQuery('#wp-inbound_email_message_box-wrap').hasClass('tmce-active')) {
                            return tinyMCE.activeEditor.getContent();
                        } else {
                            return jQuery('textarea.email_message_box').val();
                        }
                    }

                });
            </script>

            <?php
        }


        public static function get_addressing_settings() {
            if (isset($_POST['email_id']) && !empty($_POST['email_id'])) {

                $id = intval($_POST['email_id']);
                $inbound_email_meta = new Inbound_Email_Meta;
                $email_settings = $inbound_email_meta->get_settings($id);
                echo json_encode($email_settings);
            }
            die();

        }


        public static function send_email_to_lead() {
            $inbound_email_meta = new Inbound_Email_Meta;
            $inbound_mail_daemon = new Inbound_Mail_Daemon;

            /*if the email is a premade auto one, make sure the info is provided to send it and send it*/
            if ($_POST['use_premade_template'] == '1') {

                /*make sure the settings have been supplied*/
                if (empty($_POST['post_id']) && $_POST['post_id'] != '0') {
                    echo json_encode(array('system_error' => __('The lead id was not supplied', 'inbound-pro'), 'title' => __('System Error:', 'inbound-pro')));
                    die();
                }
                if (empty($_POST['recipient_email']) || !is_email($_POST['recipient_email'])) {
                    echo json_encode(array('basic_error' => __('There\'s an error with the recipient email', 'inbound-pro'), 'title' => __('Field Error:', 'inbound-pro')));
                    die();
                }
                if (empty($_POST['premade_email_id']) && $_POST['premade_email_id'] != '0') {
                    echo json_encode(array('system_error' => __('The email template id was not supplied', 'inbound-pro'), 'title' => __('System Error:', 'inbound-pro')));
                    die();
                }
                if (empty($_POST['variation_selected']) && $_POST['variation_selected'] != '0') {
                    echo json_encode(array('system_error' => __('The variation id was not supplied', 'inbound-pro'), 'title' => __('System Error:', 'inbound-pro')));
                    die();
                }

                $post_id = intval($_POST['post_id']);
                $recipient_email = sanitize_text_field($_POST['recipient_email']);
                $premade_email_id = intval($_POST['premade_email_id']);
                $variation_selected = intval($_POST['variation_selected']);

                /*sending args*/
                $args = array('email_address' => $recipient_email,
                    'email_id' => $premade_email_id,
                    'vid' => $variation_selected,
                    'lead_id' => $post_id,
                    'is_test' => 0);

                /*send the email!*/
                $inbound_mail_daemon::send_solo_email($args);
                echo json_encode(array('success' => __('Your email has been sent!', 'inbound-pro'), 'title' => __('SUCCESS!', 'inbound-pro')));
                die();
            }


            /***if the email is a custom one, create a new custom one***/


            /*these are the variables set by the user*/
            $user_filled_vars = array(
                __('Subject', 'inbound-pro') => $_POST['subject'],
                __('From Name', 'inbound-pro') => $_POST['from_name'],
                __('From Email', 'inbound-pro') => $_POST['from_email'],
                __('Reply Email', 'inbound-pro') => $_POST['reply_email'],
                __('Recipient Email', 'inbound-pro') => $_POST['recipient_email'],
                __('Email Content', 'inbound-pro') => $_POST['email_content'],
                __('Footer Address', 'inbound-pro') => $_POST['footer_address'],);

            /*check to make sure the variables are set*/
            foreach ($user_filled_vars as $key => $value) {
                if (empty($value)) {
                    echo json_encode(array('basic_error' => __('Please fill in the ' . $key, 'inbound-pro'), 'title' => __('Empty field', 'inbound-pro')));
                    die();
                }

            }

            /*check to make sure the email addresses are setup correctly*/
            if (!is_email($_POST['recipient_email'])) {
                echo json_encode(array('basic_error' => __('There\'s an error with the Recipient Email Address', 'inbound-pro'), 'title' => __('Field Error:', 'inbound-pro')));
                die();
            }

            if (!is_email($_POST['from_email'])) {
                echo json_encode(array('basic_error' => __('There\'s an error with the From Email', 'inbound-pro'), 'title' => __('Field Error:', 'inbound-pro')));
                die();
            }

            if (!is_email($_POST['reply_email'])) {
                echo json_encode(array('basic_error' => __('There\'s an error with the Reply Email', 'inbound-pro'), 'title' => __('Field Error:', 'inbound-pro')));
                die();
            }

            /*check to make sure the post and user ids have been supplied*/
            if (empty($_POST['post_id'])) {
                echo json_encode(array('system_error' => __('The post id was not supplied', 'inbound-pro'), 'title' => __('System Error:', 'inbound-pro')));
                die();
            }
            if (empty($_POST['user_id'])) {
                echo json_encode(array('system_error' => __('The user id was not supplied', 'inbound-pro'), 'title' => __('System Error:', 'inbound-pro')));
                die();
            }


            /*set the variables*/
            $post_id = intval($_POST['post_id']); //$post_id is also used as the lead id
            $user_id = intval($_POST['user_id']); //$user_id is the id of the wp user who's sending the email
            $subject = sanitize_text_field($_POST['subject']);
            $from_name = sanitize_text_field($_POST['from_name']);
            $from_email = sanitize_text_field($_POST['from_email']);
            $reply_email = sanitize_text_field($_POST['reply_email']);
            $recipient_email = sanitize_text_field($_POST['recipient_email']);
            $email_content = $_POST['email_content'];
            $footer_address = sanitize_text_field($_POST['footer_address']);


            /*get the current time according to the wp format*/
            $time = new DateTime('', new DateTimeZone(get_option('timezone_string')));
            $format = get_option('date_format') . ' \a\t ' . get_option('time_format');


            /*assemble the post data*/
            $direct_email = array(
                'post_title' => __('Direct email to ', 'inbound-pro') . $recipient_email . __(' on ', 'inbound-pro') . $time->format($format),
                'post_content' => '',
                'post_status' => 'direct_email',
                'post_author' => $user_id,
                'post_type' => 'inbound-email',
            );

            /*create the email*/
            $direct_email_id = wp_insert_post($direct_email);

            /*add the settings needed for the email to render*/
            $the_meta_to_add = array(
                //Use the template config.php field "names" for the keys
                'logo' => '',
                'logo_positioning' => '',
                'logo_url' => '',
                'email_font' => 'serif',
                'headline' => '',  /*$subject*/
                'headline_size' => '24px',
                'sub_headline_size' => '',
                'sub_headline' => '',
                'featured_image' => '',
                'image_width' => '',
                'image_height' => '',
                'message_content' => $email_content,
                'align_message_content' => '',
                'footer_address' => $footer_address,
                'contrast_background_color' => '#e8e8e8',
                'content_background_color' => '#ffffff',
                'content_color' => '#000000',
                'show_email_content_border' => '',
                'hide_show_email_in_browser' => '',
            );

            foreach ($the_meta_to_add as $key => $value) {
                update_post_meta($direct_email_id, $key, $value);
            }


            /*assemble the settings inbound-mailer uses for sending the email*/
            $mailer_settings = array(
                'variations' => array(
                    0 => array(
                        'selected_template' => 'inboundnow',
                        'user_ID' => $user_id,
                        'subject' => $subject,
                        'from_name' => $from_name,
                        'from_email' => $from_email,
                        'reply_email' => $reply_email,
                        'variation_status' => 'active',
                    ),
                ),
                'email_type' => 'automated',
            );

            /*add the settings to the email*/
            $inbound_email_meta::update_settings($direct_email_id, $mailer_settings);

            /*sending args*/
            $args = array(
                'email_address' => $recipient_email,
                'email_id' => $direct_email_id,
                'vid' => 0,
                'lead_id' => $post_id,
                'is_test' => 0,
            );


            /*and send*/
            $inbound_mail_daemon::send_solo_email($args);
            //		wp_delete_post($direct_email_id, true); //uncomment to stop saving direct emails
            echo json_encode(array('success' => __('Your custom email has been sent!', 'inbound-pro'), 'title' => __('SUCCESS!', 'inbound-pro')));

            //		echo json_encode(error_get_last()); //debug
            die();

        }


    }


    /**
     *    Only load Inbound_Mailer_Direct_Email_Leads if an email service provider has been selected
     */
    function confirm_email_service_provider() {
        $Inbound_Mailer_Settings = new Inbound_Mailer_Settings;
        $email_settings = $Inbound_Mailer_Settings::get_settings();
        if ($email_settings['mail-service'] != 'none') {

            new Inbound_Mailer_Direct_Email_Leads;
        }
    }

    add_action('admin_init', 'confirm_email_service_provider');


}


?>
