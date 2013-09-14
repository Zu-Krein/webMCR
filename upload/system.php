<?php
error_reporting(E_ALL);

$user = false; $link = false; $mcr_tools = array('base.class.php');

define('MCR_ROOT', dirname(__FILE__).'/');
define('MCR_LANG', 'ru_RU');

require(MCR_ROOT.'instruments/base.class.php');

if (!file_exists(MCR_ROOT.'config.php')) { header("Location: install/install.php"); exit; }

require(MCR_ROOT.'instruments/locale/'.MCR_LANG.'.php');
require(MCR_ROOT.'config.php');

require(MCR_ROOT.'instruments/auth/'.$config['p_logic'].'.php');

define('MCRAFT', MCR_ROOT.$site_ways['mcraft']);
define('MCR_STYLE', MCR_ROOT.$site_ways['style']); 

define('STYLE_URL', $site_ways['style']); 
define('DEF_STYLE_URL', STYLE_URL . View::def_theme . '/');

define('BASE_URL', $config['s_root']);

date_default_timezone_set($config['timezone']);

function BD( $query ) {	return mcrDB::ask($query); } // depricated

/* Системные функции */

function lng($key, $lang = false) {
global $MCR_LANG;

	return isset($MCR_LANG[$key]) ? $MCR_LANG[$key] : $key;
}

function tmp_name($folder, $pre = '', $ext = 'tmp'){
    $name  = $pre.time().'_';
	  
    for ($i=0;$i<8;$i++) $name .= chr(rand(97,121));
	  
    $name .= '.'.$ext;
	  
return (file_exists($folder.$name))? tmp_name($folder,$pre,$ext) : $name;
}

function InputGet($key, $method = 'POST', $type = 'str') {
	
	$blank_result = array( 'str' => '', 'int' => 0, 'float' => 0, 'bool' => false);
	
	if (($method == 'POST' and !isset($_POST[$key])) or
		($method != 'POST' and !isset($_GET[$key]))) return $blank_result[$type];
	
	$var = ($method == 'POST')? $_POST[$key] : $_GET[$key];
	
    switch($type){
		case 'str': return TextBase::HTMLDestruct($var); break;
		case 'int': return (int) $var; break;
		case 'float': return (float) $var; break;
		case 'bool': return (bool) $var; break;
	}	
}

function POSTGood($post_name, $format = array('png')) {

if ( empty($_FILES[$post_name]['tmp_name']) or 

     $_FILES[$post_name]['error'] != UPLOAD_ERR_OK or
	 
	 !is_uploaded_file($_FILES[$post_name]['tmp_name']) ) return false;
   
$extension = strtolower(substr($_FILES[$post_name]['name'], 1 + strrpos($_FILES[$post_name]['name'], ".")));

if (is_array($format) and !in_array($extension, $format)) return false;
   
return true;
}

function POSTSafeMove($post_name, $tmp_dir = false) {
	
	if (!POSTGood($post_name, false)) return false;
	
	if (!$tmp_dir) $tmp_dir = MCRAFT.'tmp/';

	if (!is_dir($tmp_dir)) mkdir($tmp_dir, 0777); 

	$tmp_file = tmp_name($tmp_dir);
	if (!move_uploaded_file( $_FILES[$post_name]['tmp_name'], $tmp_dir.$tmp_file )) { 

	mcrSys::log('[POSTSafeMove] --> "'.$tmp_dir.'" <-- '.lng('WRITE_FAIL'));
	return false;
	}

return array('tmp_name' => $tmp_file, 'name' => $_FILES[$post_name]['name'], 'size_mb' => round($_FILES[$post_name]['size'] / 1024 / 1024, 2));
}

function randString( $pass_len = 50 ) {
    $allchars = "abcdefghijklmnopqrstuvwxyz0123456789";
    $string = "";
    
    mt_srand( (double) microtime() * 1000000 );
    
    for ( $i=0; $i<$pass_len; $i++ )
	$string .= $allchars{ mt_rand( 0, strlen( $allchars )-1 ) };
	
    return $string;
}

function sqlConfigGet($type){
global $bd_names;
	
	if (!in_array($type, ItemType::$SQLConfigVar)) return false;
	
    $result = BD("SELECT `value` FROM `{$bd_names['data']}` WHERE `property`='".mcrDB::safe($type)."'");   

    if ( $result->num_rows != 1 ) return false;
	
	$line = $result->fetch_array( MYSQL_NUM );
	
	return $line[0];		
}

function sqlConfigSet($type, $value) {
global $bd_names;

	if (!in_array($type, ItemType::$SQLConfigVar)) return false;
	
	$result = BD("INSERT INTO `{$bd_names['data']}` (value,property) VALUES ('".mcrDB::safe($value)."','".mcrDB::safe($type)."') ON DUPLICATE KEY UPDATE `value`='".mcrDB::safe($value)."'");
	return true;
}
?>