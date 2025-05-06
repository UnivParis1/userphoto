<?php

# Use it with the following conf on the reverse proxy for your Rocket.Chat
#
# location /userphoto.php {
#     proxy_pass http://userphoto-as.univ-paris1.fr/rocketchat.php;
#     proxy_set_header X-Real-IP $remote_addr;
#     proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
# }
# 
# location ~ ^/avatar/@?[^@,/]+$ {
#     if ($query_string ~ rc_uid=) {
#         rewrite ^/avatar/@?(.*)$ /userphoto.php?ratio=1&eppn=$1;
#     }
# }

include 'config.php';

$rc_username = null;
$rc_uid = get_param('rc_uid');
$rc_token = get_param('rc_token');
if ($rc_uid) {
  # we are using PHP session to avoid calling Rocket.Chat API for many photos.

  # do not mess with Rocket.Chat cookies: reuse rc_token cookie
  session_name('rc_token');
  session_id(str_replace('_', '-', $rc_token));
  ini_set('session.use_cookies', 0);
  session_start();
    
  $rc_username = @$_SESSION['rc_username'];
  if ($rc_username) {
    error_log("reusing session stored $rc_username for $rc_token");
  } else {

    $ch = curl_init();
    $url = $conf['rocket.chat']['url'] . "/api/v1/me";
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'X-User-Id: ' . $rc_uid, 'X-Auth-Token: ' . $rc_token ]);
    $server_output = curl_exec($ch);

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($status !== 200) { 
        error_log("rocket.chat $url returned $status: $server_output" . curl_error($ch));
        $server_output = null;
    }
    curl_close ($ch);

    if ($server_output) {
        $rc_username = json_decode($server_output)->username;
        if ($rc_username) {
            $_SESSION['rc_username'] = $rc_username;
            error_log("got $rc_username for $rc_token");

            # ProlongationENT will not work in Electron where the CAS session is seldom valid.
            # so directly call EsupUserApps here for stats.
            if (preg_match("! Electron/!", $_SERVER['HTTP_USER_AGENT'])) {
                #error_log("stats esupUserApps for $rc_username");
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $conf['EsupUserApps']['url']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
                curl_setopt($ch, CURLOPT_REFERER, $_SERVER['HTTP_REFERER']);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [ "Authorization: Bearer " . $conf['EsupUserApps']['bearerToken'], "REMOTE_USER: $rc_username" ]);
                $server_output = curl_exec($ch);
            }
        }
    }

  }
}

if ($rc_username) {
    $_GET["penpal"] = eppn_to_uid($rc_username);
    if (isset($_GET["eppn"])) $_GET["uid"] = eppn_to_uid($_GET["eppn"]);
    $_GET["penpalAffiliation"] = 'loggedUser';
    
    chdir("trusted"); // for includes
    include "trusted/index.php";
} else {
    header("HTTP/1.1 401 Unauthorized");
}

function get_param($name) {
    return isset($_COOKIE[$name]) ? $_COOKIE[$name] : (isset($_GET[$name]) ? $_GET[$name] : null);
}

function eppn_to_uid($eppn) {
    return preg_replace('/@univ-paris1[.]fr$/', '', $eppn);
}

?>
