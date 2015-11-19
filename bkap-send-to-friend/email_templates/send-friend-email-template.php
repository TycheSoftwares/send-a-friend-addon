<?php
/**
 * Send friend request to purchase Bookings
 *
 * @author 		TycheSoftwares
 * @package 	wapbk-send-to-friend/
 * @version     1.0
 */ 

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<p>{{client_name}}<?php _e( " has just made a booking via ", 'bkap-send-to-friend' );?>{{site_name}}<?php _e( " and wanted to invite you to join in with him/her.", 'bkap-send-to-friend' ); ?></p>

<p><?php _e( "They've booked the following products:", 'bkap-send-to-friend' ); ?></p>

<p>{{product_list}}</p>

<p>{{personalized_message}}</p>

<p><?php _e( "Click the links to book the products for yourself too.", 'bkap-send-to-friend' );?></p>

<p><?php _e( "Thanks,", 'bkap-send-to-friend' );?>
<br>
{{site_name}}</p>