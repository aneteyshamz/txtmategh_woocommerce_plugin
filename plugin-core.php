<?php

if (!defined('ABSPATH')) exit;  

//Check whether WPML is active
$wpml_active = function_exists('icl_object_id');
$wpml_regstr = function_exists('icl_register_string');
$wpml_trnslt = function_exists('icl_translate');

//Obtain the settings
$suwcsms_settings = get_option('suwcsms_settings');
function suwcsms_field($var)
{
    global $suwcsms_settings;
    return isset($suwcsms_settings[$var]) ? $suwcsms_settings[$var] : '';
}

//Utility function for registering string to WPML
function suwcsms_register_string($str)
{
    global $suwcsms_settings, $wpml_active, $wpml_regstr, $suwcsms_plugin_domn;
    if ($wpml_active) {
        ($wpml_regstr) ?
            icl_register_string($suwcsms_plugin_domn, $str, $suwcsms_settings[$str]) :
            do_action('wpml_register_single_string', $suwcsms_plugin_domn, $str, $suwcsms_settings[$str]);
    }
}

//Utility function to fetch string from WPML
function suwcsms_fetch_string($str)
{
    global $suwcsms_settings, $wpml_active, $wpml_trnslt, $suwcsms_plugin_domn;
    if ($wpml_active) {
        return ($wpml_trnslt) ?
            icl_translate($suwcsms_plugin_domn, $str, $suwcsms_settings[$str]) :
            apply_filters('wpml_translate_single_string', $suwcsms_settings[$str], $suwcsms_plugin_domn, $str);
    }
    return suwcsms_field($str);
}

//Add phone field to Shipping Address
add_filter('woocommerce_checkout_fields', 'suwcsms_add_shipping_phone_field');
function suwcsms_add_shipping_phone_field($fields)
{
    if (!isset($fields['shipping']['shipping_phone'])) {
        $fields['shipping']['shipping_phone'] = array(
            'label' => __('Mobile Phone', 'woocommerce'),
            'placeholder' => _x('Mobile Phone', 'placeholder', 'woocommerce'),
            'required' => false,
            'class' => array('form-row-wide'),
            'clear' => true
        );
    }
    return $fields;
}

//Display shipping phone field on order edit page
add_action('woocommerce_admin_order_data_after_shipping_address', 'suwcsms_display_shipping_phone_field', 10, 1);
function suwcsms_display_shipping_phone_field($order)
{
    echo '<p><strong>' . __('Shipping Phone') . ':</strong> ' . get_post_meta($order->get_id(), '_shipping_phone', true) . '</p>';
}

//Change label of billing phone field
add_filter('woocommerce_checkout_fields', 'suwcsms_phone_field_label');
function suwcsms_phone_field_label($fields)
{
    $fields['billing']['billing_phone']['label'] = 'Mobile Phone';
    return $fields;
}

//Initialize the plugin
add_action('init', 'suwcsms_initialize');
function suwcsms_initialize()
{
    suwcsms_register_string('msg_new_order');
    suwcsms_register_string('msg_pending');
    suwcsms_register_string('msg_on_hold');
    suwcsms_register_string('msg_processing');
    suwcsms_register_string('msg_completed');
    suwcsms_register_string('msg_cancelled');
    suwcsms_register_string('msg_refunded');
    suwcsms_register_string('msg_failure');
    suwcsms_register_string('msg_custom');
}

//Add settings page to woocommerce admin menu 
add_action('admin_menu', 'suwcsms_admin_menu', 20);
function suwcsms_admin_menu()
{
    global $suwcsms_plugin_domn;
    add_submenu_page('woocommerce', __('WooCommerce SMS Notification Settings', $suwcsms_plugin_domn), __('WooCommerce SMS Notifications', $suwcsms_plugin_domn), 'manage_woocommerce', $suwcsms_plugin_domn, $suwcsms_plugin_domn . '_tab');
    function suwcsms_tab()
    {
        include('settings-page.php');
    }
}

//Add screen id for enqueuing WooCommerce scripts
add_filter('woocommerce_screen_ids', 'suwcsms_screen_id');
function suwcsms_screen_id($screen)
{
    global $suwcsms_plugin_domn;
    $screen[] = 'woocommerce_page_' . $suwcsms_plugin_domn;
    return $screen;
}

//Set the options
add_action('admin_init', 'suwcsms_regiser_settings');
function suwcsms_regiser_settings()
{
    register_setting('suwcsms_settings_group', 'suwcsms_settings');
}

//Schedule notifications for new order
if (suwcsms_field('use_msg_new_order') == 1)
    add_action('woocommerce_new_order', 'suwcsms_owner_notification', 20);
function suwcsms_owner_notification($order_id)
{
    if (suwcsms_field('mnumber') == '')
        return;
    $order = new WC_Order($order_id);
    $template = suwcsms_fetch_string('msg_new_order');
    $message = suwcsms_process_variables($template, $order);
    $owners_phone = suwcsms_process_phone($order, suwcsms_field('mnumber'), false, true);
    suwcsms_send_sms($owners_phone, $message);
    $additional_numbers = suwcsms_field('addnumber');
    if (!empty($additional_numbers)) {
        $numbers = explode(",", $additional_numbers);
        foreach ($numbers as $number) {
            $phone = suwcsms_process_phone($order, trim($number), false, true);
            suwcsms_send_sms($phone, $message);
        }
    }
}

add_filter('woocommerce_cod_process_payment_order_status', 'suwcsms_cod_order_status', 1);
function suwcsms_cod_order_status($status)
{
    return suwcsms_field('otp_pre_status');
}

add_action('woocommerce_thankyou', 'suwcsms_otp_verify_order', 1);
add_action('woocommerce_view_order', 'suwcsms_otp_verify_order', 1);
function suwcsms_otp_verify_order($order_id)
{
    $otp_cod = suwcsms_field('otp_cod');
    $otp_bacs = suwcsms_field('otp_bacs');
    $otp_cheque = suwcsms_field('otp_cheque');
    $payment_method = get_post_meta($order_id, '_payment_method', true);
    $otp_verified = get_post_meta($order_id, 'otp_verified', true);
    if ((($otp_cod && ($payment_method == 'cod')) || ($otp_bacs && ($payment_method == 'bacs')) || ($otp_cheque && ($payment_method == 'cheque'))) && ('Yes' != $otp_verified)) {
        $phone = get_post_meta($order_id, '_billing_phone', true);
        update_post_meta($order_id, 'otp_verified', 'No');
        suwcsms_send_new_order_otp($order_id, $phone);
        suwcsms_display_otp_verification($order_id, $phone);
    }
}

//Verify OTP via AJAX
add_action('wp_ajax_suwcsms_verify_otp', 'suwcsms_verify_otp_callback');
add_action('wp_ajax_nopriv_suwcsms_verify_otp', 'suwcsms_verify_otp_callback');
function suwcsms_verify_otp_callback()
{
    if (isset($_POST['action']) && $_POST['action'] == 'suwcsms_verify_otp') {
        $data = ['error' => true, 'message' => 'OTP could not be verified', 'verification_failure' => true];
        if (isset($_POST['order_id'])) {
            $order_id = $_POST['order_id'];
            $otp_submitted = $_POST['otp'];
            $otp_stored = get_post_meta($order_id, 'otp_value', true);
            if ($otp_stored == $otp_submitted) {
                update_post_meta($order_id, 'otp_verified', 'Yes');
                $pre_status = suwcsms_field('otp_pre_status');
                $post_status = suwcsms_field('otp_post_status');
                $order = wc_get_order($order_id);
                // if ($pre_status == $order->get_status()) {
                $order->update_status($post_status);
                // }
                $data = ['success' => true, 'message' => "Thank You! Your order #$order_id has been confirmed.", 'otp_verified' => true];
            }
        }
        wp_send_json($data);
    }
    die();
}

//Request OTP resend via AJAX
add_action('wp_ajax_suwcsms_resend_otp', 'suwcsms_resend_otp_callback');
add_action('wp_ajax_nopriv_suwcsms_resend_otp', 'suwcsms_resend_otp_callback');
function suwcsms_resend_otp_callback()
{
    if (isset($_POST['action']) && $_POST['action'] == 'suwcsms_resend_otp') {
        $data = ['error' => true, 'message' => 'Failed to send OTP'];
        if (isset($_POST['order_id'])) {
            $order_id = $_POST['order_id'];
            $otp_verified = get_post_meta($order_id, 'otp_verified', true);
            if ($otp_verified != 'Yes') {
                $phone = get_post_meta($order_id, '_billing_phone', true);
                suwcsms_send_new_order_otp($order_id, $phone);
                $data = ['success' => true, 'message' => "OTP Sent to $phone for order #$order_id"];
            }
        }
        wp_send_json($data);
    }
    die();
}

function suwcsms_generate_otp()
{
    return mt_rand(100000, 999999);
}

function suwcsms_send_new_order_otp($order_id, $phone)
{
    $order = wc_get_order($order_id);
    $phone = suwcsms_process_phone($order, $phone);
    $otp_number = suwcsms_generate_otp();
    $shop_name = get_bloginfo('name');
    $signature = suwcsms_field('signature');
    $message = "Dear Customer, Your OTP for verifying order no. $order_id on $shop_name is $otp_number. Kindly verify to confirm your order. $signature";
    suwcsms_send_otp($phone, $message);
    update_post_meta($order_id, 'otp_value', $otp_number);
}

function suwcsms_display_otp_verification($order_id, $phone)
{
    ?>
    <script type='text/javascript'>
    jQuery(function($){
        var otp_failure_count = 0,
            otp_resend_count = 0;
        function showSpinner() {
            $('.suwcsms-notifications').html('<center><img src="<?= admin_url("images/spinner-2x.gif") ?>"/></center>');
        }
        function process_json_response(response) {
            var jsonobj = JSON.parse(JSON.stringify(response));
            if (jsonobj.error) {
                $('.suwcsms-notifications').html('<div class="woocommerce-error">'+jsonobj.message+'</div>');
                if (jsonobj.verification_failure) {
                    otp_failure_count++;
                    if (otp_failure_count > 3) {
                        $('.suwcsms-notifications').append('<br/><h3>It seems that there is a difficulty in verifying your order. Please call our support number to verify your order.</h3>');
                    }
                }
            } else {
                if (jsonobj.otp_verified) {
                    $('#su-otp-verification-block').html('<h3>'+jsonobj.message+'</h3>');
                } else {
                    $('.suwcsms-notifications').html('<div class="woocommerce-message">'+jsonobj.message+'</div>');
                    otp_resend_count++;
                }
            }
        }
        function suwcsms_verify_otp() {
            showSpinner();
            var data = {
                'action' : 'suwcsms_verify_otp',
                'order_id' : '<?= $order_id ?>',
                'otp' : document.getElementById('suwcsms-otp-field').value
            };
            $.post(
                "<?php echo admin_url("admin-ajax.php"); ?>",
                data,
                process_json_response
            );
        }
        function suwcsms_resend_otp() {
            showSpinner();
            var data = {
                'action' : 'suwcsms_resend_otp',
                'order_id' : '<?= $order_id ?>'
            };
            $.post(
                "<?php echo admin_url("admin-ajax.php"); ?>",
                data,
                process_json_response
            );
            disableResendOTP();
        }
        function enableResendOTP() {
            if (otp_resend_count < 3) {
                $('#suwcsms_resend_otp_btn').prop('disabled', false);
            }
        }
        function disableResendOTP() {
            $('#suwcsms_resend_otp_btn').prop('disabled', true);
            setTimeout(enableResendOTP, 120000);
        }
        $('p.woocommerce-thankyou-order-received, ul.woocommerce-thankyou-order-details').hide();
        $('#suwcsms_verify_otp_btn').click(suwcsms_verify_otp);
        $('#suwcsms_resend_otp_btn').click(suwcsms_resend_otp);
        disableResendOTP();
    });
    </script>
    <div id='su-otp-verification-block' style='background:#EEE;padding:10px;border-radius:5px'>
        <h3>OTP Verification</h3>
        <div class='suwcsms-notifications'>
            <div class="woocommerce-info">
            OTP sent to mobile no: <?= $phone ?> for order #<?= $order_id ?>. Your order will be confirmed upon completion of OTP verification.
            </div>
        </div>
        <center>
        <label style='font-weight:bold;color:#000'>OTP </label>
        <input id='suwcsms-otp-field' size='6' style='letter-spacing:5px;font-weight:bold;padding:10px'/>
        <input id='suwcsms_verify_otp_btn' type='button' class='button' value='Verify'/>
        <input id='suwcsms_resend_otp_btn' type='button' class='button alt' value='Resend OTP'/>
        </center>
        <p>Please make sure you are in a good mobile signal zone. Resend button will get activated in 2 minutes. Please request again if you have not received the OTP in next 2 minutes.</p>
    </div>
    <?php

}
    
//Schedule notifications for order status change
add_action('woocommerce_order_status_changed', 'suwcsms_process_status', 10, 3);
function suwcsms_process_status($order_id, $old_status, $status)
{
    $order = new WC_Order($order_id);
    $shipping_phone = false;
    $phone = $order->get_billing_phone();

    //If have to send messages to shipping phone
    if (suwcsms_field('alt_phone') == 1) {
        $phone = get_post_meta($order->get_id(), '_shipping_phone', true);
        $shipping_phone = true;
    }
    
    //Remove old 'wc-' prefix from the order status
    $status = str_replace('wc-', '', $status);
    
    //Sanitize the phone number
    $phone = suwcsms_process_phone($order, $phone, $shipping_phone);
    
    //Get the message corresponding to order status
    $message = "";
    switch ($status) {
        case 'pending':
            if (suwcsms_field('use_msg_pending') == 1)
                $message = suwcsms_process_variables(suwcsms_fetch_string('msg_new_order'), $order);
            break;
        case 'on-hold':
            if (suwcsms_field('use_msg_on_hold') == 1)
                $message = suwcsms_process_variables(suwcsms_fetch_string('msg_on_hold'), $order);
            break;
        case 'processing':
            if (suwcsms_field('use_msg_processing') == 1)
                $message = suwcsms_process_variables(suwcsms_fetch_string('msg_processing'), $order);
            break;
        case 'completed':
            if (suwcsms_field('use_msg_completed') == 1)
                $message = suwcsms_process_variables(suwcsms_fetch_string('msg_completed'), $order);
            break;
        case 'cancelled':
            if (suwcsms_field('use_msg_cancelled') == 1)
                $message = suwcsms_process_variables(suwcsms_fetch_string('msg_cancelled'), $order);
            break;
        case 'refunded':
            if (suwcsms_field('use_msg_refunded') == 1)
                $message = suwcsms_process_variables(suwcsms_fetch_string('msg_refunded'), $order);
            break;
        case 'failed':
            if (suwcsms_field('use_msg_failure') == 1)
                $message = suwcsms_process_variables(suwcsms_fetch_string('msg_failure'), $order);
            break;
        default:
            if (suwcsms_field('use_msg_custom') == 1)
                $message = suwcsms_process_variables(suwcsms_fetch_string('msg_custom'), $order);
    }
    
    //Send the SMS
    if (!empty($message))
        suwcsms_send_sms($phone, $message);
}

function suwcsms_message_encode($message)
{
    return urlencode(html_entity_decode($message, ENT_QUOTES, "UTF-8"));
}

function suwcsms_process_phone($order, $phone, $shipping = false, $owners_phone = false)
{
    //Sanitize phone number
    $phone = str_replace(array('+', '-'), '', filter_var($phone, FILTER_SANITIZE_NUMBER_INT));
    $phone = ltrim($phone, '0');
     
    //Obtain country code prefix
    $country = WC()->countries->get_base_country();
    if (!$owners_phone) {
        $country = $shipping ? $order->get_shipping_country() : $order->get_billing_country();
    }
    $intl_prefix = suwcsms_country_prefix($country);

    //Check for already included prefix
    preg_match("/(\d{1,4})[0-9.\- ]+/", $phone, $prefix);
    
    //If prefix hasn't been added already, add it
    if (strpos($prefix[1], $intl_prefix) !== 0) {
        $phone = $intl_prefix . $phone;
    }
    
    /* //Prefix '+' as required
    if ( strpos( $prefix[1], "+" ) !== 0 ) {
        $phone = "+" . $phone;
    } */

    return $phone;
}


function suwcsms_process_variables($message, $order)
{
    $sms_strings = array("id", "status", "prices_include_tax", "tax_display_cart", "display_totals_ex_tax", "display_cart_ex_tax", "order_date", "modified_date", "customer_message", "customer_note", "post_status", "shop_name", "note", "order_product");
    $suwcsms_variables = array("order_key", "billing_first_name", "billing_last_name", "billing_company", "billing_address_1", "billing_address_2", "billing_city", "billing_postcode", "billing_country", "billing_state", "billing_email", "billing_phone", "shipping_first_name", "shipping_last_name", "shipping_company", "shipping_address_1", "shipping_address_2", "shipping_city", "shipping_postcode", "shipping_country", "shipping_state", "shipping_method", "shipping_method_title", "payment_method", "payment_method_title", "order_discount", "cart_discount", "order_tax", "order_shipping", "order_shipping_tax", "order_total");
    $specials = array("order_date", "modified_date", "shop_name", "id", "order_product", 'signature');
    $order_variables = get_post_custom($order->get_id()); //WooCommerce 2.1
    $custom_variables = explode("\n", str_replace(array("\r\n", "\r"), "\n", suwcsms_field('variables')));

    preg_match_all("/%(.*?)%/", $message, $search);
    foreach ($search[1] as $variable) {
        $variable = strtolower($variable);

        if (!in_array($variable, $sms_strings) && !in_array($variable, $suwcsms_variables) && !in_array($variable, $specials) && !in_array($variable, $custom_variables)) {
            continue;
        }

        if (!in_array($variable, $specials)) {
            if (in_array($variable, $sms_strings)) {
                $message = str_replace("%" . $variable . "%", $order->$variable, $message); //Standard fields
            } else if (in_array($variable, $suwcsms_variables)) {
                $message = str_replace("%" . $variable . "%", $order_variables["_" . $variable][0], $message); //Meta fields
            } else if (in_array($variable, $custom_variables) && isset($order_variables[$variable])) {
                $message = str_replace("%" . $variable . "%", $order_variables[$variable][0], $message);
            }
        } else if ($variable == "order_date" || $variable == "modified_date") {
            $message = str_replace("%" . $variable . "%", date_i18n(woocommerce_date_format(), strtotime($order->$variable)), $message);
        } else if ($variable == "shop_name") {
            $message = str_replace("%" . $variable . "%", get_bloginfo('name'), $message);
        } else if ($variable == "id") {
            $message = str_replace("%" . $variable . "%", $order->get_order_number(), $message);
        } else if ($variable == "order_product") {
            $products = $order->get_items();
            $quantity = $products[key($products)]['name'];
            if (strlen($quantity) > 10) {
                $quantity = substr($quantity, 0, 10) . "...";
            }
            if (count($products) > 1) {
                $quantity .= " (+" . (count($products) - 1) . ")";
            }
            $message = str_replace("%" . $variable . "%", $quantity, $message);
        } else if ($variable == "signature") {
            $message = str_replace("%" . $variable . "%", suwcsms_field('signature'), $message);
        }
    }
    return $message;
}

function suwcsms_country_prefix($country = '')
{
    $countries = array(
        'AC' => '247',
        'AD' => '376',
        'AE' => '971',
        'AF' => '93',
        'AG' => '1268',
        'AI' => '1264',
        'AL' => '355',
        'AM' => '374',
        'AO' => '244',
        'AQ' => '672',
        'AR' => '54',
        'AS' => '1684',
        'AT' => '43',
        'AU' => '61',
        'AW' => '297',
        'AX' => '358',
        'AZ' => '994',
        'BA' => '387',
        'BB' => '1246',
        'BD' => '880',
        'BE' => '32',
        'BF' => '226',
        'BG' => '359',
        'BH' => '973',
        'BI' => '257',
        'BJ' => '229',
        'BL' => '590',
        'BM' => '1441',
        'BN' => '673',
        'BO' => '591',
        'BQ' => '599',
        'BR' => '55',
        'BS' => '1242',
        'BT' => '975',
        'BW' => '267',
        'BY' => '375',
        'BZ' => '501',
        'CA' => '1',
        'CC' => '61',
        'CD' => '243',
        'CF' => '236',
        'CG' => '242',
        'CH' => '41',
        'CI' => '225',
        'CK' => '682',
        'CL' => '56',
        'CM' => '237',
        'CN' => '86',
        'CO' => '57',
        'CR' => '506',
        'CU' => '53',
        'CV' => '238',
        'CW' => '599',
        'CX' => '61',
        'CY' => '357',
        'CZ' => '420',
        'DE' => '49',
        'DJ' => '253',
        'DK' => '45',
        'DM' => '1767',
        'DO' => '1809',
        'DO' => '1829',
        'DO' => '1849',
        'DZ' => '213',
        'EC' => '593',
        'EE' => '372',
        'EG' => '20',
        'EH' => '212',
        'ER' => '291',
        'ES' => '34',
        'ET' => '251',
        'EU' => '388',
        'FI' => '358',
        'FJ' => '679',
        'FK' => '500',
        'FM' => '691',
        'FO' => '298',
        'FR' => '33',
        'GA' => '241',
        'GB' => '44',
        'GD' => '1473',
        'GE' => '995',
        'GF' => '594',
        'GG' => '44',
        'GH' => '233',
        'GI' => '350',
        'GL' => '299',
        'GM' => '220',
        'GN' => '224',
        'GP' => '590',
        'GQ' => '240',
        'GR' => '30',
        'GT' => '502',
        'GU' => '1671',
        'GW' => '245',
        'GY' => '592',
        'HK' => '852',
        'HN' => '504',
        'HR' => '385',
        'HT' => '509',
        'HU' => '36',
        'ID' => '62',
        'IE' => '353',
        'IL' => '972',
        'IM' => '44',
        'IN' => '91',
        'IO' => '246',
        'IQ' => '964',
        'IR' => '98',
        'IS' => '354',
        'IT' => '39',
        'JE' => '44',
        'JM' => '1876',
        'JO' => '962',
        'JP' => '81',
        'KE' => '254',
        'KG' => '996',
        'KH' => '855',
        'KI' => '686',
        'KM' => '269',
        'KN' => '1869',
        'KP' => '850',
        'KR' => '82',
        'KW' => '965',
        'KY' => '1345',
        'KZ' => '7',
        'LA' => '856',
        'LB' => '961',
        'LC' => '1758',
        'LI' => '423',
        'LK' => '94',
        'LR' => '231',
        'LS' => '266',
        'LT' => '370',
        'LU' => '352',
        'LV' => '371',
        'LY' => '218',
        'MA' => '212',
        'MC' => '377',
        'MD' => '373',
        'ME' => '382',
        'MF' => '590',
        'MG' => '261',
        'MH' => '692',
        'MK' => '389',
        'ML' => '223',
        'MM' => '95',
        'MN' => '976',
        'MO' => '853',
        'MP' => '1670',
        'MQ' => '596',
        'MR' => '222',
        'MS' => '1664',
        'MT' => '356',
        'MU' => '230',
        'MV' => '960',
        'MW' => '265',
        'MX' => '52',
        'MY' => '60',
        'MZ' => '258',
        'NA' => '264',
        'NC' => '687',
        'NE' => '227',
        'NF' => '672',
        'NG' => '234',
        'NI' => '505',
        'NL' => '31',
        'NO' => '47',
        'NP' => '977',
        'NR' => '674',
        'NU' => '683',
        'NZ' => '64',
        'OM' => '968',
        'PA' => '507',
        'PE' => '51',
        'PF' => '689',
        'PG' => '675',
        'PH' => '63',
        'PK' => '92',
        'PL' => '48',
        'PM' => '508',
        'PR' => '1787',
        'PR' => '1939',
        'PS' => '970',
        'PT' => '351',
        'PW' => '680',
        'PY' => '595',
        'QA' => '974',
        'QN' => '374',
        'QS' => '252',
        'QY' => '90',
        'RE' => '262',
        'RO' => '40',
        'RS' => '381',
        'RU' => '7',
        'RW' => '250',
        'SA' => '966',
        'SB' => '677',
        'SC' => '248',
        'SD' => '249',
        'SE' => '46',
        'SG' => '65',
        'SH' => '290',
        'SI' => '386',
        'SJ' => '47',
        'SK' => '421',
        'SL' => '232',
        'SM' => '378',
        'SN' => '221',
        'SO' => '252',
        'SR' => '597',
        'SS' => '211',
        'ST' => '239',
        'SV' => '503',
        'SX' => '1721',
        'SY' => '963',
        'SZ' => '268',
        'TA' => '290',
        'TC' => '1649',
        'TD' => '235',
        'TG' => '228',
        'TH' => '66',
        'TJ' => '992',
        'TK' => '690',
        'TL' => '670',
        'TM' => '993',
        'TN' => '216',
        'TO' => '676',
        'TR' => '90',
        'TT' => '1868',
        'TV' => '688',
        'TW' => '886',
        'TZ' => '255',
        'UA' => '380',
        'UG' => '256',
        'UK' => '44',
        'US' => '1',
        'UY' => '598',
        'UZ' => '998',
        'VA' => '379',
        'VA' => '39',
        'VC' => '1784',
        'VE' => '58',
        'VG' => '1284',
        'VI' => '1340',
        'VN' => '84',
        'VU' => '678',
        'WF' => '681',
        'WS' => '685',
        'XC' => '991',
        'XD' => '888',
        'XG' => '881',
        'XL' => '883',
        'XN' => '857',
        'XN' => '858',
        'XN' => '870',
        'XP' => '878',
        'XR' => '979',
        'XS' => '808',
        'XT' => '800',
        'XV' => '882',
        'YE' => '967',
        'YT' => '262',
        'ZA' => '27',
        'ZM' => '260',
        'ZW' => '263'
    );

    return ($country == '') ? $countries : (isset($countries[$country]) ? $countries[$country] : '');
}

function suwcsms_remote_get($url)
{
    $response = wp_remote_get($url, array('timeout' => 15));
    if (is_wp_error($response)) {
        $response = $response->get_error_message();
    } elseif (is_array($response)) {
        $response = $response['body'];
    }
    return $response;
}

function suwcsms_send_sms($phone, $message)
{
    $aid = suwcsms_field('aid');
    $pin = suwcsms_field('pin');
    $sender = suwcsms_field('sender');
    suwcsms_send_sms_text($phone, $message, $aid, $pin, $sender);
}

function suwcsms_send_otp($phone, $message)
{
    $aid = suwcsms_field('otp_aid');
    $pin = suwcsms_field('otp_pin');
    $sender = suwcsms_field('otp_sender');

    //Send transactional SMS if required fields are missing
    if (empty($aid) || empty($pin) || empty($sender)) {
        suwcsms_send_sms($phone, $message);
    } else {
        suwcsms_send_sms_text($phone, $message, $aid, $pin, $sender);
    }
}

function suwcsms_send_sms_text($phone, $message, $aid, $pin, $sender)
{
    global $suwcsms_plugin_domn;

    //Don't send the SMS if required fields are missing
    if (empty($phone) || empty($message) || empty($aid) || empty($pin) || empty($sender))
        return;
    
    //Send the SMS by calling the API
    $message = suwcsms_message_encode($message);
    switch(suwcsms_field('api')) {
        case 1:
            $fetchurl = "http://rslr.connectbind.com:8080/bulksms/bulksms?username=$aid&password=$pin&type=0&dlr=1&destination=$phone&source=$sender&message=$message"; break;
        case 2:
            $fetchurl = "http://rslr.connectbind.com:8080/bulksms/bulksms?username=$aid&password=$pin&type=0&dlr=1&destination=$phone&source=$sender&message=$message"; break;
        default:
            $fetchurl = "http://rslr.connectbind.com:8080/bulksms/bulksms?username=$aid&password=$pin&type=0&dlr=1&destination=$phone&source=$sender&message=$message";
    }
    $response = suwcsms_remote_get($fetchurl);
    
	
	
	
    //Log the response
    if (1 == suwcsms_field('log_sms')) {
        $log_txt = __('Mobile number: ', $suwcsms_plugin_domn) . $phone . PHP_EOL;
        $log_txt .= __('Message: ', $suwcsms_plugin_domn) . $message . PHP_EOL;
        // $log_txt .= __('Raw API Call: ', $suwcsms_plugin_domn) . $fetchurl . PHP_EOL;
        $log_txt .= __('Gateway response: ', $suwcsms_plugin_domn) . $response . PHP_EOL;
        file_put_contents(__DIR__ . '/sms_log.txt', $log_txt, FILE_APPEND);
    }
}

/**
 * User registration OTP mechanism
 */

if (suwcsms_field('otp_user_reg') == 1) {
    add_action('register_form', 'suwcsms_register_form');
    // add_action('woocommerce_register_form_start', 'suwcsms_register_form');
    add_filter('registration_errors', 'suwcsms_registration_errors', 10, 3);
    add_action('woocommerce_register_post', 'suwcsms_wc_registration_errors', 10, 3);
    add_action('user_register', 'suwcsms_user_register');
    add_action('woocommerce_created_customer', 'suwcsms_user_register');
    if (suwcsms_field('otp_user_reg_wc') == 1)
        add_action('woocommerce_register_form', 'suwcsms_register_form');
}

function suwcsms_sanitize_data($data)
{
    $data = (!empty($data)) ? sanitize_text_field($data) : '';
    $data = preg_replace('/[^0-9]/', '', $data);
    return ltrim($data, '0');
}

function suwcsms_country_name($country='') {
    $countries = array(
		"AL" => 'Albania',
		"DZ" => 'Algeria',
		"AS" => 'American Samoa',
		"AD" => 'Andorra',
		"AO" => 'Angola',
		"AI" => 'Anguilla',
		"AQ" => 'Antarctica',
		"AG" => 'Antigua and Barbuda',
		"AR" => 'Argentina',
		"AM" => 'Armenia',
		"AW" => 'Aruba',
		"AU" => 'Australia',
		"AT" => 'Austria',
		"AZ" => 'Azerbaijan',
		"BS" => 'Bahamas',
		"BH" => 'Bahrain',
		"BD" => 'Bangladesh',
		"BB" => 'Barbados',
		"BY" => 'Belarus',
		"BE" => 'Belgium',
		"BZ" => 'Belize',
		"BJ" => 'Benin',
		"BM" => 'Bermuda',
		"BT" => 'Bhutan',
		"BO" => 'Bolivia',
		"BA" => 'Bosnia and Herzegovina',
		"BW" => 'Botswana',
		"BV" => 'Bouvet Island',
		"BR" => 'Brazil',
		"BQ" => 'British Antarctic Territory',
		"IO" => 'British Indian Ocean Territory',
		"VG" => 'British Virgin Islands',
		"BN" => 'Brunei',
		"BG" => 'Bulgaria',
		"BF" => 'Burkina Faso',
		"BI" => 'Burundi',
		"KH" => 'Cambodia',
		"CM" => 'Cameroon',
		"CA" => 'Canada',
		"CT" => 'Canton and Enderbury Islands',
		"CV" => 'Cape Verde',
		"KY" => 'Cayman Islands',
		"CF" => 'Central African Republic',
		"TD" => 'Chad',
		"CL" => 'Chile',
		"CN" => 'China',
		"CX" => 'Christmas Island',
		"CC" => 'Cocos [Keeling] Islands',
		"CO" => 'Colombia',
		"KM" => 'Comoros',
		"CG" => 'Congo - Brazzaville',
		"CD" => 'Congo - Kinshasa',
		"CK" => 'Cook Islands',
		"CR" => 'Costa Rica',
		"HR" => 'Croatia',
		"CU" => 'Cuba',
		"CY" => 'Cyprus',
		"CZ" => 'Czech Republic',
		"CI" => 'Côte d’Ivoire',
		"DK" => 'Denmark',
		"DJ" => 'Djibouti',
		"DM" => 'Dominica',
		"DO" => 'Dominican Republic',
		"NQ" => 'Dronning Maud Land',
		"DD" => 'East Germany',
		"EC" => 'Ecuador',
		"EG" => 'Egypt',
		"SV" => 'El Salvador',
		"GQ" => 'Equatorial Guinea',
		"ER" => 'Eritrea',
		"EE" => 'Estonia',
		"ET" => 'Ethiopia',
		"FK" => 'Falkland Islands',
		"FO" => 'Faroe Islands',
		"FJ" => 'Fiji',
		"FI" => 'Finland',
		"FR" => 'France',
		"GF" => 'French Guiana',
		"PF" => 'French Polynesia',
		"TF" => 'French Southern Territories',
		"FQ" => 'French Southern and Antarctic Territories',
		"GA" => 'Gabon',
		"GM" => 'Gambia',
		"GE" => 'Georgia',
		"DE" => 'Germany',
		"GH" => 'Ghana',
		"GI" => 'Gibraltar',
		"GR" => 'Greece',
		"GL" => 'Greenland',
		"GD" => 'Grenada',
		"GP" => 'Guadeloupe',
		"GU" => 'Guam',
		"GT" => 'Guatemala',
		"GG" => 'Guernsey',
		"GN" => 'Guinea',
		"GW" => 'Guinea-Bissau',
		"GY" => 'Guyana',
		"HT" => 'Haiti',
		"HM" => 'Heard Island and McDonald Islands',
		"HN" => 'Honduras',
		"HK" => 'Hong Kong SAR China',
		"HU" => 'Hungary',
		"IS" => 'Iceland',
		"IN" => 'India',
		"ID" => 'Indonesia',
		"IR" => 'Iran',
		"IQ" => 'Iraq',
		"IE" => 'Ireland',
		"IM" => 'Isle of Man',
		"IL" => 'Israel',
		"IT" => 'Italy',
		"JM" => 'Jamaica',
		"JP" => 'Japan',
		"JE" => 'Jersey',
		"JT" => 'Johnston Island',
		"JO" => 'Jordan',
		"KZ" => 'Kazakhstan',
		"KE" => 'Kenya',
		"KI" => 'Kiribati',
		"KW" => 'Kuwait',
		"KG" => 'Kyrgyzstan',
		"LA" => 'Laos',
		"LV" => 'Latvia',
		"LB" => 'Lebanon',
		"LS" => 'Lesotho',
		"LR" => 'Liberia',
		"LY" => 'Libya',
		"LI" => 'Liechtenstein',
		"LT" => 'Lithuania',
		"LU" => 'Luxembourg',
		"MO" => 'Macau SAR China',
		"MK" => 'Macedonia',
		"MG" => 'Madagascar',
		"MW" => 'Malawi',
		"MY" => 'Malaysia',
		"MV" => 'Maldives',
		"ML" => 'Mali',
		"MT" => 'Malta',
		"MH" => 'Marshall Islands',
		"MQ" => 'Martinique',
		"MR" => 'Mauritania',
		"MU" => 'Mauritius',
		"YT" => 'Mayotte',
		"FX" => 'Metropolitan France',
		"MX" => 'Mexico',
		"FM" => 'Micronesia',
		"MI" => 'Midway Islands',
		"MD" => 'Moldova',
		"MC" => 'Monaco',
		"MN" => 'Mongolia',
		"ME" => 'Montenegro',
		"MS" => 'Montserrat',
		"MA" => 'Morocco',
		"MZ" => 'Mozambique',
		"MM" => 'Myanmar [Burma]',
		"NA" => 'Namibia',
		"NR" => 'Nauru',
		"NP" => 'Nepal',
		"NL" => 'Netherlands',
		"AN" => 'Netherlands Antilles',
		"NT" => 'Neutral Zone',
		"NC" => 'New Caledonia',
		"NZ" => 'New Zealand',
		"NI" => 'Nicaragua',
		"NE" => 'Niger',
		"NG" => 'Nigeria',
		"NU" => 'Niue',
		"NF" => 'Norfolk Island',
		"KP" => 'North Korea',
		"VD" => 'North Vietnam',
		"MP" => 'Northern Mariana Islands',
		"NO" => 'Norway',
		"OM" => 'Oman',
		"PC" => 'Pacific Islands Trust Territory',
		"PK" => 'Pakistan',
		"PW" => 'Palau',
		"PS" => 'Palestinian Territories',
		"PA" => 'Panama',
		"PZ" => 'Panama Canal Zone',
		"PG" => 'Papua New Guinea',
		"PY" => 'Paraguay',
		"YD" => 'People\'s Democratic Republic of Yemen',
		"PE" => 'Peru',
		"PH" => 'Philippines',
		"PN" => 'Pitcairn Islands',
		"PL" => 'Poland',
		"PT" => 'Portugal',
		"PR" => 'Puerto Rico',
		"QA" => 'Qatar',
		"RO" => 'Romania',
		"RU" => 'Russia',
		"RW" => 'Rwanda',
		"RE" => 'Réunion',
		"BL" => 'Saint Barthélemy',
		"SH" => 'Saint Helena',
		"KN" => 'Saint Kitts and Nevis',
		"LC" => 'Saint Lucia',
		"MF" => 'Saint Martin',
		"PM" => 'Saint Pierre and Miquelon',
		"VC" => 'Saint Vincent and the Grenadines',
		"WS" => 'Samoa',
		"SM" => 'San Marino',
		"SA" => 'Saudi Arabia',
		"SN" => 'Senegal',
		"RS" => 'Serbia',
		"CS" => 'Serbia and Montenegro',
		"SC" => 'Seychelles',
		"SL" => 'Sierra Leone',
		"SG" => 'Singapore',
		"SK" => 'Slovakia',
		"SI" => 'Slovenia',
		"SB" => 'Solomon Islands',
		"SO" => 'Somalia',
		"ZA" => 'South Africa',
		"GS" => 'South Georgia and the South Sandwich Islands',
		"KR" => 'South Korea',
		"ES" => 'Spain',
		"LK" => 'Sri Lanka',
		"SD" => 'Sudan',
		"SR" => 'Suriname',
		"SJ" => 'Svalbard and Jan Mayen',
		"SZ" => 'Swaziland',
		"SE" => 'Sweden',
		"CH" => 'Switzerland',
		"SY" => 'Syria',
		"ST" => 'São Tomé and Príncipe',
		"TW" => 'Taiwan',
		"TJ" => 'Tajikistan',
		"TZ" => 'Tanzania',
		"TH" => 'Thailand',
		"TL" => 'Timor-Leste',
		"TG" => 'Togo',
		"TK" => 'Tokelau',
		"TO" => 'Tonga',
		"TT" => 'Trinidad and Tobago',
		"TN" => 'Tunisia',
		"TR" => 'Turkey',
		"TM" => 'Turkmenistan',
		"TC" => 'Turks and Caicos Islands',
		"TV" => 'Tuvalu',
		"UM" => 'U.S. Minor Outlying Islands',
		"PU" => 'U.S. Miscellaneous Pacific Islands',
		"VI" => 'U.S. Virgin Islands',
		"UG" => 'Uganda',
		"UA" => 'Ukraine',
		"SU" => 'Union of Soviet Socialist Republics',
		"AE" => 'United Arab Emirates',
		"GB" => 'United Kingdom',
		"US" => 'United States',
		"ZZ" => 'Unknown or Invalid Region',
		"UY" => 'Uruguay',
		"UZ" => 'Uzbekistan',
		"VU" => 'Vanuatu',
		"VA" => 'Vatican City',
		"VE" => 'Venezuela',
		"VN" => 'Vietnam',
		"WK" => 'Wake Island',
		"WF" => 'Wallis and Futuna',
		"EH" => 'Western Sahara',
		"YE" => 'Yemen',
		"ZM" => 'Zambia',
		"ZW" => 'Zimbabwe',
		"AX" => 'Åland Islands',
	);

    return ($country == '') ? $countries : (isset($countries[$country]) ? $countries[$country] : '');
}

function suwcsms_register_form()
{
    global $suwcsms_plugin_domn, $woocommerce;
    $country_code = sanitize_text_field($_POST['country_code']) ?: (class_exists('WC_Countries') ? WC_Countries::get_base_country() : 'IN');
    $phone_number = suwcsms_sanitize_data($_POST['phone_number']);
    $registration_otp = suwcsms_sanitize_data($_POST['registration_otp']);
    ?>
        <p>
            <label for="country_code"><?php _e('Phone Number', $suwcsms_plugin_domn) ?><br />
                <select name="country_code" id="country_code" class="input">
                <? foreach(suwcsms_country_name() as $code => $name) {
                    echo "<option value='$code' ", selected($country_code, $code), ">$name</option>";
                } ?>
                </select>
                <!--input type="text" name="country_code" id="country_code" class="input" value="<?php echo esc_attr($country_code); ?>" size="5" placeholder="Country Code"/-->
                <input type="text" name="phone_number" id="phone_number" class="input" value="<?php echo esc_attr($phone_number); ?>" size="20" placeholder="Phone Number"/>
            </label>
        </p>
        <p>
            <label for="registration_otp"><?php _e('Registration OTP', $suwcsms_plugin_domn) ?><br />
                <input type="text" name="registration_otp" id="registration_otp" class="input" value="<?php echo esc_attr($registration_otp); ?>" size="25" placeholder="To be sent..."/>
            </label>
        </p>
    <?php

}

function suwcsms_registration_errors($errors, $username, $user_email)
{
    global $suwcsms_plugin_domn;
    $country_code = sanitize_text_field($_POST['country_code']);
    $phone_number = suwcsms_sanitize_data($_POST['phone_number']);

    if (empty($country_code)) {
        $errors->add('country_code_error', __('Country name is required.', $suwcsms_plugin_domn));
    }

    if (empty($phone_number)) {
        $errors->add('phone_number_error', __('Numeric Phone Number is required.', $suwcsms_plugin_domn));
    }

    if (!empty($country_code) && !empty($phone_number)) {
        $billing_phone = suwcsms_country_prefix($country_code) . $phone_number;
        $billing_phone_otp = 'OTP_REG_' . $billing_phone;
        $stored_phone_otp = get_transient($billing_phone_otp);
        if ($stored_phone_otp == false) {
            $otp_number = suwcsms_generate_otp();
            $shop_name = get_bloginfo('name');
            $signature = suwcsms_field('signature');
            $message = "Dear Customer, Your OTP for registration confirmation on $shop_name is $otp_number. Kindly verify to confirm your registration. $signature";
            suwcsms_send_otp($billing_phone, $message);
            set_transient($billing_phone_otp, $otp_number, 3600);
            $errors->add('registration_otp_error', __('Info: Registration OTP has been sent to your mobile number.', $suwcsms_plugin_domn));
        } else {
            $registration_otp = suwcsms_sanitize_data($_POST['registration_otp']);
            if (empty($registration_otp)) {
                $errors->add('registration_otp_error', __('Registration OTP is required.', $suwcsms_plugin_domn));
            } elseif ($registration_otp !== $stored_phone_otp) {
                $errors->add('registration_otp_error', __('Registration OTP is invalid.', $suwcsms_plugin_domn));
            }
        }
    }

    return $errors;
}

function suwcsms_wc_registration_errors($username, $email, $errors)
{
    suwcsms_registration_errors($errors, $username, $user_email);
}

function suwcsms_user_register($user_id)
{
    $country_code = sanitize_text_field($_POST['country_code']);
    $phone_number = suwcsms_sanitize_data($_POST['phone_number']);
    if (!empty($country_code) && !empty($phone_number)) {
        $billing_phone = suwcsms_country_prefix($country_code) . $phone_number;
        $billing_phone_otp = 'OTP_REG_' . $billing_phone;
        delete_transient($billing_phone_otp);
        update_user_meta($user_id, 'billing_phone', $billing_phone);
        update_user_meta($user_id, 'billing_country', $country_code);
    }
}

/**
 * User login through OTP
 */

add_shortcode('suwcsms_otp_login', 'suwcsms_otp_login');
function suwcsms_otp_login($atts, $content = null)
{
    ob_start();
    $country_code = sanitize_text_field($_POST['country_code']) ?: (class_exists('WC_Countries') ? WC_Countries::get_base_country() : 'IN');
    $phone_number = suwcsms_sanitize_data($_POST['phone_number']);
    $login_otp = suwcsms_sanitize_data($_POST['login_otp']);
    ?>
<div id="suwcsms-otp-login-form">
    <div class='suwcsms-notifications'>
        <div class="woocommerce-info">
        An OTP will be sent to your registered mobile no. You will be logged-in upon completion of OTP verification.
        </div>
    </div>
    <div class="woocommerce-form">
        <p>
            <label for="suwcsms-phone-number"><?php _e('Phone Number', $suwcsms_plugin_domn) ?>
                <select name="country_code" id="suwcsms-country-code" class="input">
                <? foreach(suwcsms_country_name() as $code => $name) {
                    echo "<option value='$code' ", selected($country_code, $code), ">$name</option>";
                } ?>
                </select>
                <input type="text" id="suwcsms-phone-number" class="input" value="<?php echo esc_attr($phone_number); ?>" size="25"/>
                <a class="button" id="suwcsms_resend_otp_btn">Send OTP</a>
            </label>
        </p>
        <p class="otp_block">
            <label for="suwcsms-otp-field"><?php _e('OTP', $suwcsms_plugin_domn) ?>
                <input type="text" id="suwcsms-otp-field" class="input" value="<?php echo esc_attr($login_otp); ?>" size="25"/>
                <a class="button" id="suwcsms_verify_otp_btn">Verify & Login</a>
            </label>
        </p>
    </div>
</div>
<script type="text/javascript">
    var otp_failure_count = 0,
        otp_resend_count = 0;
    function showSpinner() {
        document.querySelector('.suwcsms-notifications').innerHTML = '<center><img src="<?= admin_url("images/spinner-2x.gif") ?>"/></center>';
    }
    function process_json_response(response) {
        var jsonobj = JSON.parse(response);
        if (jsonobj.error) {
            document.querySelector('.suwcsms-notifications').innerHTML = '<div class="woocommerce-error">'+jsonobj.message+'</div>';
            if (jsonobj.verification_failure) {
                otp_failure_count++;
                if (otp_failure_count > 3) {
                    document.querySelector('.suwcsms-notifications').innerHTML += '<br/><h3>It seems that there is some difficulty in logging you in. Please try again later.</h3>';
                }
            }
        } else {
            if (jsonobj.otp_verified) {
                // window.location.reload();
                window.location = '<?= esc_url(home_url("/")) ?>';
            } else {
                document.querySelector('.suwcsms-notifications').innerHTML = '<div class="woocommerce-message">'+jsonobj.message+'</div>';
                otp_resend_count++;
            }
        }
    }
    function suwcsms_make_ajax_post(data) {
        var request = new XMLHttpRequest();
        request.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>', true);
        request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;');
        request.onload = function() {
            if (request.status == 200) {
                process_json_response(request.responseText);
            }
        };
        request.send(data);
    }
    function suwcsms_verify_otp() {
        var country = document.getElementById('suwcsms-country-code').value;
        var phone = document.getElementById('suwcsms-phone-number').value;
        var otp = document.getElementById('suwcsms-otp-field').value;
        if (country.trim() == '') {
            document.querySelector('.suwcsms-notifications').innerHTML = 'Please select your country.';
            return;
        }
        if (phone.trim() == '') {
            document.querySelector('.suwcsms-notifications').innerHTML = 'Please enter the registered phone number.';
            return;
        }
        if (otp.trim() == '') {
            document.querySelector('.suwcsms-notifications').innerHTML = 'Please enter a valid OTP.';
            return;
        }
        showSpinner();
        suwcsms_make_ajax_post("action=suwcsms_verify_otp_login&country="+country+"&phone="+phone+"&otp="+otp);
    }
    function suwcsms_resend_otp() {
        var country = document.getElementById('suwcsms-country-code').value;
        var phone = document.getElementById('suwcsms-phone-number').value;
        if (country.trim() == '') {
            document.querySelector('.suwcsms-notifications').innerHTML = 'Please select your country.';
            return;
        }
        if (phone.trim() == '') {
            document.querySelector('.suwcsms-notifications').innerHTML = 'Please enter the registered phone number.';
            return;
        }
        disableResendOTP();
        showSpinner();
        suwcsms_make_ajax_post("action=suwcsms_send_otp_login&country="+country+"&phone="+phone)
    }
    function enableResendOTP() {
        if (otp_resend_count < 3) {
            document.querySelector('#suwcsms_resend_otp_btn').text = 'Resend OTP';
            document.querySelector('#suwcsms_resend_otp_btn').style.visibility = 'visible';
        }
    }
    function disableResendOTP() {
        document.querySelector('#suwcsms_resend_otp_btn').style.visibility = 'hidden';
        setTimeout(enableResendOTP, 120000);
    }
    document.querySelector('#suwcsms_resend_otp_btn').addEventListener('click', suwcsms_resend_otp);
    document.querySelector('#suwcsms_verify_otp_btn').addEventListener('click', suwcsms_verify_otp);
</script>
<?php
return ob_get_clean();
}

function suwcsms_get_user_by_phone($phone_number)
{
    return reset(
        get_users(
            array(
                'meta_key' => 'billing_phone',
                'meta_value' => $phone_number,
                'number' => 1,
                'fields' => 'ids',
                'count_total' => false
            )
        )
    );
}

//Request OTP via AJAX
add_action('wp_ajax_nopriv_suwcsms_send_otp_login', 'suwcsms_send_otp_login_callback');
function suwcsms_send_otp_login_callback()
{
    if (isset($_POST['action']) && $_POST['action'] == 'suwcsms_send_otp_login') {
        $data = ['error' => true, 'message' => 'Failed to send OTP. Ensure that you have included the ISD code in the number.'];
        $country_code = sanitize_text_field($_POST['country']);
        $billing_phone = suwcsms_sanitize_data($_POST['phone']);
        if (!empty($country_code) && !empty($billing_phone)) {
            $billing_phone = suwcsms_country_prefix($country_code) . $billing_phone;
            $user_id = suwcsms_get_user_by_phone($billing_phone);
            if (!empty($user_id)) {
                $transient_id = 'OTP_LOGIN_' . $user_id;
                $otp_number = get_transient($transient_id);
                if ($otp_number == false) {
                    $otp_number = suwcsms_generate_otp();
                    set_transient($transient_id, $otp_number, 120);
                }
                $shop_name = get_bloginfo('name');
                $signature = suwcsms_field('signature');
                $message = "Dear Customer, Your OTP for login confirmation on $shop_name is $otp_number. Kindly verify to confirm your login. $signature";
                suwcsms_send_otp($billing_phone, $message);
                $data = ['success' => true, 'message' => "OTP Sent to $billing_phone for login"];
            }
        }
        wp_send_json($data);
    }
    die();
}

add_action('wp_ajax_nopriv_suwcsms_verify_otp_login', 'suwcsms_verify_otp_login_callback');
function suwcsms_verify_otp_login_callback()
{
    if (isset($_POST['action']) && $_POST['action'] == 'suwcsms_verify_otp_login') {
        $data = ['error' => true, 'message' => 'OTP could not be verified', 'verification_failure' => true];
        $country_code = sanitize_text_field($_POST['country']);
        $billing_phone = suwcsms_sanitize_data($_POST['phone']);
        $user_otp = suwcsms_sanitize_data($_POST['otp']);
        if (!empty($country_code) && !empty($billing_phone) && !empty($user_otp)) {
            $billing_phone = suwcsms_country_prefix($country_code) . $billing_phone;
            $user_id = suwcsms_get_user_by_phone($billing_phone);
            if (!empty($user_id)) {
                $transient_id = 'OTP_LOGIN_' . $user_id;
                $otp_number = get_transient($transient_id);
                if ($otp_number == $user_otp) {
                    delete_transient($transient_id);
                    wp_clear_auth_cookie();
                    wp_set_current_user($user_id);
                    wp_set_auth_cookie($user_id);
                    $data = ['success' => true, 'message' => "Congrats! Your login is successful.", 'otp_verified' => true];
                }
            }
        }
        wp_send_json($data);
    }
    die();
}

// Add link on default login form
if (suwcsms_field('otp_user_log') == 1)
    add_action('login_form', 'suwcsms_disply_otp_login_option');
function suwcsms_disply_otp_login_option()
{
    ?>
    <p><a href="#suwcsms-login-form-popup">Login with OTP</a></p>
    <style>#suwcsms-login-form-popup{background:rgba(0,0,0,.5);position:absolute;top:0;left:0;width:100vw;height:100vh;overflow:hidden;display:none}#suwcsms-login-form-popup:target{display:flex;justify-content:center;align-items:center}#suwcsms-login-form-popup .close_btn{position:absolute;text-decoration:none;top:1vh;right:1vw;color:#fff;font-size:3em}#suwcsms-otp-login-form{background:#fff;min-width:50%;max-width:90%;padding:5%}</style>
    <div id="suwcsms-login-form-popup">
        <?= do_shortcode('[suwcsms_otp_login]') ?>
        <a href="#" class="close_btn">&times;</a>
    </div>
<?php

}