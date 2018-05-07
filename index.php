<?php
require_once("settings.php");
require_once("db.php");
require_once("auth.php");
require_once("html.php");
require_once("billing.php");

db_connect();

// Get auth variables from cookies
if(isset($_COOKIE['username'])) $username=html_strip($_COOKIE['username']);
else $username="";
if(isset($_COOKIE['passwd_hash'])) $passwd_hash=html_strip($_COOKIE['passwd_hash']);
else $passwd_hash="";
if(isset($_COOKIE['action_message'])) {
    $action_message=html_strip($_COOKIE['action_message']);
    setcookie("action_message",'');
} else {
    $action_message="";
}

// Get token from variables and from db
$username_token=auth_get_current_token($username);
if(isset($_GET['token'])) $received_token=html_strip($_GET['token']);
else if(isset($_POST['token'])) $received_token=html_strip($_POST['token']);
else $received_token="";

// Branch for registered user
if(auth_check($username,$passwd_hash)) {
    if(isset($_POST['action'])) {
        if($username_token!=$received_token) die($message_bad_token);

        // Change settings
        if($_POST['action']=='change_settings') {
            $email=html_strip($_POST['email']);
            $grc_address=html_strip($_POST['grc_address']);
            $password=html_strip($_POST['password']);
            $new_password1=html_strip($_POST['new_password1']);
            $new_password2=html_strip($_POST['new_password2']);

            $passwd_hash2=auth_hash($username,$password);
            if($passwd_hash2==$passwd_hash) {
                $result=auth_change_settings($username,$email,$new_password1,$new_password2,$grc_address);
                if($result==TRUE) {
                    setcookie("action_message",$message_change_settings_ok);
                } else {
                    setcookie("action_message",$message_change_settings_validation_fail);
                }
            } else {
                setcookie("action_message",$message_change_settings_password_fail);
            }
            header("Location: ./");
            die();
        // Attach project
        } else if($_POST['action']=='attach') {
            $project_uid=html_strip($_POST['project_uid']);
            $host_uid=html_strip($_POST['host_uid']);

            $project_uid_escaped=db_escape($project_uid);
            $host_uid_escaped=db_escape($host_uid);
            $username_escaped=db_escape($username);

            $host_uid=db_query_to_variable("SELECT `uid` FROM `boincmgr_hosts` WHERE `uid`='$host_uid_escaped' AND `username`='$username_escaped'");
            if($host_uid) {
                $host_uid_escaped=db_escape($host_uid);
                db_query("INSERT INTO `boincmgr_attach_projects` (`project_uid`,`host_uid`) VALUES ('$project_uid_escaped','$host_uid_escaped')
ON DUPLICATE KEY UPDATE `detach`=0");
            }

            setcookie("action_message",$message_project_attached);
            header("Location: ./");
            die();
        // Detach project
        } else if($_POST['action']=='detach') {
            $attached_uid=html_strip($_POST['attached_uid']);
            $host_uid=html_strip($_POST['host_uid']);

            $attached_uid_escaped=db_escape($attached_uid);
            $host_uid_escaped=db_escape($host_uid);
            $username_escaped=db_escape($username);
            $host_uid=db_query_to_variable("SELECT `uid` FROM `boincmgr_hosts` WHERE `uid`='$host_uid_escaped' AND `username`='$username_escaped'");

            if($host_uid) {
                $host_uid_escaped=db_escape($host_uid);
                db_query("UPDATE `boincmgr_attach_projects` SET detach=1 WHERE `uid`='$attached_uid_escaped' AND `host_uid`='$host_uid_escaped'");
            }
            setcookie("action_message",$message_project_detached);
            header("Location: ./");
            die();
        // Change user status (for admin)
        } else if($_POST['action']=='change_user_status') {
            $user_uid=html_strip($_POST['user_uid']);
            $status=html_strip($_POST['status']);
            $user_uid_escaped=db_escape($user_uid);
            $status_escaped=db_escape($status);
            db_query("UPDATE `boincmgr_users` SET `status`='$status_escaped' WHERE `uid`='$user_uid_escaped'");
            setcookie("action_message",$message_user_status_changed);
            header("Location: ./");
            die();
        // Change project_status (for admin)
        } else if($_POST['action']=='change_project_status') {
            $project_uid=html_strip($_POST['project_uid']);
            $status=html_strip($_POST['status']);
            $project_uid_escaped=db_escape($project_uid);
            $status_escaped=db_escape($status);
            db_query("UPDATE `boincmgr_projects` SET `status`='$status_escaped' WHERE `uid`='$project_uid_escaped'");
            setcookie("action_message",$message_project_status_changed);
            header("Location: ./");
            die();
        // Calculate payouts
        } else if($_POST['action']=='billing') {
            $start_date=html_strip($_POST['start_date']);
            $stop_date=html_strip($_POST['stop_date']);
            $reward=html_strip($_POST['reward']);

            bill_close_period($start_date,$stop_date,$reward);
            setcookie("action_message",$message_billing_ok);
            header("Location: ./");
            die();
        }
    }
    // Logout
    if(isset($_GET['action'])) {
        if($username_token!=$received_token) die($message_bad_token);

        if($_GET['action']=='logout') {
            auth_logout();
            setcookie("action_message",$message_logout_success);
            header("Location: ./");
            die();
        }
    }

    // Standard page beginning
    echo html_page_begin();

    // Menu for registered user
    echo html_page_header(array("logout"));

    // Pool info
    echo html_pool_info();

    // Change settings form
    echo html_change_settings_form();

    // Current user hosts
    echo html_user_hosts();

    // Current user BOINC results (for his hosts)
    echo html_boinc_results();

    // Admin menu
    if(auth_is_admin($username)) {
        // Grant user privelegies
        echo "<h2>User control</h2>\n";
        echo html_user_control_form();

        // Control projects
        echo "<h2>Projects control</h2>\n";
        echo html_project_control_form();

        // Calculate rewards
        echo "<h2>Billing</h2>\n";
        echo html_billing_form();
    }

    // Standard page end
    echo html_page_end();
} else {
    if(isset($_POST['action'])) {
        // Register new user
        if($_POST['action']=="register") {
            $username=html_strip($_POST['username']);
            $password_1=html_strip($_POST['password_1']);
            $password_2=html_strip($_POST['password_2']);
            $email=html_strip($_POST['email']);
            $grc_address=html_strip($_POST['grc_address']);
            $result=auth_add_user($username,$email,$password_1,$password_2,$grc_address);
            if($result==FALSE) {
                setcookie("action_message",$message_register_fail);
            } else {
                setcookie("action_message",$message_register_success);
            }
            header("Location: ./");
            die();
        }
        // Login existing user
        if($_POST['action']=='login') {
            $username=html_strip($_POST['username']);
            $password=html_strip($_POST['password']);
            auth_login($username,$password);
            auth_get_new_token($username);
            header("Location: ./");
            die();
        }
    }

    if($username!="") $action_message=$message_login_error;

    // Standard page begin
    echo html_page_begin();

    // For register form we have link to login, then register form
    if(isset($_GET['action']) && $_GET['action']=='register') {
        echo html_page_header(array("login"));
        echo html_register_form();
    // For login form we have link to register, then login form
    } else {
        echo html_page_header(array("register"));
        echo html_login_form();
    }

    // Pool info
    echo html_pool_info();

    // End page
    echo html_page_end();
}

?>