<?php

// Check that the file is not accessed directly.
if(!defined("ABSPATH")) {
    die("We are sorry, but you can not directly access this file.");
}

// Setup admin actions.
add_action("admin_menu", "wp_enforcer_admin_menu");
add_action("admin_init", "wpenforcer_admin_init");
add_action("admin_enqueue_scripts", "wp_enforcer_load_admin_style");

// Load the CSS to style the plugin.
function wp_enforcer_load_admin_style() {
    wp_enqueue_style("admin_css", WP_ENFORCER_PLUGIN_URL."css/style.css", false, WP_ENFORCER_PLUGIN_VERSION);
    wp_enqueue_script("admin_js", WP_ENFORCER_PLUGIN_URL."js/base.js", array("jquery"), WP_ENFORCER_PLUGIN_VERSION, false);
}

// Add an options page.
function wp_enforcer_admin_menu() {
    add_options_page("WP Enforcer Options", "WP Enforcer", "manage_options", "wpenforcer", "wpenforcer_plugin_options");
}

// Initialize a couple settings.
function wpenforcer_admin_init() {
    register_setting("wpenforcer-group", "wp-enforcer-access-key");
    register_setting("wpenforcer-group", "wp-enforcer-blocklist-protection");
    register_setting("wpenforcer-group", "wp-enforcer-spam-protection");
}

// Simple settings page needed to configure the plugin to start publishing audit data.
function wpenforcer_plugin_options() {
    $api_url = WP_ENFORCER_PROD_API_URL;
    $base_url = WP_ENFORCER_PROD_BASE_URL;

    // For dev work.
    if(strpos(get_site_url(), "http://localhost:8888") !== false) {
        $api_url = WP_ENFORCER_DEV_API_URL;
        $base_url = WP_ENFORCER_DEV_BASE_URL;
    }

    $no_key = false;
    $status = 200;
    $plan = "unknown";

    if(get_option("wp-enforcer-access-key")) {
        $res = wp_remote_get($api_url."webhook/info/".get_option("wp-enforcer-access-key")."?=wp_enforcer_version=".WP_ENFORCER_PLUGIN_VERSION, array(
            "timeout" => 1,
            "redirection" => 1
        ));

        if(!is_wp_error($res)) {
            $status = $res["response"]["code"];

            if($status != "200") {
                $status = 400;

                error_log("error code 1: could not get webook info");
            } else {
                $res = json_decode($res["body"]);

                if(isset($res) && isset($res->plan)) {
                    $plan = $res->plan;
                }
            }
        } else {
            $status = 500;

            error_log("error code 2: could not get webook info");
        }
    } else {
        $no_key = true;
    } ?>

    <div id="wpe-plugin-wrapper">
        <div id="wpe-header-wrapper">
            <div id="wpe-header">
                <div id="wpe-logo">
                    <a href="<?php echo $base_url; ?>" target="_blank">
                        <img src="https://wpenforcer.com/img/logo.svg" />
                    </a>
                    <?php
                    if($no_key) { ?>
                        <code class="wpe-background-default">Please enter access key</code>
                    <?php
                    } else if($status == 400) { ?>
                        <code class="wpe-background-warning">Invalid access key</code>
                    <?php
                    } else if($status == 500) { ?>
                        <code class="wpe-background-danger">Error communicating with WP Enforcer</code>
                    <?php
                    } else { ?>
                        <code class="wpe-background-success"> Key active</code>

                        <?php
                        if($plan == "premium") { ?>
                            <code class="wpe-background-info">Premium</code>

                            <?php
                            if($res->view_only) { ?>
                                <code class="wpe-background-info">View Only Enabled</code>
                            <?php
                            } else { ?>
                                <code class="wpe-background-default">View Only Disabled</code>
                            <?php
                            } ?>
                        <?php
                        } else if($plan == "basic") { ?>
                            <code class="wpe-background-info">Free</code>
                        <?php
                        } ?>
                    <?php
                    } ?>
                </div>
                <div id="wpe-button-switch">
                    <a id="wpe-enable-dashboard" href="#" onclick="return false;" type="button" class="wpe-button-switch wpe-button-switch-left <?php if($status == 200 && !$no_key) { echo 'wpe-button-switch-primary'; } ?>">Dashboard</a>
                    <a id="wpe-enable-settings" href="#" onclick="return false;" type="button" class="wpe-button-switch wpe-go-to-settings wpe-button-switch-right <?php if($status != 200 || $no_key) { echo 'wpe-button-switch-primary'; } ?>">Settings</a>
                </div>
                <div class="wpe-clear-both"></div>
            </div>
        </div>

        <div id="wpe-body">
            <div id="wpe-settings-wrapper" <?php if($status == 200 && !$no_key) { echo 'style=display:none;'; } ?>>
                <form action="options.php" method="post">
                    <?php settings_fields("wpenforcer-group"); ?>
                    <div class="wpe-header-section">
                        <h2 class="wpe-section-header">Settings</h2>
                    </div>
                    <div class="wpe-content-container">
                        <div class="wpe-content-header">
                            <p><strong>Access Key</strong></p>
                            <p><small>Found via your <a href="<?php echo $base_url."keys"; ?>" target="_blank">WP Enforcer</a> keys page. It's <strong>FREE</strong> to signup and start auditing!</small></p>
                        </div>
                        <div class="wpe-content-body">
                            <?php if($status !== 500) { ?>
                                <input id="wpe-access-key" type="text" name="wp-enforcer-access-key" value="<?php echo sanitize_text_field(get_option("wp-enforcer-access-key")); ?>" placeholder="" autocomplete="off">
                            <?php } else { ?>
                                <p>Error communicating with WP Enforcer.</p>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="wpe-header-section">
                        <h2 class="wpe-section-header">Protection</h2>
                    </div>
                    <div class="wpe-content-container wpe-forty-nine wpe-left">
                        <div class="wpe-content-header">
                            <p>
                                <?php if($plan == "premium") { ?>
                                    <strong>Manual Blocklist</strong>
                                <?php } else { ?>
                                    <strong>Manual Blocklist (Premium Only)</strong>
                                <?php } ?>
                            </p>
                            <p><small>Redirect illegitimate traffic to a <a href="<?php echo $base_url."blocked"; ?>" target="_blank">safe zone</a> away from your site.</small></p>
                        </div>
                        <div class="wpe-content-body">
                            <?php if($plan == "premium") { ?>
                                <?php if(get_option("wp-enforcer-blocklist-protection") == "on") { ?>
                                    <input type="checkbox" name="wp-enforcer-blocklist-protection" id="wp-enforcer-blocklist-protection" checked="checked"> Enable
                                <?php } else { ?>
                                    <input type="checkbox" name="wp-enforcer-blocklist-protection" id="wp-enforcer-blocklist-protection"> Enable
                                <?php } ?>
                            <?php } else if($plan == "basic") { ?>
                                <p>To enable blocklist protection please sign up for a <a href="<?php echo $base_url."billing"; ?>" target="blank">premium</a> plan.</p>
                            <?php } else if($status !== 500) { ?>
                                <p>To enable blocklist protection please use a valid access key.</p>
                            <?php } else { ?>
                                <p>Error communicating with WP Enforcer.</p>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="wpe-content-container wpe-forty-nine wpe-right">
                        <div class="wpe-content-header">
                            <p>
                                <?php if($plan == "premium") { ?>
                                    <strong>SPAM</strong>
                                <?php } else { ?>
                                    <strong>SPAM (Premium Only)</strong>
                                <?php } ?>
                            </p>
                            <p><small>Powered by <a href="https://akismet.com/" target="_blank">Akismet</a>. No additional license required.</small></p>
                        </div>
                        <div class="wpe-content-body">
                            <?php if($plan == "premium") { ?>
                                <?php if(get_option("wp-enforcer-spam-protection") == "on") { ?>
                                    <input type="checkbox" name="wp-enforcer-spam-protection" id="wp-enforcer-spam-protection" checked="checked"> Enable
                                <?php } else { ?>
                                    <input type="checkbox" name="wp-enforcer-spam-protection" id="wp-enforcer-spam-protection"> Enable
                                <?php } ?>
                            <?php } else if($plan == "basic") { ?>
                                <p>To enable SPAM protection please sign up for a <a href="<?php echo $base_url."billing"; ?>" target="blank">premium</a> plan.</p>
                            <?php } else if($status !== 500) { ?>
                                <p>To enable SPAM protection please use a valid access key.</p>
                            <?php } else { ?>
                                <p>Error communicating with WP Enforcer.</p>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="wpe-clear-both"></div>
                    <br />
                    <?php if($status !== 500) { @submit_button("Save Settings"); } ?>
                </form>

                <p>For more detailed information visit your WP Enforcer <a href="<?php echo $base_url."dashboard"; ?>" target="_blank">dashboard</a>.</p>
            </div>

            <?php
            if($status == 200 && !$no_key) { ?>
                <div id="wpe-dashboard-wrapper">
                    <div class="wpe-header-section">
                        <h2 class="wpe-section-header">Activity</h2>
                    </div>
                    <div class="wpe-content-container">
                        <div class="wpe-content-header">
                            <p><strong class="wpe-success">Allowed Activity <?php if($plan != "premium") { echo '(Free)'; } ?></strong></p>
                            <p><small>Traffic that has been allowed into your site.</small></p>
                        </div>
                        <div class="wpe-content-body">
                            <?php
                            if(count($res->activity) > 0) { ?>
                                <table class="wpe-table">
                                    <tr>
                                        <th class="wpe-table-right-pad">Username</th>
                                        <th class="wpe-table-right-pad">Type</th>
                                        <th class="wpe-table-right-pad">Tag</th>
                                        <th class="wpe-table-right-pad">Country</th>
                                        <th class="wpe-table-right-pad">Region</th>
                                        <th class="wpe-table-right-pad">City</th>
                                        <th>Threat Level</th>
                                    </tr>

                                    <?php
                                    foreach($res->activity as $activity) {
                                        echo "<tr>";
                                        echo "<td class='wpe-table-right-pad'>".$activity->username."</td>";
                                        echo "<td class='wpe-table-right-pad'>".$activity->type."</td>";
                                        echo "<td class='wpe-table-right-pad'>".$activity->tag."</td>";
                                        echo "<td class='wpe-table-right-pad'>".$activity->country_name."</td>";
                                        echo "<td class='wpe-table-right-pad'>".$activity->region_code."</td>";
                                        echo "<td class='wpe-table-right-pad'>".$activity->city."</td>";
                                        echo "<td>".$activity->threat_level."</td>";
                                        echo "</tr>";
                                    } ?>
                                </table>

                                <br />
                                <small>To view the complete allowed activity login to your <a href="<?php echo $base_url."dashboard"; ?>" target="blank">dashboard</a>.</small>
                            <?php
                            } else { ?>
                                <p>No recent allowed activity.</p>
                            <?php
                            } ?>
                        </div>
                    </div>

                    <br />

                    <div class="wpe-content-container">
                        <div class="wpe-content-header">
                            <div class="wpe-left wpe-forty-nine">
                                <p><strong class="wpe-danger">Manual Blocked Activity <?php if($plan != "premium") { echo '(Premium)'; } ?></strong></p>
                                <p><small>Rules you have created to block specific patterns of traffic.</small></p>
                            </div>
                            <div class="wpe-right wpe-forty-nine wpe-text-right">
                                <small><strong>WP Enforcer: <?php if($plan == "premium") { echo '<span class="wpe-success">Online</span>'; } else { echo '<span class="wpe-warning">Not Available</span>'; } ?></span></strong></small>

                                <br />

                                <small>
                                    <strong>
                                        <?php
                                        if(get_option("wp-enforcer-blocklist-protection") == "on") { ?>
                                            Plugin Settings: <span class="wpe-success">Enabled</span>
                                        <?php
                                        } else { ?>
                                            Plugin Settings: <span class="wpe-warning wpe-go-to-settings wpe-cursor-hover">Not Enabled</span>
                                        <?php
                                        } ?>
                                    </strong>
                                </small>
                            </div>
                            <div class="wpe-clear-both"></div>
                        </div>
                        <div class="wpe-content-body">
                            <?php
                            if($plan == "premium") {
                                if(count($res->blocks) > 0) { ?>
                                    <table class="wpe-table">
                                        <tr>
                                            <th class="wpe-table-right-pad">Country</th>
                                            <th class="wpe-table-right-pad">Region</th>
                                            <th class="wpe-table-right-pad">City</th>
                                            <th class="wpe-table-right-pad">Type</th>
                                            <th class="wpe-table-right-pad">Tag</th>
                                            <th>Num Blocks</th>
                                        </tr>

                                        <?php
                                        foreach($res->blocks as $block) {
                                            echo "<tr>";
                                            echo "<td class='wpe-table-right-pad'>".$block->country_name."</td>";
                                            echo "<td class='wpe-table-right-pad'>".$block->region_code."</td>";
                                            echo "<td class='wpe-table-right-pad'>".$block->city."</td>";
                                            echo "<td class='wpe-table-right-pad'>".$block->type."</td>";
                                            echo "<td class='wpe-table-right-pad'>".$block->tag."</td>";
                                            echo "<td>".$block->num_blocks."</td>";
                                            echo "</tr>";
                                        } ?>
                                    </table>

                                    <br />
                                    <small>To view the complete manual blocked activity login to your <a href="<?php echo $base_url."dashboard"; ?>" target="blank">dashboard</a>.</small>
                                <?php
                                } else { ?>
                                    <p>No recent blocked activity.</p>
                                <?php
                                } ?>
                            <?php
                            } else { ?>
                                <p>To enable blocklist protection please sign up for a <a href="<?php echo $base_url."billing"; ?>" target="blank">premium</a> plan.</p>
                            <?php
                            } ?>
                        </div>
                    </div>

                    <br />

                    <div class="wpe-content-container">
                        <div class="wpe-content-header">
                            <div class="wpe-left wpe-forty-nine">
                                <p><strong class="wpe-danger">Automatic Blocked Activity <?php if($plan != "premium") { echo '(Premium)'; } ?></strong></p>
                                <p><small>Traffic that has been checked against a well maintained list of known nefarious IPs.</small></p>
                            </div>
                            <div class="wpe-right wpe-forty-nine wpe-text-right">
                                <small>
                                    <strong>
                                        <?php
                                        if($res->auto_block_enabled) { ?>
                                            WP Enforcer: <span class="wpe-success">Enabled</span>
                                        <?php
                                        } else { ?>
                                            WP Enforcer: <span><a class="wpe-warning wpe-cursor-hover-warning" href="<?php echo $base_url."keys"; ?>" target=_blank>Not Enabled</a></span>
                                        <?php
                                        } ?>
                                    </strong>
                                </small>
                            </div>
                            <div class="wpe-clear-both"></div>
                        </div>
                        <div class="wpe-content-body">
                            <?php
                            if($plan == "premium") {
                                if(count($res->auto_blocks) > 0) { ?>
                                    <table class="wpe-table">
                                        <tr>
                                            <th class="wpe-table-right-pad">Reason</th>
                                            <th>Num Blocks</th>
                                        </tr>

                                        <?php
                                        foreach($res->auto_blocks as $block) {
                                            echo "<tr>";
                                            echo "<td class='wpe-table-right-pad'>".$block->reason."</td>";
                                            echo "<td>".$block->num_blocks."</td>";
                                            echo "</tr>";
                                        } ?>
                                    </table>

                                    <br />
                                    <small>To view the complete automatic blocking activity login to your <a href="<?php echo $base_url."dashboard"; ?>" target="blank">dashboard</a>.</small>
                                <?php
                                } else { ?>
                                    <p>No recent automatic blocked activity.</p>
                                <?php
                                } ?>
                            <?php
                            } else { ?>
                                <p>To enable automatic blocklist protection please sign up for a <a href="<?php echo $base_url."billing"; ?>" target="blank">premium</a> plan.</p>
                            <?php
                            } ?>
                        </div>
                    </div>

                    <br />

                    <div class="wpe-content-container wpe-forty-nine wpe-left">
                        <div class="wpe-content-header">
                            <div class="wpe-left wpe-forty-nine">
                                <p><strong class="wpe-danger">SPAM Protection <?php if($plan != "premium") { echo '(Premium)'; } ?></strong></p>
                                <p><small>Comments flagged as SPAM.</small></p>
                            </div>
                            <div class="wpe-right wpe-forty-nine wpe-text-right">
                                <small>
                                    <strong>
                                        <?php
                                        if($res->spam_block_enabled) { ?>
                                            WP Enforcer: <span class="wpe-success">Enabled</span>
                                        <?php
                                        } else { ?>
                                            WP Enforcer: <span><a class="wpe-warning wpe-cursor-hover-warning" href="<?php echo $base_url."keys"; ?>" target=_blank>Not Enabled</a></span>
                                        <?php
                                        } ?>
                                    </strong>
                                </small>

                                <br />

                                <small>
                                    <strong>
                                        <?php
                                        if(get_option("wp-enforcer-spam-protection") == "on") { ?>
                                            Plugin Settings: <span class="wpe-success">Enabled</span>
                                        <?php
                                        } else { ?>
                                            Plugin Settings: <span class="wpe-warning wpe-go-to-settings wpe-cursor-hover-warning">Not Enabled</span>
                                        <?php
                                        } ?>
                                    </strong>
                                </small>
                            </div>
                            <div class="wpe-clear-both"></div>
                        </div>
                        <div class="wpe-content-body">
                            <?php
                            if($plan == "premium") {
                                if(count($res->spam_blocks) > 0) { ?>
                                    <table class="wpe-table">
                                        <tr>
                                            <th class="wpe-table-right-pad">Date</th>
                                            <th class="wpe-table-right-pad">Num Blocks</th>
                                        </tr>

                                        <?php
                                        foreach($res->spam_blocks as $spam_blocks) {
                                            echo "<tr>";
                                            echo "<td class='wpe-table-right-pad'>".$spam_blocks->date."</td>";
                                            echo "<td class='wpe-table-right-pad'>".$spam_blocks->value."</td>";
                                            echo "</tr>";
                                        } ?>
                                    </table>

                                    <br />
                                    <small>To view the complete SPAM protection login to your <a href="<?php echo $base_url."dashboard"; ?>" target="blank">dashboard</a>.</small>
                                <?php
                                } else { ?>
                                    <p>No recent SPAM detected.</p>
                                <?php
                                } ?>
                            <?php
                            } else { ?>
                                <p>To enable SPAM protection please sign up for a <a href="<?php echo $base_url."billing"; ?>" target="blank">premium</a> plan.</p>
                            <?php
                            } ?>
                        </div>
                    </div>

                    <div class="wpe-content-container wpe-forty-nine wpe-right">
                        <div class="wpe-content-header">
                            <div class="wpe-left wpe-forty-nine">
                                <p><strong class="wpe-danger">View Only Protection <?php if($plan != "premium") { echo '(Premium)'; } ?></strong></p>
                                <p><small>Only your IP address can create content.</small></p>
                            </div>
                            <div class="wpe-right wpe-forty-nine wpe-text-right">
                                <small>
                                    <strong>
                                        <?php
                                        if($res->view_only) { ?>
                                            WP Enforcer: <span class="wpe-success">Enabled</span>
                                        <?php
                                        } else { ?>
                                            WP Enforcer: <span><a class="wpe-warning wpe-cursor-hover-warning" href="<?php echo $base_url."keys"; ?>" target=_blank>Not Enabled</a></span>
                                        <?php
                                        } ?>
                                    </strong>
                                </small>
                            </div>
                            <div class="wpe-clear-both"></div>
                        </div>
                        <div class="wpe-content-body">
                            <?php
                            if($plan == "premium") {
                                if(count($res->view_blocks) > 0) { ?>
                                    <table class="wpe-table">
                                        <tr>
                                            <th class="wpe-table-right-pad">Date</th>
                                            <th class="wpe-table-right-pad">Num Blocks</th>
                                        </tr>

                                        <?php
                                        foreach($res->view_blocks as $view_blocks) {
                                            echo "<tr>";
                                            echo "<td class='wpe-table-right-pad'>".$view_blocks->date."</td>";
                                            echo "<td class='wpe-table-right-pad'>".$view_blocks->value."</td>";
                                            echo "</tr>";
                                        } ?>
                                    </table>

                                    <br />
                                    <small>To view the complete view only protection login to your <a href="<?php echo $base_url."dashboard"; ?>" target="blank">dashboard</a>.</small>
                                <?php
                                } else { ?>
                                    <p>No recent activity blocked from view only mode.</p>
                                <?php
                                } ?>
                            <?php
                            } else { ?>
                                <p>To enable view only protection please sign up for a <a href="<?php echo $base_url."billing"; ?>" target="blank">premium</a> plan.</p>
                            <?php
                            } ?>
                        </div>
                    </div>

                    <div class="wpe-clear-both"></div>

                    <div class="wpe-header-section">
                        <h2 class="wpe-section-header">Uptime</h2>
                    </div>

                    <div class="wpe-content-container">
                        <div class="wpe-content-header">
                            <div class="wpe-left wpe-forty-nine">
                                <p><strong class="wpe-info">Health Checks <?php if($plan != "premium") { echo '(Premium)'; } ?></strong></p>
                                <p><small>Get alerts via email or Slack whenever your site goes down.</small></p>
                            </div>
                            <div class="wpe-right wpe-forty-nine wpe-text-right">
                                <small>
                                    <strong>
                                        <?php
                                        if($res->uptime_enabled) { ?>
                                            WP Enforcer: <span class="wpe-success">Enabled</span>
                                        <?php
                                        } else { ?>
                                            WP Enforcer: <span><a class="wpe-warning wpe-cursor-hover-warning" href="<?php echo $base_url."keys"; ?>" target=_blank>Not Enabled</a></span>
                                        <?php
                                        } ?>
                                    </strong>
                                </small>
                            </div>
                            <div class="wpe-clear-both"></div>
                        </div>
                        <div class="wpe-content-body">
                            <?php
                            if($plan == "premium") {
                                if($res->uptime_percentage != "Enable uptime monitoring.") { ?>
                                    <table class="wpe-table">
                                        <tr>
                                            <th class="wpe-table-right-pad">Average Uptime<br /><small class="wpe-400-weight">Last 24 hours.</small></th>
                                            <th>Current Response Time<br /><small class="wpe-400-weight"><?php echo $res->uptime_last_check; ?></small></th>
                                        </tr>
                                        <tr>
                                            <td class="wpe-table-right-pad"><?php echo $res->uptime_percentage; ?></td>
                                            <td><?php echo $res->uptime_response_ms; ?></td>
                                        </tr>
                                    </table>
                                <?php
                                } else { ?>
                                    <p>Enable uptime monitoring via your <a href="<?php echo $base_url."dashboard"; ?>" target="blank">dashboard</a> to take advantage of this feature.</p>
                                <?php
                                } ?>
                            <?php
                            } else { ?>
                                <p>To enable uptime monitoring please sign up for a <a href="<?php echo $base_url."billing"; ?>" target="blank">premium</a> plan.</p>
                            <?php
                            } ?>
                        </div>
                    </div>

                    <br />

                    <p>For more detailed information visit your WP Enforcer <a href="<?php echo $base_url."dashboard"; ?>" target="_blank">dashboard</a>.</p>
                </div>
            <?php
            } else { ?>
                <div id="wpe-dashboard-wrapper" style="display:none;">
                    <div class="wpe-header-section">
                        <h2 class="wpe-section-header">Dashboard Unavailable</h2>
                    </div>

                    <div class="wpe-content-container">
                        <div class="wpe-content-body">
                            <?php
                            if($status == 500) { ?>
                                <p>Error communicating with WP Enforcer.</p>
                            <?php
                            } else { ?>
                                <p>Please navigate to <span class="wpe-info wpe-go-to-settings wpe-cursor-hover">settings</span> and input a valid access key.</p>
                            <?php
                            } ?>
                        </div>
                    </div>

                    <br />

                    <p>For more detailed information visit your WP Enforcer <a href="<?php echo $base_url."dashboard"; ?>" target="_blank">dashboard</a>.</p>
                </div>
            <?php
            } ?>
        </div>
    </div>
<?php
} ?>
