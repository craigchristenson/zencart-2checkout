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
// Updated: Craig christenson 07/2012

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
	$this->form_action_url = 'https://www.2checkout.com/checkout/spurchase';

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
	$confirmation = array('title' => $title);
	return $confirmation;
  }

////////////////////////////////////////////////////
// Functions to execute before finishing the form
// Examples: add extra hidden fields to the form
////////////////////////////////////////////////////

  function process_button() {
	global $_POST, $order, $currency, $db, $currencies;

	$cOrderTotal = $order->info['total'];

    // $cOrderTotal = $currencies->get_value(MODULE_PAYMENT_2CHECKOUT_CURRENCY) * $order->info['total'];

	// Create an ID for this transaction
	$co_insert = array('start_time'	=>	'now()',
						'status'		=>	'Requested',
						'amount'		=>	$cOrderTotal,
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


	$process_button_string = zen_draw_hidden_field('x_login', MODULE_PAYMENT_2CHECKOUT_LOGIN) .
							 zen_draw_hidden_field('x_amount', number_format($cOrderTotal, 2, '.', '')) .
							 zen_draw_hidden_field('x_invoice_num', $new_order_ref) .
							 zen_draw_hidden_field('x_first_name', $order->customer['firstname']) .
							 zen_draw_hidden_field('x_last_name', $order->customer['lastname']) .
							 zen_draw_hidden_field('x_address', $order->customer['street_address']) .
							 zen_draw_hidden_field('x_city', $order->customer['city']) .
							 zen_draw_hidden_field('x_state', $state) .
							 zen_draw_hidden_field('x_zip', $order->customer['postcode']) .
							 zen_draw_hidden_field('x_country', $order->customer['country']['title']) .
							 zen_draw_hidden_field('x_email', $order->customer['email_address']) .
							 zen_draw_hidden_field('x_phone', $order->customer['telephone']) .
							 zen_draw_hidden_field('x_ship_to_first_name', $order->delivery['firstname']) .
							 zen_draw_hidden_field('x_ship_to_last_name', $order->delivery['lastname']) .
							 zen_draw_hidden_field('x_ship_to_address', $order->delivery['street_address']) .
							 zen_draw_hidden_field('x_ship_to_city', $order->delivery['city']) .
							 zen_draw_hidden_field('x_ship_to_state', $order->delivery['state']) .
							 zen_draw_hidden_field('x_ship_to_zip', $order->delivery['postcode']) .
							 zen_draw_hidden_field('x_ship_to_country', $order->delivery['country']['title']) .
							 zen_draw_hidden_field('2co_cart_type', 'Zen Cart') .
                             zen_draw_hidden_field('2co_tax', number_format($tax, 2, '.', '')) .
							 zen_draw_hidden_field('x_email_merchant', ((MODULE_PAYMENT_2CHECKOUT_EMAIL_MERCHANT == 'True') ? 'TRUE' : 'FALSE'));
        if (ENABLE_SSL != 'false') {
	$process_button_string .= zen_draw_hidden_field('fixed', 'Y') .
                                  zen_draw_hidden_field('x_receipt_link_url', HTTPS_SERVER . DIR_WS_CATALOG . 'process_2checkout.php');
        } else {
	$process_button_string .= zen_draw_hidden_field('fixed', 'Y') .
                                  zen_draw_hidden_field('x_receipt_link_url', HTTP_SERVER . DIR_WS_CATALOG . 'process_2checkout.php');
        }

	if (MODULE_PAYMENT_2CHECKOUT_CONVERSION == 'Yes') {
	  $process_button_string .= zen_draw_hidden_field('tco_currency', $order->info['currency']);
	}
	if (MODULE_PAYMENT_2CHECKOUT_TESTMODE == 'Test'){
	  $process_button_string .= zen_draw_hidden_field('demo', 'Y');
	}
	if (MODULE_PAYMENT_2CHECKOUT_PRODUCTS == 'Enabled') {
	  $products = $order->products;
	  $process_button_string .= zen_draw_hidden_field('id_type', '1');
	  $process_button_string .= zen_draw_hidden_field('sh_cost', number_format(($currencies->get_value(MODULE_PAYMENT_2CHECKOUT_CURRENCY) * $order->info['shipping_cost']), 2, '.', ''));
	  for ($i = 0; $i < sizeof($products); $i++) {
		$prod_array = explode(':', $products[$i]['id']);
		$product_id = $prod_array[0];
		$process_button_string .= zen_draw_hidden_field('c_prod_' . ($i + 1), $product_id . ',' . $products[$i]['qty']);
		$process_button_string .= zen_draw_hidden_field('c_name_' . ($i + 1), $products[$i]['name']);
		$process_button_string .= zen_draw_hidden_field('c_description_' . ($i + 1), $products[$i]['model']);
		$process_button_string .= zen_draw_hidden_field('c_price_' . ($i + 1), number_format(($currencies->get_value(MODULE_PAYMENT_2CHECKOUT_CURRENCY) * $products[$i]['final_price']), 2, '.', ''));
		$process_button_string .= zen_draw_hidden_field('c_tangible_' . ($i + 1), (zen_get_products_virtual($products[$i]['id']) == true ? 'N' : 'Y'));
	  }
	}

	$process_button_string .= zen_draw_hidden_field(zen_session_name(), zen_session_id());
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

        $db->Execute("CREATE TABLE `2checkout` (
        `2co_id` int(11) NOT NULL auto_increment,
        `start_time` datetime NOT NULL default '0000-00-00 00:00:00',
        `finish_time` datetime NOT NULL default '0000-00-00 00:00:00',
        `status` varchar(50) collate latin1_general_ci NOT NULL default '',
        `amount` float NOT NULL default '0',
        `2co_order_id` int(11) NOT NULL default '0',
        `session_id` varchar(50) collate latin1_general_ci NOT NULL default '',
        PRIMARY KEY  (`2co_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;");

        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('2CheckOut Receive Currency', 'MODULE_PAYMENT_2CHECKOUT_CURRENCY', 'USD', 'Which Currency do you want to accept payments in? This should be the same currency as you are set to receive in the 2CheckOut admin, and uses the three letter currency code.', '6', '1', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable 2CheckOut Module', 'MODULE_PAYMENT_2CHECKOUT_STATUS', 'True', 'Do you want to accept 2CheckOut payments?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Login/Store Number', 'MODULE_PAYMENT_2CHECKOUT_LOGIN', '000000', 'Login/Store Number used for the 2CheckOut service', '6', '2', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Mode', 'MODULE_PAYMENT_2CHECKOUT_TESTMODE', 'Test', 'Transaction mode used for the 2Checkout service', '6', '3', 'zen_cfg_select_option(array(\'Test\', \'Production\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Merchant Notifications', 'MODULE_PAYMENT_2CHECKOUT_EMAIL_MERCHANT', 'True', 'Should 2CheckOut e-mail a receipt to the store owner?', '6', '4', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_2CHECKOUT_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '5', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_2CHECKOUT_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '6', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_2CHECKOUT_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '7', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Pending Payment Order Status', 'MODULE_PAYMENT_2CHECKOUT_ORDER_STATUS_PENDING_ID', '0', 'Set the status of orders which are returned from 2checkOut as pending', '6', '7', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Secret Word', 'MODULE_PAYMENT_2CHECKOUT_SECRET_WORD', 'secret', 'Secret word for the 2CheckOut MD5 hash facility', '6', '9', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Currency Converter', 'MODULE_PAYMENT_2CHECKOUT_CONVERSION', 'Yes', 'Payments are sent to 2checkout in your chosen currency.  This is set in the 2checkout admin. If you have more than currency enabled on your store, do you want to display the 2checkout page in the same currency? Please note: 2checkout exchange rate will be used, not your local exchange rate.', '6', '10', 'zen_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Product Integration', 'MODULE_PAYMENT_2CHECKOUT_PRODUCTS', 'Enabled', '2checkout requires that it is informed of all the products you sell. This is in accordance with its Accepted Products Policy. Do you wish to send these details to 2CO? Please read the information on the 2CO site regarding this, as well as the README document with this module.', '6', '10', 'zen_cfg_select_option(array(\'Enabled\', \'Disabled\'), ', now())");
        }

    ////////////////////////////////////////////////////
    // Remove the module (Administration Tool)
    // TABLES: configuration
////////////////////////////////////////////////////

    function remove() {
	global $db;
       $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
       $db->Execute("DROP TABLE IF EXISTS `2checkout`" );
   }

////////////////////////////////////////////////////
// Create our Key - > Value Arrays
////////////////////////////////////////////////////
  function keys() {
    return array('MODULE_PAYMENT_2CHECKOUT_STATUS',
				 'MODULE_PAYMENT_2CHECKOUT_LOGIN',
				 'MODULE_PAYMENT_2CHECKOUT_CURRENCY',
				 'MODULE_PAYMENT_2CHECKOUT_TESTMODE',
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
