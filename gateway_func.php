<?php

/*
Plugin Name: paystar-payment-for-cf7
Plugin URI: https://paystar.ir/
Description: paystar-payment-for-cf7-desc
Version: 1.0
Author: paystar.ir
Text Domain: paystar-payment-for-cf7
Domain Path: /languages
*/

load_plugin_textdomain('paystar-payment-for-cf7', false, basename(dirname(__FILE__)) . '/languages');
$paystar_cf7_plugin_name = __('paystar-payment-for-cf7', 'paystar-payment-for-cf7');
$paystar_cf7_plugin_desc = __('paystar-payment-for-cf7-desc', 'paystar-payment-for-cf7');

function psgcf7_PayStar_CF7_relative_time($ptime)
{
	$etime = time() - $ptime;
	if ($etime < 1) {
		return '0 '.__('second', 'paystar-payment-for-cf7');
	}
	$a = array(12 * 30 * 24 * 60 * 60 => __('year', 'paystar-payment-for-cf7'),
		30 * 24 * 60 * 60 => __('month', 'paystar-payment-for-cf7'),
		24 * 60 * 60 => __('day', 'paystar-payment-for-cf7'),
		60 * 60 => __('hour', 'paystar-payment-for-cf7'),
		60 => __('minute', 'paystar-payment-for-cf7'),
		1 => __('second', 'paystar-payment-for-cf7')
	);
	foreach ($a as $secs => $str) {
		$d = $etime / $secs;
		if ($d >= 1) {
			$r = round($d);
			return $r . ' ' . $str . ($r > 1 ? ' ' : '');
		}
	}
}


function psgcf7_result_payment_func($atts) {
	global $wpdb;
	$post_status = sanitize_text_field($_POST['status']);
	$post_order_id = sanitize_text_field($_POST['order_id']);
	$post_ref_num = sanitize_text_field($_POST['ref_num']);
	$post_tracking_code = sanitize_text_field($_POST['tracking_code']);
	$post_card_number = sanitize_text_field($_POST['card_number']);
	$Theme_Message = get_option('cf7pp_theme_message', '');
	$theme_error_message = get_option('cf7pp_theme_error_message', '');
	$options = get_option('cf7pp_options');
	foreach ($options as $k => $v) {
		$value[$k] = $v;
	}
	$sucess_color = sanitize_hex_color($value['sucess_color']);
	$error_color = sanitize_hex_color($value['error_color']);
	$table_name = $wpdb->prefix . 'paystar_contact_form_7';
	$cf_Form = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE transid=" . $post_ref_num));
	require_once(dirname(__FILE__) . '/paystar_payment_helper.class.php');
	$p = new PayStar_Payment_Helper($value['paystar_terminal']);
	$r = $p->paymentVerify($request_payment_data = array(
			'status' => $post_status,
			'order_id' => $post_order_id,
			'ref_num' => $post_ref_num,
			'tracking_code' => $post_tracking_code,
			'amount' => $cf_Form->cost,
		));
	if ($r) {
		$wpdb->update($table_name, array('status' => 'success', 'transid' => $wpdb->prepare(sanitize_text_field($p->txn_id))), array('transid' => $wpdb->prepare($post_ref_num)), array('%s', '%s'), array('%d'));
		$body = '<b style="color:'.$sucess_color.';">'.stripslashes(str_replace('[transaction_id]', $wpdb->prepare(sanitize_text_field($p->txn_id)), $wpdb->prepare($Theme_Message))).'<b/>';
		return psgcf7_CreateMessage_cf7("", "", $body);
	} else {
		// $p->error
		$wpdb->update($table_name, array('status' => 'error'), array('transid' => $wpdb->prepare($post_ref_num)), array('%s'), array('%d'));
		$body = '<b style="color:'.$error_color.';">'.$theme_error_message.'<b/>';
		return psgcf7_CreateMessage_cf7("", "", $body);
	}
}

add_shortcode('result_payment', 'psgcf7_result_payment_func');


function psgcf7_CreateMessage_cf7($title, $body, $endstr = "") {
	if ($endstr != "") {
		return $endstr;
	}
	$tmp = '<div style="border:#CCC 1px solid; width:90%;">' . $title . '<br />' . $body . '</div>';
	$tmp = esc_html($tmp);
	return $tmp;
}


function psgcf7_CreatePage_cf7($title, $body)
{
	$tmp = '
	<html>
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>' . $title . '</title>
	</head>'
	. wp_enqueue_style("style", plugins_url('style.css', __FILE__), null, null) .
	'<body class="vipbody">	
	<div class="mrbox2" > 
	<h3><span>' . $title . '</span></h3>
	' . $body . '	
	</div>
	</body>
	</html>';
	$tmp = esc_html($tmp);
	return $tmp;
}

register_activation_hook(__FILE__, function() {
	global $wpdb;
	$table_name = $wpdb->prefix . "paystar_contact_form_7";
	$table_name = sanitize_title($table_name);
	if ($wpdb->get_var($wpdb->prepare("show tables like '$table_name'")) != $table_name) {
		$sql = $wpdb->prepare("CREATE TABLE " . $table_name . " (
			id mediumint(11) NOT NULL AUTO_INCREMENT,
			idform bigint(11) DEFAULT '0' NOT NULL,
			transid VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			gateway VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			cost bigint(11) DEFAULT '0' NOT NULL,
			created_at bigint(11) DEFAULT '0' NOT NULL,
			email VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci  NULL,
			description VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			user_mobile VARCHAR(11) CHARACTER SET utf8 COLLATE utf8_persian_ci  NULL,
			status VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			PRIMARY KEY id (id)
		);");
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
	$cf7pp_options = array(
		'paystar_terminal' => '',
		'return' => '',
		'error_color'=>'#f44336',
		'sucess_color' => '#8BC34A',
	);
	add_option("cf7pp_options", $cf7pp_options);
});

register_deactivation_hook(__FILE__, function() {
	delete_option("cf7pp_options");
	delete_option("cf7pp_my_plugin_notice_shown");
});

add_action('admin_notices', function() {
	if (!get_option('cf7pp_my_plugin_notice_shown')) {
		echo "<div class='updated'><p><a href='admin.php?page=psgcf7_cf7pp_admin_table'>".__('to set payment configuration, click here', 'paystar-payment-for-cf7')."</a>.</p></div>";
		update_option("cf7pp_my_plugin_notice_shown", "true");
	}
});


include_once(ABSPATH . 'wp-admin/includes/plugin.php');
if (is_plugin_active('contact-form-7/wp-contact-form-7.php')) {
	add_action('admin_menu', 'psgcf7_cf7pp_admin_menu', 20);
	function psgcf7_cf7pp_admin_menu()
	{
		$addnew = add_submenu_page('wpcf7',
			__('PayStar Settings', 'paystar-payment-for-cf7'),
			__('PayStar Settings', 'paystar-payment-for-cf7'),
			'wpcf7_edit_contact_forms', 'psgcf7_cf7pp_admin_table',
			'psgcf7_cf7pp_admin_table');

		$addnew = add_submenu_page('wpcf7',
			__('Transactions List', 'paystar-payment-for-cf7'),
			__('Transactions List', 'paystar-payment-for-cf7'),
			'wpcf7_edit_contact_forms', 'psgcf7_cf7pp_admin_list_trans',
			'psgcf7_cf7pp_admin_list_trans');
	}


	add_action('wpcf7_before_send_mail', 'psgcf7_cf7pp_before_send_mail');
	function psgcf7_cf7pp_before_send_mail($cf7) {
	}


	add_action('wpcf7_mail_sent', 'psgcf7_cf7pp_after_send_mail');
	function psgcf7_cf7pp_after_send_mail($cf7) {
		global $wpdb;
		global $postid;
		$postid = $cf7->id();
		$enable = get_post_meta($postid, "_cf7pp_enable", true);
		$email = get_post_meta($postid, "_cf7pp_email", true);
		if ($enable == "1") {
			if ($email == "2") {
				include_once ('redirect.php');
				exit;
			}
		}
	}

	add_action('wpcf7_admin_after_additional_settings', 'psgcf7_cf7pp_admin_after_additional_settings');
	function psgcf7_cf7pp_editor_panels($panels) {
		$new_page = array(
			'PricePay' => array(
				'title' => __('Payment Data', 'paystar-payment-for-cf7'),
				'callback' => 'psgcf7_cf7pp_admin_after_additional_settings'
			)
		);
		$panels = array_merge($panels, $new_page);
		return $panels;
	}
	add_filter('wpcf7_editor_panels', 'psgcf7_cf7pp_editor_panels');

	function psgcf7_cf7pp_admin_after_additional_settings($cf7) {
		$post_id = sanitize_text_field($_GET['post']);
		$enable = get_post_meta($post_id, "_cf7pp_enable", true);
		$price = get_post_meta($post_id, "_cf7pp_price", true);
		$email = get_post_meta($post_id, "_cf7pp_email", true);
		$user_mobile = get_post_meta($post_id, "_cf7pp_mobile", true);
		$description = get_post_meta($post_id, "_cf7pp_description", true);

		if ($enable == "1") {
			$checked = "CHECKED";
		} else {
			$checked = "";
		}

		if ($email == "1") {
			$before = "SELECTED";
			$after = "";
		} elseif ($email == "2") {
			$after = "SELECTED";
			$before = "";
		} else {
			$before = "";
			$after = "";
		}

		$admin_table_output = "";
		$admin_table_output .= "<form>";
		$admin_table_output .= "<div id='additional_settings-sortables' class='meta-box-sortables ui-sortable'><div id='additionalsettingsdiv' class='postbox'>";
		$admin_table_output .= "<div class='handlediv' title='Click to toggle'><br></div><h3 class='hndle ui-sortable-handle'> <span>".__('Payment Data of form', 'paystar-payment-for-cf7')."</span></h3>";
		$admin_table_output .= "<div class='inside'>";

		$admin_table_output .= "<div class='mail-field'>";
		$admin_table_output .= "<input name='enable' id='cf71' value='1' type='checkbox' $checked>";
		$admin_table_output .= "<label for='cf71'>".__('Enable Payment for this form', 'paystar-payment-for-cf7')."</label>";
		$admin_table_output .= "</div>";

		//input -name
		$admin_table_output .= "<table>";
		$admin_table_output .= "<tr><td>".__('Amount', 'paystar-payment-for-cf7').": </td><td><input type='text' name='price' style='text-align:left;direction:ltr;' value='$price'></td><td>(".__('Amount in RIAL', 'paystar-payment-for-cf7').")</td></tr>";

		$admin_table_output .= "</table>";


		//input -id
		$admin_table_output .= "<br> برای اتصال به درگاه پرداخت میتوانید از نام فیلدهای زیر استفاده نمایید ";
		$admin_table_output .= "<br />
		<span style='color:#F00;'>
		user_email نام فیلد دریافت ایمیل کاربر بایستی user_email انتخاب شود.
		<br />
		description نام فیلد  توضیحات پرداخت بایستی description انتخاب شود.
		<br />
		user_mobile نام فیلد  موبایل بایستی user_mobile انتخاب شود.
		<br />
		user_price اگر کادر مبلغ در بالا خالی باشد می توانید به کاربر اجازه دهید مبلغ را خودش انتخاب نماید . کادر متنی با نام user_price ایجاد نمایید
		<br/>
		مانند [text* user_price]
		</span>	";
		$admin_table_output .= "<input type='hidden' name='email' value='2'>";

		$admin_table_output .= "<input type='hidden' name='post' value='$post_id'>";

		$admin_table_output .= "</td></tr></table></form>";
		$admin_table_output .= "</div>";
		$admin_table_output .= "</div>";
		$admin_table_output .= "</div>";
		echo esc_html($admin_table_output);

	}

	add_action('wpcf7_save_contact_form', 'psgcf7_cf7pp_save_contact_form');
	function psgcf7_cf7pp_save_contact_form($cf7) {
		$post_id = sanitize_text_field($_POST['post']);
		if (!empty($_POST['enable'])) {
			$enable = sanitize_text_field($_POST['enable']);
			update_post_meta($post_id, "_cf7pp_enable", $enable);
		} else {
			update_post_meta($post_id, "_cf7pp_enable", 0);
		}

		$price = sanitize_text_field($_POST['price']);
		update_post_meta($post_id, "_cf7pp_price", $price);

		$email = sanitize_text_field($_POST['email']);
		update_post_meta($post_id, "_cf7pp_email", $email);
	}

	function psgcf7_cf7pp_admin_list_trans() {
		if (!current_user_can("manage_options")) {
			wp_die(__("You do not have sufficient permissions to access this page."));
		}
		global $wpdb;
		$pagenum = isset($_GET['pagenum']) ? absint(sanitize_text_field($_GET['pagenum'])) : 1;
		$limit = 6;
		$offset = ($pagenum - 1) * $limit;
		$table_name = $wpdb->prefix . "paystar_contact_form_7";
		$transactions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name where (status NOT like 'none') ORDER BY $table_name.id DESC LIMIT $offset, $limit", ARRAY_A));
		$transactions = (array)$transactions;
		$total = $wpdb->get_var($wpdb->prepare("SELECT COUNT($table_name.id) FROM $table_name where (status NOT like 'none') "));
		$num_of_pages = ceil($total / $limit);
		$cntx = 0;
		echo '<div class="wrap">
		<h2>'.__('Transactions of Forms', 'paystar-payment-for-cf7').'</h2>
		<table class="widefat post fixed" cellspacing="0">
			<thead>
				<tr>
					<th scope="col" id="name" width="15%" class="manage-column" style="">نام فرم</th>
					<th scope="col" id="name" width="" class="manage-column" style="">تاريخ</th>
					<th scope="col" id="name" width="" class="manage-column" style="">ایمیل</th>
					<th scope="col" id="name" width="" class="manage-column" style="">شماره تماس</th>
					<th scope="col" id="name" width="15%" class="manage-column" style="">مبلغ</th>
					<th scope="col" id="name" width="13%" class="manage-column" style="">وضعیت</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th scope="col" id="name" width="15%" class="manage-column" style="">نام فرم</th>
					<th scope="col" id="name" width="" class="manage-column" style="">تاريخ</th>
					<th scope="col" id="name" width="" class="manage-column" style="">ایمیل</th>
					<th scope="col" id="name" width="" class="manage-column" style="">شماره تماس</th>
					<th scope="col" id="name" width="15%" class="manage-column" style="">مبلغ</th>
					<th scope="col" id="name" width="13%" class="manage-column" style="">وضعیت</th>
				</tr>
			</tfoot>
			<tbody>';
		if (count($transactions) == 0) {
			echo '<tr class="alternate author-self status-publish iedit" valign="top">
					<td class="" colspan="6">هيج تراکنش وجود ندارد.</td>
				</tr>';
		} else {
			foreach ($transactions as $transaction) {
				$transaction = (array)$transaction;
				echo '<tr class="alternate author-self status-publish iedit" valign="top">
					<td class="">' . esc_html(get_the_title($transaction['idform'])) . '</td>';
				echo '<td class="">' . esc_html(strftime("%a, %B %e, %Y %r", $transaction['created_at']));
				echo '<br />(';
				echo esc_html(psgcf7_PayStar_CF7_relative_time($transaction["created_at"]));
				echo ' قبل)</td>';
				echo '<td class="">' . esc_html($transaction['email']) . '</td>';
				echo '<td class="">' . esc_html($transaction['user_mobile']) . '</td>';
				echo '<td class="">' . esc_html($transaction['cost']) . ' ریال</td>';
				echo '<td class="">';
				if ($transaction['status'] == "success") {
					echo '<b style="color:#0C9F55">موفقیت آمیز</b>';
				} else {
					echo '<b style="color:#f00">انجام نشده</b>';
				}
				echo '</td></tr>';
			}
		}
		echo '</tbody></table><br>';
		$page_links = paginate_links(array(
			'base' => add_query_arg('pagenum', '%#%'),
			'format' => '',
			'prev_text' => __('&laquo;', 'aag'),
			'next_text' => __('&raquo;', 'aag'),
			'total' => $num_of_pages,
			'current' => $pagenum
		));
		if ($page_links) {
			echo '<center><div class="tablenav"><div class="tablenav-pages"  style="float:none; margin: 1em 0">' . esc_html($page_links) . '</div></div></center>';
		}
		echo '<br><hr></div>';
	}

	function psgcf7_cf7pp_admin_table()
	{
		global $wpdb;
		if (!current_user_can("manage_options")) {
			wp_die(__("You do not have sufficient permissions to access this page."));
		}
		echo '<form method="post" action=' . esc_url($_SERVER["REQUEST_URI"]) . ' enctype="multipart/form-data">';
		if (isset($_POST['update'])) {
		
			$options['paystar_terminal'] = sanitize_text_field($_POST['paystar_terminal']);
			$options['return'] = sanitize_text_field($_POST['return']);
			$options['sucess_color'] = sanitize_hex_color($_POST['sucess_color']);
			$options['error_color'] = sanitize_hex_color($_POST['error_color']);
			update_option("cf7pp_options", $options);
			update_option('cf7pp_theme_message', wp_filter_post_kses($_POST['theme_message']));
			update_option('cf7pp_theme_error_message', wp_filter_post_kses($_POST['theme_error_message']));
			echo "<br /><div class='updated'><p><strong>";
			_e("Settings Updated.");
			echo "</strong></p></div>";
		}
		$options = get_option('cf7pp_options');
		foreach ($options as $k => $v) {
			$value[$k] = $v;
		}
		$theme_message = get_option('cf7pp_theme_message', '');
		$theme_error_message = get_option('cf7pp_theme_error_message', '');
		echo "<div class='wrap'><h2>Contact Form 7 - Gateway Settings</h2></div><br /><table width='90%'><tr><td>";
		echo '<div style="background-color:#333333;padding:8px;color:#eee;font-size:12pt;font-weight:bold;">&nbsp; پرداخت آنلاین برای فرم های Contact Form 7
		</div><div style="background-color:#fff;border: 1px solid #E5E5E5;padding:5px;"><br />
		<q1 style="color:#09F;">با استفاده از این قسمت میتوانید اطلاعات مربوط به درگاه  خود را تکمیل نمایید 
		<br> در بخش ایجاد فرم جدید می توانید براساس نام فیلد های زیر فرم را برای اتصال به درگاه پرداخت آماده کنید
		<br>user_email : برای دریافت ایمیل کاربر   
		<br>description : برای در یافت توضیحات خرید استفاده شود و الزامی شود  
		<br>user_mobile : برای دریافت موبایل کاربر   
		<br>user_price : جهت دریافت مبلغ از کاربر
		<br>برای نمونه : [text user_price]
		<br>برای مهم واجباری کردن* قرار دهید : [text* user_price]
		</q1>
		<br/><br/><br/>
		<q1 style="color:#60F;">لینک بازگشت از تراکنش بایستی به یکی از برگه های سایت باشد 
		<br>در این برگه بایستی از شورت کد زیر استفاده شود
		<br>[result_payment]   
		<br><br/><br/>
		<br>حتما برررسی نمایید کد زیر در فایل wp-config.php وجود داشته باشد. که اگر نبود خودتان اضافه نمایید.
		<br>
		<pre style="direction: ltr;">define("WPCF7_LOAD_JS",false);</pre>
		<br/><br/><br/>
		<q1>
		<q1></q1></q1></q1></q1></b></b></div><b><b>
		<br /><br />
		</div><br /><br />
		<div style="background-color:#333333;padding:8px;color:#eee;font-size:12pt;font-weight:bold;">&nbsp; اطلاعات درگاه پرداخت
		</div>
		<div style="background-color:#fff;border: 1px solid #E5E5E5;padding:20px;">
		<hr>	
		<table>
			<tr>
				<td>کد درگاه پرداخت پی استار :</td>
				<td><input type="text" style="width:450px;text-align:left;direction:ltr;" name="paystar_terminal" value="' . esc_html($value['paystar_terminal']) . '">الزامی</td>
			</tr>
		</table> 
		<hr>
		<table> 
			<tr>
				<td>لینک بازگشت از تراکنش :</td>
				<td><input type="text" name="return" style="width:450px;text-align:left;direction:ltr;" value="' . esc_html($value['return']) . '"> الزامی
				<br />فقط  عنوان  برگه را قرار دهید مانند  Vpay
				<br />حتما باید یک برگه ایجادکنید و کد [result_payment]  را در ان قرار دهید 
				<br />
				<br />
				</td>
				<td></td>
			</tr>
			<tr>
				<td>قالب تراکنش موفق :</td>
				<td>
				<textarea name="theme_message" style="width:450px;text-align:left;direction:ltr;">' . esc_html($theme_message) . '</textarea>
				<br/> متنی که میخواهید در هنگام موفقیت آمیز بودن تراکنش نشان دهید
				<br/>
				<b>از شورتکد [transaction_id] برای نمایش شماره تراکنش در قالب های نمایشی استفاده کنید</b>
				</td>
				<td></td>
			</tr>
			<tr><td></td></tr>
			<tr>
			<td>قالب تراکنش ناموفق :</td>
				<td>
				<textarea name="theme_error_message" style="width:450px;text-align:left;direction:ltr;">' . esc_html($theme_error_message) . '</textarea>
				<br/>
				متنی که میخواهید در هنگام موفقیت آمیز نبودن تراکنش نشان دهید
				<br/>

				</td>
				<td></td>
			</tr>
			<tr>
			<td>رنگ متن موفقیت آمیز بودن تراکنش :  </td>

				<td>
				<input type="text" name="sucess_color" style="width:150px;text-align:left;direction:ltr;color:'.esc_html($value['sucess_color']).'" value="' . esc_html($value['sucess_color']) . '">
	 مانند : #8BC34A
			</td>
			</tr>
			<tr>
			<td>رنگ متن موفقیت آمیز نبودن تراکنش :  </td>
				<td>
				<input type="text" name="error_color" style="width:150px;text-align:left;direction:ltr;color:'.esc_html($value['error_color']).'" value="' . esc_html($value['error_color']) . '"> مانند : #f44336
				</td>
			</tr>
			<tr><td></td></tr><tr><td></td></tr>
			<tr>
			<td colspan="3">
			<input type="submit" name="btn2" class="button-primary" style="font-size: 17px;line-height: 28px;height: 32px;float: right;" value="ذخیره تنظیمات">
			</td>
			</tr>
			</table>
			</div>
			<br /><br />
			<br />
			<input type="hidden" name="update">
			</form>		
			</td></tr></table>';
	}
} else {
	add_action('admin_notices', function() {
		echo '<div class="error">
			<p>' . esc_html(_e('<b> افزونه درگاه بانکی برای افزونه Contact Form 7 :</b> Contact Form 7 باید فعال باشد ', 'paystar-payment-for-cf7')) . '</p>
		</div>';
	});
}
?>