<?php
/**
 * This is the original template. It has been left here so that it can be modified in the future.
 * The html of this file must be passed through an inliner in order to work correctly.
 * Having the css with the html in the same file is not enough because there are email clients that strip the css code out.
 * The html of this template has been passed through http://templates.mailchimp.com/resources/inline-css/
 * After the html is passed through the inliner it's necessary to check and fix the php inside the html because the inliner turns '<' and '>' characters in their html entities
 * The result is the index.php file that produces the actual email code.
 * 
 * Template Name: Gallery
 * @package  Inbound Email
 * @author   Inbound Now
*/

/* Declare Template Key */
$key = basename(dirname(__FILE__));

/* do global action */
do_action('inbound_mail_header');

/* Load post */
if (have_posts()) : while (have_posts()) : the_post();

/* Main content */
$post_id		 = get_the_ID();
$logo_url		 = get_field('logo_url', $post_id);
$header_bg_color = get_field('header_bg_color', $post_id);
$callout_text	 = get_field('callout_text', $post_id);

/* Footer */
$footer_bg_color   = get_field('footer_bg_color', $post_id);
$facebook_page_url = get_field('facebook_page', $post_id);
$twitter_handle	   = get_field('twitter_handle', $post_id);
$google_plus_url   = get_field('google_plus', $post_id);
$phone_number	   = get_field('phone_number', $post_id);
$email			   = get_field('email', $post_id);
$terms_page_url    = get_field('terms_page_url', $post_id);
$privacy_page_url  = get_field('privacy_page_url', $post_id);

?>


<?php

endwhile; endif;