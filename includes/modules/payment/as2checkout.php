<?php
//
// +----------------------------------------------------------------------+
// |zen-cart Open Source E-commerce                                       |
// +----------------------------------------------------------------------+
// | Copyright (c) 2003 The zen-cart developers                           |
// |                                                                      |
// | http://www.zen-cart.com/index.php                                    |
// |                                                                      |
// | Portions Copyright (c) 2003 Zen Cart                               |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at the following url:           |
// | http://www.zen-cart.com/license/2_0.txt.                             |
// | If you did not receive a copy of the zen-cart license and are unable |
// | to obtain it through the world-wide-web, please send a note to       |
// | license@zen-cart.com so we can mail you a copy immediately.          |
// +----------------------------------------------------------------------+
// $Id: pm2checkout.php,v 1.0.1 2004/04/22 17:45:00 networkdad Exp $
// Updated: Craig christenson 01/2013

class as2checkout extends base {
    var $code, $title, $description, $enabled;

////////////////////////////////////////////////////
// Class constructor -> initialize class variables.
// Sets the class code, description, and status.
////////////////////////////////////////////////////
    function as2checkout() {
        global $order;

        $this->code = 'as2checkout';
        $this->title = MODULE_PAYMENT_2CHECKOUT_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_2CHECKOUT_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_PAYMENT_2CHECKOUT_SORT_ORDER;
        $this->enabled = ((MODULE_PAYMENT_2CHECKOUT_STATUS == 'True') ? true : false);
        $this->secret_word = MODULE_PAYMENT_2CHECKOUT_SECRET_WORD;
        $this->login_id = MODULE_PAYMENT_2CHECKOUT_LOGIN;
        $this->form_action_url = 'https://beta.2checkout.com/checkout/purchase';

        if ((int)MODULE_PAYMENT_2CHECKOUT_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_2CHECKOUT_ORDER_STATUS_ID;
        }
    }

    function update_status() {
        global $order, $db;

        if ( ($this->enabled == true) && ((int)MODULE_PAYMENT_2CHECKOUT_ZONE > 0) ) {
            $check_flag = false;
            $check =  $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_2CHECKOUT_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
            while (!$check->EOF) {
                if ($check->fields['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check->fields['zone_id'] == $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check->MoveNext();
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }
////////////////////////////////////////////////////
// Javascript form validation
// Check the user input submited on checkout_payment.php with javascript (client-side).
// Examples: validate credit card number, make sure required fields are filled in
////////////////////////////////////////////////////

    function javascript_validation() {
        return false;
    }


////////////////////////////////////////////////////
// !Form fields for user input
// Output any required information in form fields
// Examples: ask for extra fields (credit card number), display extra information
////////////////////////////////////////////////////


    function selection() {
        global $order;
        $selection = array('id' => $this->code,
                                        'module' => $this->title
                                        );
        return $selection;
    }


////////////////////////////////////////////////////
// Pre confirmation checks (ie, check if credit card
// information is right before sending the info to
// the payment server
////////////////////////////////////////////////////

    function pre_confirmation_check() {
            return false;
    }


////////////////////////////////////////////////////
// Functions to execute before displaying the checkout
// confirmation page
////////////////////////////////////////////////////

    function confirmation() {
        global $_POST;
        if (MODULE_PAYMENT_2CHECKOUT_DIRECT == 'Direct') {
            echo '<script src="https://beta.2checkout.com/static/checkout/javascript/direct.js"></script>';
        }
        $confirmation = array('title' => $title);
        return $confirmation;
    }

////////////////////////////////////////////////////
// Functions to execute before finishing the form
// Examples: add extra hidden fields to the form
////////////////////////////////////////////////////
    function check_coupons() {
        global $order, $currency, $db, $currencies;

        $sql = "select * from " . TABLE_COUPONS . " where coupon_id=:couponID: and coupon_active='Y' ";
        $sql = $db->bindVars($sql, ':couponID:', $_SESSION['cc_id'], 'integer');
        $coupon = $db->Execute($sql);
        $coupon_amount = $coupon->fields['coupon_amount'];
        $coupon_amount_formatted = number_format($coupon_amount, 2, '.', '');
        $coupon_result = 0;

        switch ($coupon->fields['coupon_type']){
            case 'P': // percentage
                $coupon_result = $coupon_amount_formatted*0.01*($order->info['subtotal']);
                break;
            case 'F': // exact value
                $coupon_result = $coupon_amount_formatted;
                break;
        }
        return $coupon_result;
    }


    function pass_through_products() {
        global $order, $currency, $db, $currencies;

        $process_button_string_lineitems;
        $products = $order->products;
        $process_button_string_lineitems .= zen_draw_hidden_field('mode', '2CO');
        for ($i = 0; $i < sizeof($products); $i++) {
            $prod_array = explode(':', $products[$i]['id']);
            $product_id = $prod_array[0];
            $process_button_string_lineitems .= zen_draw_hidden_field('li_' . ($i + 1) . '_quantity', $products[$i]['qty']);
            $process_button_string_lineitems .= zen_draw_hidden_field('li_' . ($i + 1) . '_name', $products[$i]['name']);
            $process_button_string_lineitems .= zen_draw_hidden_field('li_' . ($i + 1) . '_description', $products[$i]['model']);
            $process_button_string_lineitems .= zen_draw_hidden_field('li_' . ($i + 1) . '_price', number_format(($currencies->get_value($order->info['currency']) * $products[$i]['final_price']), 2, '.', ''));
        }
        //shipping
        if ($order->info['shipping_method'] && $order->info['shipping_cost'] > 0) {
            $i++;
            $process_button_string_lineitems .= zen_draw_hidden_field('li_' . ($i) . '_type', 'shipping');
            $process_button_string_lineitems .= zen_draw_hidden_field('li_' . ($i) . '_name', $order->info['shipping_method']);
            $process_button_string_lineitems .= zen_draw_hidden_field('li_' . ($i) . '_price', number_format(($currencies->get_value($order->info['currency']) * $order->info['shipping_cost']), 2, '.', ''));
        }
        //tax
        if ($order->info['tax'] > 0) {
            $i++;
            $process_button_string_lineitems .= zen_draw_hidden_field('li_' . ($i) . '_type', 'tax');
            $process_button_string_lineitems .= zen_draw_hidden_field('li_' . ($i) . '_name', 'Tax');
            $process_button_string_lineitems .= zen_draw_hidden_field('li_' . ($i) . '_price', number_format(($currencies->get_value($order->info['currency']) * $order->info['tax']), 2, '.', ''));
        }
        //coupons
        $coupon_result = $this->check_coupons();
        if ($coupon_result > 0 ) {
            $i++;
            $process_button_string_lineitems .= zen_draw_hidden_field('li_' . ($i) . '_type', 'coupon');
            $process_button_string_lineitems .= zen_draw_hidden_field('li_' . ($i) . '_name', $order->info['coupon_code']);
            $process_button_string_lineitems .= zen_draw_hidden_field('li_' . ($i) . '_price', number_format(($currencies->get_value($order->info['currency']) * $coupon_result), 2, '.', ''));
        }

        return $process_button_string_lineitems;
    }

    function third_party_cart($merchant_order_id) {
        global $order, $currency, $db, $currencies;

        $process_button_string_cprod;
        $products = $order->products;
        $process_button_string_cprod .= zen_draw_hidden_field('id_type', '1');
        $process_button_string_cprod .= zen_draw_hidden_field('total', number_format(($currencies->get_value($order->info['currency']) * $order->info['total']), 2, '.', ''));
        $process_button_string_cprod .= zen_draw_hidden_field('cart_order_id', $merchant_order_id);
        for ($i = 0; $i < sizeof($products); $i++) {
            $prod_array = explode(':', $products[$i]['id']);
            $product_id = $prod_array[0];
            $process_button_string_cprod .= zen_draw_hidden_field('c_prod_' . ($i + 1), $product_id . ',' . $products[$i]['qty']);
            $process_button_string_cprod .= zen_draw_hidden_field('c_name_' . ($i + 1), $products[$i]['name']);
            $process_button_string_cprod .= zen_draw_hidden_field('c_description_' . ($i + 1), $products[$i]['model']);
            $process_button_string_cprod .= zen_draw_hidden_field('c_price_' . ($i + 1), number_format(($currencies->get_value($order->info['currency']) * $products[$i]['final_price']), 2, '.', ''));
        }
        return $process_button_string_cprod;
    }

    function check_total() {
        global $order, $currency, $db, $currencies;
        $lineitem_total = 0;
        $products = $order->products;
        for ($i = 0; $i < sizeof($products); $i++) {
                $lineitem_total += $products[$i]['qty'] * number_format(($currencies->get_value($order->info['currency']) * $products[$i]['final_price']), 2, '.', '');
        }
        //shipping
        if ($order->info['shipping_method']) {
            $lineitem_total += number_format(($currencies->get_value($order->info['currency']) * $order->info['shipping_cost']), 2, '.', '');
        }
        //tax
        if ($order->info['tax'] > 0) {
            $lineitem_total += number_format(($currencies->get_value($order->info['currency']) * $order->info['tax']), 2, '.', '');
        }
        //coupons
        $coupon_result = $this->check_coupons();
        if ($coupon_result > 0) {
            $lineitem_total -= number_format(($currencies->get_value($order->info['currency']) * $coupon_result), 2, '.', '');
        }
        return $lineitem_total;
    }

    function process_button() {
        global $_POST, $order, $currency, $db, $currencies;

        // Create an ID for this transaction
        $cOrderTotal = $order->info['total'];
        if (MODULE_PAYMENT_2CHECKOUT_CONVERSION == 'Yes') {
        $db_total = number_format(($currencies->get_value($order->info['currency']) * $cOrderTotal), 2, '.', '');
        } else {
        $db_total = $cOrderTotal;
        }
        $co_insert = array('start_time'	=>	'now()',
                                                'status'		=>	'Requested',
                                                'amount'		=>	$db_total,
                                                'session_id'	=>	zen_session_id()
                                                );
        zen_db_perform(TABLE_2CHECKOUT, $co_insert);
        $new_order_ref = $db->insert_ID();

        //Get Tax Value
        $tax = $order->info['tax'];

        //Get State
        $country = $order->customer['country']['title'];

        switch ($country) {
        case 'United States':
            $state = $order->customer['state'];
            break;
        case 'Canada':
            $state = $order->customer['state'];
            break;

        default:
            $state = 'XX';
            break;
        }

        $process_button_string = zen_draw_hidden_field('sid', MODULE_PAYMENT_2CHECKOUT_LOGIN) .
                            zen_draw_hidden_field('merchant_order_id', $new_order_ref) .
                            zen_draw_hidden_field('first_name', $order->customer['firstname']) .
                            zen_draw_hidden_field('last_name', $order->customer['lastname']) .
                            zen_draw_hidden_field('street_address', $order->customer['street_address']) .
                            zen_draw_hidden_field('street_address2', $order->customer['suburb']) .
                            zen_draw_hidden_field('city', $order->customer['city']) .
                            zen_draw_hidden_field('state', $state) .
                            zen_draw_hidden_field('zip', $order->customer['postcode']) .
                            zen_draw_hidden_field('country', $order->customer['country']['title']) .
                            zen_draw_hidden_field('email', $order->customer['email_address']) .
                            zen_draw_hidden_field('phone', $order->customer['telephone']);


        if (isset($order->delivery)) {
            $process_button_string .= zen_draw_hidden_field('sid', MODULE_PAYMENT_2CHECKOUT_LOGIN) .
                            zen_draw_hidden_field('merchant_order_id', $new_order_ref) .
                            zen_draw_hidden_field('ship_name', $order->delivery['firstname'] . " " . $order->delivery['lastname']) .
                            zen_draw_hidden_field('ship_street_address', $order->delivery['street_address']) .
                            zen_draw_hidden_field('ship_street_address2', $order->delivery['suburb']) .
                            zen_draw_hidden_field('ship_city', $order->delivery['city']) .
                            zen_draw_hidden_field('ship_state', $order->delivery['state']) .
                            zen_draw_hidden_field('ship_zip', $order->delivery['postcode']) .
                            zen_draw_hidden_field('ship_country', $order->delivery['country']['title']);
        }

        $process_button_string .= zen_draw_hidden_field('2co_cart_type', 'Zen Cart') .
                            zen_draw_hidden_field('2co_tax', number_format($tax, 2, '.', '')) .
                            zen_draw_hidden_field('purchase_step', 'payment-method');

        if (ENABLE_SSL != 'false') {
            $process_button_string .= zen_draw_hidden_field('fixed', 'Y') .
                                zen_draw_hidden_field('x_receipt_link_url', HTTPS_SERVER . DIR_WS_CATALOG . 'process_2checkout.php');
        } else {
            $process_button_string .= zen_draw_hidden_field('fixed', 'Y') .
                                zen_draw_hidden_field('x_receipt_link_url', HTTP_SERVER . DIR_WS_CATALOG . 'process_2checkout.php');
        }

        if (MODULE_PAYMENT_2CHECKOUT_CONVERSION == 'Yes') {
            $process_button_string .= zen_draw_hidden_field('currency_code', $order->info['currency']);
        }
        if (MODULE_PAYMENT_2CHECKOUT_TESTMODE == 'Test'){
            $process_button_string .= zen_draw_hidden_field('demo', 'Y');
        }

        $lineitem_total = $this->check_total();

        if (MODULE_PAYMENT_2CHECKOUT_PRODUCTS == 'Enabled' && sprintf("%01.2f", $lineitem_total) == sprintf("%01.2f", number_format(($currencies->get_value($order->info['currency']) * $cOrderTotal), 2, '.', ''))) {
            $process_button_string .= $this->pass_through_products();
        } else {
            $process_button_string .= $this->third_party_cart($new_order_ref);
        }

        return $process_button_string;
    }


////////////////////////////////////////////////////
// Test Credit Card# 4111111111111111
// Expiration any date after current date.
// Functions to execute before processing the order
// Examples: retreive result from online payment services
////////////////////////////////////////////////////

    function before_process() {
        global $_POST, $db, $order, $messageStack, $_GET;

	   $check_return = $db->Execute("select status from " . TABLE_2CHECKOUT . " where 2co_id = '" . zen_db_prepare_input($_GET['cID']) . "' order by 2co_id desc limit 1");

    	switch ($check_return->fields['status']) {
            case 'Y':
                return true;
                break;
            case 'K':
                if ((int)MODULE_PAYMENT_2CHECKOUT_ORDER_STATUS_PENDING_ID > 0) {
                    $order->info['order_status'] = MODULE_PAYMENT_2CHECKOUT_ORDER_STATUS_PENDING_ID;
                }
                return true;
                break;
            default:
                $messageStack->add_session('checkout_payment', MODULE_PAYMENT_2CHECKOUT_TEXT_ERROR_MESSAGE, 'error');
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
                break;
    	}
    }

    function after_process() {
	    return false;
    }

////////////////////////////////////////////////////
// If an error occurs with the process, output error messages here
////////////////////////////////////////////////////

    function get_error() {
        global $_GET;

        $error = array('title' => MODULE_PAYMENT_2CHECKOUT_TEXT_ERROR,
                        'error' => stripslashes(urldecode($_GET['error'])));

        return $error;
    }

////////////////////////////////////////////////////
// Check if module is installed (Administration Tool)
// TABLES: configuration
////////////////////////////////////////////////////

    function check() {
	    global $db;

        if (!isset($this->_check)) {
            $check_query =  $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_2CHECKOUT_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

////////////////////////////////////////////////////
// Install the module (Administration Tool)
// TABLES: configuration
////////////////////////////////////////////////////

    function install() {
        global $db;

        define('TABLE_2CHECKOUT', DB_PREFIX.'2checkout');

        $db->Execute("CREATE TABLE " . TABLE_2CHECKOUT . " (
        `2co_id` int(11) NOT NULL auto_increment,
        `start_time` datetime NOT NULL default '0000-00-00 00:00:00',
        `finish_time` datetime NOT NULL default '0000-00-00 00:00:00',
        `status` varchar(50) collate latin1_general_ci NOT NULL default '',
        `amount` float NOT NULL default '0',
        `2co_order_id` int(11) NOT NULL default '0',
        `session_id` varchar(50) collate latin1_general_ci NOT NULL default '',
        PRIMARY KEY  (`2co_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;");

        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('2CheckOut Receive Currency', 'MODULE_PAYMENT_2CHECKOUT_CURRENCY', 'USD', 'This should be the same currency as you are set to receive in the 2CheckOut admin, and uses the three letter currency code.', '6', '1', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable 2CheckOut Module', 'MODULE_PAYMENT_2CHECKOUT_STATUS', 'True', 'Do you want to accept 2CheckOut payments?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Login/Store Number', 'MODULE_PAYMENT_2CHECKOUT_LOGIN', '000000', 'Your 2Checkout Seller ID.', '6', '2', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Mode', 'MODULE_PAYMENT_2CHECKOUT_TESTMODE', 'Test', 'Transaction mode used for the 2Checkout service', '6', '3', 'zen_cfg_select_option(array(\'Test\', \'Production\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Checkout Mode', 'MODULE_PAYMENT_2CHECKOUT_DIRECT', 'Direct', 'Use Direct or Dynamic Checkout', '6', '3', 'zen_cfg_select_option(array(\'Direct\', \'Dynamic\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Merchant Notifications', 'MODULE_PAYMENT_2CHECKOUT_EMAIL_MERCHANT', 'True', 'Should 2CheckOut e-mail a receipt to the store owner?', '6', '4', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_2CHECKOUT_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '5', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_2CHECKOUT_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '6', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_2CHECKOUT_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '7', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Pending Payment Order Status', 'MODULE_PAYMENT_2CHECKOUT_ORDER_STATUS_PENDING_ID', '0', 'Set the status of orders which are returned from 2checkOut as pending', '6', '7', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Secret Word', 'MODULE_PAYMENT_2CHECKOUT_SECRET_WORD', 'secret', 'Secret word for the 2CheckOut MD5 hash facility', '6', '9', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Multi-Currency Pricing', 'MODULE_PAYMENT_2CHECKOUT_CONVERSION', 'Yes', 'If you have more than 1 currency enabled on your store, this should be set to Yes. Please note: This feature uses the currency_code parameter to override your 2Checkout account level currency for each sale.', '6', '10', 'zen_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Pass Through Products', 'MODULE_PAYMENT_2CHECKOUT_PRODUCTS', 'Enabled', 'This will enable the Pass Through Product parameter set to cleanly display lineitems on the 2Checkout custom checkout page.', '6', '10', 'zen_cfg_select_option(array(\'Enabled\', \'Disabled\'), ', now())");
    }

    ////////////////////////////////////////////////////
    // Remove the module (Administration Tool)
    // TABLES: configuration
    ////////////////////////////////////////////////////

    function remove() {
	    global $db;

        define('TABLE_2CHECKOUT', DB_PREFIX.'2checkout');
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
        $db->Execute("DROP TABLE IF EXISTS ". TABLE_2CHECKOUT );
    }

////////////////////////////////////////////////////
// Create our Key - > Value Arrays
////////////////////////////////////////////////////
    function keys() {
        return array('MODULE_PAYMENT_2CHECKOUT_STATUS',
                    'MODULE_PAYMENT_2CHECKOUT_LOGIN',
                    'MODULE_PAYMENT_2CHECKOUT_CURRENCY',
                    'MODULE_PAYMENT_2CHECKOUT_TESTMODE',
                    'MODULE_PAYMENT_2CHECKOUT_DIRECT',
                    'MODULE_PAYMENT_2CHECKOUT_EMAIL_MERCHANT',
                    'MODULE_PAYMENT_2CHECKOUT_ZONE',
                    'MODULE_PAYMENT_2CHECKOUT_ORDER_STATUS_ID',
                    'MODULE_PAYMENT_2CHECKOUT_ORDER_STATUS_PENDING_ID',
                    'MODULE_PAYMENT_2CHECKOUT_SORT_ORDER',
                    'MODULE_PAYMENT_2CHECKOUT_SECRET_WORD',
                    'MODULE_PAYMENT_2CHECKOUT_CONVERSION',
                    'MODULE_PAYMENT_2CHECKOUT_PRODUCTS'
                );
    }

}

?>
