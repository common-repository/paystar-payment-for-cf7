<?php

global $wpdb;
global $postid;
		
$wpcf7 = WPCF7_ContactForm::get_current();
$submission = WPCF7_Submission::get_instance();
$user_email = '';
$user_mobile = '';
$description = '';
$user_price = '';

if ($submission) {
	$data = $submission->get_posted_data();
	$user_email = isset($data['user_email']) ? sanitize_text_field($data['user_email']) : "";
	$user_mobile = isset($data['user_mobile']) ? sanitize_text_field($data['user_mobile']) : "";
	$description = isset($data['description']) ? sanitize_text_field($data['description']) : "";
	$user_price = isset($data['user_price']) ? sanitize_text_field($data['user_price']) : "";
}

$price = get_post_meta($postid, "_cf7pp_price", true);
if ($price == "") {
	$price = $user_price;
}
$options = get_option('cf7pp_options');
foreach ($options as $k => $v) {
	$value[$k] = $v;
}
$active_gateway = 'PayStar';
if(!$value['paystar_terminal']) {
	echo 'لطفا کد درگاه پی استار را در تنظیمات وارد نمایید.';
	die();
}


$table_name = $wpdb->prefix . "paystar_contact_form_7";
$table = array();
$table['idform'] = $postid;
$table['transid'] = '';
$table['gateway'] = esc_sql($active_gateway);
$table['cost'] = $price;
$table['created_at'] = time();
$table['email'] = esc_sql($user_email);
$table['user_mobile'] = esc_sql($user_mobile);
$table['description'] = esc_sql($description);
$table['status'] = 'none';
$table_fill = array('%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s');

if ($active_gateway == 'PayStar') {
	require_once(dirname(__FILE__) . '/paystar_payment_helper.class.php');
	$p = new PayStar_Payment_Helper($value['paystar_terminal']);
	$r = $p->paymentRequest(array(
			'amount'		=> intval(ceil($price)),
			'order_id'		=> strval(time()),
			'name'			=> '',
			'mail'			=> $user_email,
			'phone'			=> $user_mobile,
			'description'	=> $description,
			'callback'		=> get_site_url().'/'.$value['return']
		));
	if ($r) {
		$table['transid'] = $r;
		$sql = $wpdb->insert($table_name, $table, $table_fill);
		session_write_close();
		echo '<form name="frmPayStarPayment" method="post" action="'.esc_url($p->getPaymentUrl()).'"><input type="hidden" name="token" value="'.esc_html($p->data->token).'" />';
		echo '<input class="paystar_btn btn button" type="submit" value="'.__('Pay', 'paystar-payment-for-cf7').'" /></form>';
		echo '<script>document.frmPayStarPayment.submit();</script>';
	} else {
		$tmp = 'خطایی رخ داده در اطلاعات پرداختی درگاه' . '<br>Error:' . $p->error . '<br> لطفا به مدیر اطلاع دهید <br><br>';
		$tmp .= '<a href="' . esc_url(get_option('siteurl')) . '" class="mrbtn_red" > '.__('return to site', 'paystar-payment-for-cf7').' </a>';
		echo psgcf7_CreatePage_cf7(__('Error in Payment', 'paystar-payment-for-cf7'), $tmp);
	}
}

?>