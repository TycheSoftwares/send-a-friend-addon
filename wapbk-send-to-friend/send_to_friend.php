<?php 
/*
Plugin Name: Send to a Friend Addon
Plugin URI: http://www.tychesoftwares.com/store/premium-plugins/woocommerce-booking-plugin
Description: This is an addon for the WooCommerce Booking & Appointment Plugin which allows the end users to send product links to friends or book extra slots for bookable products 
Version: 1.0
Author: Tyche Softwares
Author URI: http://www.tychesoftwares.com/
*/
/******************************************
 *Return if the Booking plugin is inactive 
 *****************************************/
if ( in_array( 'woocommerce-booking/woocommerce-booking.php', get_option( 'active_plugins' ) ) ) {
}
else {
	return;
}
/**
 * Localisation
 **/
load_plugin_textdomain('woocommerce-booking', false, dirname( plugin_basename( __FILE__ ) ) . '/');
include_once( ABSPATH . 'wp-content/plugins/wapbk-send-to-friend/lang.php' );

/**
 * send_to_friend class
 **/
if ( !class_exists( 'send_to_friend' ) ) {

	class send_to_friend {
			
		public function __construct() {
		    add_action( 'admin_notices', array( &$this, 'send_to_friend_error_notice' ) );
			// Add the Book another slot and send to friend button on the Order Received Page and the customer emails
			add_action( 'woocommerce_order_item_meta_end', array( &$this, 'bkap_completed_page' ), 10, 3 );
			// redirect to the 'tell a friend' page
			add_action( 'template_include', array( &$this, 'bkap_request_friend_redirect' ), 99, 1 );
			// Ajax calls
			add_action( 'init', array( &$this, 'bkap_send_friend_load_ajax' ) );
			// pre-populate date and time slots on the front end product page
			add_action( 'woocommerce_before_add_to_cart_button', array( &$this, 'bkap_prepopulate_data' ), 99 );
			
		}
		/*******************************************************
		 * Functions
		 ******************************************************/
		function send_to_friend_error_notice() {
		    if ( !is_plugin_active( 'woocommerce-booking/woocommerce-booking.php' ) ) {
		        echo "<div class=\"error\"><p>WAPBK Send to a Friend Addon for Woocommerce Booking and Appointment Plugin is enabled but not effective. It requires WooCommerce Booking and Appointment plugin in order to work.</p></div>";
		    }
		}
		
		/**
		 * List the ajax calls for the addon
		 * 
		 * @since 1.0
		 */
		function bkap_send_friend_load_ajax() {
			if ( !is_user_logged_in() ){
				add_action('wp_ajax_nopriv_bkap_send_email_to_friend', array(&$this, 'bkap_send_email_to_friend'));
			}
			else {
				add_action('wp_ajax_bkap_send_email_to_friend', array(&$this, 'bkap_send_email_to_friend'));
			}
		}
		
		/**
		 * Add buttons on the Thank You page and order emails
		 * 
		 * This function adds the availability left and the Book
		 * Another Slot and Send to Friend buttons on the Order 
		 * Received page and the customer emails.
		 * 
		 * @since 1.0
		 */
		function bkap_completed_page( $item_id, $item, $order ) {
			$product_id = $item['product_id'];
			// Booking Settings
			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
			if ( isset( $booking_settings ) && $booking_settings['booking_enable_date'] == 'on' ) {
				// Get the booking details
				$booking_date = $item['wapbk_booking_date'];
				$checkout_date = $booking_time = '';
				if ( isset( $item['wapbk_checkout_date'] ) ) {
					$checkout_date = $item['wapbk_checkout_date'];
				}
				if ( isset( $item['wapbk_time_slot'] ) ) {
					$booking_time = $item['wapbk_time_slot'];
				}
				// Get the availability for the product
				$availability = $this->get_availability( $product_id, $booking_date, $checkout_date, $booking_time);
				
				$display = 'NO';
				if ($availability > 0 ) {
					$display = 'YES';
				}
				else if ( $availability === "Unlimited" ) {
					$display = 'YES';
				}
				
				$message = wapbk_send_friend( 'book.availability-order-received1' ) . $availability . wapbk_send_friend( 'book.availability-order-received2' );
				if ( isset( $booking_settings['booking_enable_multiple_day'] ) && $booking_settings['booking_enable_multiple_day'] == 'on' ) {
					$message .= wapbk_send_friend( 'book.availability-multiple-order-received' );
				}
				else if( $booking_settings['booking_enable_time'] == 'on' ) {
					$message .= wapbk_send_friend( 'book.availability-time-order-received' );
				}
				else {
					$message .= wapbk_send_friend( 'book.availability-single-order-received' );
				}
				if ($display == "YES") {
					?>
					<br><br>
					<?php 
					echo $message;
					$parm = array( 'send-booking-to-friend' => 1,
							'order_id' => $order->id );
					?> 
					<br>
					<a href="<?php echo esc_url_raw( add_query_arg( 'item_id', $item_id, get_permalink( $product_id ) ) ); ?>"><input type="button" class="button" style="padding-top:5px; padding-bottom:5px;" id="book_another" name="book_another" value="<?php _e( 'BOOK ANOTHER SPACE', 'woocommerce-booking' ); ?>" /></a>
					<a href="<?php echo esc_url_raw( add_query_arg( $parm, get_permalink( woocommerce_get_page_id( 'shop' ) ) ) );?>"><input type="button" class="button" style="padding-top:5px; padding-bottom:5px;" id="send_friend" name="send_friend" value="<?php _e( 'SEND TO A FRIEND', 'woocommerce-booking' ); ?>" /></a>
				<?php 	 
				}
			}
		}
		
		/**
		 * Calculates the avalability for a given product
		 * 
		 * Calculates the availability left for a given product
		 * 
		 * @since 1.0
		 * @param int $product_id
		 * str $booking_date
		 * str $checkout_date
		 * str $booking_time
		 * 
		 * @return $availability as integer value or 'Unlimited' if no lockout is set
		 */
		public static function get_availability( $product_id, $booking_date, $checkout_date, $booking_time ) {
			global $wpdb;
			// Default the availability to 0
			$availability = 0;
			// Get all the booking details
			$product_date = $booking_date;
			$product_checkout_date = $product_from_time = $product_to_time = '';
			if ( isset( $checkout_date ) && $checkout_date != '' ) {
				$product_checkout_date = $checkout_date;
			}
			
			if ( isset( $booking_time ) && $booking_time != '' ) {
				// Time should be set to G:i
				$time_explode = explode( '-', $booking_time );	
				$product_from_time = date( 'G:i', strtotime( trim( $time_explode[0] ) ) );
				if ( isset( $time_explode[1] ) && trim( $time_explode[1] ) != '' ) {
					$product_to_time = date( 'G:i', strtotime( trim( $time_explode[1] ) ) );
				}
			}
			
			// check the booking type
			$booking_settings = get_post_meta( $product_id , 'woocommerce_booking_settings', true);
			// multiple day
			if ( isset( $booking_settings['booking_enable_multiple_day'] ) && $booking_settings['booking_enable_multiple_day'] == 'on' ) {
				$date_checkout = date( 'd-n-Y', strtotime( $product_checkout_date ) );
				$date_checkin = date( 'd-n-Y', strtotime( $product_date ) );
				$order_dates = bkap_common::bkap_get_betweendays( $date_checkin, $date_checkout );
				$todays_date = date( 'Y-m-d' );
				
				$query_date ="SELECT DATE_FORMAT( start_date, '%d-%c-%Y' ) as start_date, DATE_FORMAT( end_date, '%d-%c-%Y' ) as end_date FROM ".$wpdb->prefix."booking_history
				WHERE start_date >='".$todays_date."' AND post_id = '".$product_id."'";
				
				$results_date = $wpdb->get_results( $query_date );
				
				$dates_new = array();
				
				foreach( $results_date as $k => $v ) {
					$start_date = $v->start_date;
					$end_date = $v->end_date;
					$dates = bkap_common::bkap_get_betweendays( $start_date, $end_date );
					$dates_new = array_merge( $dates, $dates_new );
				}
				$dates_new_arr = array_count_values( $dates_new );
				
				$lockout = 0;
				if ( isset( $booking_settings['booking_date_lockout'] ) && ( $booking_settings['booking_date_lockout'] != '' || $booking_settings['booking_date_lockout'] != 0 ) ) {
					$lockout = $booking_settings['booking_date_lockout'];
					
					foreach ( $order_dates as $k => $v ) {
						if ( array_key_exists( $v, $dates_new_arr ) ) {
							$availability = $lockout - $dates_new_arr[$v];
						} else {
							$availability = $lockout;
						}
					}
				}
				else {
					$availability = 'Unlimited';
				}
			}
			// date and time
			else if( $booking_settings['booking_enable_time'] == 'on' ) {
				$availability_query = "SELECT total_booking, available_booking FROM `".$wpdb->prefix."booking_history`
										WHERE post_id = %d
										AND start_date = %s
										AND from_time = %s
										AND to_time = %s
										AND status != 'inactive'";
				$get_availability = $wpdb->get_results( $wpdb->prepare( $availability_query, $product_id, $product_date, $product_from_time, $product_to_time ) );
				
				if ( isset( $get_availability ) && count( $get_availability ) > 0 ) {
					if ( $get_availability[0]->total_booking == 0 ) {
						$availability = 'Unlimited';
					} else {
						$availability = $get_availability[0]->available_booking;
					}
				}
			}
			// only day bookings
			else {
				$availability_query = "SELECT total_booking, available_booking FROM `".$wpdb->prefix."booking_history`
										WHERE post_id = %d
										AND start_date = %s
										AND status != 'inactive'";
				$get_availability = $wpdb->get_results( $wpdb->prepare( $availability_query, $product_id, $product_date ) );
				if ( isset( $get_availability ) && count( $get_availability ) > 0 ) {
					if ( $get_availability[0]->total_booking == 0 ) {
						$availability = 'Unlimited';
					} else {
						$availability = $get_availability[0]->available_booking;	
					}
				}
			}
			
			return $availability;
		}
		
		/**
		 * Redirect Tell a Friend page
		 * 
		 * redirects to the 'Tell a Friend' page when the 'Send 
		 * to a Friend' button is clicked on the Order Received
		 * Page
		 * 
		 * @since 1.0
		 * @return str $template
		 */
		function bkap_request_friend_redirect( $template ) {
			if ( isset( $_GET['send-booking-to-friend'] ) && $_GET['send-booking-to-friend'] == 1 ) {
				$templatefilename = 'request-friend.php';
				if ( file_exists( ABSPATH . 'wp-content/plugins/wapbk-send-to-friend/' . $templatefilename ) ) {
           			$template = ABSPATH . 'wp-content/plugins/wapbk-send-to-friend/' . $templatefilename;
        		} 
			}
			return $template;
		}
		
		/**
		 * Send email to friend
		 * 
		 * Sends emails to the friends
		 * 
		 * @since 1.0
		 */ 
		function bkap_send_email_to_friend() {
			// get the order object
			$order = new WC_Order( $_POST['order_id'] );
			$items = $order->get_items();
			//products selected by client
			$products = explode( ',', $_POST['details'] );
			//get the content
			$email_content = $this->get_template();
			// Replace all the shortcodes with real time data
			// client name
			$email_content = str_replace( '<client_name>', $_POST['client_name'], $email_content );
			// site name
			$email_content = str_replace( '<site_name>', get_option( 'blogname' ), $email_content );
			// get the booking date and time labels, so they can be used to retrieve data
			$booking_date_label = get_option( 'book.date-label' );
			$booking_time_label = get_option( 'book.time-label' );
			$checkout_date_label = trim( get_option( 'checkout.date-label' ) );
			// Product table
			$product_table = "<table cellpadding='10' border='1'  style='border-collapse:collapse; border-color:Black;'>
								<tr style='background-color:#f4f5f4;'>
								<th>Product</th><td></td>
								</tr>";
			foreach ( $items as $key => $value ) {
				// Add the product in the table only if it has been selected by the client on the 'Tell a Friend' Page
				if ( is_array( $products ) && count( $products ) > 0 && in_array( $value['product_id'], $products ) ) {
					$product_table .= "<tr>
										<td>";
					// Product link
					$product_link = get_permalink( $value['product_id'] );
					// Add order Id to product link
					$button_link = esc_url_raw( add_query_arg( 'item_id', $key, get_permalink( $value['product_id'] ) ) );
					// Add the product name, booking details
					$booking_date = $value['wapbk_booking_date'];
					$checkout_date = $time_slot = '';
					if ( isset( $value['wapbk_checkout_date'] ) ) {
						$checkout_date = $value['wapbk_checkout_date'];
					}
					if ( isset( $value['wapbk_time_slot'] ) ) {
						$time_slot = $value['wapbk_time_slot'];
					}
					$product_table .= "<a href='" . $product_link . "'>" . $value['name'] . "</a><br>";
					$product_table .= "<b>" . $booking_date_label . ":</b><br>".
										$value[$booking_date_label];
					if ( isset( $value[$checkout_date_label] ) ) {
						$product_table .= "<br><b>" . $checkout_date_label . ":</b><br>".
								$value[$checkout_date_label];
					}
					if ( isset( $value[$booking_time_label] ) ) {
						$product_table .= "<br><b>" . $booking_time_label . ":</b><br>".
								$value[$booking_time_label];
					}
					$product_table .= '</td>';
					// Get the availability for the product
					$availability = $this->get_availability( $value['product_id'], $booking_date, $checkout_date, $time_slot );
					
					// check the booking type
					$booking_settings = get_post_meta( $value['product_id'], 'woocommerce_booking_settings', true);
						
					$message = wapbk_send_friend( 'book.availability-order-received1' ) . $availability . wapbk_send_friend( 'book.availability-order-received2' );
					if ( isset( $booking_settings['booking_enable_multiple_day'] ) && $booking_settings['booking_enable_multiple_day'] == 'on' ) {
						$message .= wapbk_send_friend( 'book.availability-multiple-order-received' );
					}
					else if( $booking_settings['booking_enable_time'] == 'on' ) {
						$message .= wapbk_send_friend( 'book.availability-time-order-received' );
					}
					else {
						$message .= wapbk_send_friend( 'book.availability-single-order-received' );
					}
					// Display availability message and the button to allow the user to directly book an order.
					$product_table .= "<td>" . $message . "<br>";
					$product_table .= "<a href='" . $button_link . "'><input type='button' class='button' style='width:150px;' id='book_me_in' name='book_me_in' value='Book me in!' /></a></td></tr>";					
				}
			} 
			$product_table .= '</table>';
			$email_content = str_replace( '<product_list>', $product_table, $email_content );
			// Personalized msg
			$email_content = str_replace( '<personalized_message>', $_POST['msg_txt'], $email_content );
			// Multiple email addresses are taken in using comma as the seperator, hence can be used as is
			$recepients = $_POST['friend_email'];
			// Create the header, mark admin and client in cc
			$headers = "From: <" . get_option( 'admin_email' ) . ">" . "\r\n";
			$headers .= "Cc: " . get_option( 'admin_email' ) . "," . $_POST['client_email'] . "\r\n";
			$headers .= "Content-Type: text/html"."\r\n";
			$headers .= "Reply-To:  " . get_option( 'admin_email' ) . " " . "\r\n";
		//	mail('pinalj1612@gmail.com','headers woo_pinal',$headers);
			// email subject
			$email_subject = 'Join me at ' . get_option( 'blogname' );
			// Send the email
			wp_mail( $recepients, $email_subject, $email_content, $headers );
			die();
		}
		
		/**
		 * Fetch email template
		 * 
		 * Fetches the email template to be sent to the friends
		 * 
		 * @since 1.0
		 */
		function get_template() {
			$order = wc_get_order( $_POST['order_id'] );
			ob_start();
			wc_get_template( 'send-friend-email-template.php', array(), '', dirname( __FILE__ ) . '/email_templates/' );
			return ob_get_clean();
		}
		
		/**
		 * Booking data pre-population
		 * 
		 * Pre-populate the booking data on the front end product 
		 * page 
		 * 
		 * @since 1.0
		 */
		function bkap_prepopulate_data() {
			global $post, $wpdb;
			// If the order ID is present, it means the booking data needs to be pre-populated
			if ( isset( $_GET['item_id'] ) && $_GET['item_id'] != '' ) {
				$duplicate_of = bkap_common::bkap_get_product_id( $post->ID );
				$item_id = $_GET['item_id'];

				// default fields
				$hidden_date = '';
				// Set the attribute field string to make sure the ajax for time slots works fine
				$product = get_product( $duplicate_of );
				if ( $product->product_type == 'variable' ) {
					$variations = $product->get_available_variations();
					$attributes = $product->get_variation_attributes();
					$attribute_fields_str = "";
					$attribute_name = "";
					$attribute_value = "";
					$attribute_value_selected = "";
					$attribute_fields = array();
					$i = 0;
					foreach ( $variations as $var_key => $var_val ) {
						foreach ( $var_val['attributes'] as $a_key => $a_val ) {
							if ( !in_array( $a_key, $attribute_fields ) ) {
								$attribute_fields[] = $a_key;
								$attribute_fields_str .= ",\"$a_key\": jQuery(\"[name='$a_key']\").val() ";
								$key = str_replace( "attribute_", "", $a_key );
								$attribute_value .= "attribute_values =  attribute_values + '|' + jQuery('#" . $key . "').val();";
								$attribute_value_selected .= "attribute_selected =  attribute_selected + '|' + jQuery('#" . $key . " :selected').text();";
								$on_change_attributes[] = $a_key;
							}
							$i++;
						}
					}
				} else {
					$attribute_fields_str = ',tyche: 1';
				}
				$order_id_query = "SELECT order_id FROM `".$wpdb->prefix."woocommerce_order_items`
									WHERE order_item_id = %d";
				$results_order_id = $wpdb->get_results( $wpdb->prepare( $order_id_query, $item_id ) );
				
				$order = new WC_Order( $results_order_id[0]->order_id );
				// check if the order is refunded, trashed or cancelled
				if( isset( $order->post_status ) && ( $order->post_status != 'wc-cancelled' ) && ( $order->post_status != 'wc-refunded' ) && ( $order->post_status != 'trash' ) && ( $order->post_status != '' ) && ( $order->post_status != 'wc-failed' ) ) {
				
					$booking_settings = get_post_meta( $duplicate_of, 'woocommerce_booking_settings', true );
					// get the booking details from the woocommerce_order_itemmeta table
					$booking_details = WC_Abstract_Order::get_item_meta( $item_id );
					
					if ( isset( $booking_details['_wapbk_booking_date'][0] ) ) {
						$booking_date = $booking_details['_wapbk_booking_date'][0];
						// Date formats
						$date_formats = bkap_get_book_arrays('date_formats');
						// get the global settings to find the date & time formats
						$global_settings = json_decode(get_option('woocommerce_booking_global_settings'));
						$date_format_set = $date_formats[$global_settings->booking_date_format];
						$display_date = date ( $date_format_set, strtotime( $booking_date ) );
					}
					$checkout_date = $booking_time = '';
					$hidden_date_checkout = '';
					// if multiple days is enabled, fetch the checkout date
					if ( isset( $booking_settings['booking_enable_multiple_day'] ) && $booking_settings['booking_enable_multiple_day'] == 'on' ) {
						if ( isset( $booking_details['_wapbk_checkout_date'] ) ) {
							$checkout_date = $booking_details['_wapbk_checkout_date'];
							$hidden_date_checkout = date( 'j-n-Y', strtotime( $checkout_date ) );
						}
					}
					// if time settings is enabled, fetch the time slot selected
					if( $booking_settings['booking_enable_time'] == 'on' ) {
						if ( isset( $booking_details['_wapbk_time_slot'][0] ) ) {
							$booking_time = $booking_details['_wapbk_time_slot'][0];
							// create a time stamp for the booking date and start time
							$time_explode = explode( '-', $booking_time );
							$book_date = $booking_date . ' ' . $time_explode[0];
							$date_timestamp = strtotime( $book_date );
						}
					}
					// check if bookings are still available for the given date/s and/or time
					$availability = $this->get_availability( $duplicate_of, $booking_date, $checkout_date, $booking_time);
					
					$pre_populate = 0;
					if ($availability > 0 ) {
						$pre_populate = 1;
					}
					else if ( $availability === "Unlimited" ) {
						$pre_populate = 1;
					}
					
					$current_time = current_time( 'timestamp' );
					$past_date = $past_time = 1; // means its a future date and time
					if ( isset( $current_time ) && isset( $date_timestamp ) ) {
						if ( $date_timestamp < $current_time ) {
							$past_time = 0; // means the booking date and time hv already passed
						}
					}
					else if ( strtotime( $booking_date ) < $current_time ) {
						// As the Booking date is a past date
						$pre_populate = $past_date = 0;
					}
					
					$hidden_date = date ( 'j-n-Y', strtotime( $booking_date ) );
		
					if ( $pre_populate == 1 ) {
						?>
						<script type="text/javascript">
						function bkap_init() {
							jQuery( "#wapbk_hidden_date" ).val( '<?php echo $hidden_date; ?>' );
							var split = jQuery( "#wapbk_hidden_date" ).val().split( "-" );
							var bookingDate = new Date( split[2], split[1]-1, split[0] );
							<?php
							if ( isset( $booking_settings['enable_inline_calendar'] ) && $booking_settings['enable_inline_calendar'] == 'on' ) { 
							?>
								var timestamp = Date.parse( bookingDate ); 
								if ( isNaN( timestamp ) == false ) { 
									var default_date_selection = new Date( timestamp );
									jQuery( "#inline_calendar" ).datepicker( "setDate", default_date_selection );
								}
							<?php 
							} else {
							?>
								jQuery( "#booking_calender" ).datepicker( "setDate", bookingDate );
							<?php 
							}?>
							if ( jQuery( "#wapbk_bookingEnableTime" ).val() == "on" && jQuery( "#wapbk_booking_times" ).val() == "YES" ) {
								var sold_individually = jQuery( "#wapbk_sold_individually" ).val();
								jQuery( "#ajax_img" ).show();
								jQuery( ".single_add_to_cart_button" ).hide();	
								var time_slots_arr = jQuery( "#wapbk_booking_times" ).val();
								var data = {
									current_date: jQuery( "#wapbk_hidden_date" ).val(),
									post_id: '<?php echo $duplicate_of; ?>', 
									action: 'bkap_check_for_time_slot'
									<?php echo $attribute_fields_str; ?>
								};
								jQuery.post( '<?php echo get_admin_url() . 'admin-ajax.php'; ?>', data, function( response ) {
									jQuery( "#ajax_img" ).hide();
									jQuery( "#show_time_slot" ).html( response );
									// for today if the time slot is past, display a message saying the same
									<?php 
									if ( isset( $past_time ) && $past_time == 0 ) {
									?>
										// Pre-populate the time slots with the order time slots
										jQuery( "#time_slot" ).val( '' );
									<?php 
									} else {?>
										// Pre-populate the time slots with the order time slots
										jQuery( "#time_slot" ).val( '<?php echo $booking_time; ?>' );
										var time_slot_value = jQuery( "#time_slot" ).val();
										// Availability display for the time slot selected if setting is enabled			
										if ( typeof time_slot_value != "undefined" && jQuery( "#wapbk_availability_display" ).val() == "yes" ) {
											var data = {
												checkin_date: jQuery( "#wapbk_hidden_date" ).val(),
												timeslot_value: time_slot_value,
												post_id: '<?php echo $duplicate_of; ?>', 
												action: 'bkap_get_time_lockout'
											};
											jQuery.post( '<?php echo get_admin_url() . 'admin-ajax.php'; ?>', data, function( response ) {
												jQuery( "#show_stock_status" ).html( response );
											});
										}
											
										if ( jQuery( "#time_slot" ).val() != "" ) {
		                                    jQuery( ".payment_type" ).show();
											if( sold_individually == "yes" ) {
												jQuery( ".quantity" ).hide();
												jQuery( ".payment_type" ).hide();
												jQuery(".partial_message").hide();
											} else {
												jQuery( ".quantity" ).show();
												jQuery( ".payment_type" ).show();
											}
										} else if ( jQuery("#time_slot").val() == "" ) {
											jQuery( ".single_add_to_cart_button" ).hide();
											jQuery( ".quantity" ).hide();
		                                    jQuery( ".payment_type" ).hide();
											jQuery( ".partial_message" ).hide();
										}
										// This is called to ensure the variable pricing for time slots is displayed
										bkap_single_day_price();
									<?php 
									}?>
								});
							} else {
								// check if multiple day is enabled, if yes then pre-populate the end date
								if ( jQuery( "#booking_calender_checkout" ).length ) {
									jQuery( "#wapbk_hidden_date_checkout" ).val( '<?php echo $hidden_date_checkout; ?>' );
									var split = jQuery( "#wapbk_hidden_date_checkout" ).val().split( "-" );
									var bookingDate = new Date( split[2], split[1]-1, split[0] );
									<?php
									if ( isset( $booking_settings['enable_inline_calendar'] ) && $booking_settings['enable_inline_calendar'] == 'on' ) { 
									?>
										var timestamp = Date.parse( bookingDate ); 
										if ( isNaN( timestamp ) == false ) { 
											var default_date_selection = new Date( timestamp );
											jQuery( "#inline_calendar_checkout" ).datepicker( "setDate", default_date_selection );
											jQuery( "#inline_calendar_checkout" ).datepicker( "option", "minDate", default_date_selection );
										}
									<?php 
									} else {
									?>
										jQuery( "#booking_calender_checkout" ).datepicker( "setDate", bookingDate );
									<?php 
									}?>
									jQuery( ".single_add_to_cart_button" ).show();
									jQuery( ".quantity" ).show();
									bkap_calculate_price();
								} else {
									jQuery( ".single_add_to_cart_button" ).show();
									jQuery( ".quantity" ).show();
									// This is called to ensure the special pricing for days/dates is displayed
									bkap_single_day_price();
								}
								// Availability Display for the date selected only if setting is enabled
								if ( jQuery( "#wapbk_availability_display" ).val() == "yes" ) {
									var data = {
											checkin_date: jQuery( "#wapbk_hidden_date" ).val(),
											post_id: '<?php echo $duplicate_of; ?>',
											action: 'bkap_get_date_lockout'
											};
					
									jQuery.post( '<?php echo get_admin_url() . 'admin-ajax.php'; ?>', data, function(response)
									{
										jQuery( "#show_stock_status" ).html(response);
									});
								}
							}
						}
						window.onload = bkap_init;
						</script>
						<?php 
					} else {
						if ( isset( $past_date ) && $past_date == 0 ) {
						?>
							<script type="text/javascript">
							function bkap_show_status() {
								jQuery( "#show_stock_status" ).html( "The date <?php echo $display_date; ?> is past today. Please select another date" );
							}
							window.onload = bkap_show_status;
							</script>
						<?php 
						}
						else {
						?>
							<script type="text/javascript">
							function bkap_show_status() {
								jQuery( "#show_stock_status" ).html( "There are no longer any spaces available for <?php echo $display_date; ?>" );
							}
							window.onload = bkap_show_status;
							</script>
						<?php
						}
					}
				}
			}
		}
	}
	$send_to_friend = new send_to_friend();
}
?>