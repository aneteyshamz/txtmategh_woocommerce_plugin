<?php

if (!defined('ABSPATH')) exit;

global $suwcsms_plugin_domn, $suwcsms_settings, $wpml_active;

function suwcsms_gets_value($var, $check = false)
{
    global $suwcsms_settings;
    $retval = '';
    if (isset($suwcsms_settings[$var])) {
        if ($check) {
            if ($suwcsms_settings[$var] == 1) {
                $retval = 'checked="checked"';
            }
        } else {
            $retval = $suwcsms_settings[$var];
        }
    }
    return $retval;
}
?>
<style>
h3.title {
	background-color: #ddd !important;
	padding: 10px;
}
#template_settings {
    width: 70%;
    display: inline-block;
    margin: 0;
}
#edit_instructions {
    width: 25%;
    display: inline-block;
    margin: 0;
    padding: 0 10px;
    background-color: #ddd;
    vertical-align: top;
    margin-top: 1rem;
}
</style>
<div class="wrap woocommerce">
  <?php settings_errors(); ?>

  <h2>WooCommerce SMS Notifications (TxtMateGH)</h2>
  <?php _e('Allows WooCommerce to send <abbr title="Short Message Service" lang="en">SMS</abbr> notifications on each order status change. It can also notify the owner when a new order is received. You can also send notifications for custom status, and use custom variables.', $suwcsms_plugin_domn); ?>
  <br/>
  
  <form method="post" action="options.php" id="mainform">
    <?php settings_fields('suwcsms_settings_group'); ?>
    
    <h3 class="title">Account Credentials</h3>
    <?php _e('You can obtain credentials by registering at <a href="http://txtmategh.com" target="_blank">our site</a>', $suwcsms_plugin_domn); ?>
    <br/>
    <table class="form-table">
    <?php
    $reg_fields = array(
        'aid' => 'Username',
        'pin' => 'Password',
        'sender' => 'Sender ID',
        'mnumber' => 'Registered Mobile Number',
    );

    foreach ($reg_fields as $k => $v) {
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo $k; ?>"><?php echo $v; ?></label>
                <?php _e(wc_help_tip(sprintf(__("Your %s as registerd with TxtMateGH", $suwcsms_plugin_domn), __($v, $suwcsms_plugin_domn))), $suwcsms_plugin_domn); ?>
            </th>
            <td class="forminp">
                <input type="text" id="<?php echo $k; ?>" name="suwcsms_settings[<?php echo $k; ?>]" size="50" value="<?php echo suwcsms_gets_value($k); ?>" <?php echo ($k != 'mnumber') ? 'required="required"' : ''; ?>/>
            </td>
        </tr>
    <?php

}
$selected_api = suwcsms_gets_value('api') ?: 3;
?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="api">API</label>
                <?php _e(wc_help_tip(__("Your API as instructed by txtmategh", $suwcsms_plugin_domn)), $suwcsms_plugin_domn); ?>
            </th>
            <td class="forminp">
                <select id="api" name="suwcsms_settings[api]">
                    <option value=1 <?= $selected_api == 1 ? 'selected' : '' ?>>API #1</option>
                    <option value=2 <?= $selected_api == 2 ? 'selected' : '' ?>>API #2</option>
                    <option value=3 <?= $selected_api == 3 ? 'selected' : '' ?>>API #3</option>
                </select>
            </td>
        </tr>
    </table>
    
    <span id="template_settings">
    <h3 class="title">SMS Templates</h3>
    <ol>
        <li>
            <b><?php _e('All SMS template changes need to be whitelisted. Please do not modify the templates below unless you receive approval mail for a change.', $suwcsms_plugin_domn); ?></b>
        </li>
        <li>
            <?php
            _e('You can use following variables in your templates:', $suwcsms_plugin_domn);

            $vars = array('id', 'order_key', 'billing_first_name', 'billing_last_name', 'billing_company', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_postcode', 'billing_country', 'billing_state', 'billing_email', 'billing_phone', 'shipping_first_name', 'shipping_last_name', 'shipping_company', 'shipping_address_1', 'shipping_address_2', 'shipping_city', 'shipping_postcode', 'shipping_country', 'shipping_state', 'shipping_method', 'shipping_method_title', 'payment_method', 'payment_method_title', 'order_discount', 'cart_discount', 'order_tax', 'order_shipping', 'order_shipping_tax', 'order_total', 'status', 'prices_include_tax', 'tax_display_cart', 'display_totals_ex_tax', 'display_cart_ex_tax', 'order_date', 'modified_date', 'customer_message', 'customer_note', 'post_status', 'shop_name', 'order_product');

            foreach ($vars as $var) {
                echo ' <code>%' . $var . '%</code>';
            }
            ?>
        </li>
        <li>
            <?php _e('<b>CAUTION:</b> Any undefined variable will be included as it is upon its use.', $suwcsms_plugin_domn); ?>
        </li>
        <li>
            <?php _e('You can also add custom variables which are created by other plugins, and are part of order meta. Each variable must be entered onto a new line without percentage character ( % ). Example: <code>_custom_variable_name</code> <code>_another_variable_name</code>.', $suwcsms_plugin_domn); ?>
        </li>
    </ol>
    
    <table class="form-table">
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="variables"><?php _e('Custom variables', $suwcsms_plugin_domn); ?></label>
            </th>
            <td class="forminp forminp-number">
                <textarea id="variables" name="suwcsms_settings[variables]" cols="50" rows="5" ><?php echo stripcslashes(suwcsms_gets_value('variables')); ?></textarea>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="signature"><?php _e('Signature', $suwcsms_plugin_domn); ?></label>
                <?php _e(wc_help_tip('Text to append to all client messages. E.g., Reach us at support@yoursite.com'), $suwcsms_plugin_domn); ?>
            </th>
            <td class="forminp">
                <input type="text" id="signature" name="suwcsms_settings[signature]" size="50" value="<?php echo suwcsms_gets_value('signature'); ?>"/>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="addnumber"><?php _e('Additional Numbers', $suwcsms_plugin_domn); ?></label>
                <?php _e(wc_help_tip('Additional Numbers for New Order Notifications: comma-separated'), $suwcsms_plugin_domn); ?>
            </th>
            <td class="forminp">
                <input type="text" id="addnumber" name="suwcsms_settings[addnumber]" size="50" value="<?php echo suwcsms_gets_value('addnumber'); ?>"/>
            </td>
        </tr>
    <?php
    $templates = array(
        'msg_new_order' => array(
            'New Order message',
            'Message sent to you on receipt of a new order',
            isset($suwcsms_settings['msg_new_order']) ? $suwcsms_settings['msg_new_order'] : "Order %id% has been received on %shop_name%."
        ),
        'msg_pending' => array(
            'Pending Payment message',
            'Message sent to the client when a new order is awaiting payment',
            isset($suwcsms_settings['msg_pending']) ? $suwcsms_settings['msg_pending'] : "Dear %billing_first_name%, your order on %shop_name% is awaiting payment. %signature%"
        ),
        'msg_on_hold' => array(
            'On-Hold message',
            'Message sent to the client when an order goes on-hold',
            isset($suwcsms_settings['msg_on_hold']) ? $suwcsms_settings['msg_on_hold'] : "Dear %billing_first_name%, your order %id% on %shop_name% is on-hold. %signature%"
        ),
        'msg_processing' => array(
            'Order Processing message',
            'Message sent to the client when an order is under process',
            isset($suwcsms_settings['msg_processing']) ? $suwcsms_settings['msg_processing'] : "Dear %billing_first_name%, your order %id% on %shop_name% is being processed. %signature%"
        ),
        'msg_completed' => array(
            'Order Completed message',
            'Message sent to the client when an order is completed',
            isset($suwcsms_settings['msg_completed']) ? $suwcsms_settings['msg_completed'] : "Dear %billing_first_name%, your order %id% on %shop_name% has been completed. %signature%"
        ),
        'msg_cancelled' => array(
            'Order Cancelled message',
            'Message sent to the client when an order is cancelled',
            isset($suwcsms_settings['msg_cancelled']) ? $suwcsms_settings['msg_cancelled'] : "Dear %billing_first_name%, your order %id% on %shop_name% has been cancelled. %signature%"
        ),
        'msg_refunded' => array(
            'Payment Refund message',
            'Message sent to the client when an order payment is refunded',
            isset($suwcsms_settings['msg_refunded']) ? $suwcsms_settings['msg_refunded'] : "Dear %billing_first_name%, payment for your order %id% on %shop_name% has been refunded. It may take a few business days to reflect in your account. %signature%"
        ),
        'msg_failure' => array(
            'Payment Failure message',
            'Message sent to the client when a payment fails',
            isset($suwcsms_settings['msg_failure']) ? $suwcsms_settings['msg_failure'] : "Dear %billing_first_name%, recent attempt for payment towards your order on %shop_name% has failed. Please retry by visiting order history in My Account section. %signature%"
        ),
        'msg_custom' => array(
            'Custom Status message',
            'Message sent to the client when order moves to a custom status (defined by other plugins)',
            isset($suwcsms_settings['msg_custom']) ? $suwcsms_settings['msg_custom'] : "Dear %billing_first_name%, your order %id% on %shop_name% has been %status%. Please review your order. %signature%"
        )
    );

    $script_cont = "";
    foreach ($templates as $k => $a) {
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo 'use_' . $k; ?>"><?php _e($a[0], $suwcsms_plugin_domn); ?></label>
                <?php _e(wc_help_tip($a[1]), $suwcsms_plugin_domn); ?>
            </th>
            <td class="forminp">
                <input id="<?php echo 'use_' . $k; ?>" name="suwcsms_settings[<?php echo 'use_' . $k; ?>]" type="checkbox" value="1" <?php echo suwcsms_gets_value('use_' . $k, true); ?> /> <?php _e('Send this message', $suwcsms_plugin_domn); ?>
                <span class="<?php echo $k; ?>">
                    <br/>
                    <input class="msg-template" id="<?php echo $k; ?>" name="suwcsms_settings[<?php echo $k; ?>]" type="text" size="50" value="<?php echo stripcslashes($a[2]); ?>" readonly="readonly" required="required"/>
                    <a class="<?php echo $k; ?>_link"><?php _e('Edit Template', $suwcsms_plugin_domn); ?></a>
                </span>
            </td>
        </tr>
    <?php
    $script_cont .= ($suwcsms_settings['use_' . $k] == 1) ? '' : ('$(".' . $k . '").hide();' . PHP_EOL);
    $script_cont .= '$("input#use_' . $k . '").change(function(){$(".' . $k . '").toggle();});' . PHP_EOL;
    $script_cont .= '$(".' . $k . '_link").click(function(){$(".' . $k . ' input").attr("readonly", false).focus();});' . PHP_EOL;
        // $script_cont .= 'defaults["' . $k . '"] = "' . $a[2] . '";' . PHP_EOL;
}
?>
    </table>
    
    <h3 class="title">OTP Settings</h3>
    <table class="form-table">
    <?php
    $otp_fields = array(
        'otp_aid' => 'OTP User Account ID',
        'otp_pin' => 'OTP User PIN / API Key',
        'otp_sender' => 'OTP Sender ID (6-letter)',
        'otp_mnumber' => 'OTP Registered Mobile Number',
    );

    foreach ($otp_fields as $k => $v) {
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo $k; ?>"><?php echo $v; ?></label>
                <?php _e(wc_help_tip(sprintf(__("Your %s as registerd with TxtmateGH", $suwcsms_plugin_domn), __($v, $suwcsms_plugin_domn))), $suwcsms_plugin_domn); ?>
            </th>
            <td class="forminp">
                <input type="text" id="<?php echo $k; ?>" name="suwcsms_settings[<?php echo $k; ?>]" size="50" value="<?php echo suwcsms_gets_value($k); ?>"/>
            </td>
        </tr>
    <?php 
} ?>
        <p>Note: If no credentials are provided then the credentials from Account Credentials section will be used.</p>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="otp_cod"><?php _e('Require OTP For', $suwcsms_plugin_domn); ?></label>
            </th>
            <td class="forminp">
                <input id="otp_cod" name="suwcsms_settings[otp_cod]" type="checkbox" value="1" <?php echo suwcsms_gets_value('otp_cod', true); ?> /> <?php _e('Cash on Delivery Orders', $suwcsms_plugin_domn); ?><br/>
                <input id="otp_cheque" name="suwcsms_settings[otp_cheque]" type="checkbox" value="1" <?php echo suwcsms_gets_value('otp_cheque', true); ?> /> <?php _e('Check Payment Orders', $suwcsms_plugin_domn); ?><br/>
                <input id="otp_bacs" name="suwcsms_settings[otp_bacs]" type="checkbox" value="1" <?php echo suwcsms_gets_value('otp_bacs', true); ?> /> <?php _e('BACS Payment Orders', $suwcsms_plugin_domn); ?><br/>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="otp_pre_status"><?php _e('Order status until OTP verification', $suwcsms_plugin_domn); ?></label>
            </th>
            <td class="forminp">
                <input type="text" id="otp_pre_status" name="suwcsms_settings[otp_pre_status]" size="50" value="<?php echo suwcsms_gets_value('otp_pre_status') ? : 'pending'; ?>" required/>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="otp_post_status"><?php _e('Order status after OTP verification', $suwcsms_plugin_domn); ?></label>
            </th>
            <td class="forminp">
                <input type="text" id="otp_post_status" name="suwcsms_settings[otp_post_status]" size="50" value="<?php echo suwcsms_gets_value('otp_post_status') ? : 'processing'; ?>" required/>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="otp_cod"><?php _e('User Registration', $suwcsms_plugin_domn); ?></label>
                <?php _e(wc_help_tip(__("Adds Mobile Number and OTP fields in WordPress & WooCommerce Registration Forms", $suwcsms_plugin_domn)), $suwcsms_plugin_domn); ?>
            </th>
            <td class="forminp">
                <input id="otp_user_reg" name="suwcsms_settings[otp_user_reg]" type="checkbox" value="1" <?php echo suwcsms_gets_value('otp_user_reg', true); ?> /> <?php _e('Require Mobile Number & OTP Verification for User Registration', $suwcsms_plugin_domn); ?><br/>
                <input id="otp_user_reg_wc" name="suwcsms_settings[otp_user_reg_wc]" type="checkbox" value="1" <?php echo suwcsms_gets_value('otp_user_reg_wc', true); ?> /> <?php _e('Check this box if the new fields do not appear on your WooCommerce user registration form', $suwcsms_plugin_domn); ?><br/>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="otp_cod"><?php _e('User Login', $suwcsms_plugin_domn); ?></label>
                <?php _e(wc_help_tip(__("Adds Mobile Number and OTP based login link on default login page. You can also use [suwcsms_otp_login] shortcode anywhere to display a mobile & OTP based login form.", $suwcsms_plugin_domn)), $suwcsms_plugin_domn); ?>
            </th>
            <td class="forminp">
                <input id="otp_user_log" name="suwcsms_settings[otp_user_log]" type="checkbox" value="1" <?php echo suwcsms_gets_value('otp_user_log', true); ?> /> <?php _e('Allow login with Mobile Number through OTP', $suwcsms_plugin_domn); ?><br/>
            </td>
        </tr>
    </table>
    
    <h3 class="title">Additional Settings</h3>
    Please send a mail to <a href="mailto:support@txtmategh.com">support@txtmategh.com</a> to buy international SMS pack
    <table class="form-table">
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="alt_phone"><?php _e('Use Shipping Phone', $suwcsms_plugin_domn); ?></label>
            </th>
            <td class="forminp">
                <input id="alt_phone" name="suwcsms_settings[alt_phone]" type="checkbox" value="1" <?php echo suwcsms_gets_value('alt_phone', true); ?> /> <?php _e('Send SMS to phone number in shipping address', $suwcsms_plugin_domn); ?>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="log_sms"><?php _e('Keep A Log', $suwcsms_plugin_domn); ?></label>
            </th>
            <td class="forminp">
                <input id="log_sms" name="suwcsms_settings[log_sms]" type="checkbox" value="1" <?php echo suwcsms_gets_value('log_sms', true); ?> /> <?php _e('Maintain a log of all SMS activities', $suwcsms_plugin_domn); ?>
            </td>
        </tr>
    </table>
    </span>
    <span id="edit_instructions">
    <h2>Instructions for Template Editing</h2>
    <ol>
        <li>Enable the "Send this message" checkbox for an event, for which you wish to edit the template.</li>
        <li>A message temaplate may include static text, standard variables, and custom variables.</li>
        <li>All the message templates require to be whitelisted before they can be used by SMS notifications.</li>
        <li>If you wish to modify a template, drop a mail to <a href="mailto:support@txtmategh.com">support@txtmategh.com</a> with the message template.</li>
        <li>When a template is approved/rejected, you will receive a notification for the same on email.</li>
        <li>After the message template has been approved, click on "Edit Template" link after the template input box. Template input box will now become editable, and you can update the approved template text here.</li>
        <li>Once all desired templates have been modified, click on "Save Changes" button.</li>
    </ol>
    </span>
    <p class="submit">
        <input class="button-primary" type="submit" value="<?php _e('Save Changes', $suwcsms_plugin_domn); ?>"  name="submit" id="submit" />
    </p>
  </form>
</div>
<script type="text/javascript">
    jQuery(document).ready(function($){
       <?php echo $script_cont; ?>
       
       if ( $('#aid').val() == '' || $('#pin').val() == '' || $('#sender').val() == '' )
           $('#template_settings, #edit_instructions').hide();
    });
</script>