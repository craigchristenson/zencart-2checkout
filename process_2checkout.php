<?php

require('includes/application_top.php');

$co_id 		= $_REQUEST['merchant_order_id'];
$result 	= $_REQUEST['credit_card_processed'];
$return_md 	= $_REQUEST['key'];
$co_order_id	= $_REQUEST['order_number'];
$hash_order_id	= $co_order_id;
$order = $db->Execute("select amount from " . TABLE_2CHECKOUT . " where 2co_id = '" . $co_id . "'");
$order_value = number_format($order->fields['amount'], 2, '.', '');

$ssl_str = 'NONSSL';
if (ENABLE_SSL != 'false') {
    $ssl_str = 'SSL';
}

if (MODULE_PAYMENT_2CHECKOUT_TESTMODE == 'Test'){
    $hash_order_id = '1';
}

$check_string = MODULE_PAYMENT_2CHECKOUT_SECRET_WORD . MODULE_PAYMENT_2CHECKOUT_LOGIN . $hash_order_id . $order_value;
$check_hash = strtoupper(md5($check_string));
if ($check_hash != $return_md) {
    zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode(MODULE_PAYMENT_2CHECKOUT_TEXT_ERROR_HASH_MESSAGE), $ssl_str, true, false));
}

$checkout = $db->Execute("select session_id from " . TABLE_2CHECKOUT . " where 2co_id = '" . $co_id . "'");
zen_session_id($checkout->fields['session_id']);

$checkout_update = array('finish_time'=>'now()',
                   'status'=>zen_db_prepare_input( $result ),
                   '2co_order_id'=>zen_db_prepare_input( $co_order_id )
                   );

zen_db_perform(TABLE_2CHECKOUT, $checkout_update, 'update', "2co_id = '" . $co_id . "'");

if ($result != 'Y' && $result != 'K') {
    $messageStack->add_session('checkout_payment', MODULE_PAYMENT_UNET_TEXT_DATA_RETURN_ERROR_MESSAGE, 'error');
    $redirect_url = zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', $ssl_str, true, false);
} else {
    $redirect_url = zen_href_link(FILENAME_CHECKOUT_PROCESS, 'cID=' . $co_id, $ssl_str, true, false);
}
//FIREFOX FIX
$redirect_url = str_replace('&amp;', '&', $redirect_url);

?>
<body>
<script type="text/javascript" language="javascript">window.location="<?php echo $redirect_url; ?>"</script>
<noscript>
If you are not redirected please <a href="<?php echo $redirect_url; ?>">click here</a> to confirm your order.
</noscript>
</body>
