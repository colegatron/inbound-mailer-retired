<?php
/**
* Template Name: Gallery
* @package	Inbound Email
* 
*/

$key = basename(dirname(__FILE__));


/* Configures Template Information */
$inbound_email_data[$key]['info'] = array(
	'data_type' =>'email-template',
	'label' => __( 'Gallery' , 'inbound-mailer') ,
	'category' => 'responsive',
	'demo' => '',
	'description' => __( 'Gallery email template' , 'inbound-mailer' ),
	'acf' => true
);

/*
* Define ACF Fields to be used in this template
* Pay special attention to the 'location' key as this is where we tell ACF to load when this template is selected
*/