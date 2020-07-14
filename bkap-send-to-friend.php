<?php 
/**
 * Plugin Name: Send to a Friend Addon
 * Plugin URI: https://www.tychesoftwares.com/store/premium-plugins/send-to-friend-addon-woocommerce-booking-appointment-plugin
 * Description: This is an addon for the WooCommerce Booking & Appointment Plugin which allows the end users to send product links to friends or book extra slots for bookable products. To get started: Go to <strong>Dashboard -> <a href="admin.php?page=woocommerce_booking_page&action=addon_settings">Booking</a></strong>.
 * Version: 1.4.1
 * Author: Tyche Softwares
 * Author URI: http://www.tychesoftwares.com/
 * Requires PHP: 5.6
 * WC requires at least: 3.0.0
 * WC tested up to: 3.6.4
 *
 * @package bkap-stf
 */

/**
 * Localisation
 **/
load_plugin_textdomain( 'bkap-send-to-friend', false, dirname( plugin_basename( __FILE__ ) ) . '/' );
include_once( dirname( __FILE__ ) . '/bkap_tell_a_friend_page.php' );
include_once( plugin_dir_path( __DIR__ ) .'woocommerce-booking/bkap-common.php' );

function is_bkap_send_friend_active() {
	if ( is_plugin_active( 'send-booking-invites-to-friends/bkap-send-to-friend.php' ) ) {
		return true;
	} else {
		return false;
	}
}

register_uninstall_hook( __FILE__, 'bkap_send_friend_delete' );

/**
 * Delete the addon settings when the addon is uninstalled
 * 
 * @since 1.0
 */
function bkap_send_friend_delete() {
	delete_option( 'bkap_friend_enable_send_a_friend' );
	delete_option( 'bkap_friend_enable_admin_cc' );
	delete_option( 'bkap_friend_book_another_button_text' );
	delete_option( 'bkap_friend_send_friend_button_text' );
	delete_option( 'bkap_friend_email_button_text' );
	delete_option( 'bkap_friend_availability_msg_single_days' );
	delete_option( 'bkap_friend_availability_msg_date_time' );
	delete_option( 'bkap_friend_availability_msg_multiple_days' );
	delete_option( 'bkap_friend_button_css' );
	delete_option( 'bkap_friend_tell_friend_page_url' );
}
/**
 * send_to_friend class
 **/
if ( !class_exists( 'send_to_friend' ) ) {

	class send_to_friend {
			
		public function __construct() {
		    
			// Display a notice in the admin, when the addon is enabled without the base plugin
		    add_action( 'admin_notices', array( &$this, 'bkap_send_to_friend_error_notice' ) );
		    
		    // Initialize settings
		    register_activation_hook( __FILE__, array( &$this, 'bkap_send_to_friend_activate' ) );
		    
		    // Wordpress settings API
		    add_action('admin_init', array( &$this, 'bkap_friend_plugin_options' ) );
		    
		    // Add settings link on the Plugins page
		    add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( &$this, 'bkap_plugin_settings_link' ) );
		    
		    // Add the new settings tab for the addon
		    add_action( 'bkap_add_addon_settings', array( &$this, 'bkap_send_friend_tab' ), 10 );
			
			// Add the Book another slot and send to friend button on the Order Received Page and the customer emails
			add_action( 'woocommerce_order_item_meta_end', array( &$this, 'bkap_completed_page' ), 10, 3 );
			
			// redirect to the 'tell a friend' page
			add_action( 'init', array( &$this, 'load_tell_a_friend_page' ) );
			
			// Ajax calls
			add_action( 'init', array( &$this, 'bkap_send_friend_load_ajax' ) );
			
			// pre-populate date and time slots on the front end product page
			add_action( 'woocommerce_before_add_to_cart_button', array( &$this, 'bkap_prepopulate_data' ), 99 );
			
		}
		
		/*******************************************************
		 * Functions
		 ******************************************************/
		/**
		 * Display admin notice
		 * 
		 * A notice is displayed in the admin section when the 
		 * addon is activate and the Woocommerce Booking and Appointment
		 * plugin is inactive.
		 * 
		 * @since 1.0
		 */
		function bkap_send_to_friend_error_notice() {
		    if ( !is_plugin_active( 'woocommerce-booking/woocommerce-booking.php' ) ) {
		        echo "<div class=\"error\"><p>Send to a Friend Addon is enabled but not effective. It requires WooCommerce Booking and Appointment plugin in order to work.</p></div>";
		    }
		}
		
		/**
		 * List the ajax calls for the addon
		 * 
		 * @since 1.0
		 */
		function bkap_send_friend_load_ajax() {
			if ( !is_user_logged_in() ){
				add_action( 'wp_ajax_nopriv_bkap_send_email_to_friend', array( &$this, 'bkap_send_email_to_friend' ) );
			} else {
				add_action( 'wp_ajax_bkap_send_email_to_friend', array( &$this, 'bkap_send_email_to_friend' ) );
			}
		}
		
		/**
		 * Add a settings link on the Plugins page
		 *
		 * The below code adds a Settings link on the Wordpress Dashboard->Plugins page
		 * which redirects the user to the addon settings.
		 *
		 * @param array $links
		 * @since 1.0
		 * @return array
		 */
		function bkap_plugin_settings_link( $links ) {
			$setting_link[ 'settings' ] = '<a href="' . esc_url( get_admin_url( null, 'admin.php?page=woocommerce_booking_page&action=addon_settings' ) ) . '">Settings</a>';
			$links = $setting_link + $links;
			return $links;
		}
		
		/**
		 * Add settings when the plugin is activated
		 *
		 * @since 1.0
		 */
		function bkap_send_to_friend_activate() {			
			//Set default settings
			add_option( 'bkap_friend_enable_send_a_friend', '' );
			add_option( 'bkap_friend_enable_admin_cc', '' );
				
			add_option( 'bkap_friend_book_another_button_text', 'Book Another Space' );
			add_option( 'bkap_friend_send_friend_button_text', 'Send to a Friend' );
			add_option( 'bkap_friend_email_button_text', 'Book me in !!');
				
			add_option( 'bkap_friend_availability_msg_single_days', 'We still have <available_spots> spaces left for this date.' );
			add_option( 'bkap_friend_availability_msg_date_time', 'We still have <available_spots> spaces left for this date and time slot.' );
			add_option( 'bkap_friend_availability_msg_multiple_days', 'We still have <available_spots> spaces left for this date range.' );
			
			add_option( 'bkap_friend_button_css', 'display: block;background: #f4f5f4;width: 160px;height: 35px;padding-top: 5px;padding-bottom: 5px;text-align: center;border-radius: 5px;color: black;font-family: Calibri;font-size: 110%;border: 1px solid;margin-top: 5px;' );
			add_option( 'bkap_friend_tell_friend_page_url', 'send-booking-to-friend' );
				
		}
		
		/**
		 * Wordpress Settings API
		 * 
		 * The below function uses the Wordpress Settings API
		 * to register all the setting fields and their callback 
		 * functions
		 * 
		 * @since 1.0
		 */
		function bkap_friend_plugin_options() {
			
			// First, we register a section. This is necessary since all future options must belong to a section
			add_settings_section(
					'bkap_friend_settings_section',         // ID used to identify this section and with which to register options
					__( 'Send to a Friend Addon Settings', 'bkap-send-to-friend' ),                  // Title to be displayed on the administration page
					array( $this, 'bkap_friend_callback' ), // Callback used to render the description of the section
					'woocommerce_booking_page-bkap_friend_settings_section'     // Page on which to add this section of options
			);
				
			add_settings_field(
					'bkap_friend_enable_send_a_friend',
					__( 'Enable a user to send booking details to a friend:', 'bkap-send-to-friend' ),
					array( $this, 'bkap_friend_enable_friend_callback' ),
					'woocommerce_booking_page-bkap_friend_settings_section',
					'bkap_friend_settings_section',
					array( __( 'Yes, show the \'Book Another\' and \'Send to a Friend\' buttons on the Thank You page and Order emails.', 'bkap-send-to-friend' ) )
			);
				
			add_settings_field(
					'bkap_friend_enable_admin_cc',
					__( 'Mark the admin in cc in emails sent to friends:', 'bkap-send-to-friend' ),
					array( $this, 'bkap_friend_enable_admin_cc_callback' ),
					'woocommerce_booking_page-bkap_friend_settings_section',
					'bkap_friend_settings_section',
					array( __( 'Mark the site admin in cc in the emails sent to friends from the \'Tell a Friend\' page.', 'bkap-send-to-friend' ) )
			);
				
			add_settings_field(
					'bkap_friend_book_another_button_text',
					__( 'Text for the Book Another Space button:', 'bkap-send-to-friend' ),
					array( $this, 'bkap_friend_book_another_button_text_callback' ),
					'woocommerce_booking_page-bkap_friend_settings_section',
					'bkap_friend_settings_section',
					array( __( 'Text for the Book Another Space button on the Thank You page and Order emails.', 'bkap-send-to-friend' ) )
			);
				
			add_settings_field(
					'bkap_friend_send_friend_button_text',
					__( 'Text for the Send to a Friend button:', 'bkap-send-to-friend' ),
					array( $this, 'bkap_friend_send_friend_button_text_callback' ),
					'woocommerce_booking_page-bkap_friend_settings_section',
					'bkap_friend_settings_section',
					array( __( 'Text for the Send to a Friend button on the Thank You page and Order emails.', 'bkap-send-to-friend' ) )
			);
				
			add_settings_field(
					'bkap_friend_email_button_text',
					__( 'Text for the button in emails sent to friends:', 'bkap-send-to-friend' ),
					array( $this, 'bkap_friend_email_button_text_callback' ),
					'woocommerce_booking_page-bkap_friend_settings_section',
					'bkap_friend_settings_section',
					array( __( 'Text for the Book me in button which appears in emails sent to friends.', 'bkap-send-to-friend' ) )
			);
				
			add_settings_field(
					'bkap_friend_availability_msg_single_days',
					__( 'Message for availability left for single day bookings:', 'bkap-send-to-friend' ),
					array( $this, 'bkap_friend_avail_msg_single_days_callback' ),
					'woocommerce_booking_page-bkap_friend_settings_section',
					'bkap_friend_settings_section',
					array( __( 'Availability Message to be displayed in emails for products with single day bookings.', 'bkap-send-to-friend' ) )
			);
				
			add_settings_field(
					'bkap_friend_availability_msg_date_time',
					__( 'Message for availability left for date and time slot bookings:', 'bkap-send-to-friend' ),
					array( $this, 'bkap_friend_avail_msg_date_time_callback' ),
					'woocommerce_booking_page-bkap_friend_settings_section',
					'bkap_friend_settings_section',
					array( __( 'Availability Message to be displayed in emails for products with date and time bookings.', 'bkap-send-to-friend' ) )
			);
				
			add_settings_field(
					'bkap_friend_availability_msg_multiple_days',
					__( 'Message for availability left for multiple day bookings:', 'bkap-send-to-friend' ),
					array( $this, 'bkap_friend_avail_msg_multiple_days_callback' ),
					'woocommerce_booking_page-bkap_friend_settings_section',
					'bkap_friend_settings_section',
					array( __( 'Availability Message to be displayed in emails for products with multiple day bookings.', 'bkap-send-to-friend' ) )
			);
				
			add_settings_field(
					'bkap_friend_button_css',
					__( 'Enter the css to be applied to the buttons displayed on the Thank You page and Order emails:', 'bkap-send-to-friend' ),
					array( $this, 'bkap_friend_button_css_callback' ),
					'woocommerce_booking_page-bkap_friend_settings_section',
					'bkap_friend_settings_section',
					array( __( 'The css to be applied to the buttons displayed on the Thank You page, Order emails and emails sent to friends.', 'bkap-send-to-friend' ) )
			);
			
			add_settings_field(
			         'bkap_friend_tell_friend_page_url',
			         __( 'Tell A Friend Address (URL):', 'bkap-send-to-friend' ),
			         array( $this, 'bkap_friend_tell_friend_page_url_callback' ),
			         'woocommerce_booking_page-bkap_friend_settings_section',
			         'bkap_friend_settings_section',
			         array( __( '/?order_id={order_id} . The URL that should be used for the Tell A Friend Page.', 'bkap-send-to-friend' ) )
			);
			
			// Finally, we register the fields with WordPress
			register_setting(
					'bkap_friend_settings',
					'bkap_friend_enable_send_a_friend'
			);
				
			register_setting(
					'bkap_friend_settings',
					'bkap_friend_enable_admin_cc'
			);
				
			register_setting(
					'bkap_friend_settings',
					'bkap_friend_book_another_button_text'
			);
				
			register_setting(
					'bkap_friend_settings',
					'bkap_friend_send_friend_button_text'
			);
				
			register_setting(
					'bkap_friend_settings',
					'bkap_friend_email_button_text'
			);
				
			register_setting(
					'bkap_friend_settings',
					'bkap_friend_availability_msg_single_days'
			);
				
			register_setting(
					'bkap_friend_settings',
					'bkap_friend_availability_msg_date_time'
			);
				
			register_setting(
					'bkap_friend_settings',
					'bkap_friend_availability_msg_multiple_days'
			);
			
			register_setting(
					'bkap_friend_settings',
					'bkap_friend_button_css'
			);
			
			register_setting(
			         'bkap_friend_settings',
			         'bkap_friend_tell_friend_page_url',
			         array( &$this, 'bkap_friend_tell_friend_page_url_save_callback' )
			);
		}
		
		/**
		 * WP Settings API callback for section
		 * 
		 * @since 1.0
		 */
		function bkap_friend_callback() {
		}
		
		/**
		 * WP Settings API callback for enable send a friend
		 * 
		 * @param array $args
		 * @since 1.0
		 */
		function bkap_friend_enable_friend_callback( $args ) {
			
			// First, we read the option
			$enable_send_a_friend = get_option( 'bkap_friend_enable_send_a_friend' );
			// This condition added to avoid the notice displyed while Check box is unchecked.
			if( isset( $enable_send_a_friend ) &&  '' == $enable_send_a_friend ) {
				$enable_send_a_friend = 'off';
			}
			// Next, we update the name attribute to access this element's ID in the context of the display options array
			// We also access the show_header element of the options collection in the call to the checked() helper function
			$html = '<input type="checkbox" id="bkap_friend_enable_send_a_friend" name="bkap_friend_enable_send_a_friend" value="on" ' . checked( 'on', $enable_send_a_friend, false ) . '/>';
			// Here, we'll take the first argument of the array and add it to a label next to the checkbox
			$html .= '<label for="bkap_friend_enable_send_a_friend"> '  . $args[0] . '</label>';
			
			echo $html;
		}
		
		/**
		 * WP Settings API callback for enable admin in cc 
		 * 
		 * @param array $args
		 * @since 1.0
		 */
		function bkap_friend_enable_admin_cc_callback( $args ) {
			
			// First, we read the option
			$enable_admin_cc = get_option( 'bkap_friend_enable_admin_cc' );
			// This condition added to avoid the notice displyed while Check box is unchecked.
			if( isset( $enable_admin_cc ) &&  '' == $enable_admin_cc ) {
				$enable_admin_cc = 'off';
			}
			// Next, we update the name attribute to access this element's ID in the context of the display options array
			// We also access the show_header element of the options collection in the call to the checked() helper function
			$html = '<input type="checkbox" id="bkap_friend_enable_admin_cc" name="bkap_friend_enable_admin_cc" value="on" ' . checked( 'on', $enable_admin_cc, false ) . '/>';
			// Here, we'll take the first argument of the array and add it to a label next to the checkbox
			$html .= '<label for="bkap_friend_enable_admin_cc"> '  . $args[0] . '</label>';
			
			echo $html;
		}
		
		/**
		 * WP Settings API callback for Book Another space button text 
		 * 
		 * @param array $args
		 * @since 1.0
		 */
		function bkap_friend_book_another_button_text_callback( $args ) {
			
			// First, we read the option
			$book_another_space_button = get_option( 'bkap_friend_book_another_button_text' );
			// This condition added to avoid the notice displyed when no text is set.
			if( isset( $book_another_space_button ) &&  '' == $book_another_space_button ) {
				$book_another_space_button = 'Book Another Space';
			}
			// Next, we update the name attribute to access this element's ID in the context of the display options array
			// We also access the show_header element of the options collection in the call to the checked() helper function
			$html = '<input type="text" id="bkap_friend_book_another_button_text" name="bkap_friend_book_another_button_text" value="' . $book_another_space_button . '"/>';
			// Here, we'll take the first argument of the array and add it to a label next to the field
			$html .= '<label for="bkap_friend_book_another_button_text"> '  . $args[0] . '</label>';
			
			echo $html;
		}
		
		/**
		 * WP Settings API callback for Send to a Friend button text 
		 * 
		 * @param array $args
		 * @since 1.0
		 */
		function bkap_friend_send_friend_button_text_callback( $args ) {
			
			// First, we read the option
			$send_friend_button = get_option( 'bkap_friend_send_friend_button_text' );
			// This condition added to avoid the notice displyed when no text is set
			if( isset( $send_friend_button ) &&  '' == $send_friend_button ) {
				$send_friend_button = 'Send to a Friend';
			}
			// Next, we update the name attribute to access this element's ID in the context of the display options array
			// We also access the show_header element of the options collection in the call to the checked() helper function
			$html = '<input type="text" id="bkap_friend_send_friend_button_text" name="bkap_friend_send_friend_button_text" value="' . $send_friend_button . '"/>';
			// Here, we'll take the first argument of the array and add it to a label next to the field
			$html .= '<label for="bkap_friend_send_friend_button_text"> '  . $args[0] . '</label>';
			
			echo $html;
		}
		
		/**
		 * WP Settings API callback for button in emails sent to friends.
		 * 
		 * @param array $args
		 * @since 1.0
		 */
		function bkap_friend_email_button_text_callback( $args ) {
			
			// First, we read the option
			$email_button_text = get_option( 'bkap_friend_email_button_text' );
			// This condition added to avoid the notice displyed when no text is set
			if( isset( $email_button_text ) &&  '' == $email_button_text ) {
				$email_button_text = 'Book me in !!';
			}
			// Next, we update the name attribute to access this element's ID in the context of the display options array
			// We also access the show_header element of the options collection in the call to the checked() helper function
			$html = '<input type="text" id="bkap_friend_email_button_text" name="bkap_friend_email_button_text" value="' . $email_button_text . '"/>';
			// Here, we'll take the first argument of the array and add it to a label next to the field
			$html .= '<label for="bkap_friend_email_button_text"> '  . $args[0] . '</label>';
			
			echo $html;
		}
		
		/**
		 * WP Settings API callback for availability message for single day bookings 
		 * 
		 * @param array $args
		 * @since 1.0
		 */
		function bkap_friend_avail_msg_single_days_callback( $args ) {
			
			// First, we read the option
			$available_msg_single_days = stripslashes( get_option( 'bkap_friend_availability_msg_single_days' ) );
			// This condition added to avoid the notice displyed when no text is set
			if( isset( $available_msg_single_days ) &&  '' == $available_msg_single_days ) {
				$available_msg_single_days = 'We still have <available_spots> spaces left for this date.';
			}
			// Next, we update the name attribute to access this element's ID in the context of the display options array
			// We also access the show_header element of the options collection in the call to the checked() helper function
			$html = '<textarea rows="3" cols="60" id="bkap_friend_availability_msg_single_days" name="bkap_friend_availability_msg_single_days" style="width:250px;">' . $available_msg_single_days . '</textarea>';
			// Here, we'll take the first argument of the array and add it to a label next to the field
			$html .= '<label for="bkap_friend_availability_msg_single_days"> '  . $args[0] . '</label>';
			
			echo $html;
		}
		
		/**
		 * WP Settings API callback for availability message for date and time bookings
		 *
		 * @param array $args
		 * @since 1.0
		 */
		function bkap_friend_avail_msg_date_time_callback( $args ) {
			
			// First, we read the option
			$available_msg_date_time = stripslashes( get_option( 'bkap_friend_availability_msg_date_time' ) );
			// This condition added to avoid the notice displyed when no text is set
			if( isset( $available_msg_date_time ) &&  '' == $available_msg_date_time ) {
				$available_msg_date_time = 'We still have <available_spots> spaces left for this date and time slot.';
			}
			// Next, we update the name attribute to access this element's ID in the context of the display options array
			// We also access the show_header element of the options collection in the call to the checked() helper function
			$html = '<textarea rows="3" cols="60" id="bkap_friend_availability_msg_date_time" name="bkap_friend_availability_msg_date_time" style="width:250px;">' . $available_msg_date_time . '</textarea>';
			// Here, we'll take the first argument of the array and add it to a label next to the field
			$html .= '<label for="bkap_friend_availability_msg_date_time"> '  . $args[0] . '</label>';
			
			echo $html;
		}
		
		/**
		 * WP Settings API callback for availability message for multiple day bookings
		 * 
		 * @param array $args
		 * @since 1.0
		 */
		function bkap_friend_avail_msg_multiple_days_callback( $args ) {
			
			// First, we read the option
			$available_msg_multiple_days = stripslashes( get_option( 'bkap_friend_availability_msg_multiple_days' ) );
			// This condition added to avoid the notice displyed when no text is set
			if( isset( $available_msg_multiple_days ) &&  '' == $available_msg_multiple_days ) {
				$available_msg_multiple_days = 'We still have <available_spots> spaces left for this date range.';
			}
			// Next, we update the name attribute to access this element's ID in the context of the display options array
			// We also access the show_header element of the options collection in the call to the checked() helper function
			$html = '<textarea rows="3" cols="60" id="bkap_friend_availability_msg_multiple_days" name="bkap_friend_availability_msg_multiple_days" style="width:250px;">' . $available_msg_multiple_days . '</textarea>';
			// Here, we'll take the first argument of the array and add it to a label next to the field
			$html .= '<label for="bkap_friend_availability_msg_multiple_days"> '  . $args[0] . '</label>';
			
			echo $html;
		}
		
		/**
		 * WP Settings API callback for button css
		 * 
		 * @param array $args
		 * @since 1.0
		 */
		function bkap_friend_button_css_callback( $args ) {
			
			// First, we read the option
			$button_css = stripslashes( get_option( 'bkap_friend_button_css' ) );
			// This condition added to avoid the notice displyed when no text is set
			if( isset( $button_css ) && '' == $button_css ) {
				$button_css = 'display: block;background: #f4f5f4;width: 160px;height: 35px;padding-top: 5px;padding-bottom: 5px;text-align: center;border-radius: 5px;color: black;font-family: Calibri;font-size: 110%;border: 1px solid;margin-top: 5px;';
			}
			// Next, we update the name attribute to access this element's ID in the context of the display options array
			// We also access the show_header element of the options collection in the call to the checked() helper function
			$html = '<textarea rows="4" cols="60" id="bkap_friend_button_css" name="bkap_friend_button_css" style="width:600px;">' . $button_css . '</textarea>';
			// Here, we'll take the first argument of the array and add it to a label next to the field
			$html .= '<label for="bkap_friend_button_css"> '  . $args[0] . '</label>';
			
			echo $html;
		}
		
		/**
		 * WP Settings API callback for Tell a Friend page url
		 *
		 * @param array $args
		 * @since 1.0
		 */
		function bkap_friend_tell_friend_page_url_callback( $args ) {
		    
		    // First, we read the option
		    $tell_a_friend_page_url = stripslashes( get_option( 'bkap_friend_tell_friend_page_url' ) );
		    // This condition added to avoid the notice displayed when no text is set
		    if( isset( $tell_a_friend_page_url ) &&  '' == $tell_a_friend_page_url ) {
		        $tell_a_friend_page_url = 'send-booking-to-friend';
		    }
		    // Next, we update the name attribute to access this element's ID in the context of the display options array
		    // We also access the show_header element of the options collection in the call to the checked() helper function
		    $html = '<input type="text" id="bkap_friend_tell_friend_page_url" name="bkap_friend_tell_friend_page_url" value="' . $tell_a_friend_page_url . '"/>';
		    // Here, we'll take the first argument of the array and add it to a label next to the field
		    $html .= '<label for="bkap_friend_tell_friend_page_url"> '  . $args[0] . '</label>';
		    
		    echo $html;
		}
		
		/**
		 * WP Settings API validation callback for Tell a Friend page url
		 * 
		 * @param str $input
		 * @since 1.0
		 */
		function bkap_friend_tell_friend_page_url_save_callback( $input ) {
		    
		    if ( isset( $input ) && '' != $input ) {
		        $new_input = $input;
		    } else {
		        $new_input = 'send-booking-to-friend';
		        $message = __( 'The Tell A Friend Address (URL) has been set to the default value as it cannot be blank.', 'bkap-send-to-friend' );
		        add_settings_error( 'bkap_friend_tell_friend_page_url', 'page_url_error', $message, 'updated' );
		    }
		    
		    return $new_input;
		}
		
		/**
		 * Add a new tab in Booking->Settings menu
		 *
		 * This function adds a new tab Send a Friend Addon Settings tab
		 * in the Booking->Settings menu, thereby allowing the site owner
		 * to make settings for the addon
		 *
		 *  @since 1.0
		 */
		function bkap_send_friend_tab() {
			if ( isset( $_GET[ 'action' ] ) ) {
				$action = $_GET[ 'action' ];
			} else {
				$action = '';
			} 
			if ( 'addon_settings' == $action ) {
				?>
				<div id="content">
					<form method="post" action="options.php">
					    <?php settings_fields( 'bkap_friend_settings' ); ?>
				        <?php do_settings_sections( 'woocommerce_booking_page-bkap_friend_settings_section' ); ?>
				        <p><?php _e( 'Please note that the Tell a Friend page is not compatible with the Default and Numeric permalink setting defined in Settings->Permalinks.', 'bkap-send-to-friend' ); ?></p> 
						<?php submit_button(); ?>
			        </form>
			    </div>
				<?php 
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
			$product_id = $item[ 'product_id' ];
			
			// Booking Settings
			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
			
			// get the addon settings
			$enable_send_a_friend = get_option( 'bkap_friend_enable_send_a_friend' );
			if ( isset( $booking_settings ) && 'on' == $booking_settings[ 'booking_enable_date' ] && isset( $enable_send_a_friend ) && 'on' == $enable_send_a_friend ) {
				
				// Get the booking details
				$booking_date = '';
				if ( isset( $item[ 'wapbk_booking_date' ] ) ) {
				    $booking_date = $item[ 'wapbk_booking_date' ];
				}
				$checkout_date = $booking_time = '';
				if ( isset( $item[ 'wapbk_checkout_date' ] ) ) {
					$checkout_date = $item[ 'wapbk_checkout_date' ];
				}
				if ( isset( $item[ 'wapbk_time_slot' ] ) ) {
					$booking_time = $item[ 'wapbk_time_slot' ];
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
				$availability = $this->get_availability( $base_product_id, $booking_date, $checkout_date, $booking_time );
				
				$display = 'NO';
				if ( $availability > 0 ) {
					$display = 'YES';
				} else if ( "Unlimited" === $availability ) {
					$display = 'YES';
				}
				
				if ( isset( $booking_settings[ 'booking_enable_multiple_day' ] ) && 'on' == $booking_settings[ 'booking_enable_multiple_day' ] ) {
					$message = get_option( 'bkap_friend_availability_msg_multiple_days' );
				} else if( 'on' == $booking_settings[ 'booking_enable_time' ] ) {
					$message = get_option( 'bkap_friend_availability_msg_date_time' );
				} else {
					$message = get_option( 'bkap_friend_availability_msg_single_days' );
				}
				$message = str_replace( '<available_spots>', $availability, $message );
				if ( "YES" == $display ) { 
					?>
					<br>
					<?php
					echo $message;
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
					    $product_id_to_link = icl_object_id( $product_id, 'post', true );
					} else {
					    $url = home_url( '/' );
					    $product_id_to_link = $product_id;
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
					<br>
					<a href="<?php echo esc_url_raw( add_query_arg( 'item_id', $item_id, get_permalink( $product_id_to_link ) ) ); ?>" style="<?php echo get_option( 'bkap_friend_button_css' ); ?>"><?php _e( get_option( 'bkap_friend_book_another_button_text' ), 'bkap-send-to-friend' ); ?></a>
					<?php $order_id = ( version_compare( WOOCOMMERCE_VERSION, "3.0.0" ) < 0 ) ? $order->id : $order->get_id(); ?>
					<a href="<?php echo esc_url_raw( add_query_arg( 'order_id', $order_id, $url ) ); ?>" style="<?php echo get_option( 'bkap_friend_button_css' ); ?>"><?php _e( get_option( 'bkap_friend_send_friend_button_text' ), 'bkap-send-to-friend' ); ?></a>
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
			$product_checkout_date = '';
			$product_from_time = '';
			$product_to_time = '';
			if ( isset( $checkout_date ) && '' != $checkout_date ) {
				$product_checkout_date = $checkout_date;
			}
			
			if ( isset( $booking_time ) && '' != $booking_time ) {
				// Time should be set to G:i
				$time_explode = explode( '-', $booking_time );	
				$product_from_time = date( 'G:i', strtotime( trim( $time_explode[0] ) ) );
				if ( isset( $time_explode[1] ) && '' != trim( $time_explode[1] ) ) {
					$product_to_time = date( 'G:i', strtotime( trim( $time_explode[1] ) ) );
				}
			}
			
			// check the booking type
			$booking_settings = get_post_meta( $product_id , 'woocommerce_booking_settings', true );
			
			// multiple day
			if ( isset( $booking_settings[ 'booking_enable_multiple_day' ] ) && 'on' == $booking_settings[ 'booking_enable_multiple_day' ] ) {
				$date_checkout = date( 'd-n-Y', strtotime( $product_checkout_date ) );
				$date_checkin = date( 'd-n-Y', strtotime( $product_date ) );
				$order_dates = bkap_common::bkap_get_betweendays( $date_checkin, $date_checkout );
				$todays_date = date( 'Y-m-d' );
				
				$query_date ="SELECT DATE_FORMAT( start_date, '%d-%c-%Y' ) as start_date, DATE_FORMAT( end_date, '%d-%c-%Y' ) as end_date FROM ".$wpdb->prefix."booking_history
				                WHERE start_date >='" . $todays_date . "' AND post_id = '" . $product_id . "'";
				
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
				if ( isset( $booking_settings[ 'booking_date_lockout' ] ) && ( '' != $booking_settings[ 'booking_date_lockout' ] || 0 != $booking_settings[ 'booking_date_lockout' ] ) ) {
					$lockout = $booking_settings[ 'booking_date_lockout' ];
					
					$date_to_check = $order_dates[0];
					if ( array_key_exists( $date_to_check, $dates_new_arr ) ) {
						$availability = $lockout - $dates_new_arr[$date_to_check];
					} else {
						$availability = $lockout;
					}
				} else {
					$availability = 'Unlimited';
				}
			} else if( 'on' == $booking_settings[ 'booking_enable_time' ] ) { // date and time
				$availability_query = "SELECT total_booking, available_booking FROM `".$wpdb->prefix."booking_history`
										WHERE post_id = %d
										AND start_date = %s
										AND from_time = %s
										AND to_time = %s
										AND status != 'inactive'";
				$get_availability = $wpdb->get_results( $wpdb->prepare( $availability_query, $product_id, $product_date, $product_from_time, $product_to_time ) );
				
				if ( isset( $get_availability ) && count( $get_availability ) > 0 ) {
					if ( 0 == $get_availability[0]->total_booking ) {
						$availability = 'Unlimited';
					} else {
						$availability = $get_availability[0]->available_booking;
					}
				}
			} else { // only day bookings
				$availability_query = "SELECT total_booking, available_booking FROM `".$wpdb->prefix."booking_history`
										WHERE post_id = %d
										AND start_date = %s
										AND status != 'inactive'";
				$get_availability = $wpdb->get_results( $wpdb->prepare( $availability_query, $product_id, $product_date ) );
				if ( isset( $get_availability ) && count( $get_availability ) > 0 ) {
					if ( 0 == $get_availability[0]->total_booking ) {
						$availability = 'Unlimited';
					} else {
						$availability = $get_availability[0]->available_booking;	
					}
				}
			}
			
			return $availability;
		}

		/**
		 * Load Tell a Friend page by creating a virtual page so it works with all themes
		 * 
		 * the content passed to that page is of the view file 'request-friend.php'
		 * that content is loaded in a variable using output buffering functions - ob_start, ob_get_contents, ob_end_clean
		 * creates new page object & adds to $posts array in bkap_tell_a_friend_page()
		 * 
		 * @since 1.0
		 */
		function load_tell_a_friend_page() {

			$url = '';
			if ( isset( $_SERVER[ 'REQUEST_URI' ] ) && '' != $_SERVER[ 'REQUEST_URI' ] ) {
				$url = trim( parse_url( $_SERVER[ 'REQUEST_URI' ], PHP_URL_PATH ), '/' );
			}
			$tell_friend_page_url = get_option( 'bkap_friend_tell_friend_page_url' );
			if( ( isset( $tell_friend_page_url ) && '' == $tell_friend_page_url ) || ! isset( $tell_friend_page_url ) ) {
			    $tell_friend_page_url = 'send-booking-to-friend';
			}
			$page_url_setting = '/' . trim( $tell_friend_page_url ) . '/';
			
			if ( preg_match( $page_url_setting, $url ) ) {
				
				ob_start();
				$templatefilename = 'request-friend.php';
				if ( file_exists( dirname( __FILE__ ) . '/' . $templatefilename ) ) {
					$template = dirname( __FILE__ ) . '/' . $templatefilename;
					include( $template );
				}
				$content = ob_get_contents();
				ob_end_clean();
				
				$args = array( 'slug'    => 'send-booking-to-friend',
							   'title'   => 'Tell a friend',
							   'content' => $content );
				$pg = new bkap_tell_a_friend_page( $args );
			}
		}
		
		/**
		 * Send email to friend
		 * 
		 * Sends emails to the friends
		 * 
		 * @since 1.0
		 */ 
		function bkap_send_email_to_friend() {	    
		    
		    // create the tell a friend page url to return back once the email is sent
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
			
			// check if the email address field is populated for the friends
			if( isset( $_POST[ 'friend_email' ] ) ) {
				if ( '' == trim( $_POST[ 'friend_email' ] ) ) {
					$message = 'Please enter the email address of atleast one friend.';
					wc_add_notice( __( $message, 'bkap-send-to-friend' ), $notice_type = 'error' );
					echo( esc_url_raw( add_query_arg( 'order_id', $_POST[ 'order_id' ], $url ) ) );
					die;
				}
			}
			
			// get the addon settings
			$enable_admin_cc = get_option( 'bkap_friend_enable_admin_cc' );
			
			// get the order object
			$order = new WC_Order( $_POST[ 'order_id' ] );
			$items = $order->get_items();
			
			//products selected by client
			$products = explode( ',', $_POST[ 'details' ] );
			
			//get the content
			$email_content = $this->get_template();
			
			// Replace all the shortcodes with real time data
			// client name
			$email_content = str_replace( '{{client_name}}', $_POST[ 'client_name' ], $email_content );
			// site name
			$email_content = str_replace( '{{site_name}}', get_option( 'blogname' ), $email_content );
			
			// get the booking date and time labels, so they can be used to retrieve data
			$booking_meta_label = get_option( 'book_item-meta-date' );
			$booking_time_meta_label = get_option( 'book_item-meta-time' );
			$checkout_meta_label = trim( strip_tags( get_option( 'checkout_item-meta-date' ) ) );
				
			// Product table
			$product_table = "<table cellpadding='10' border='1'  style='border-collapse:collapse; border-color:Black;'>
								<tr style='background-color:#f4f5f4;'>
								<th>";
			$product_table .= __( "Product", 'bkap-send-to-friend' );
			$product_table .= "</th><td></td>
								</tr>";
			$item_count = 0;
			foreach ( $items as $key => $value ) {		
				// Add the product in the table only if it has been selected by the client on the 'Tell a Friend' Page
				if ( is_array( $products ) && count( $products ) > 0 && in_array( $value[ 'product_id' ], $products ) ) {
					// Get the product ID for a multi language site
				    if ( function_exists('icl_object_id') ) {
				        $product_id_to_link = icl_object_id( $value[ 'product_id' ], 'post', true );
				    } else {
				        $product_id_to_link = $value[ 'product_id' ];
				    }
					// Add order Id to product link
					$button_link = esc_url_raw( add_query_arg( 'item_id', $key, get_permalink( $product_id_to_link ) ) );
					// Add the product name, booking details
					$booking_date = $value[ 'wapbk_booking_date' ];
					$checkout_date = $time_slot = '';
					if ( isset( $value[ 'wapbk_checkout_date' ] ) ) {
						$checkout_date = $value[ 'wapbk_checkout_date' ];
					}
					if ( isset( $value[ 'wapbk_time_slot' ] ) ) {
						$time_slot = $value[ 'wapbk_time_slot' ];
					}
					// WPML is enabled, then get the base language product ID to calculate availability
					if ( function_exists('icl_object_id') ) {
					    global $sitepress;
					    $default_lang = $sitepress->get_default_language();
					    $base_product_id = icl_object_id( $value[ 'product_id' ], 'product', false, $default_lang );
					} else {
					    $base_product_id = $value[ 'product_id' ];
					}
					// Get the availability for the product
					$availability = $this->get_availability( $base_product_id, $booking_date, $checkout_date, $time_slot );
						
					$display = 'NO';
					if ( $availability > 0 ) {
						$display = 'YES';
					} else if ( "Unlimited" === $availability ) {
						$display = 'YES';
					}
					if ( 'YES' == $display ) {
						$item_count++;
						$product_table .= "<tr>
										<td>";
						$product_table .= "<a href='" . $button_link . "'>" . $value[ 'name' ] . "</a><br>";
						$product_table .= "<b>" . $booking_meta_label . ":</b><br>".
											$value[ $booking_meta_label ];
						if ( isset( $value[ $checkout_meta_label ] ) ) {
							$product_table .= "<br><b>" . $checkout_meta_label . ":</b><br>".
									$value[ $checkout_meta_label ];
						}
						if ( isset( $value[ $booking_time_meta_label ] ) ) {
							$product_table .= "<br><b>" . $booking_time_meta_label . ":</b><br>".
									$value[ $booking_time_meta_label ];
						}
						$product_table .= '</td>';
						
						// check the booking type
						$booking_settings = get_post_meta( $value[ 'product_id' ], 'woocommerce_booking_settings', true );
							
						if ( isset( $booking_settings[ 'booking_enable_multiple_day' ] ) && 'on' == $booking_settings[ 'booking_enable_multiple_day' ] ) {
							$message = get_option( 'bkap_friend_availability_msg_multiple_days' );
						} else if( 'on' == $booking_settings[ 'booking_enable_time' ] ) {
							$message = get_option( 'bkap_friend_availability_msg_date_time' );
						} else {
							$message = get_option( 'bkap_friend_availability_msg_single_days' );
						}
						$message = str_replace( '<available_spots>', $availability, $message );
						
						// Display availability message and the button to allow the user to directly book an order.
						$product_table .= "<td>" . $message . "<br>";
						$product_table .= "<a href='" . $button_link . "' style='".get_option( 'bkap_friend_button_css' ) ."'>" . get_option( 'bkap_friend_email_button_text' ) . "</a></td></tr>";
					}					
				}
			} 
			$product_table .= '</table>';
			if ( $item_count > 0 ) {
				$email_content = str_replace( '{{product_list}}', $product_table, $email_content );
				
				// Personalized msg
				$per_msg = stripslashes( $_POST[ 'msg_txt' ] );
				$email_content = str_replace( '{{personalized_message}}', $per_msg, $email_content );
				
				// Multiple email addresses are taken in using comma as the seperator, hence can be used as is
				$recipients = $_POST[ 'friend_email' ];
				
				// Create the header, mark admin and client in cc
				$headers = "From: <" . get_option( 'admin_email' ) . ">" . "\r\n";
				if ( isset( $enable_admin_cc ) && 'on' == $enable_admin_cc ) {
					$headers .= "Cc: " . get_option( 'admin_email' ) . "\r\n";
				}
				$headers .= "Bcc: " . $recipients . "\r\n";
				$headers .= "Content-Type: text/html" . "\r\n";
				$headers .= "Reply-To:  " . get_option( 'admin_email' ) . " " . "\r\n";
				
				// email subject
				$email_subject = __( 'Join me at ' . get_option( 'blogname' ), 'bkap-send-to-friend' );
				
				// Send the email
				wp_mail( '', $email_subject, $email_content, $headers );
				$message = 'Email sent successfully.';
				wc_add_notice( __( $message, 'bkap-send-to-friend' ), $notice_type = 'success' );
			} else {
				$message = 'Email could not be sent as all the items have been fully booked.';
				wc_add_notice( __( $message, 'bkap-send-to-friend' ), $notice_type = 'error' );
			}
			echo( esc_url_raw( add_query_arg( 'order_id', $_POST[ 'order_id' ], $url ) ) );
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
			$order = wc_get_order( $_POST[ 'order_id' ] );
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
			
			// If the item ID is present, it means the booking data needs to be pre-populated
			if ( isset( $_GET[ 'item_id' ] ) && '' != $_GET[ 'item_id' ] ) {
			    // If WPML is enabled, the make sure that the base language product ID is used to calculate the availability
			    if ( function_exists( 'icl_object_id' ) ) {
			        global $sitepress;
			        $default_lang = $sitepress->get_default_language();
			        $duplicate_of = icl_object_id( $post->ID, 'product', false, $default_lang );
			    } else {
			        $duplicate_of = $post->ID;
			    }
				$item_id = $_GET[ 'item_id' ];

				// default fields
				$hidden_date = '';
				// Set the attribute field string to make sure the ajax for time slots works fine
				$product = wc_get_product( $duplicate_of );
				if ( 'variable' == $product->get_type() ) {
					$variations = $product->get_available_variations();
					$attributes = $product->get_variation_attributes();
					$attribute_fields_str = "";
					$attribute_name = "";
					$attribute_value = "";
					$attribute_value_selected = "";
					$attribute_fields = array();
					$i = 0;
					foreach ( $variations as $var_key => $var_val ) {
						foreach ( $var_val[ 'attributes' ] as $a_key => $a_val ) {
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
				$order_status = $order->get_status();
				if( isset( $order_status ) && ( 'wc-cancelled' != $order_status ) && ( 'wc-refunded' != $order_status ) && ( 'trash' != $order_status ) && ( '' != $order_status ) && ( 'wc-failed' != $order_status ) ) {	
					$booking_settings = get_post_meta( $duplicate_of, 'woocommerce_booking_settings', true );
					
					// get the booking details from the woocommerce_order_itemmeta table
					$booking_details = array();
					$booking_details[ '_variation_id' ] = wc_get_order_item_meta( $item_id, '_variation_id' );
					$booking_details[ '_wapbk_booking_date' ] = wc_get_order_item_meta( $item_id, '_wapbk_booking_date' );
					$booking_details[ '_wapbk_checkout_date' ] = wc_get_order_item_meta( $item_id, '_wapbk_checkout_date' );
					$booking_details[ '_wapbk_time_slot' ] = wc_get_order_item_meta( $item_id, '_wapbk_time_slot' );
					
					$variation_id = '';
					$display_date = '';
					if ( isset( $booking_details[ '_variation_id' ] ) && 0 != $booking_details[ '_variation_id' ] ) {
						$attribute_array = array();
						$attr = wc_get_product_variation_attributes( $booking_details[ '_variation_id' ] );
						if ( isset( $attr ) && is_array( $attr ) && count( $attr ) > 0 ) {
							foreach( $attr as $attr_key => $attr_value ) {
								$attribute_name_array = explode( '_', $attr_key );
								$attribute_array[$attribute_name_array[1]] = $attr_value;
							}
							$variation_id = $booking_details[ '_variation_id' ];
						}
					}
					
					if ( isset( $booking_details[ '_wapbk_booking_date' ] ) ) {
						$booking_date = $booking_details[ '_wapbk_booking_date' ];	
						// Date formats
						$date_formats = bkap_get_book_arrays( 'bkap_date_formats' );
						// get the global settings to find the date & time formats
						$global_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
						$date_format_set = $date_formats[$global_settings->booking_date_format];
						$display_date = date ( $date_format_set, strtotime( $booking_date ) );
					}
					
					$checkout_date = '';
					$hidden_date_checkout = '';
					$booking_time = '';
					$display_time = '';
					$fixed_block_name = '';
					
					// if multiple days is enabled, fetch the checkout date
					if ( isset( $booking_settings[ 'booking_enable_multiple_day' ] ) && 'on' == $booking_settings[ 'booking_enable_multiple_day' ] ) {
						if ( isset( $booking_details[ '_wapbk_checkout_date' ] ) ) {
							$checkout_date = $booking_details[ '_wapbk_checkout_date' ];
							$hidden_date_checkout = date( 'j-n-Y', strtotime( $checkout_date ) );
						}
						
						// check if fixed blocks is enabled, if yes then calculate the difference between the checkin and checkout date and populate the fixed block name
						if( isset( $booking_settings[ 'booking_fixed_block_enable' ] ) && 'yes' == $booking_settings[ 'booking_fixed_block_enable' ] ) {
							$number_of_days =  strtotime( $checkout_date ) - strtotime( $booking_date );
							$number = floor( $number_of_days / ( 60*60*24 ) );
							$fixed_block_query = "SELECT start_day, price FROM `".$wpdb->prefix."booking_fixed_blocks`
													WHERE number_of_days = %d
													AND post_id = %d";
							$results_fixed_blocks = $wpdb->get_results( $wpdb->prepare( $fixed_block_query, $number, $duplicate_of ) );
								
							if( isset( $results_fixed_blocks ) && is_array( $results_fixed_blocks ) && count( $results_fixed_blocks ) > 0 ) {
								$fixed_block_name = $results_fixed_blocks[0]->start_day . '&' . $number . '&' . $results_fixed_blocks[0]->price;
							}
						}
					}
					
					// if time settings is enabled, fetch the time slot selected
					if( 'on' == $booking_settings[ 'booking_enable_time' ] ) {
						if ( isset( $booking_details[ '_wapbk_time_slot' ] ) ) {
							$booking_time = $booking_details[ '_wapbk_time_slot' ];
							
							// create a time stamp for the booking date and start time
							$time_explode = explode( '-', $booking_time );
							$book_date = $booking_date . ' ' . $time_explode[0];
							$date_timestamp = strtotime( $book_date );
							
							// set the format in which the time is to be displayed
							$time_format = '12';
							if ( isset( $global_settings ) ) {
								$time_format = $global_settings->booking_time_format;
							}
							$display_time = $booking_time;
							$to_time = '';
							if ( '12' == $time_format ) {
								
								$from_time = date( 'h:i A', strtotime( $time_explode[0] ) );
								if ( isset( $time_explode[1] ) ) {
									$to_time = date( 'h:i A', strtotime( $time_explode[1] ) );
								}
								$display_time = $from_time . ' - ' . $to_time;
							} else {
								$from_time = date( 'H:i', strtotime( $time_explode[0] ) );
								if ( isset( $time_explode[1] ) ) {
									$to_time = date( 'H:i', strtotime( $time_explode[1] ) );
								}
								$display_time = $from_time . ' - ' . $to_time;
							}
						}
					}
					
					// check if bookings are still available for the given date/s and/or time
					$availability = $this->get_availability( $duplicate_of, $booking_date, $checkout_date, $booking_time);
					
					$pre_populate = 0;
					if ( $availability > 0 ) {
						$pre_populate = 1;
					} else if ( "Unlimited" === $availability ) {
						$pre_populate = 1;
					}
					
					$current_time = current_time( 'timestamp' );
					$past_date = 1; // means its a future date
					$past_time = 1; // means its a future time
					if ( isset( $current_time ) && isset( $date_timestamp ) ) {
						if ( $date_timestamp < $current_time ) {
							$past_time = 0; // means the booking date and time hv already passed
						}
					} else if ( strtotime( $booking_date ) < $current_time ) { // As the Booking date is a past date
						$pre_populate = $past_date = 0;
					}
					
					$hidden_date = date ( 'j-n-Y', strtotime( $booking_date ) );
		
					if ( 1 == $pre_populate ) {
						?>
						<script type="text/javascript">
						function bkap_init() {

							// If it's a variable product, populate the variations first
							<?php
							if ( isset( $attribute_array ) && is_array( $attribute_array ) && count( $attribute_array ) > 0 ) {
								foreach( $attribute_array as $attr_key => $attr_value ) {
									?>
									jQuery( "#<?php echo $attr_key;?>" ).val( '<?php echo $attr_value; ?>' );
									<?php
								} 
								?>
								jQuery( ".variation_id" ).val( '<?php echo $variation_id; ?>' );
								<?php 
							} 
							?>

							var variation_id = 0;
                            if ( jQuery( ".variation_id" ).length > 0 ) {
                                variation_id = jQuery( ".variation_id" ).val();
                            }

                            var field_name = "#wapbk_bookings_placed_" + variation_id;
                            var variation_bookings_placed = "";
                            if ( jQuery( field_name ).length > 0 ) {
                            	variation_bookings_placed = jQuery( field_name ).val();
                            }
				    
                            var attr_bookings_placed = "";
                            if ( jQuery( "#wapbk_attribute_list").length > 0 ) {
                               var attribute_list = jQuery( "#wapbk_attribute_list").val().split(",");
                               
                                for ( i = 0; i < attribute_list.length; i++ ) {
                                
                                    if ( attribute_list[i] != "" && jQuery( "#" + attribute_list[i] ).val() > 0 ) {
                            
                                       var field_name = "#wapbk_bookings_placed_" + attribute_list[i];
                                       if ( jQuery( field_name ).length > 0 ) {
                                           attr_bookings_placed = attr_bookings_placed + attribute_list[i] + "," + jQuery( field_name ).val() + ";";
                                       }
                                   }
                               }
                            }
							// Populate the Booking date
							jQuery( "#wapbk_hidden_date" ).val( '<?php echo $hidden_date; ?>' );
							var split = jQuery( "#wapbk_hidden_date" ).val().split( "-" );
							var bookingDate = new Date( split[2], split[1]-1, split[0] );
							<?php
							if ( isset( $booking_settings[ 'enable_inline_calendar' ] ) && 'on' == $booking_settings[ 'enable_inline_calendar' ] ) { 
							?>
								var timestamp = Date.parse( bookingDate ); 
								if ( false == isNaN( timestamp ) ) { 
									var default_date_selection = new Date( timestamp );
									jQuery( "#inline_calendar" ).datepicker( "setDate", default_date_selection );
								}
							<?php 
							} else {
							?>
								jQuery( "#booking_calender" ).datepicker( "setDate", bookingDate );
							<?php 
							}?>

							// If time is enabled then populate the selected slot
							if ( "on" == jQuery( "#wapbk_bookingEnableTime" ).val() && "YES" == jQuery( "#wapbk_booking_times" ).val() ) {
								var sold_individually = jQuery( "#wapbk_sold_individually" ).val();
								jQuery( "#ajax_img" ).show();
								jQuery( ".single_add_to_cart_button" ).hide();	
								var time_slots_arr = jQuery( "#wapbk_booking_times" ).val();

								var field_name = "#wapbk_timeslot_lockout_" + variation_id;
                                var time_slot_lockout = "";
                                if ( jQuery( field_name ).length > 0 ) {
                                    time_slot_lockout = jQuery( field_name ).val();
                                }
					    
                                var attr_lockout = "";
                                if ( jQuery( "#wapbk_attribute_list").length > 0 ) {
                                   var attribute_list = jQuery( "#wapbk_attribute_list").val().split(",");
                                   
                                    for ( i = 0; i < attribute_list.length; i++ ) {
                                    
                                        if ( attribute_list[i] != "" && jQuery( "#" + attribute_list[i] ).val() > 0 ) {
                                
                                           var field_name = "#wapbk_timeslot_lockout_" + attribute_list[i];
                                           if ( jQuery( field_name ).length > 0 ) {
                                               attr_lockout = attr_lockout + attribute_list[i] + "," + jQuery( field_name ).val() + ";";
                                           }
                                       }
                                   }
                                }
								var data = {
									current_date: jQuery( "#wapbk_hidden_date" ).val(),
									post_id: '<?php echo $duplicate_of; ?>', 
									variation_id: variation_id,
                                    variation_timeslot_lockout: time_slot_lockout,
					                attribute_timeslot_lockout: attr_lockout,
									action: 'bkap_check_for_time_slot'
									<?php echo $attribute_fields_str; ?>
								};
								jQuery.post( '<?php echo get_admin_url() . 'admin-ajax.php'; ?>', data, function( response ) {
									jQuery( "#ajax_img" ).hide();
									jQuery( "#show_time_slot" ).html( response );

									// for today if the time slot is past, display a message saying the same
									<?php 
									if ( isset( $past_time ) && 0 == $past_time ) {
									?>
										// Pre-populate the time slots with the order time slots
										jQuery( "#time_slot" ).val( '' );
										jQuery( "#show_stock_status" ).html( "The time slot <?php echo $display_time; ?> is a past time slot. Please select another slot." );
									<?php 
									} else {?>
										// Pre-populate the time slots with the order time slots
										jQuery( "#time_slot" ).val( '<?php echo $display_time; ?>' );
										var time_slot_value = jQuery( "#time_slot" ).val();

										// Availability display for the time slot selected if setting is enabled			
										if ( "undefined" != typeof time_slot_value && "yes" == jQuery( "#wapbk_availability_display" ).val() ) {

											var data = {
												checkin_date: jQuery( "#wapbk_hidden_date" ).val(),
												timeslot_value: time_slot_value,
												post_id: '<?php echo $duplicate_of; ?>', 
												variation_id: variation_id,
		                                        bookings_placed: variation_bookings_placed,
		                                        attr_bookings_placed: attr_bookings_placed,
												action: 'bkap_get_time_lockout'
											};
											jQuery.post( '<?php echo get_admin_url() . 'admin-ajax.php'; ?>', data, function( response ) {
												jQuery( "#show_stock_status" ).html( response );
											});
										}
											
										if ( "" != jQuery( "#time_slot" ).val() ) {
		                                    jQuery( ".payment_type" ).show();
											if( "yes" == sold_individually ) {
												jQuery( ".quantity" ).hide();
												jQuery( ".payment_type" ).hide();
												jQuery( ".partial_message" ).hide();
											} else {
												jQuery( ".quantity" ).show();
												jQuery( ".payment_type" ).show();
											}
										} else if ( "" == jQuery( "#time_slot" ).val() ) {
											jQuery( ".single_add_to_cart_button" ).hide();
											jQuery( ".quantity" ).hide();
		                                    jQuery( ".payment_type" ).hide();
											jQuery( ".partial_message" ).hide();
										}

										// This is called to ensure the variable pricing for time slots is displayed
										bkap_single_day_price();

										// This is called to create the change event for the time slot drop down
										bkap_time_slot_events();
									<?php 
									}?>
								});
							} else {

								// check if multiple day is enabled, if yes then pre-populate the end date
								if ( jQuery( "#booking_calender_checkout" ).length ) {

									// populate fixed blocks name if its enabled
									if ( jQuery( "#block_option" ).length ) {
										jQuery( "#block_option" ).val( '<?php echo $fixed_block_name; ?>' );
									}

									// populate the checkout date
									jQuery( "#wapbk_hidden_date_checkout" ).val( '<?php echo $hidden_date_checkout; ?>' );
									var split = jQuery( "#wapbk_hidden_date_checkout" ).val().split( "-" );
									var bookingDate = new Date( split[2], split[1]-1, split[0] );
									<?php
									if ( isset( $booking_settings[ 'enable_inline_calendar' ] ) && 'on' == $booking_settings[ 'enable_inline_calendar' ] ) { 
									?>
										var timestamp = Date.parse( bookingDate ); 
										if ( false == isNaN( timestamp ) ) { 
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
									bkap_single_day_price();
									bkap_calculate_price();
								} else {
									jQuery( ".single_add_to_cart_button" ).show();
									jQuery( ".quantity" ).show();

									// This is called to ensure the special pricing for days/dates is displayed
									bkap_single_day_price();
								}

								// Availability Display for the date selected only if setting is enabled
								if ( "yes" == jQuery( "#wapbk_availability_display" ).val() ) {

									var data = {
											checkin_date: jQuery( "#wapbk_hidden_date" ).val(),
											post_id: '<?php echo $duplicate_of; ?>',
											variation_id: variation_id,
		                                    bookings_placed: variation_bookings_placed,
							                attr_bookings_placed: attr_bookings_placed,
											action: 'bkap_get_date_lockout'
											};
					
									jQuery.post( '<?php echo get_admin_url() . 'admin-ajax.php'; ?>', data, function( response ) {
										jQuery( "#show_stock_status" ).html( response );
									});
								}
							}
						}
						window.onload = bkap_init;
						</script>
						<?php 
					} else {
						if ( isset( $past_date ) && 0 == $past_date ) {
						?>
							<script type="text/javascript">
							function bkap_show_status() {
								jQuery( "#show_stock_status" ).html( "The date <?php echo $display_date; ?> is past today. Please select another date" );
							}
							window.onload = bkap_show_status;
							</script>
						<?php 
						} else {
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