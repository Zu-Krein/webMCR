<?php
require('../system.php');

if (empty($_GET['user']) or empty($_GET['serverId'])) {
  mcrSys::log("[checkserver.php] checkserver process [GET parameter empty] [ ".((empty($_GET['user']))? 'LOGIN ':'').((empty($_GET['serverId']))? 'SERVERID ':'')."]");
  exit('NO');
}
	mcrSys::loadTool('user.class.php'); 
	mcrDB::connect('checkserver');
	
	$user 		= $_GET['user']; 
	$serverid 	= $_GET['serverId'];

	if (!preg_match("/^[a-zA-Z0-9_-]+$/", $user)  or
	    !preg_match("/^[a-z0-9_-]+$/", $serverid)) {
		
		mcrSys::log("[checkserver.php] error checkserver process [info login ".$user." serverid ".$serverid."]");
		exit('NO');				
	} 	
		
	$result = BD("SELECT `{$bd_users['login']}` FROM {$bd_names['users']} WHERE `{$bd_users['login']}`='".mcrDB::safe($user)."' AND `{$bd_users['server']}`='".mcrDB::safe($serverid)."'");

	if( $result->num_rows == 1 ){
		
	   $user_login = new User($user,$bd_users['login']);
	   $user_login->gameLoginConfirm();
	   mcrSys::log("[checkserver.php] Server Test [Success]");
	   exit('YES'); 		   
	}		
	   
	mcrSys::log("[checkserver.php] [User not found] User [$user] Server ID [$serverid]");
    exit('NO');
?>