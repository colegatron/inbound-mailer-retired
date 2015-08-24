<?php
/**
* Template Name: Newsletter Image Right
* @package  Inbound Email
* @author   Inbound Now
*/

/* Declare Template Key */
$key = basename(dirname(__FILE__));

/* do global action */
do_action('inbound_mail_header');

/* Load post */
if (have_posts()) : while (have_posts()) : the_post();

/* Header */
$post_id		  = get_the_ID();
$logo_url		  = get_field('header_logo', $post_id);
$header_bg_color  = get_field('header_bg_color', $post_id);
$header_bg_image  = get_field('header_bg_image', $post_id);
$issue_title	  = get_field('issue_title', $post_id);
$issue_date		  = get_field('issue_date', $post_id);
$title_date_color = get_field('title_date_color', $post_id);
$home_page_url	  = get_field('home_page_url', $post_id);

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>

	<meta name="viewport" content="width=device-width,initial-scale=1"/>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
	<title>Newsletter image right</title>

<style type='css/text'>
  .reverse-row .text-wrap{ float:right; width:512px; }
  .reverse-row .img-wrap{ float:left !important; margin-left:0 !important; margin-right:30px; }
  .reverse-row .pcont-text { margin-right:0 !important;}
  
  @media screen and (max-width: 480px), only screen and (device-aspect-ratio: 40/71), only screen and (device-aspect-ratio: 2/3)
    
   {
      .nl-top-cell-with-bg { background-position:-300px center; }
      .prev-logo-cell { display:none;} 
      #wdhd-table { margin-bottom:20px; margin-top:0;}  
      
      .img-wrap { float:none !important; margin:0 0 10px !important; text-align:center; display:block;}
      .img-wrap a { display:inline-block; width:100%;}
      
    .gap-cell { width:14px !important;}
    .post-cell { background:#fff; padding:19px 19px 10px; border:1px solid #ccc;
    border-radius:6px;
    -webkit-border-radius:6px;
    display:block; clear:both;
    }
    .post-cell h1 { font-size:20px !important; line-height:25px !important;}
    .post-cell p { font-size:14px; line-height:21px; }
    .post-cell img{ width:100%; max-width:100%; height:auto;}
    
    .pcont-text { margin-right:0px !important;}
    
    .top-unsub-txt,
    .hhh-cell, .shd-row , .shd-cell, .sep-row{ display:none;}
    .hhs-block { width:100% !important;}
   
      .nl-issue-row table, .nl-issue-row td{ width:100% !important; background:inherit;}
      .issue-cell span { font-weight:bold; font-size:14px !important; padding-right:10px; }
    
    .hh-table, .content-table { width:100% !important; max-width:100%;}
    .content-table { background:#dfdfdf;}

    .art-img-row { display:none;}
    .art-img-row td{ height:auto;}
    .art-img-row img { width:100% !important; height:auto !important;}
    .mid-row { height:14px !important;}

    .fb-img-lnk img, .rss-img-lnk img, .tw-img-lnk img{display:none;}
   
    
    .footer-row br { display:none;}
    .footer-row * { font-weight:normal !important;}
    .footer-row *, .footer-row td{ font-size:4px !important; }
    .text-chunk-hide { display:none;}
    .view-online-row { display:none;}
    .hc-table { margin-top:20px;}
    .footer-support-row { display:none; }
    
    table  {max-width:100%; width:100%;}  
    

  }
</style>
	
</head>

<body style="padding:0; margin:0;">

<table style="width:100%;float:left;background-color:#dfdfdf;font-family:Arial, sans serif;">
  
	<tr>                            
		<td align="center">
      
			<table cellspacing="0" cellpadding="0" border="0">

				<tr class="view-online-row">    
					<td>      
						<table width="100%">
							<tr>
								<td width="100px"></td>
								<td class="viewWebsite" align="center" height="60px" valign="middle">
									<p style="font-family: Arial, Helvetica, sans-serif; color: #555555; font-size: 10px; padding: 0; margin: 0;">Trouble viewing? Read this <a href="<?php echo get_permalink( $post_id ); ?>" style="color: #990000;" class='do-not-tracks'><?php _e('online' , 'inbound-email' ); ?></a>.</p>
								</td>
								<td align="right" height="60px" valign="middle" width="100px"></td>
							</tr>
						</table>
					</td>
				</tr>

				<tr>
					<td  width="730" class="hh-table">
						<!-- header-content table -->
						<table style="width:730px; border:0;" class="hh-table hc-table" id="wdhd-table" cellspacing="0" cellpadding="0" >

							<tr class="nl-img-row">
								<td class="nl-top-cell-with-bg" style="background-color:<?php echo $header_bg_color ?>; background-image:url(<?php echo $title_date_color ?>); height:100px; vertical-align:middle;">

									<table style="width:100%; vertical-align:middle;" cellspacing="0" cellpadding="0">
										<tr>
											<td width="2%" class="hhh-cell"></td>

											<td class="hhh-cell" width="30%" align="left" style="white-space:nowrap;font-size:13px; font-weight:bold;font-family:Arial, sans serif;color:<?php echo $title_date_color ?>; ">
												<b><span><?php echo $issue_title ?></span></b> 
											</td>
											<td width="10"></td>

											<td style="width:30%; margin:auto" class="prev-logo-cell">
												<?php if ($logo_url) { ?>
													<div style="margin:auto;width:200px">
														<a target="_blank" style="display:inline-block;" href="<?php  echo $home_page_url; ?>">
														<img style="border:0;width:200px" alt="" src="<?php  echo $logo_url; ?>"/>
														</a>
													</div>
												<?php } ?>
											</td>

											<td class="issue-cell" align="right"  style="color:<?php echo $title_date_color ?>; white-space:nowrap; font-size:13px; font-weight:bold;font-family:Arial, sans serif;">
												<strong><?php echo $issue_date ?></strong>
											</td>

											<td width="2%"></td>

										</tr>
									</table>

								</td>
							</tr>

						</table>            <!-- content -table -->
						<table width="730" class="content-table" cellspacing="0" cellpadding="0" bgcolor="#ffffff">  

						<!-- top gap -->
							<tr class="sep-row"><td height="18"></td></tr>
						<!-- entry content cell -->
							<tr>
							  <td>

								<table width="100%" cellspacing="0" cellpadding="0" >
								  <tr>

									<td width="30" class="gap-cell"></td>


									<td class="post-cell" valign="top" style="color:#333333; font-size:14px; line-height:20px;font-family:Arial, sans serif;">
										<?php
										if ( function_exists('have_rows') ) {
											if (have_rows('news_line')) {
												while ( have_rows('news_line')) {
													the_row();

													switch( get_row_layout() ) {
														case 'news_line':
															$news_title = get_sub_field('news_title');
															$news_url = get_sub_field('news_url');
															$news_excerpt = get_sub_field('news_excerpt');
															$featured_image = get_sub_field('featured_image');
															$featured_image_url = get_sub_field('featured_image_url');
															?>
															<p><?php if ( $hero_image_url = get_sub_field('hero_image_url') ) { ?>
															<img alt="" src="<?php  echo $hero_image_url; ?>" width="600" height="300"/>
															<?php } ?></p><!-- /hero -->
															<?php 
															?>
															<?php 
															echo $main_email_content; 
															$button_link = get_sub_field('button_link'); 
															$button_text = get_sub_field('button_text');
															$style = 'color: ';
															if ( $button_text_color = get_sub_field('button_text_color') ) {
																$style .= $button_text_color[1] . ';';
															} else { $style .= '#fff;'; }
															$style .= 'background-color: ';
															if ( $button_bg_color = get_sub_field('button_bg_color') ) {
																$style .= $button_bg_color[1] . ';';
															} else { $style .= '#666;'; }
															?>
															<a href="<?php echo $button_link; ?>" style="<?php echo $style; ?>
																 margin: 0;padding: 10px 16px;font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;text-decoration: none;font-weight: bold;margin-right: 10px;text-align: center;cursor: pointer;display: inline-block;" class="btn"><?php echo $button_text; ?></a>
															<?php
															break;
													}
												}
											}

											if(!have_rows('email_hero_box')) {
												echo '<div class="container">';
												the_content();
												echo "</div>";
											}
										}
										?>
										<div class="img-wrap" style="float:right; margin-left:30px;">
											<a target="_blank" href="http://bigstock.7eer.net/c/165264/221496/1736">
												<img style="margin-top:4px;" width="150" border="0" height="150" alt="" align="right" src="http://netdna.webdesignerdepot.com/uploads/2015/08/bigstock-aug20.jpg" />
											</a>

										</div>
										<h1 style='font-family:"HelveticaNeueBold", "HelveticaNeue-Bold", "Helvetica Neue Bold", helvetica, arial, sans serif;color:#201f1f; font-size:22px; font-weight:bold; line-height:25px; margin:0 0 -10px 0;'>25% off Images from Bigstock</h1>
										<div style="margin-right:140px;" class="pcont-text">
										<p><a target="_blank" style="color:#DD4D42; text-decoration:none; font-weight:bold;" href="http://bigstock.7eer.net/c/165264/221496/1736">Bigstock</a> is a royalty-free marketplace with millions of photos, illustrations, icons, and vectors. Their collection of high-quality design assets comes from artists and photographers around the world. They're currently offering 25% off any credit pack, so <a target="_blank" style="color:#DD4D42; text-decoration:none; font-weight:bold;" href="http://bigstock.7eer.net/c/165264/221496/1736">join Bigstock today</a> and get the design assets you need for your latest projects. <span style="color: #cfcfcf;"><strong>- AD</strong></span></p>
										</div>
									</td>

									<td width="30" class="gap-cell"></td>

								  </tr>
								</table>

							  </td>
							</tr>

							<!-- bottom gap -->
							<tr class="mid-row"><td height="18"></td></tr>

							<!-- separator -->
							<tr class="sep-row">
							  <td>
								<table width="100%" cellspacing="0" cellpadding="0">
								  <tr>

								   <td bgcolor="#eeeeee" height="1" ></td>

								  </tr>
								</table>

							  </td>
							</tr>

							<tr class="sep-row"><td height="30"></td></tr>
							<tr>
								<td>
									<table width="100%" cellpadding="10" cellspacing="0">
										<tr>
											<td width="10"></td>
											<td bgcolor="#efefef" align="center" style="font-size:15px; border:1px solid #ddd; color:#000; font-weight:bold; font-style:italic;">Want more cool news? <a style="color:#DD4D42; text-decoration:none; font-weight:bold;" href="http://www.webdesignernews.com/subscribe-email?email=*|EMAIL|*&amp;ref=wddnl186&amp;rd=1">Click here</a> to automatically subscribe to WebdesignerNews...</td>
											<td width="10"></td>
										</tr>
									</table>
								</td>
							</tr>
							<tr class="sep-row"><td height="60"></td></tr>


							<tr class="shd-row" bgcolor="#dfdfdf"><td height="18"></td></tr>


							<tr class="footer-row" bgcolor="#dfdfdf">
							  <td align="center" style="color:#666; font-size:12px; font-weight:bold;font-family:Arial, sans serif;">   
								<a target="_blank" style="color:#666;text-decoration:none;" href="http://www.webdesignerdepot.com">Go to WebdesignerDepot.com</a>&nbsp; | &nbsp;<a target="_blank" style="color:#666;text-decoration:none;" href="http://www.webdesignerdepot.com/about/">About</a> &nbsp;| &nbsp;<a target="_blank" style="color:#666;text-decoration:none;" href="http://www.webdesignerdepot.com/disclaimer-and-privacy-policy/">Disclaimer</a> &nbsp;|&nbsp; <a target="_blank" style="color:#666;text-decoration:none;" href="mailto:advertising@webdesignerdepot.com">Advertising</a>&nbsp; | &nbsp;<a target="_blank" style="color:#666;text-decoration:none;" href="http://www.mightydeals.com/all_deals?ref=wddnlbtm">Deals</a> &nbsp;| &nbsp;<a target="_blank" style="color:#666;text-decoration:none;" href="mailto:info@webdesignerdepot.com">Contact</a>

							  </td>
							</tr>

							 <tr bgcolor="#dfdfdf"><td height="8"></td></tr>
							<tr class="footer-row" bgcolor="#dfdfdf">
							  <td align="center" style="color:#666; font-size:12px;font-family:Arial, sans serif;">
								  Has this newsletter been forwarded to you? <a target="_blank" style="color:#666; text-decoration:underline; font-weight:bold; text-decoration:none;" href="http://www.webdesignerdepot.com/newsletter/">Subscribe to the WDD newsletter</a> 
							  </td>
							</tr>

							<tr bgcolor="#dfdfdf"><td height="8"></td></tr>

							<tr class="footer-row"  bgcolor="#dfdfdf">
							  <td align="center" style="font-family:Arial, sans serif; font-size:12px; font-weight:bold; color:#666;">
								<a class="fb-img-lnk" style="color:#666;text-decoration:none;" target="_blank" href="http://www.facebook.com/WebdesignerDepot">Find us on Facebook</a>
								&nbsp; | &nbsp;
								<a class="tw-img-lnk" style="color:#666;text-decoration:none;" target="_blank" href="https://twitter.com/DesignerDepot">Follow us on Twitter</a>
								&nbsp; | &nbsp;
								<a class="rss-img-lnk" style="color:#666;text-decoration:none;" target="_blank" href="http://feeds2.feedburner.com/webdesignerdepot?format=html">RSS Feed</a>
							  </td>
							</tr>

						</table> <!-- end of content table -->          
					</td>

				</tr>

			</table>
    
		</td> <!-- main inner cell -->
	</tr>
  
	<tr class="shd-row"><td height="30"></td></tr>
  
</table>

<center>
<br/>
<br/>
<br/>
<br/>
<br/>
<br/>

</center>

</body>
</html>

<?php

endwhile; endif;