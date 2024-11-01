<?php

/*
Plugin Name: WP Enforcer
Plugin URI: https://wpenforcer.com/
Description: Security, spam detection & uptime monitoring for your WordPress installation. Keep an eye on the most important aspects of your site from one central location. To get started, sign up for a <strong>FREE</strong> account at <a href="https://wpenforcer.com" target="_blank">WP Enforcer</a> and copy your access key into Settings -> WP Enforcer within WordPress.
Version: 1.3.0
Requires at least: 5.3
Tested up to: 5.8
Requires PHP: 7.0
Author: WP Enforcer
Author URI: https://wpenforcer.com/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: wpenforcer
*/

// Check that the file is not accessed directly.
if(!defined("ABSPATH")) {
    die("We are sorry, but you can not directly access this file.");
}

// Define plugin version.
define("WP_ENFORCER_PLUGIN_VERSION", "1.3.0");

// Set the absolute path for the plugin.
define("WP_ENFORCER_PLUGIN_DIRECTORY", dirname(__FILE__));
define("WP_ENFORCER_PLUGIN_URL", plugin_dir_url(__FILE__));

// API constants.
define("WP_ENFORCER_PROD_BASE_URL", "https://wpenforcer.com/");
define("WP_ENFORCER_DEV_BASE_URL", "http://localhost:3000/");
define("WP_ENFORCER_PROD_API_URL", "https://api.wpenforcer.com/");
define("WP_ENFORCER_DEV_API_URL", "http://localhost:3000/");

// Include class-files used by wpenforcer.
require_once(WP_ENFORCER_PLUGIN_DIRECTORY."/includes/wpenforcer.php");
require_once(WP_ENFORCER_PLUGIN_DIRECTORY."/includes/wpenforcer-admin-menu.php");

// Initialize.
new WPEnforcer();

?>
