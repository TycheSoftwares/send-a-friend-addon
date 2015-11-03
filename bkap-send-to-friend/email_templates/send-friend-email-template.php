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
<p><?php _e( "<client_name> has just made a booking via <site_name> and wanted to invite you to join in with him/her.", 'woocommerce-booking' ); ?></p>

<p><?php _e( "They've booked the following products:", 'woocommerce-booking' ); ?></p>

<p><?php _e("<product_list>",'woocommerce-booking');?></p>

<p><?php _e("<personalized_message>",'woocommerce-booking');?></p>

<p><?php _e("Click the links to book the products for yourself too.",'woocommerce-booking');?></p>

<p><?php _e("Thanks mate,",'woocommerce-booking');?>
<br>
<?php _e("<site_name>",'woocommerce-booking');?></p>