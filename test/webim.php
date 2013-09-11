<?php

/**
 * WebIM PHP Lib Test
 *
 * Author: Hidden
 *
 * First config, and then run it.
 *
 */

$domain = "test";
$apikey = "test";
$host = "localhost";
$port = "8000";

require_once(dirname(__FILE__).'/../http_client.php');
require_once(dirname(__FILE__).'/../functions.helper.php');
require_once(dirname(__FILE__).'/../functions.json.php');
require_once(dirname(__FILE__).'/../class.webim_client.php');

$test = (object)array("id" => 'test', "nick" => "Test", "show" => "available");
$susan = (object)array("id" => 'susan', "nick" => "Susan", "show" => "available");
$jack = (object)array("id" => 'jack', "nick" => "Jack", "show" => "available");

$large_buddies = array("susan", "josh");

for($i = 0; $i < 2000; $i++){
	$large_buddies[] = "test_buddy_$i";
}

$large_buddies = implode(",", $large_buddies);
echo "<br>online_buddy_size: ".strlen($large_buddies);


$im_test = new webim_client($test, null, $domain, $apikey, $host, $port);
$im = new webim_client($susan, null, $domain, $apikey, $host, $port);
$im->online("jack,josh", "room1,room2");

$im = new webim_client($jack, null, $domain, $apikey, $host, $port);

//var_export($im);
echo "<br>\n\n\nWebIM PHP Lib Test\n<br>";
echo "<br>===================================\n\n<br>";

$count = 0;
$error = 0;
$res = $im_test->check_connect();
debug($res->success, "check_connect", $res);

$res = $im->online($large_buddies, "room1");
debug($res->success, "online", $res);

$res = json_decode($im->presence("dnd", "I'm buzy now."));
debug($res->status == "ok", "presence", $res);

$res = json_decode($im->message("chat", "susan", "Hello."));
debug($res->status == "ok", "message", $res);

$res = json_decode($im->status("susan", "inputting..."));
debug($res->status == "ok", "status", $res);

$res = $im->join("room2");
debug($res, "join", $res);

$res = json_decode($im->leave("room2"));
debug($res->status == "ok", "leave", $res);

$res = $im->members("room1");
debug($res, "members", $res);

$res = json_decode($im->offline());
debug($res->status == "ok", "offline", $res);

$res = $im->online("", "");
debug($res->success && empty($res->rooms) && empty($res->buddies), "online with empty room and buddy", $res);

echo "<br>===================================<br>\n";
$succ = $count - $error;
echo "<br>$count test, $succ pass, $error error.<br>\n\n";

function debug($succ, $mod, $res){
	global $count, $error;
	$count++;
	echo "$mod: ";
	if(is_string($res)){
		echo $res;
	}else{
		echo "<br>".json_encode($res)."<br>";
	}
	echo "\n";
	if($succ){
		echo "<br>------------------------------------<br>\n\n";
	}else{
		$error++;
		echo "<br>~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~<br>\n\n";
	}
}


?>
