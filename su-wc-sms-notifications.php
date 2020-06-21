<?php
/*
Plugin Name: WooCommerce  TxtmateGH SMS Notifications
Version: 1.4.1
Plugin URI: http://TxtmateGH.com/
Description: Sends SMS notifications to your clients for order status changes. You can also receive an SMS message when a new order is received.
Author: TxtmateGH
Requires at least: 3.8
Tested up to: 4.9.8
 */

//Exit if accessed directly
if (!defined('ABSPATH')) {
    exit();
}

//Define text domain
$suwcsms_plugin_name = 'WooCommerce SMS Notification';
$suwcsms_plugin_file = plugin_basename(__FILE__);
$suwcsms_plugin_domn = 'suwcsms';
load_plugin_textdomain($suwcsms_plugin_domn, false, dirname($suwcsms_plugin_file) . '/languages');

//Add links to plugin listing
add_filter("plugin_action_links_$suwcsms_plugin_file", 'suwcsms_add_action_links');
function suwcsms_add_action_links($links)
{
    global $suwcsms_plugin_domn;
    $links[] = '<a href="' . admin_url("admin.php?page=$suwcsms_plugin_domn") . '">Settings</a>';
    $links[] = '<a href="https://txtmategh.com" target="_blank">Plugin Documentation</a>';
    return $links;
}

//Add links to plugin settings page
add_filter('plugin_row_meta', "suwcsms_plugin_row_meta", 10, 2);
function suwcsms_plugin_row_meta($links, $file)
{
    global $suwcsms_plugin_file;
    if (strpos($file, $suwcsms_plugin_file) !== false) {
        $links[] = '<a href="https://apps.txtmategh.com/" target="_blank">Get Credentials</a>';
        $links[] = '<a href="https://txtmategh.com/" target="_blank">Plugin Documentation</a>';
    }
    return $links;
}

//WooCommerce is required for the plugin to work
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    include('plugin-core.php');
} else {
    add_action('admin_notices', 'suwcsms_require_wc');
    function suwcsms_require_wc()
    {
        global $suwcsms_plugin_name, $suwcsms_plugin_domn;
        echo '<div class="error fade" id="message"><h3>' . $suwcsms_plugin_name . '</h3><h4>' . __("This plugin requires WooCommerce", $suwcsms_plugin_domn) . '</h4></div>';
        deactivate_plugins($suwcsms_plugin_file);
    }
}

//Handle uninstallation
register_uninstall_hook(__FILE__, 'suwcsms_uninstaller');
function suwcsms_uninstaller()
{
    delete_option('suwcsms_settings');
}
