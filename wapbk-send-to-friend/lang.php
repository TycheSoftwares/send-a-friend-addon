<?php	
global $wapbk_translations, $wapbk_lang;

/**
 * Define strings for translation
 * 
 * This function is used to call the string defined for translation.
 * 
 * @since 1.0
 */
function wapbk_send_friend( $str ) {
	global $wapbk_translations, $wapbk_lang;
        
        $wapbk_lang = 'en';
        $wapbk_translations = array(
			'en' => array(
	
			// Message shown on Order Received page if any bookings are available for that date and slot
			// Message Text: We've still got "X" space remaining for "this slot."/"these dates."/"this date." 
			'book.availability-order-received1'	=> "We've still got ",
			'book.availability-order-received2'	=> " spaces remaining for ",
			'book.availability-time-order-received' => "this slot.",
			'book.availability-multiple-order-received' => "these dates.",
			'book.availability-single-order-received' => "this date.",
		),
	);
	
	return $wapbk_translations[$wapbk_lang][$str];
}
?>