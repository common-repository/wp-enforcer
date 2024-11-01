<?php

// Check that the file is not accessed directly.
if(!defined("ABSPATH")) {
    die("We are sorry, but you can not directly access this file.");
}

// If not uninstalling.
if(!defined("WP_UNINSTALL_PLUGIN")) {
    die;
}

// Remove options.
delete_option("wp-enforcer-blocklist-protection");
delete_option("wp-enforcer-access-key");

?>
