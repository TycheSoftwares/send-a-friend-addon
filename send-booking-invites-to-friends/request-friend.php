<?php
// by default we show the tell a friend div
$show_tell_friend = 'block';
// by default we hide the Thank You div
$show_another_friend = 'none';

/* Create an object for the session handler class
 This needs to be done as the session is destroyed for a guest user on the Thank You page
 Without the session, the notices cannot be displayed, hence we need to recreate and destroy the session for guest users*/
$session_obj = new WC_Session_Handler();

// create an array of all the notices in the session
$all_notices = WC()->session->get( 'wc_notices', array() );
foreach( $all_notices as $key => $value ) {
    $success_message = __( 'Email sent successfully.', 'bkap-send-to-friend' );
	if ( 'success' == $key && $value[0] == $success_message ) {
		$show_tell_friend = 'none';
		$show_another_friend = 'block';
		// if it's a guest user
		if ( ! is_user_logged_in() ) {
		    // destroy the session once the email is sent
		    $session_obj->destroy_session();
		}
	}
}
wc_print_notices();

// check if a session exists
$session_status = $session_obj->has_session();
if ( isset( $session_status ) && true == $session_status ) {
} else {
    // if not, create one, so the notices can be added and displayed
    $session_obj->set_customer_session_cookie( true );
}
?>

<div id="content" class="col-full">
<br>
<div id="tell_a_friend" style="display:<?php echo $show_tell_friend; ?>">
	<p>
		<?php _e( 'Enter the details of your friends below and we\'ll email and invite them to join you on your booked days below.', 'bkap-send-to-friend' );?>
	</p>
	<br>
	<?php 
	$order = new WC_Order( $_GET[ 'order_id' ] );
	$items = $order->get_items();
	$product_list = '';

	foreach ( $items as $key => $value ) {
		$product_id = $value[ 'product_id' ];
		// Booking Settings
		$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
		if ( isset( $booking_settings[ 'booking_enable_date' ] ) && 'on' == $booking_settings[ 'booking_enable_date' ] ) {
			// Get the date and time slot label
			$booking_date = $value[ 'wapbk_booking_date' ];
			$checkout_date = '';
			$booking_time = '';
			if ( isset( $value[ 'wapbk_checkout_date' ] ) ) {
				$checkout_date = $value[ 'wapbk_checkout_date' ];
			}
			if ( isset( $value[ 'wapbk_time_slot' ] ) ) {
				$booking_time = $value[ 'wapbk_time_slot' ];
			}
			// If WPML is enabled, the make sure that the base language product ID is used to calculate the availability
			if ( function_exists( 'icl_object_id' ) ) {
			    global $sitepress;
			    $default_lang = $sitepress->get_default_language();
			    $base_product_id = icl_object_id( $product_id, 'product', false, $default_lang );
			} else {
			    $base_product_id = $product_id;
			}
			// Get the availability for the product
			$availability = send_to_friend::get_availability( $base_product_id, $booking_date, $checkout_date, $booking_time );
			$display = 'NO';
			if ( $availability > 0 ) {
				$display = 'YES';
			} else if ( "Unlimited" === $availability ) {
				$display = 'YES';
			}
			if ( "YES" == $display ) {
			    // Add the product to the list of selected products as the checkoboxes are selected by default
			    $product_list .= $product_id . ',';
				echo '<input class="bkap_product_select" type="checkbox" id="product_' . $value[ "product_id" ] . '" name="product_' . $value[ "product_id" ] . '" checked onClick="add_product(' . $value[ "product_id" ] . ')">';
				echo '&nbsp;&nbsp;<a href="' . get_permalink( $value[ "product_id" ]) . '">' . $value[ "name" ] . '</a><br>';
			}
		}
	}
	?>
	<input type="hidden" id="selected_products" name="selected_products" value="<?php echo $product_list; ?>" />
	
	<script type="text/javascript">
	function add_product( product_id ) {
		var field_name = 'product_' + product_id;
		if ( jQuery( "input[name="+field_name+"]" ).attr( "checked" ) ) {
			var already_selected = jQuery( "#selected_products" ).val();
			already_selected += product_id + ",";
			jQuery( "#selected_products" ).val( already_selected );
		} else {
			var first_set = ''
			var already_selected = jQuery( "#selected_products" ).val();
			var start_pos = already_selected.indexOf( product_id );
			if ( start_pos > 0 ) {
				first_set = already_selected.substr( 0, start_pos );
			}
			//get the second half
			var remaining_str = already_selected.substr( start_pos );
			var separator_pos = remaining_str.indexOf( ',' );
			var second_set = remaining_str.substr( separator_pos + 1 );
			var final_list = first_set + second_set;
			jQuery( "#selected_products" ).val( final_list );
		}
		if ( '' == jQuery( "#selected_products" ).val() ) {
			jQuery( '#send_friend' ).prop( 'disabled', true ).css( 'cursor', 'default' );
		} else {
			jQuery( '#send_friend' ).prop( 'disabled', false ).css( 'cursor', 'pointer' );
		}
	}
	</script>
	
	<br>
	<table style="width:100%;max-width:750px;">
		<tr>
			<th style="vertical-align:top;">
				<label for="client_name"><?php _e( 'Your name', 'bkap-send-to-friend' ); ?></label>
			</th>
			<td>
			<?php
			$billing_first_name = ( version_compare( WOOCOMMERCE_VERSION, "3.0.0" ) < 0 ) ? $order->billing_first_name : $order->get_billing_first_name();
			$billing_last_name = ( version_compare( WOOCOMMERCE_VERSION, "3.0.0" ) < 0 ) ? $order->billing_last_name : $order->get_billing_last_name(); 
			?>
				<input type="text" style="width:100%;max-width:400px;" name="client_name" id="client_name" value="<?php echo $billing_first_name . " " . $billing_last_name; ?>">
			</td>
		</tr>
		<tr>
			<th style="vertical-align:top;">
				<label for="client_email"><?php _e( 'Your email', 'bkap-send-to-friend' ); ?></label>
			</th>
			<td>
			<?php 
			$billing_email = ( version_compare( WOOCOMMERCE_VERSION, "3.0.0" ) < 0 ) ? $order->billing_email : $order->get_billing_email();?>
				<input type="text" style="width:100%;max-width:400px;" name="client_email" id="client_email" value="<?php echo $billing_email; ?>">
			</td>
		</tr>
		<tr>
			<th style="vertical-align:top;">
				<label for="friend_email"><?php _e( 'Their email', 'bkap-send-to-friend' );?></label>
			</th>
			<td>
				<input type="text" style="width:100%;max-width:400px;" name="friend_email" id="friend_email" >
				<br>
				<?php _e( '(If more than one, separate using commas)', 'bkap-send-to-friend' ); ?>
			</td>
		</tr>
		<tr>
			<th style="vertical-align:top;">
				<label for="email_message"><?php _e( 'Personalized Message (optional)', 'bkap-send-to-friend' ); ?></label>
			</th>
			<td>
				<textarea style="width:100%;max-width:400px;" name="email_message" id="email_message"></textarea>
			</td>
		</tr>
	</table>
	<input type="button" class="button" id="send_friend" name="send_friend" value="<?php _e( 'SEND TO A FRIEND', 'bkap-send-to-friend' ); ?>" onclick="bkap_send_email(<?php echo $_GET['order_id'];?>)" />
	 
	<script type="text/javascript">
		function bkap_send_email( id ) {
			var data = {
				order_id: id,
				details: jQuery( '#selected_products' ).val(),
				client_name: jQuery( '#client_name' ).val(),
				client_email: jQuery( '#client_email' ).val(),
				friend_email: jQuery( '#friend_email' ).val(),
				msg_txt: jQuery( '#email_message' ).val(),
				action: 'bkap_send_email_to_friend'
			};
			jQuery.post( '<?php echo get_admin_url(); ?>admin-ajax.php', data, function( response ) {
				window.location.replace(response);
			});
		}
	</script>
	<p></p>
</div>
<div id="tell_another_friend" style="display:<?php echo $show_another_friend; ?>">
	<p>
		<?php _e( 'Thank you for sharing with a friend.', 'bkap-send-to-friend' ); ?> 
	</p>
	<p>
		<?php _e( 'They have been notified via email.', 'bkap-send-to-friend' ); ?>
	</p>
	<br>
	<?php 
	$url = '';
	$permalink_structure = get_option( 'permalink_structure' );
	$current_time = current_time( 'timestamp' );
	$year = date( 'Y', $current_time );
	$month = date( 'm', $current_time );
	$day = date( 'd', $current_time );
	$tell_friend_page_url = get_option( 'bkap_friend_tell_friend_page_url' );
	if( ( isset( $tell_friend_page_url ) && '' == $tell_friend_page_url ) || ! isset( $tell_friend_page_url ) ) {
	    $tell_friend_page_url = 'send-booking-to-friend';
	}
	if ( function_exists('icl_object_id') ) {
	    $url = apply_filters( 'wpml_home_url', home_url() );
	} else {
	    $url = home_url( '/' );
	}
	switch ( $permalink_structure ) {
	    case '/%year%/%monthnum%/%day%/%postname%/':
	        $url .= $year . '/' . $month . '/' . $day . '/' . $tell_friend_page_url . '/';
	        break;
	    case '/%year%/%monthnum%/%postname%/':
	        $url .= $year . '/' . $month . '/' . $tell_friend_page_url . '/';
	        break;
	    case '/%postname%/':
	        $url .= $tell_friend_page_url . '/';
	        break;
	    default:
	        $custom_link = trim( $permalink_structure );
	        $last_char = substr( $custom_link, -1 );
	        $url .= $permalink_structure;
	
	        if ( '/' == $last_char ) {
	            $url .= $tell_friend_page_url . '/';
	        } else {
	            $url .= '/' . $tell_friend_page_url . '/';
	        }
	
	        break;
	
	}
	?>
	<input type="button" class="button" id="send_another_friend" name="send_another_friend" value="<?php _e( 'SEND TO ANOTHER FRIEND', 'bkap-send-to-friend' ); ?>" onclick="window.location.replace('<?php echo esc_url_raw( add_query_arg( 'order_id', $_GET['order_id'], $url ) ); ?>');" />
	<br><br>
	<input type="button" class="button" id="return_shop" name="return_shop" value="<?php _e( 'RETURN TO SHOP', 'bkap-send-to-friend' ); ?>" onclick="window.location.replace('<?php echo esc_url( add_query_arg('post_type','product',home_url( '/' )));?>');" />
	<p></p>
</div>
</div>