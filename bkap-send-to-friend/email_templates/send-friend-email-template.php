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
<p>{{client_name}}<?php _e( " has just made a booking via ", 'woocommerce-booking' );?>{{site_name}}<?php _e(" and wanted to invite you to join in with him/her.", 'woocommerce-booking' ); ?></p>

<p><?php _e( "They've booked the following products:", 'woocommerce-booking' ); ?></p>

<p>{{product_list}}</p>

<p>{{personalized_message}}</p>

<p><?php _e("Click the links to book the products for yourself too.",'woocommerce-booking');?></p>

<p><?php _e("Thanks,",'woocommerce-booking');?>
<br>
{{site_name}}</p>