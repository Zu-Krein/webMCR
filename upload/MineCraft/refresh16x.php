<?php
require('../system.php');

function generateSessionId() {
    srand(time());
    $randNum = rand(1000000000, 2147483647) . rand(1000000000, 2147483647) . rand(0, 9);
    return $randNum;
}

function logExit($text, $output = "Bad login") {
    mcrSys::log($text);
    exit($output);
}

if (($_SERVER['REQUEST_METHOD'] == 'POST' ) && (stripos($_SERVER["CONTENT_TYPE"], "application/json") === 0)) 

    $json = json_decode($HTTP_RAW_POST_DATA);
	
 else logExit("Bad request method. POST/json required", "Bad request method. POST/json required");

if (empty($json->accessToken) or empty($json->clientToken))
    logExit("[refresh16x.php] refresh process [Empty input] [ " . ((empty($json->accessToken)) ? 'Session ' : '') . ((empty($json->clientToken)) ? 'clientToken ' : '') . "]");

mcrSys::loadTool('user.class.php');
mcrDB::connect('auth');

$sessionid = $json->accessToken;
$clientToken = $json->clientToken;

if (!preg_match("/^[a-f0-9-]+$/", $sessionid) or
    !preg_match("/^[a-f0-9-]+$/", $clientToken))
    logExit("[refresh16x.php] refresh process [Bad symbols] Session [$sessionid] clientToken [$clientToken]");

$result = BD("SELECT `{$bd_users['id']}` FROM `{$bd_names['users']}` WHERE `{$bd_users['session']}`='" . mcrDB::safe($sessionid) . "' AND `{$bd_users['clientToken']}`='" . mcrDB::safe($clientToken) . "'");

if ($result->num_rows != 1) logExit("[refresh16x.php] refresh process, wrong accessToken/clientToken pair [$sessionid] [$clientToken]");

$line = $result->fetch_array( MYSQL_NUM);

$auth_user = new User($line[0], $bd_users['id']);

$sessid = generateSessionId();
BD("UPDATE `{$bd_names['users']}` SET `{$bd_users['session']}`='" . mcrDB::safe($sessid) . "' WHERE `{$bd_users['id']}`='" . $auth_user->id() . "'");

$profile = array('id' => $auth_user->id(), 'name' => $auth_user->name());

mcrSys::log("[refresh16x.php] refresh process [Success] User [{$profile['name']}] NewSession [$sessid] OldSession[$sessionid]");

$responce = array(
    'clientToken' => $clientToken,
    'accessToken' => $sessid,
    'selectedProfile' => $profile);

exit(json_encode($responce));
?>