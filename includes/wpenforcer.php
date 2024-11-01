<?php

// Check that the file is not accessed directly.
if(!defined("ABSPATH")) {
    die("We are sorry, but you can not directly access this file.");
}

// Primary WP Enforcer class.
class WPEnforcer {
    private $site_url;
    private $api_url;

    // Initialize from the constructor.
    public function __construct() {
        $this->init();
	}

    // Initialize all the actions needed to ship history.
    public function init() {
        global $site_url;

        $site_url = get_site_url();
        $this->site_url = &$site_url;

        $this->api_url = WP_ENFORCER_PROD_API_URL;
        $this->base_url = WP_ENFORCER_PROD_BASE_URL;

        // For dev work.
        if(strpos($this->site_url, "http://localhost:8888") !== false) {
            $this->api_url = WP_ENFORCER_DEV_API_URL;
            $this->base_url = WP_ENFORCER_DEV_BASE_URL;
        }

        // Authentication actions.
		add_action("wp_login", array(&$this, "wph_login"), 10, 2);
        add_action("clear_auth_cookie", array(&$this, "wph_logout"), 10);
		add_action("lost_password", array(&$this, "wph_lost_password"), 10, 0);
        add_action("wp_login_failed", array(&$this, "wph_login_failed"));
        add_action("user_profile_update_errors", array(&$this, "wph_admin_user_register"), 10, 3);
        add_action("register_post", array(&$this, "wph_public_user_register"), 10, 3);
        add_action("delete_user", array(&$this, "wph_delete_user"));

        // Page/Post actions.
        add_action("delete_post", array(&$this, "wph_delete_post"), 10);
        add_action("post_updated", array(&$this, "wph_post_updated"),  10, 3);
        add_action("transition_post_status", array(&$this, "wph_transition_post_status"), 10, 3); // @WORKS @CLEAN

        // Comment actions.
        add_action("transition_comment_status", array(&$this, "wph_transition_comment_status"), 10, 3); // @WORKS @CLEAN
        add_action("comment_post", array(&$this, "wph_comment_post")); // @WORKS @CLEAN
        add_action("edit_comment", array(&$this, "wph_edit_comment"), 10, 2); // @TODO

        // Attachment actions.
        add_action("add_attachment", array(&$this, "wph_add_attachment"), 10, 1); // @WORKS @CLEAN
        add_action("attachment_updated", array(&$this, "wph_edit_attachment"), 10, 3); // @WORKS @CLEAN
		add_action("delete_attachment", array(&$this, "wph_delete_attachment"));

        // Plugin actions.
        add_action("activated_plugin", array(&$this, "wph_activated_plugin"), 10, 2); // @WORKS @CLEAN
        add_action("deactivated_plugin", array(&$this, "wph_deactivated_plugin"), 10, 2);
        add_filter("upgrader_pre_install", array(&$this, "wph_upgrader_pre_install"), 10, 3);

        // Filter to have a link to the options page.
        add_filter("plugin_action_links_wp-enforcer/wpenforcer.php", array(&$this, "wp_enforcer_settings_link"));
	}

    public function wp_enforcer_settings_link($links) {
        $settings_links = array(
            '<a href="'.admin_url("options-general.php?page=wpenforcer").'">Settings</a>',
        );

        // Return the normal links array with the additional settings link.
        return array_merge($settings_links, $links);
    }

    // This forces the site to go into maintenance mode. Need to prevent that if
    // the request was blocked.
    public function wph_upgrader_pre_install($one, $plugin) {
        if($this->check_blocklist(array("tag" => "updated", "type" => "plugin"))) {
            global $wp_filesystem;

            $file = $wp_filesystem->abspath().".maintenance";
            $wp_filesystem->delete($file);

            $this->redirect_and_logout();
            return false;
        }

        $this->ship(array(
            "username" => wp_get_current_user()->display_name,
            "tag" => "updated",
            "type" => "plugin",
            "description" => $plugin["plugin"]
        ), "wph_pre_plugin_update");
    }

    // When a new comment is published.
    public function wph_comment_post($id) {
        $com = get_comment($id);

        if($this->check_spam(array("content" => $com->comment_content, "email" => $com->comment_author_email, "name" => $com->comment_author))) {
            wp_spam_comment($id);

            $this->just_logout();
        }

        if($this->check_blocklist(array("tag" => "publish", "type" => "comment"))) {
            wp_delete_comment($id);

            $this->redirect_and_logout();
        }

        $this->ship(array(
            "username" => $com->comment_author,
            "tag" => "publish",
            "type" => "comment",
            "description" => $com->comment_content
        ), "wph_comment_post");
    }

    // When a comment is edited.
    public function wph_edit_comment($id, $data) {
        if($this->check_blocklist(array("tag" => "edit", "type" => "comment"))) {
            $this->redirect_and_logout();
        }

        $this->ship(array(
            "username" => get_comment($id)->comment_author,
            "tag" => "edit",
            "type" => "comment",
            "description" => get_comment($id)->comment_content
        ), "wph_edit_comment");
    }

    // When a comments state is transitioned.
    public function wph_transition_comment_status($new, $old, $comment) {
        if($this->check_blocklist(array("tag" => $new, "type" => "comment"))) {
            $c = array();
            $c["comment_ID"] = $comment->comment_ID;

            // Set it to the previous comment status.
            switch($old) {
                case "approved":
                    $c["comment_approved"] = 1;
                    break;
                case "unapproved":
                    $c["comment_approved"] = 0;
                    break;
                case "spam":
                    $c["comment_approved"] = "spam";
                    break;
            }

            wp_update_comment($c);

            $this->redirect_and_logout();
        }

        $this->ship(array(
            "username" => wp_get_current_user()->display_name,
            "tag" => $new,
            "type" => "comment",
            "description" => $comment->comment_content
        ), "wph_transition_comment_status");
    }

    // This post/page transition will handle trash/untrash/publish.
    // It does *not* handle delete or update.
    public function wph_transition_post_status($new, $old, $post) {
        if(get_post_status($post->ID) == "auto-draft") {
            return;
        }

        if($old == "publish" && $new == "trash") {
            $status = "trash";
        } else if($old == "trash") {
            $status = "untrash";
        } else if(!($new == "publish" && $old != "publish" && isset($post->post_type))) {
            return;
        } else {
            $status = "publish";
        }

        if($this->check_blocklist(array("tag" => $status, "type" => get_post_type($post->ID)))) {
            wp_update_post(array("ID" =>  $post->ID, "post_status" => $old));

            $this->redirect_and_logout();
        }

        $this->ship(array(
            "username" => wp_get_current_user()->display_name,
            "tag" => $status,
            "type" => get_post_type($post->ID),
            "description" => get_the_title($post->ID)
        ), "wph_transition_post_status");
    }

    // When a plugin is deactivated.
    public function wph_deactivated_plugin($name) {
        if($this->check_blocklist(array("tag" => "deactivated", "type" => "plugin"))) {
            $this->redirect_and_logout();
        }

        $this->ship(array(
            "username" => wp_get_current_user()->display_name,
            "tag" => "deactivated",
            "type" => "plugin",
            "description" => explode("/", $name)[0]
        ), "wph_deactivated_plugin");
	}

    // When a plugin is activated.
    public function wph_activated_plugin($name) {
        if($this->check_blocklist(array("tag" => "activated", "type" => "plugin"))) {
            deactivate_plugins($name);

            $this->redirect_and_logout();
        }

        $this->ship(array(
            "username" => wp_get_current_user()->display_name,
            "tag" => "activated",
            "type" => "plugin",
            "description" => explode("/", $name)[0]
        ), "wph_activated_plugin");
	}

    // When an attachment is added.
    public function wph_add_attachment($id) {
        // Immediately delete and bypass trash if this is triggered. Idea here
        // is that it is a malicious intent.
        if($this->check_blocklist(array("tag" => "add", "type" => "attachment"))) {
            wp_delete_attachment($id, true);

            $this->redirect_and_logout();
        }

        $this->ship(array(
            "username" => wp_get_current_user()->display_name,
            "tag" => "add",
            "type" => "attachment"
        ), "wph_add_attachment");
	}

    // When an attachment is edited.
    public function wph_edit_attachment($post_id, $after, $before) {
        // An unmodified attachment.
        if($after->post_modified == $before->post_modified) {
            return;
        }

        // If blocklisted update the attachment to how it was before.
        if($this->check_blocklist(array("tag" => "edit", "type" => "attachment"))) {
            wp_update_post($before);

            $this->redirect_and_logout();
        }

        $this->ship(array(
            "username" => wp_get_current_user()->display_name,
            "tag" => "edit",
            "type" => "attachment"
        ), "wph_edit_attachment");
	}

    // When an attachment is deleted.
    public function wph_delete_attachment() {
        if($this->check_blocklist(array("tag" => "delete", "type" => "attachment"))) {
            $this->redirect_and_logout();
        }

        $this->ship(array(
            "username" => wp_get_current_user()->display_name,
            "tag" => "delete",
            "type" => "attachment"
        ), "wph_delete_attachment");
	}

    // When a user is registered through the public portal.
    public function wph_public_user_register($sanitized_user_login, $user_email, $errors) {
        if(count($errors->errors) == 0) {
            if($this->check_blocklist(array("tag" => "public_user_register", "type" => "authentication"))) {
                $this->redirect_and_logout();
            }

            $this->ship(array(
                "username" => $sanitized_user_login,
                "tag" => "public_user_register",
                "type" => "authentication",
                "description" => "user_email: ".$user_email
            ), "wph_public_user_register");
        }
	}

    // When a user is deleted.
    public function wph_delete_user($id) {
        if($this->check_blocklist(array("tag" => "user_deleted", "type" => "authentication"))) {
            $this->redirect_and_logout();
        }

        $user = get_userdata($id);

        $this->ship(array(
            "username" => wp_get_current_user()->display_name,
            "tag" => "user_deleted",
            "type" => "authentication",
            "description" => "username: ".$user->data->user_login." user_email: ".$user->data->user_email
        ), "wph_admin_user_register");
	}

    // When a user is registered through the admin portal.
    public function wph_admin_user_register($errors, $update, $user) {
        if(count($errors->errors) == 0) {
            if($this->check_blocklist(array("tag" => "admin_user_register", "type" => "authentication"))) {
                $this->redirect_and_logout();
            }

            $this->ship(array(
                "username" => wp_get_current_user()->display_name,
                "tag" => "admin_user_register",
                "type" => "authentication",
                "description" => "username: ".$user->user_login." role: ".$user->role." user_email: ".$user->user_email
            ), "wph_admin_user_register");
        }
	}

    // When a user logs in.
	public function wph_login($username) {
        if($this->check_blocklist(array("tag" => "login", "type" => "authentication"))) {
            $this->redirect_and_logout();
        }

        $this->ship(array(
            "username" => $username,
            "tag" => "login",
            "type" => "authentication"
        ), "wph_login");
	}

    // When a user logs out.
	public function wph_logout() {
        if(!$this->check_blocklist(array("tag" => "logout", "type" => "authentication"))) {
            $this->ship(array(
                "username" => wp_get_current_user()->data->display_name,
                "tag" => "logout",
                "type" => "authentication"
            ), "wph_logout");
        }
	}

    // When a user selects lost password.
    public function wph_lost_password() {
        if($this->check_blocklist(array("tag" => "lost_password", "type" => "authentication"))) {
            $this->redirect_and_logout();
        }

        $this->ship(array(
            "username" => "anonymous",
            "tag" => "lost_password",
            "type" => "authentication"
        ), "wph_lost_password");
	}

    // When a user fails login.
    public function wph_login_failed($username) {
        if($this->check_blocklist(array("tag" => "login_failed", "type" => "authentication"))) {
            $this->redirect_and_logout();
        }

        $this->ship(array(
            "username" => $username,
            "tag" => "login_failed",
            "type" => "authentication"
        ), "wph_login_failed");
	}

    // When a post has been updated. For *new* posts this will not trigger.
    // The $before and $after will only allow for current posts and the update.
    public function wph_post_updated($id, $post_after, $post_before) {
        $before = get_post_status($id)."/".get_post_type($id);
        $after = get_post_status($post_before)."/".get_post_type($post_after);

        if($before == $after) {
            // An unmodified post.
            if($post_after->post_modified == $post_before->post_modified) {
                return;
            }

            if($this->check_blocklist(array("tag" => "update", "type" => get_post_type($id)))) {
                wp_update_post($post_before);

                $this->redirect_and_logout();
            }

            $this->ship(array(
                "username" => wp_get_current_user()->display_name,
                "tag" => "update",
                "type" => get_post_type($id),
                "description" => get_the_title($id)
            ), "wph_post_updated");
        }
    }

    // When a post is deleted.
    public function wph_delete_post() {
        if(did_action("delete_post") === 1) {
            global $post;

            if($this->check_blocklist(array("tag" => "delete", "type" => $post->post_type))) {
                $this->redirect_and_logout();
            }

            $this->ship(array(
                "username" => wp_get_current_user()->display_name,
                "tag" => "delete",
                "type" => $post->post_type,
                "description" => $post->post_title
            ), "wph_delete_post");
        }
    }

    // Log the current user out if they are logged in.
    public function just_logout() {
        wp_logout();
        exit;
    }

    // Log the current user out if they are logged in and redirect them to a
    // WP Enforcer blocked page.
    public function redirect_and_logout() {
        wp_logout();
        wp_redirect($this->base_url."blocked");
        exit;
    }

    // Check if the action is blocklisted.
    public function check_blocklist($data) {
        // If protection is disabled return without checking the API.
        if(get_option("wp-enforcer-blocklist-protection") != "on") {
            error_log("blocklist protection disabled");
            return false;
        }

        // Add in site url as well as IP address for geo.
        $data["site"] = $this->site_url;
        $data["ip_address"] = $this->ip();
        $data["wp_enforcer_version"] = WP_ENFORCER_PLUGIN_VERSION;

        // Send POST request to the server.
        $res = wp_remote_post($this->api_url."blocklist/check/".get_option("wp-enforcer-access-key"), array(
            "timeout" => 1,
            "redirection" => 1,
            "body" => wp_json_encode($data),
            "headers" => array(
                "Content-Type" => "application/json",
                "Content-Length" => strlen(wp_json_encode($data))
            )
        ));

        // Check if there is a WordPress thrown error.
        if(is_wp_error($res)) {
            error_log("error code 1: could not get to blocklist from api");
            return false;
        }

        $status = $res["response"]["code"];

        // If the status is anything non OK or Unauthorized we had an error.
        // We can simply let the request go through.
        if($status != "200" && $status != "401") {
            error_log("error code 2: could not get to blocklist from api");
            return false;
        }

        $res = json_decode($res["body"]);

        // If you are unauthorized to perform the request you are blocked.
        // Otherwise request can go through.
        if($res->status == 401 && $res->message == "You are unauthorized.") {
            return true;
        }

        return false;
    }

    // Responsible for sending history data to the API.
    public function ship($data, $caller) {
        // Add in site url as well as IP address for geo.
        $data["site"] = $this->site_url;
        $data["ip_address"] = $this->ip();
        $data["wp_enforcer_version"] = WP_ENFORCER_PLUGIN_VERSION;

        // Send POST request to the server.
        $res = wp_remote_post($this->api_url."history/".get_option("wp-enforcer-access-key")."/".$data["type"]."/".$data["tag"], array(
            "timeout" => 1,
            "redirection" => 1,
            "body" => wp_json_encode($data),
            "headers" => array(
                "Content-Type" => "application/json",
                "Content-Length" => strlen(wp_json_encode($data))
            )
        ));

        // Check if there is a WordPress thrown error.
        if(is_wp_error($res)) {
            error_log("error code 1: could not post to history api");
            return false;
        }

        $status = $res["response"]["code"];

        // Anything non 200 and the request failed.
        if($status != "200") {
            error_log("error code 2: could not post to history api");
        }
    }

    // Get the callers IP address if available.
    public function ip() {
        if(!empty($_SERVER["HTTP_CLIENT_IP"])) {
            return $_SERVER["HTTP_CLIENT_IP"];
        } elseif(!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            return $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else {
            return $_SERVER["REMOTE_ADDR"];
        }
    }

    // Get the callers useragent.
    public function useragent() {
        return $_SERVER["HTTP_USER_AGENT"];
    }

    // Check if the comment is spam.
    public function check_spam($data) {
        // If protection is disabled return without checking the API.
        if(get_option("wp-enforcer-spam-protection") != "on") {
            error_log("spam protection disabled");
            return false;
        }

        // Add in the IP address, site and useragent.
        $data["ip_address"] = $this->ip();
        $data["site"] = $this->site_url;
        $data["useragent"] = $this->useragent();
        $data["wp_enforcer_version"] = WP_ENFORCER_PLUGIN_VERSION;

        // Send POST request to the server.
        $res = wp_remote_post($this->api_url."spam/check/".get_option("wp-enforcer-access-key"), array(
            "timeout" => 1,
            "redirection" => 1,
            "body" => wp_json_encode($data),
            "headers" => array(
                "Content-Type" => "application/json",
                "Content-Length" => strlen(wp_json_encode($data))
            )
        ));

        // Check if there is a WordPress thrown error.
        if(is_wp_error($res)) {
            error_log("error code 1: could not get to spam from api");
            return false;
        }

        $status = $res["response"]["code"];

        // If the status is anything non OK or Unauthorized we had an error.
        // We can simply let the request go through.
        if($status != "200" && $status != "401") {
            error_log("error code 2: could not get to spam from api");
            return false;
        }

        $res = json_decode($res["body"]);

        // If you are unauthorized to perform the request you are blocked.
        // Otherwise request can go through.
        if($res->status == 401 && $res->message == "You are unauthorized.") {
            return true;
        }

        return false;
    }
}

?>
