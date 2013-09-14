<?php
require_once('../../system.php');
mcrDB::connect('rss');

$title = 'Сайт '.$_SERVER['SERVER_NAME'];
$desc = 'Новости сайта '.$_SERVER['SERVER_NAME'];

$rss_doc = '';

$site_news = 'http://'.str_replace('instruments/rss20/rss.php','',$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']).'index.php';

$result = BD("SELECT COUNT(*) FROM `{$bd_names['news']}`");
$num_news = $result->fetch_row(); 

if(empty($num_news[0])) exit;

define('DATE_FORMAT_RFC822','r');

Header("content-type: application/rss+xml; charset=utf-8");

$result = BD("SELECT DATE_FORMAT(NOW(),'%a, %d %b %Y %T')"); 
$cur_date = $result->fetch_row(); 
$cur_date = $cur_date[0];

$result = BD("SELECT * FROM `{$bd_names['news']}` ORDER by time DESC LIMIT 0,10"); 

if ( $result->num_rows != 0 ) {

	ob_start();
	
	include './rss_header.html';
	
	while ( $line = $result->fetch_array() ) {
	  	  
	  $name = $line['title'];
	  $date = date("r",strtotime($line['time']));
	  $link = $site_news.'?id='.$line['id'];
	  $post = strip_tags(html_entity_decode($line['message']));
	  
	  include './rss.html';

	}
	
	include './rss_footer.html';
	
	$rss_doc = '<?xml version="1.0" encoding="UTF-8"?>' . ob_get_clean();
}
echo $rss_doc;
?>