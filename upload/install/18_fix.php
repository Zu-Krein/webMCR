<?php 
if (!defined('MCR')) exit;

$result = BD("SELECT `id`,`message_full`,`message` FROM `{$bd_names['news']}`");
$num = $result->num_rows;
if ($num) {
	
	while ( $line = $result->fetch_array() ) {
	  
	    $id = $line['id'];
		$mess = TextBase::HTMLRestore($line['message']);
		$mess_full = TextBase::HTMLRestore($line['message_full']);
			
	BD("UPDATE `{$bd_names['news']}` SET `message`='$mess',`message_full`='$mess_full' WHERE `id`='$id'");
	}
}
?>