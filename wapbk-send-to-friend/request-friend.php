<?php
get_header();
?>
<div id="content" class="col-full">
<h1>Tell a friend</h1>
<body>
<br>
<div id="tell_a_friend">
	<p>
		Enter the details of your friends below and we'll email them (copying you in) inviting them to join you on your volunteering days below.
	</p>
	<br>
	<?php 
	$order = new WC_Order( $_GET['order_id'] );
	$items = $order->get_items();
	$product_list = '';

	foreach ( $items as $key => $value ) {
		$product_id = $value['product_id'];
		// Booking Settings
		$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
		if ( isset( $booking_settings ) && $booking_settings['booking_enable_date'] == 'on' ) {
			// Add the product to the list of selected products as the checkoboxes are selected by default
			$product_list .= $product_id . ',';
			// Get the date and time slot label
			$booking_date = $value['wapbk_booking_date'];
			$checkout_date = $booking_time = '';
			if ( isset( $value['wapbk_checkout_date'] ) ) {
				$checkout_date = $value['wapbk_checkout_date'];
			}
			if ( isset( $value['wapbk_time_slot'] ) ) {
				$booking_time = $value['wapbk_time_slot'];
			}
			// Get the availability for the product
			$availability = send_to_friend::get_availability($product_id, $booking_date, $checkout_date, $booking_time );
			$display = 'NO';
			if ( $availability > 0 ) {
				$display = 'YES';
			}
			else if ( $availability === "Unlimited" ) {
				$display = 'YES';
			}
			if ( $display == "YES" ) {
				echo '<input class="bkap_product_select" type="checkbox" id="product_' . $value["product_id"] . '" name="product_' . $value["product_id"] . '" checked onClick="add_product(' . $value["product_id"] . ')">';
				echo '&nbsp;&nbsp;<a href="' . get_permalink( $value["product_id"]) . '">' . $value["name"] . '</a><br>';
			}
		}
	}
	?>
	<input type="hidden" id="selected_products" name="selected_products" value="<?php echo $product_list; ?>" />
	
	<script type="text/javascript">
	function add_product( product_id ) {
		var field_name = 'product_' + product_id;
		if ( jQuery( "input[name="+field_name+"]").attr( "checked" ) ) {
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
			var seperator_pos = remaining_str.indexOf( ',' );
			var second_set = remaining_str.substr( seperator_pos + 1 );
			var final_list = first_set + second_set;
			jQuery( "#selected_products" ).val( final_list );
		}
		if ( jQuery( "#selected_products" ).val() == '' ) {
			jQuery( '#send_friend').prop( 'disabled', true );
		} else {
			jQuery( '#send_friend').prop( 'disabled', false );
		}
	}
	</script>
	
	<br>
	<table style="width:100%;max-width:750px;">
		<tr>
			<th style="vertical-align:top;">
				<label for="client_name"><?php _e( 'Your name', 'woocommerce-booking' );?></label>
			</th>
			<td>
				<input type="text" style="width:100%;max-width:400px;" name="client_name" id="client_name" value="<?php echo $order->billing_first_name . " " . $order->billing_last_name; ?>">
			</td>
		</tr>
		<tr>
			<th style="vertical-align:top;">
				<label for="client_email"><?php _e( 'Your email', 'woocommerce-booking' );?></label>
			</th>
			<td>
				<input type="text" style="width:100%;max-width:400px;" name="client_email" id="client_email" value="<?php echo $order->billing_email; ?>">
			</td>
		</tr>
		<tr>
			<th style="vertical-align:top;">
				<label for="friend_email"><?php _e( 'Their email', 'woocommerce-booking' );?></label>
			</th>
			<td>
				<input type="text" style="width:100%;max-width:400px;" name="friend_email" id="friend_email" >
				<br>
				(If more than one, seperate using commas)
			</td>
		</tr>
		<tr>
			<th style="vertical-align:top;">
				<label for="email_message"><?php _e( 'Personalized Message (optional)', 'woocommerce-booking' );?></label>
			</th>
			<td>
				<textarea style="width:100%;max-width:400px;" name="email_message" id="email_message"></textarea>
			</td>
		</tr>
	</table>
	<input type="button" class="button" id="send_friend" name="send_friend" value="<?php _e( 'SEND TO A FRIEND', 'woocommerce-booking' ); ?>" onclick="bkap_send_email(<?php echo $_GET['order_id'];?>)" />
	 
	<script type="text/javascript">
		 function bkap_send_email( id ) {
			 var email_addr = jQuery( '#friend_email' ).val().trim();
			 if ( email_addr == '' ) {
				 alert( 'Please enter the email address of at least one friend.' );
			 	 return;			
			 }
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
					document.getElementById( "tell_a_friend" ).style.display = "none";
					document.getElementById( "tell_another_friend" ).style.display = "block";
				});
			}
	</script>
	<p></p>
</div>
<div id="tell_another_friend" style="display:none">
	<p>
		Thank you for sharing with a friend. 
	</p>
	<p>
		They have been notified via email and you have been copied in.
	</p>
	<br>
	<input type="button" class="button" id="send_another_friend" name="send_another_friend" value="<?php _e( 'SEND TO ANOTHER FRIEND', 'woocommerce-booking' ); ?>" onclick="send_another()" />
	<br><br>
	<input type="button" class="button" id="return_shop" name="return_shop" value="<?php _e( 'RETURN TO SHOP', 'woocommerce-booking' ); ?>" onclick="window.location.replace('<?php echo esc_url( add_query_arg('post_type','product',home_url( '/' )));?>');" />
	<script type="text/javascript">
	function send_another() {
		document.getElementById( "tell_a_friend" ).style.display = "block";
		document.getElementById( "tell_another_friend" ).style.display = "none";
	}
	</script>
	<p></p>
</div>
</body>
</div>
<?php
get_footer();
?>