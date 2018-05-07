<?php
// PRC for BOINC client

require_once("settings.php");
require_once("db.php");
require_once("auth.php");

db_connect();

$data=file_get_contents("php://input");
$data_escaped=db_escape($data);

db_query("INSERT INTO boincmgr_log (message) VALUES ('$data_escaped')");

libxml_use_internal_errors(TRUE);
libxml_disable_entity_loader(TRUE);

$xml_data = simplexml_load_string($data);

if($xml_data === FALSE) {
    echo "Error parsing XML";
    db_query("INSERT INTO boincmgr_log (message) VALUES ('Error parsing XML')");
//    echo "Also, current key is\n$signing_key";
    die();
}

$name=(string)$xml_data->name;
$password_hash=(string)$xml_data->password_hash;
$host_cpid=(string)$xml_data->host_cpid;
$external_host_cpid=md5($host_cpid.$boinc_account);
$domain_name=(string)$xml_data->host_info->domain_name;
$p_model=(string)$xml_data->host_info->p_model;
$p_ncpus=(string)$xml_data->host_info->p_ncpus;
$n_usable_coprocs=(string)$xml_data->host_info->n_usable_coprocs;

$name_escaped=db_escape($name);
$host_cpid_escaped=db_escape($host_cpid);
$external_host_cpid_escaped=db_escape($external_host_cpid);
$domain_name_escaped=db_escape($domain_name);
$p_model_escaped=db_escape($p_model);
$p_ncpus_escaped=db_escape($p_ncpus);
$n_usable_coprocs_escaped=db_escape($n_usable_coprocs);

/*$query="INSERT INTO `boincmgr_hosts` (`username`,`internal_host_cpid`,`external_host_cpid`,`domain_name`,`p_model`,`p_ncpus`,`n_usable_coprocs`)
VALUES ('$name_escaped','$host_cpid_escaped','$external_host_cpid_escaped','$domain_name_escaped','$p_model_escaped','$p_ncpus_escaped','$n_usable_coprocs_escaped')
ON DUPLICATE KEY UPDATE `username`=VALUES(`username`),`external_host_cpid`=VALUES(`external_host_cpid`),`domain_name`=VALUES(`domain_name`),`p_model`=VALUES(`p_model`),`p_ncpus`=VALUES(`p_ncpus`),`n_usable_coprocs`=VALUES(`n_usable_coprocs`)";

db_query("INSERT INTO boincmgr_log (message) VALUES ('".db_escape($query)."')");;
*/
db_query("INSERT INTO `boincmgr_hosts` (`username`,`internal_host_cpid`,`external_host_cpid`,`domain_name`,`p_model`,`p_ncpus`,`n_usable_coprocs`)
VALUES ('$name_escaped','$host_cpid_escaped','$external_host_cpid_escaped','$domain_name_escaped','$p_model_escaped','$p_ncpus_escaped','$n_usable_coprocs_escaped')
ON DUPLICATE KEY UPDATE `username`=VALUES(`username`),`external_host_cpid`=VALUES(`external_host_cpid`),`domain_name`=VALUES(`domain_name`),`p_model`=VALUES(`p_model`),`p_ncpus`=VALUES(`p_ncpus`),`n_usable_coprocs`=VALUES(`n_usable_coprocs`)");

foreach($xml_data->project as $project_data) {
    $project_url=(string)$project_data->url;
    $project_name=(string)$project_data->project_name;
    $project_host_id=(string)$project_data->hostid;

    $project_url_escaped=db_escape($project_url);
    $project_name_escaped=db_escape($project_name);
    $project_host_id_escaped=db_escape($project_host_id);

    db_query("INSERT INTO `boincmgr_host_projects` (`url`,`project_name`,`host_id`,`host_cpid`)
VALUES ('$project_url_escaped','$project_name_escaped','$project_host_id_escaped','$host_cpid_escaped')
ON DUPLICATE KEY UPDATE `url`=VALUES(`url`),`project_name`=VALUES(`project_name`),`host_id`=VALUES(`host_id`)");
}

$reply_xml=<<<_END
<?xml version="1.0" encoding="UTF-8" ?>
<acct_mgr_reply>

_END;

if(auth_check_hash($name,$password_hash)==FALSE) {
    $reply_xml.=<<<_END
    <error_num>-100</error_num>
    <error_msg>$message_login_error</error_msg>
    <error>$message_login_error</error>
    <name>$pool_name</name>

_END;
} else {
    $reply_xml.=<<<_END
    <name>$pool_name</name>
    <message>$pool_message</message>
<signing_key>
$signing_key
</signing_key>

_END;

    $project_data_array=db_query_to_array("SELECT p.`project_url`,p.`url_signature`,p.`weak_auth`,ap.`detach` FROM `boincmgr_attach_projects` AS ap
    LEFT JOIN `boincmgr_projects` AS p ON p.`uid`=ap.`project_uid`
    LEFT JOIN `boincmgr_hosts` AS h ON h.`uid`=ap.`host_uid`
    WHERE h.internal_host_cpid='$host_cpid_escaped'");

    foreach($project_data_array as $project_data) {
        $project_url=$project_data['project_url'];
        $weak_auth=$project_data['weak_auth'];
        $url_signature=$project_data['url_signature'];
        $detach=$project_data['detach'];

    $reply_xml.=<<<_END
        <account>
           <url>$project_url</url>
           <url_signature>
$url_signature
                </url_signature>
           <authenticator>$weak_auth</authenticator>
           <detach>$detach</detach>
        </account>

_END;
    }
}
$reply_xml.=<<<_END
</acct_mgr_reply>

_END;

$reply_xml_escaped=db_escape($reply_xml);

db_query("INSERT INTO boincmgr_log (message) VALUES ('$reply_xml_escaped')");

echo $reply_xml;
?>