<?php
require('../system.php');

mcrSys::loadTool('ajax.php');
mcrSys::loadTool('monitoring.class.php');

if (empty($_POST['id'])) exit;
$id = (int)$_POST['id'];

$now = false;

if (isset($_POST['now']) and !empty($user) and $user->lvl() >= 15) 

$now = true;

mcrDB::connect('monitoring');

$server = new Server($id);
$server->UpdateState($now);
$server->ShowInfo();
?>