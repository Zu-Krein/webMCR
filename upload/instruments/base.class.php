<?php
define('MCR', '2.3b'); 
define('PROGNAME', 'webMCR '.MCR);
define('FEEDBACK', '<a href="http://drop.catface.ru/index.php?nid=17">'.PROGNAME.'</a> &copy; 2013 NC22');  

/* TODO обобщенная модель для удаления \ проверки существования объекта */

class ItemType { 

	const News = 1;
	const Comment = 2;
	const Skin = 3;	
	
	/** @const */
	public static $SQLConfigVar = array (
		 
		'rcon-port',
		'rcon-serv',	
		'rcon-pass',
	 
		'next-reg-time',
	 
		'email-verification',
		'email-verification-salt',
		'email-name',
		'email-mail',
		
		'json-verification-salt',
		
		'latest-game-build',
		'launcher-version',
	
		'game-link-win',
		'game-link-osx',
		'game-link-lin',
	
		'smtp-user',
		'smtp-pass',
		'smtp-host',
		'smtp-port',
		'smtp-hello',		
	);
}

class mcrSys { 

	function loadTool( $name, $sub_dir = '') {
	global $mcr_tools; 

		if (in_array($name, $mcr_tools)) return;
		
		$mcr_tools[] = $name;
		
		require( MCR_ROOT . 'instruments/' . $sub_dir . $name);	
	}
	
	public static function log($string) {
	global $config;

	if (!$config['log']) return;

	$log_file = MCR_ROOT . 'log.txt';

		if (file_exists($log_file) and round(filesize ($log_file) / 1048576) >= 50) unlink($log_file);

		if ( !$fp = fopen($log_file,'a') ) exit('[mcrSys::log]  --> ' . $log_file . ' <-- ' . lng('WRITE_FAIL'));
		
		fwrite($fp, date("H:i:s d-m-Y").' < '.$string . PHP_EOL); 
		fclose($fp);	
	}
	
	public static function isBanned($ban_type = 1) {
	global $bd_names;

		$ip = self::getIP(); 
		$ban_type = (int) $ban_type;
		
		$result = mcrDB::ask("SELECT COUNT(*) FROM `{$bd_names['ip_banning']}` WHERE `IP`='".mcrDB::safe($ip)."' AND `ban_type`='".$ban_type."' AND `ban_until` <> '0000-00-00 00:00:00' AND `ban_until` > NOW()"); 
		$line = $result->fetch_array( MYSQL_NUM );
		$num = (int) $line[0];

		if ($num) {
		
			mcrDB::close();
			return true;
		}
		
		return false;					
	}
	
	public static function refreshBans() {
	global $bd_names;

	/* Default ban until time */
	mcrDB::ask("DELETE FROM {$bd_names['ip_banning']} WHERE ( ban_until = '0000-00-00 00:00:00' ) AND ( time_start < NOW()-INTERVAL ".((int) sqlConfigGet('next-reg-time'))." HOUR)");
	
	mcrDB::ask("DELETE FROM {$bd_names['ip_banning']} WHERE ( ban_until <> '0000-00-00 00:00:00' ) AND ( ban_until < NOW())");							
	}	
	
	public static function trueIP($ip) {

	$len = strlen($ip);
	$ipv4 = ($len == 4)? true : false;  if ($len <= 0 and $len != 4 and $len != 16) return '0.0.0.0';
	$prIP = ''; $split = ($ipv4) ? 1 : 2;
 
		for ($i = 0; $i < $len; $i++) {
			
			$prIP .= ($ipv4) ? ord($ip[$i]) : bin2hex($ip[$i]);
			$split--;		
			if (!$split and $i < $len - 1) { 
			
				$split = ($ipv4) ? 1 : 2; 
				$prIP .= ($ipv4) ? '.' : ':'; 
			}		
		}
		
	return $prIP;
	}
	
	/* 
	For future work with IPv6, in current must be full format of IPv6
	Specification http://www.zytrax.com/tech/protocols/ipv6.html 	
	*/
	
	public static function binIP($ip) { 
	
		if(strpos($ip, ':') !== false) { 
		
			$ip = str_split(str_replace(':', '', $ip), 2);
			
			foreach ( $ip as $key => $value ) $ip[$key] = chr(hexdec($value));
			
			return implode('', $ip);
		
        } elseif(strpos($ip,'.') !== false ){ 
            
			$ip = explode('.', $ip);
			
            if( sizeof($ip) != 4 ) return 0x00000000;
			
            return chr($ip[0]).chr($ip[1]).chr($ip[2]).chr($ip[3]);
			
        } else return 0x00000000;
	}
	
	public static function getIP() {
	
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) 	
		
			$ip = $_SERVER['HTTP_CLIENT_IP']; 	
			
		elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { 	
			
			$tmp_xfor = trim($_SERVER['HTTP_X_FORWARDED_FOR']);	 
			$tmp_xfor = explode(",", $tmp_xfor);
			
			$ip = $tmp_xfor[sizeof($addr)-1];
			
		} else 	 
			$ip = $_SERVER['REMOTE_ADDR'];
	
	return self::binIP($ip);	
	}
}

class mcrDB {

	public static function connect($log = false, $exit = true) {
	global $mcr_db_link, $config;

		if (isset($mcr_db_link) and get_class($mcr_db_link) === 'mysqli' ) $mcr_db_link->close();
		
		$mcr_db_link = new mysqli($config['db_host'].':'.$config['db_port'], $config['db_login'], $config['db_passw'], $config['db_name']);
		
		if (mysqli_connect_error()) {
			
			$err = mysqli_connect_error() . ' (' . mysqli_connect_errno() . ') ';
			
			if ($exit) exit($err); else return $err;
		}
		
		self::ask("SET time_zone = '".date('P')."'");
		self::ask("SET character_set_client='utf8'"); 
		self::ask("SET character_set_results='utf8'"); 
		self::ask("SET collation_connection='utf8_general_ci'"); 		
		
		if ( $exit and mcrSys::isBanned(2) ) exit('(-_-)zzZ <br>' . lng('IP_BANNED'));
				
		if ($log !== false) self::log($log);
		
		return true;
	}
	
	public static function ask( $query, $resultmode = MYSQLI_STORE_RESULT ) {
	global $mcr_db_link;
	
		$result = $mcr_db_link->query( $query, $resultmode ); 
		
		if ($result === false) mcrSys::log('SQLError: ['.$query.']');
		
		return $result;	
	}
	
	public static function close() {
	global $mcr_db_link;
	
		$mcr_db_link->close();
	}
	
	public static function affectedRows() {
	global $mcr_db_link;
	
		return $mcr_db_link->affected_rows;
	}
	
	public static function lastID() {
	global $mcr_db_link;
	
		return $mcr_db_link->insert_id;
	}
	
	public static function safe($str) {
	global $mcr_db_link;
	
		return $mcr_db_link->real_escape_string($str);	
	}

	public static function columnExist($table, $column) {
	global $mcr_db_link;
	
		return (@$mcr_db_link->query("SELECT `".mcrDB::safe($column)."` FROM `".mcrDB::safe($table)."` LIMIT 0, 1"))? true : false;
	}

	public static function getFieldType($table, $column) {
		
		$result = self::ask("SHOW FIELDS FROM `".mcrDB::safe($table)."` where Field ='".mcrDB::safe($column)."'");
		$lines = $result->fetch_array(MYSQL_ASSOC);
		
		return $lines['Type'];
	}	
	
	public static function log ($last_info = 'd_a') {
	global $user, $bd_names;
		
		$userID = (empty($user) or !$user->id()) ? 0 : $user->id();
		$add_sql = '';
		
		if ( $userID != 0 ) $add_sql = "`user_id` = '".$userID."',";
		
		$ip = mcrSys::getIP();
		mcrDB::ask("DELETE FROM `{$bd_names['action_log']}` WHERE `last_time` < NOW() - INTERVAL 15 MINUTE"); // timeout - ToDo move to deep log
				
		$sql  = "INSERT INTO `{$bd_names['action_log']}` (IP, first_time, last_time, user_id, info) ";
		$sql .= "VALUES ('".mcrDB::safe($ip)."', NOW(), NOW(), '".$userID."', '".mcrDB::safe($last_info)."') ";
		$sql .= "ON DUPLICATE KEY UPDATE ".$add_sql." `last_time` = NOW(), `query_count` = `query_count` + 1, `info` = `info` + ';".mcrDB::safe($last_info)."' ";
		
		mcrDB::ask($sql);		
	}
}

/* Base class for objects with Show method */

Class View {

	const def_theme = 'Default';
	
	protected $st_subdir;	
	
	public function View($style_subdir = '') {
	
		if (!$style_subdir) $style_subdir = false;
		
		$this->st_subdir = $style_subdir;		
	}
	
// ToDo transform output
	
	public function ShowPage($way, $out = false) {
	global $config;
	
		ob_start(); 
		
		include self::Get($way, $this->st_subdir);

		return ob_get_clean(); 	
	}

	public static function ShowStaticPage($way, $st_subdir = false, $out = false ) {
	global $config;
		
		ob_start(); 
		
		include self::Get($way, $st_subdir);
		
		return ob_get_clean(); 	
	}	
	
	protected function GetView($way) {
	
		return self::Get($way, $this->st_subdir);		
	}
	
	public static function GetURL($way = false) {
	global $config;
		
		$current_st_url = empty($config['s_theme'])? DEF_STYLE_URL : STYLE_URL . $config['s_theme'] . '/' ;
		
		if (!$way) return $current_st_url;
		
		if ( DEF_STYLE_URL === $current_st_url ) return DEF_STYLE_URL . $way;
		else return (file_exists( MCR_STYLE . $config['s_theme'] . '/'  . $way )? $current_st_url : DEF_STYLE_URL) . $way;	
	}
	
	public static function Get($way, $base_ = false) {
	global $config;

		$base = ($base_)? $base_ : '' ;	
		
		if ( empty ($config['s_theme']) ) $theme_dir = '';	
		else {
			
			if ( $config['s_theme'] === self::def_theme ) return MCR_STYLE. self::def_theme . '/' . $base . $way;
			
			$theme_dir = $config['s_theme'] . '/' ;			
		}	
		
		return MCR_STYLE.((file_exists(MCR_STYLE.$theme_dir.$base.$way))? $theme_dir : self::def_theme . '/'). $base . $way;
	} 


    public function ArrowsGenerator($link, $curpage, $itemsnum, $per_page, $prefix = false) { 
		
		if ( !$prefix ) { // Default arrows style
			
			$prefix = 'common';
			$st_subdir = 'other/'; 
			
		} else 
		
			$st_subdir = $this->st_subdir;
	
	  $numoflists = ceil($itemsnum / $per_page);
	  $arrows = '';
	  
			  if ($numoflists > 10 and $curpage > 4) {
			  
				$showliststart = $curpage - 4;
				$showlistend   = $curpage + 5;
				
				if ($showliststart < 1) $showliststart = 1;
				
				if ($showlistend > $numoflists) $showlistend = $numoflists;
				
			  } else {
			  
				$showliststart = 1;
				
				if ($numoflists < 10 ) $showlistend = $numoflists;
				else                   $showlistend = 10;
			  
			  }
			 
			 ob_start();	
			 
			  if ($numoflists>1) {
	 
				if ($curpage > 1) { 
				
				  if ($curpage-4 > 1) { $var = 1; $text = '<<'; include $this->Get($prefix.'_list_item.html', $st_subdir); } 
				  
				  $var = $curpage-1; $text = '<'; include $this->Get($prefix.'_list_item.html', $st_subdir); 
				
				}
				
					for ($i=$showliststart;$i<=$showlistend;$i++) {
					
					$var  = $i; 
					$text = $i;
					
						if ($i == $curpage) include $this->Get($prefix.'_list_item_selected.html', $st_subdir); 
						else			    include $this->Get($prefix.'_list_item.html', $st_subdir); 
						
					}
					
				if ($curpage < $numoflists) { 
				
				  $var = $curpage+1; $text = '>'; include $this->Get($prefix.'_list_item.html', $st_subdir); 
				  
				  if ($curpage+5 < $numoflists) { $var = $numoflists; $text = '>>'; include $this->Get($prefix.'_list_item.html', $st_subdir); } 
				
				}
				
			  }
			  
		$arrows = ob_get_clean();
		
		if ( $arrows ) {
		
			ob_start(); 
			  
			include $this->Get($prefix.'_list.html', $st_subdir);	
			  
			return ob_get_clean();			  
		}
		
	return '';
	}	
}

Class TextBase {

    public static function HTMLDestruct($text) {
	
	  return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	
	}	
	
	public static function HTMLRestore($text) {

	  return html_entity_decode($text, ENT_QUOTES, 'UTF-8');

	}
    
    public static function StringLen($text) {
     
      return mb_strlen($text, 'UTF-8');

    }	

	public static function CutString($text, $from = 0, $to = 255) {
	
	  return mb_substr($text, $from, $to, 'UTF-8');
	
	}

	public static function CutWordWrap($text) {
	
		return str_replace(array("\r\n", "\n", "\r"),'',$text);
	
	}
	
	/* WordWrap - разбиение непрерывного текстового сообщения пробелами	*/
	
	public static function WordWrap($text, $width = 60, $break = "\n") {

	   return preg_replace('#([^\s]{'. $width .'})#u', '$1'. $break , $text);
		   
	}	
}

Class EMail {
const ENCODE = 'utf-8';
	
	public static function Send($mail_to, $subject, $message) {
	global $config;	
	
		$headers = array();
		$headers[] = "Reply-To: ".sqlConfigGet('email-mail');
		$headers[] = "MIME-Version: 1.0";
		$headers[] = "Content-Type: text/html; charset=\"".self::ENCODE."\"";
		$headers[] = "Content-Transfer-Encoding: 8bit";
		$headers[] = "From: \"".sqlConfigGet('email-name')."\" <".sqlConfigGet('email-mail').">";
		$headers[] = "To: ".$mail_to." <".$mail_to.">";
		$headers[] = "X-Priority: 3";	
		$headers[] = "X-Mailer: PHP/".phpversion();
		
		$headers = implode("\r\n", $headers);

		$subject = '=?'.self::ENCODE.'?B?'.base64_encode($subject).'?=';
		
		return ($config['smtp'])? self::smtpmail($mail_to, $subject, $message, $headers) : mail($mail_to, $subject, $message, $headers);
	}
	
	private static function smtpmail($mail_to, $subject, $message, $headers) {
		
		$smtp_user	= sqlConfigGet('smtp-user');
		$smtp_pass	= sqlConfigGet('smtp-pass');
		$smtp_host	= sqlConfigGet('smtp-host');
		$smtp_port	= (int) sqlConfigGet('smtp-port');
		$smtp_hello	= sqlConfigGet('smtp-hello');
		
		$send = "Date: ".date("D, d M Y H:i:s")." UT\r\n";
		$send .= "Subject: {$subject}\r\n";			
		$send .= $headers."\r\n\r\n".$message."\r\n";

		if( !$socket = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 10) ) {
			mcrSys::log('[SMPT] '.$errno." | ".$errstr);
			return false;
		}
		
		stream_set_timeout($socket, 10);
		
		if (!self::server_action($socket, false, "220") or
			!self::server_action($socket, $smtp_hello." " . $smtp_host . "\r\n", "250", 'Приветствие сервера недоступно')) 
				return false;
			
		if (!empty($smtp_user))
			if (!self::server_action($socket, "AUTH LOGIN\r\n", "334", 'Нет ответа авторизации') or
				!self::server_action($socket, base64_encode($smtp_user) . "\r\n", "334", 'Неверный логин авторизации') or
				!self::server_action($socket, base64_encode($smtp_pass) . "\r\n", "235", 'Неверный пароль авторизации')) 
					return false;
				
		if (!self::server_action($socket, "MAIL FROM: <". $smtp_user .">\r\n", "250", 'Ошибка MAIL FROM') or
			!self::server_action($socket, "RCPT TO: <" . $mail_to . ">\r\n", "250", 'Ошибка RCPT TO') or
			!self::server_action($socket, "DATA\r\n", "354", 'Ошибка DATA') or
			!self::server_action($socket, $send."\r\n.\r\n", "250", 'Ошибка сообщения')) 
				return false;
		
		self::server_action($socket, "QUIT\r\n"); 
		return true;
	}

	private static function server_action($socket, $command = false, $correct_response = false, $error_mess = false, $line = __LINE__)	{
		
		if ($command) fputs($socket, $command);		
		if ($correct_response) { 
		
			$server_response = '';
			while (substr($server_response, 3, 1) != ' ') {
				if ($server_response = fgets($socket, 256)) continue;

				if ($error_mess) mcrSys::log('[SMPT] '.$error_mess.' Line: '.$line);			
				return false;
			}
			$code = substr($server_response, 0, 3);
			if ($code == $correct_response) return true;
		}
		
		if ($error_mess) mcrSys::log('[SMPT] '.$error_mess.' | Code: '.$code.' Line: '.$line);	
		fclose($socket);
		
		if ($correct_response) return false; return true;
	}
}

Class Message {

	/*	 
	 Comment - Валидация короткого сообщения, для хранения в БД и вывода на странице

	 Обрезать до 255 символов
	 Расформировать HTML
	 Заменить все переносы строк на <br>
	 Удалить оставшиеся символы переноса строки
	 
	*/

	public static function Comment($text) {
       
	   $text = TextBase::CutString(TextBase::HTMLDestruct($text));
	   $text = TextBase::CutWordWrap(nl2br($text));
	   
	  return mcrDB::safe($text);      
	}
	
	/*
	
	 RestoreCom - Привести короткое сообщение в редактируемый вид
	
	*/
	
	public static function RestoreCom($string){

      return preg_replace('/\<br(\s*)?\/?\>/i', "\n", $string);
	
    }
	
	// TODO BBEncode
	
	public static function BBDecode($text) {

	    $text = preg_replace("/\[b\](.*)\[\/b\]/Usi", "<b>\\1</b>", $text);
        $text = preg_replace("/\[u\](.*)\[\/u\]/Usi", "<u>\\1</u>", $text);
        $text = preg_replace("/\[i\](.*)\[\/i\]/Usi", "<i>\\1</i>", $text);
        $text = preg_replace("/\[color=(\#[0-9A-F]{6}|[a-z]+)\](.*)\[\/color\]/Usi", "<span style=\"color:\\1\">\\2</span>", $text);
	    $text = preg_replace("/\[url=(?:&#039;|&quot;)http:\/\/([^<]+)(?:&#039;|&quot;)](.*)\[\/url]/Usi", "<a href=\"http://\\1\">\\2</a>", $text, 3);
	    
		$tmp = $text;
		
		while(strcmp($text=preg_replace("/\[quote=(?:&#039;|&quot;)(.*)(?:&#039;|&quot;)\](.+?)\[\/quote\]/Uis","<div class=\"comment-quote\"><div class=\"comment-quote-a\">\\1 сказал(a):</div><div class=\"comment-quote-c\">\\2</div></div>",$tmp),$tmp)!=0) 
		 $tmp = $text; 

		return $text;
	}
	
}

class ItemLike {
private $id;
private $type;
private $user_id;

private $bd_content;
private $db;
	
	public function ItemLike($item_type, $item_id, $user_id) {
	global $bd_names;
	
	$this->id = false;
	$this->bd_content = false;
	
		switch ($item_type) {
			case ItemType::News: $this->bd_content = $bd_names['news']; break;
			case ItemType::Comment: $this->bd_content = $bd_names['comments']; break; 
			case ItemType::Skin: 
			
			if (array_key_exists('sp_skins', $bd_names)) 
			
				$this->bd_content = $bd_names['sp_skins']; 
			
			break; 
			default:  return false; break;
		}
		
	$this->db = $bd_names['likes'];	
	
	$this->id		= (int) $item_id;
	$this->type		= (int) $item_type;
	$this->user_id	= (int) $user_id;	
	}
	
	public function Like($dislike = false) {
		
		if (!$this->bd_content) return 0;
		
		$var = (!$dislike)? 1 : -1;

		$result = BD("SELECT `var` FROM `{$this->db}` WHERE `user_id` = '".$this->user_id."' AND `item_id` = '".$this->id."' AND `item_type` = '".$this->type."'"); 
		
		if ( !$result->num_rows ) { 
		
			BD("INSERT INTO `{$this->db}` (`user_id`, `item_id`, `item_type`, `var`) VALUES ('".$this->user_id."', '".$this->id."', '".$this->type."', '".$var."')");
		
			if (!$dislike) 
				BD("UPDATE `{$this->bd_content}` SET `likes` = `likes` + 1 WHERE `id` = '".$this->id."'");					
			else 	     
				BD("UPDATE `{$this->bd_content}` SET `dislikes` = `dislikes` + 1 WHERE `id` = '".$this->id."'");
		
		return 1;
		
		} else {
			
			$line = $result->fetch_array( MYSQL_NUM );
			
			if ((int)$line[0] == (int)$var) return 0; 
			
			BD("UPDATE `{$this->db}` SET `var` = '".$var."' WHERE `user_id` = '".$this->user_id."' AND `item_id` = '".$this->id."' AND `item_type` = '".$this->type."'");		
			
			if (!$dislike) 
				BD("UPDATE `{$this->bd_content}` SET `likes` = `likes` + 1, `dislikes` = `dislikes` - 1  WHERE `id` = '".$this->id."'");
			else 
				BD("UPDATE `{$this->bd_content}` SET `likes` = `likes` - 1, `dislikes` = `dislikes` + 1 WHERE `id` = '".$this->id."'");			
		
		return 2;		
		}
	}
}

Class Menu extends View {
private $menu_items;
private $menu_fname;

    public function Menu($style_sd = false, $auto_load = true, $mfile = 'instruments/menu_items.php') {
	global $config;
	
		parent::View($style_sd);
		
		$this->menu_fname = $mfile;
		
		if ($auto_load) {

		require(MCR_ROOT.$this->menu_fname);
		
		$this->menu_items = $menu_items; 				
		} else $this->menu_items = array( 0 => array(), 1 => array() );
	}
	
	private static function array_insert_before(&$array, $var, $key_name) {

	$index = array_search($key_name, array_keys($array));
	if ($index === false) return false;

	$part_array = array_splice ($array, 0, $index);
	$array = array_merge ($part_array, $var, $array);
	return true;
	}
	
	private function SaveMenu() {
	
		$txt  = "<?php if (!defined('MCR')) exit;".PHP_EOL;
		$txt .= '$menu_items = '.var_export($this->menu_items, true).';'.PHP_EOL;

		$result = file_put_contents(MCR_ROOT.$this->menu_fname, $txt);

		return (is_bool($result) and $result == false)? false : true;	
	}
	
	private function ShowItem(&$item) {
	
		$button_name  = $item['name'];
		$button_url   = $item['url'];
			
		$button_class = ($item['active'])? 'active' : 'not_active';
		
		$button_links = (isset($item['inner_html']))? $item['inner_html'] : '';
		
		$type = ($button_links)? 'menu_dropdown_item' : 'menu_item'; 

		ob_start(); include $this->GetView('menu/'.$type.'.html');
		
		return ob_get_clean();		
	}
	
	public function DeleteItem($menu, $key) {
	
	$menu_id = 1; if ($menu == 'left') $menu_id = 0;
	
	$index = array_search($key, array_keys($this->menu_items[$menu_id]));
	if ($index === false) return false;

	array_splice ($this->menu_items[$menu_id], $index, 1);
	return $this->SaveMenu();
	}
	
	/* TODO -- add config trigger cheker */
	
	public function Show() {
	global $user, $config;
	
	$menu_content = ''; $html_menu = '';
	
	if (!empty($user)) $user_lvl = $user->lvl(); else $user_lvl = 0;	
	
	for ($i = 0; $i < 2; $i ++) {
	
		if (!sizeof($this->menu_items[$i])) continue;
	
		foreach ($this->menu_items[$i] as $key => $value) {
		  
			$this->menu_items[$i][$key]['access'] = true; 
			
				if ( $user_lvl < $value['lvl'] ) 
					
					$this->menu_items[$i][$key]['access'] = false;
					
			elseif (array_key_exists('config', $value) and $value['config'] != -1 and 
					array_key_exists($value['config'], $config) and is_bool($config[$value['config']]) and !$config[$value['config']])
			
					$this->menu_items[$i][$key]['access'] = false;
			
			elseif ( $value['permission'] != -1 )
			
				if (!empty($user) and !$user->getPermission($value['permission'])) 
				
					$this->menu_items[$i][$key]['access'] = false;			
		  
			if ( $value['parent_id'] <= -1 or !$this->menu_items[$i][$key]['access'] ) continue;		
		  
			$this->menu_items[$i][$value['parent_id']]['inner_html'] .= $this->ShowItem($value);
			
			if ($value['active'] and $value['parent_id'] > -1) 
			
				$this->menu_items[$i][$value['parent_id']]['active'] = true;
		}	
	
		foreach ($this->menu_items[$i] as $key => $value) {
		  
			if ( $value['parent_id'] > -1 or ( $value['parent_id'] == -2 and !$value['inner_html'] ) or !$value['access'] ) continue;

		    $menu_content .= $this->ShowItem($value, 'menu/menu_item');
		}
		
		$menu_align = ($i == 1) ? 'pull-right' : 'pull-left';
		
		ob_start(); include $this->GetView('menu/menu.html');
		
		$html_menu .= ob_get_clean();
		
		$menu_content = '';
		unset($key, $value); 
	}
	
	return $html_menu;
	}

	public function SaveItem($id, $menu, $info, $insert_before = false) {
	
	$menu_id = 1; if ($menu == 'left') $menu_id = 0;
	
	if (!is_array($info) 			or
		!$info['name']				or
		!is_int($info['lvl'])		or
		(is_int($info['parent_id']) and $info['parent_id'] != -1) or
		(isset($info['config']) and is_int($info['config']) and $info['config'] != -1) or
		(is_int($info['permission']) and $info['permission'] != -1))

		return false;
		
	for ($i = 0; $i < 2; $i ++) 
	
		if ( array_key_exists($id, $this->menu_items[$i]) ) return false;
	
	$new_item =  array (
		
			'name'			=> $info['name'],
			'url' 			=> $info['url'],
			'parent_id'		=> ($info['parent_id'])? $info['parent_id'] : -1,
			'lvl'			=> (is_int($info['parent_id']))-1,
			'permission'	=> $info['permission'],
			'config'		=> (isset($info['config']))? $info['config'] : -1,
			'active'		=> (isset($info['active']))? $info['active'] : false,			
			'inner_html'	=> '',
		);
	
	if ($insert_before) {
	
		if (!self::array_insert_before($this->menu_items[$menu_id], array( $id => $new_item ), $insert_before))
		
			$this->menu_items[$menu_id][$id] = $new_item;		
	
	} else	{
		
		$this->menu_items[$menu_id][$id] = $new_item;
	}
	
	return $this->SaveMenu();
	}

	public function AddItem($name, $url, $active = false, $menu = 'left') { 

		for ($i = 0; $i < 2; $i ++) 
		
			foreach ($this->menu_items[$i] as $key => $value) 
			
				if ($value['name'] == $name) return $key;
		
		$menu_id = 1;
		if ($menu == 'left') $menu_id = 0;
		
		$new_key = sizeof($this->menu_items[$menu_id]);

		$this->menu_items[$menu_id][$new_key] = array (
		
			'name'			=> $name,
			'url' 			=> $url,
			'parent_id'		=> -1,
			'lvl'			=> -1,
			'permission'	=> -1,
			'active'		=> $active,
			'inner_html'	=> '',
		);

	return $new_key;
	}
	
	public function IsItemExists ($item_key) {

	if ( array_key_exists($item_key, $this->menu_items[0])) $menu_id = 0;
	elseif ( array_key_exists($item_key, $this->menu_items[1])) $menu_id = 1;
	else return false;

	return $menu_id;
	}

    public function SetItemActive($item_key) {
	global $menu_items;

		$menu_id = $this->IsItemExists($item_key);
	if ($menu_id === false) return false;
	
	$this->menu_items[$menu_id][$item_key]['active'] = true;	
	return true;
    }	
}

Class Rewrite {

	private static function IsOn() {	
	global $config;
	
		return ($config['rewrite'])? true : false;
	}

	public static function GetURL($url_data, $get_params = array('mode', 'do'), $check_rewrite = true, $amp = '&amp;') {

		$str = ''; $is_arr = (is_array($url_data))? true : false;
		
		if ($check_rewrite and self::IsOn()) {
		
			if ($is_arr) {
			
				foreach($url_data as $key => $value) 
			
					$str .= $value.'/';
					
			} else $str .= 'go/'.$url_data.'/';
		
		} else {
		
			if ($is_arr) {
			
				$first = true;
				
				foreach($get_params as $key => $value) {
				
					if (!$value) continue;
					if ($first) { $str .= '?'; $first = false; } else $str .= $amp;
					$str .= $value.'='.$url_data[$key];	
				}
			} else $str .= '?'.$get_params[0].'='.$url_data;
		}
		
		return $str;
	}

}