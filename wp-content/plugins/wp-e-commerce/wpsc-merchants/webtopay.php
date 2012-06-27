<?php

    if (session_id() == "") session_start();

    $nzshpcrt_gateways[$num]['name']             = 'Webtopay.com / Mokejimai.lt';
    $nzshpcrt_gateways[$num]['internalname']     = 'webtopay_certified';
    $nzshpcrt_gateways[$num]['function']         = 'gateway_webtopay_certified';
    $nzshpcrt_gateways[$num]['form']             = "form_webtopay_certified";
    $nzshpcrt_gateways[$num]['submit_function']  = "submit_webtopay_certified";

    require_once('libwebtopay/WebToPay.php');


    function webtopayCallback() {

        global $wpdb;

        if (isset($_GET[WebToPay::PREFIX.'projectid'])) {

            if ($_GET['wp_status'] != '1') {
                exit('Status not accepted: ' . $_GET['status']);
            }
            
            $Order = $wpdb->get_row("SELECT * FROM " . WPSC_TABLE_PURCHASE_LOGS . " WHERE `sessionid` = ".$wpdb->escape($_GET[WebToPay::PREFIX.'orderid']), OBJECT);
            $currency  = $wpdb->get_var("SELECT `code` FROM " . WPSC_TABLE_CURRENCY_LIST . " WHERE `id` = ".get_option( 'currency_type' ));            
            
            if($_GET[WebToPay::PREFIX.'amount'] != ($Order->totalprice*100)) {
                exit('Bad amount: '.$_GET[WebToPay::PREFIX.'amount']);
            }
            if($_GET[WebToPay::PREFIX.'currency'] != $currency) {
                exit('Bad currency: '.$_GET[WebToPay::PREFIX.'currency']);
            }

            try {
                WebToPay::toggleSS2(true);
                $response = WebToPay::checkResponse($_GET, array(
                        'projectid' 	=> get_option('webtopay_project_id'),
                        'sign_password' => get_option('webtopay_certified_sign'),
                    ));
            } catch (Exception $e) {
                 exit( get_class($e).': '.$e->getMessage());
            }
            
            /* Order status update */
        	$wpdb->query("UPDATE " . WPSC_TABLE_PURCHASE_LOGS . " SET `processed` = '3', `date` = '".time()."' WHERE `sessionid` = ".$wpdb->escape($response['orderid'])." LIMIT 1");

        	exit('OK');
        }
    }


    function gateway_webtopay_certified($seperator, $sessionid) {

        global $wpdb;

        $formFields = "SELECT `id`, `unique_name` FROM " . WPSC_TABLE_CHECKOUT_FORMS ." WHERE `active` = 1";
        $result = $wpdb->get_results($formFields);

        $formated = array();

        foreach($result as $item) {;
            $formated[$item->id] = $item->unique_name;
        }

        $userData = array(
        	'country'     => '',
    		'firstname'   => '',
            'lastname'    => '',
            'email'       => '',
            'street'      => '',
            'city'        => '',
            'state'       => '',
            'zip'    	  => '',
            'countrycode' => '',
        );

        foreach($_POST['collected_data'] as $key => $value) {
            ($formated[$key] == 'billingcountry')   ? $userData['country'] = $value[0] : $userData['country'] = $userData['country'];
            ($formated[$key] == 'billingfirstname') ? $userData['firstname'] = $value : $userData['firstname'] = $userData['firstname'];
            ($formated[$key] == 'billinglastname')  ? $userData['lastname'] = $value : $userData['lastname'] = $userData['lastname'];
            ($formated[$key] == 'billingemail')     ? $userData['email'] = $value : $userData['email'] = $userData['email'];
            ($formated[$key] == 'billingaddress')   ? $userData['street'] = $value : $userData['street'] = $userData['street'];
            ($formated[$key] == 'billingcity')      ? $userData['city'] = $value : $userData['city'] = $userData['city'];
            ($formated[$key] == 'billingstate')     ? $userData['state'] = $value : $userData['state'] = $userData['state'];;
            ($formated[$key] == 'billingpostcode')  ? $userData['zip'] = $value : $userData['zip'] = $userData['zip'];
            ($formated[$key] == 'billingcountry')   ? $userData['countrycode'] = $value[0] : $userData['countrycode'] = $userData['countrycode'];
        }

        $_SESSION['webtopayexpresssessionid'] = $sessionid;


        $language  = $wpdb->get_var("SELECT `option_value` FROM $wpdb->options WHERE `option_name` = 'base_country'");
        $currency  = $wpdb->get_var("SELECT `code` FROM " . WPSC_TABLE_CURRENCY_LIST . " WHERE `id` = ".get_option( 'currency_type' ));


        $_GET['sessionid'] = $sessionid;
        $_GET['gateway']   = 'webtopay';

        if(get_option('permalink_structure') != '') {
            $seperator ="?";
        } else {
            $seperator ="&";
        }

        $acceptURL    = get_option('transact_url') . $seperator . "sessionid={$_GET['sessionid']}&gateway={$_GET['gateway']}";
        $cancelURL    = get_option('checkout_url');
        $callbackURL  = get_option('siteurl') . '/';

        if (!is_object($_SESSION['wpsc_cart'])) {
            $cart = unserialize($_SESSION['wpsc_cart']);
        } else {
            $cart = $_SESSION['wpsc_cart'];
        }

        $amount = $cart->subtotal + $cart->base_shipping;


        try {
            $request = WebToPay::buildRequest(array(
                'projectid'	    => get_option('webtopay_project_id'),
                'sign_password' => get_option('webtopay_certified_sign'),
                'orderid'       => $sessionid,
                'amount'        => intval($amount * 100),

                'currency'      => $currency,
        		'lang'          => ($language === 'LT') ? 'lit' : 'eng',

                'accepturl'	    => $acceptURL,
                'cancelurl'	    => $cancelURL,
                'callbackurl'   => $callbackURL,

                'country'       => $userData['country'],
                'p_firstname'   => $userData['firstname'],
                'p_lastname'    => $userData['lastname'],
                'p_email'       => $userData['email'],
                'p_street'      => $userData['street'],
                'p_city'        => $userData['city'],
                'p_state'       => $userData['state'],
                'p_zip'    	    => $userData['zip'],
                'p_countrycode' => $userData['countrycode'],
                'test'          => get_option('webtopay_certified_test'),
            ));

        } catch (WebToPayException $e) {
            exit( $e->getMessage() );
        }


        $form = '';

        foreach ($request as $key => $value) {
            $form .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
        }

        $output = '<html>
            <head>
                <title></title>
            </head>
            <body onload=\'document.getElementById("webtopay_form").submit();\'>
                <form action="' . WebToPay::PAY_URL . '" id="webtopay_form" method="post">'.$form.'</form>
            </body>
        </html>';

        print($output);

    }

    function submit_webtopay_certified() {

        if($_POST['webtopay_project_id'] != null) {
            update_option('webtopay_project_id', $_POST['webtopay_project_id']);
        }

        if($_POST['webtopay_certified_sign'] != null) {
            update_option('webtopay_certified_sign', $_POST['webtopay_certified_sign']);
        }

        if($_POST['webtopay_certified_test'] != null) {
            update_option('webtopay_certified_test', $_POST['webtopay_certified_test']);
        }

        return true;
    }

    function form_webtopay_certified() {


        $selectOptions = '';

        if(get_option('webtopay_certified_test') == 1) {
            $selectOptions = '<option selected="true" value="1" >Enabled</option><option value="0" >Disabled</option>';
        } else {
            $selectOptions = '<option value="1" >Enabled</option><option value="0" selected="true" >Disabled</option>';
        }

        $output = '
        <tr>
			<td nowrap>'.__('Project ID:', 'wpsc').'</td>
          	<td>
              	<input type="text" size="10" value="'.get_option('webtopay_project_id').'" name="webtopay_project_id" />
              	<br>
            	<span class="small description">'.__('Your webtopay.com project ID:', 'wpsc').'</span>
          	</td>
        </tr>
        <tr>
            <td nowrap>'.__('Project password:', 'wpsc').'</td>
            <td>
                <input type="text" size="10" value="'.get_option('webtopay_certified_sign').'" name="webtopay_certified_sign" />
                <br>
                <span class="small description">'.__('Your webtopay.com project password:', 'wpsc').'</span>
            </td>
        </tr>
        <tr>
            <td nowrap>'.__('Test mode:', 'wpsc').'</td>
            <td>
                <select name="webtopay_certified_test">'.$selectOptions.'</select>
                <br>
                <span class="small description">'.__('Toggle test payments', 'wpsc').'</span>
            </td>
        </tr>';

        return $output;
    }

    add_action('init', 'webtopayCallback');
?>
