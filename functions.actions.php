<?php

function webim_action_online() {
	global $imuser, $imclient, $_IMC, $im_is_login;
	$domain = webim_gp("domain");

	if ( !$im_is_login ) {
		if ( !$_IMC[ 'disable_login' ] ) {
			//webim_validate_presence( "username", "password" );
			if( !webim_login( trim( webim_gp("username") ), trim( webim_gp("password") ), trim( webim_gp("question") ), trim( webim_gp("answer") ) ) ) {
				exit( webim_callback( array( "success" => false, "error_msg" => "Not Authorized" ) ) );
			} else {
				$imclient->user = $imuser;
			}
		} else {
			exit( webim_callback( array( "success" => false, "error_msg" => "Forbidden" ) ) );
		}
	}

	$im_buddies = array();//For online.
	$im_rooms = array();//For online.
	$strangers = webim_ids_array( webim_gp('stranger_ids') );
	$cache_buddies = array();//For find.
	$cache_rooms = array();//For find.

	$active_buddies = webim_ids_array( webim_gp('buddy_ids') );
	$active_rooms = webim_ids_array( webim_gp('room_ids') );

	$new_messages = webim_new_message();
	if( $domain ) {
		//old version has not $domain param.
		$online_buddies = webim_get_online_buddies($domain);
	} else {
		$online_buddies = webim_get_online_buddies();
	}
	$buddies_with_info = array();//Buddy with info.

	//Active buddy who send a new message.
	$count = count($new_messages);
	for($i = 0; $i < $count; $i++){
		$active_buddies[] = $new_messages[$i]->from;
	}

	//Find im_buddies
	$all_buddies = array();
	foreach($online_buddies as $k => $v){
		$id = $v->id;
		$im_buddies[] = $id;
		$buddies_with_info[] = $id;
		$v->presence = "offline";
		$v->show = "unavailable";
		$cache_buddies[$id] = $v;
		$all_buddies[] = $id;
	}

	//Get active buddies info.
	$buddies_without_info = array();
	foreach($active_buddies as $k => $v){
		if(!in_array($v, $buddies_with_info)){
			$buddies_without_info[] = $v;
		}
	}
	if(!empty($buddies_without_info) || !empty($strangers)){
		if( $domain ) {
			$bb = webim_get_buddies(implode(",", $buddies_without_info), implode(",", $strangers), $domain);
		} else {
			//old version has not $domain param.
			$bb = webim_get_buddies(implode(",", $buddies_without_info), implode(",", $strangers));
		}
		foreach( $bb as $k => $v){
			$id = $v->id;
			$im_buddies[] = $id;
			$v->presence = "offline";
			$v->show = "unavailable";
			$cache_buddies[$id] = $v;
		}
	}
	if(!$_IMC['disable_room']){
		$rooms = webim_get_rooms();
		$setting = webim_get_settings();
		$blocked_rooms = $setting && is_array($setting->blocked_rooms) ? $setting->blocked_rooms : array();
		//Find im_rooms 
		//Except blocked.
		foreach($rooms as $k => $v){
			$id = $v->id;
			if(in_array($id, $blocked_rooms)){
				$v->blocked = true;
			}else{
				$v->blocked = false;
				$im_rooms[] = $id;
			}
			$cache_rooms[$id] = $v;
		}
		//Add temporary rooms 
		$temp_rooms = $setting && is_array($setting->temporary_rooms) ? $setting->temporary_rooms : array();
		for ($i = 0; $i < count($temp_rooms); $i++) {
			$rr = $temp_rooms[$i];
			$rr->temporary = true;
			$rr->pic_url = (webim_urlpath() . "static/images/chat.png");
			$rooms[] = $rr;
			$im_rooms[] = $rr->id;
			$cache_rooms[$rr->id] = $rr;
		}
	}else{
		$rooms = array();
	}

	//===============Online===============
	//

	$data = $imclient->online( implode(",", array_unique( $im_buddies ) ), implode(",", array_unique( $im_rooms ) ) );

	if( $data->success ){
		$data->new_messages = $new_messages;

		if(!$_IMC['disable_room']){
			//Add room online member count.
			foreach ($data->rooms as $k => $v) {
				$id = $v->id;
				$cache_rooms[$id]->count = $v->count;
			}
			//Show all rooms.
		}
		$data->rooms = $rooms;

		$show_buddies = array();//For output.
		foreach($data->buddies as $k => $v){
			$id = $v->id;
			if(!isset($cache_buddies[$id])){
				$cache_buddies[$id] = (object)array(
					"id" => $id,
					"nick" => $id,
					"incomplete" => true,
				);
			}
			$b = $cache_buddies[$id];
			$b->presence = $v->presence;
			$b->show = $v->show;
			if( !empty($v->nick) )
				$b->nick = $v->nick;
			if( !empty($v->status) )
				$b->status = $v->status;
			#show online buddy
			$show_buddies[] = $id;
		}
		#show active buddy
		$show_buddies = array_unique(array_merge($show_buddies, $active_buddies, $all_buddies));
		$o = array();
		foreach($show_buddies as $id){
			//Some user maybe not exist.
			if(isset($cache_buddies[$id])){
				$o[] = $cache_buddies[$id];
			}
		}

		//Provide history for active buddies and rooms
		foreach($active_buddies as $id){
			if(isset($cache_buddies[$id])){
				$cache_buddies[$id]->history = webim_get_history($id, "chat" );
			}
		}
		foreach($active_rooms as $id){
			if(isset($cache_rooms[$id])){
				$cache_rooms[$id]->history = webim_get_history( $id, "grpchat" );
			}
		}


		$show_buddies = $o;
		$data->buddies = $show_buddies;
		webim_new_message_to_histroy();
		echo webim_callback($data);

	}else{
		exit( webim_callback( array( "success" => false, "error_msg" => empty( $data->error_msg ) ? "IM Server Not Found" : "IM Server Not Authorized", "im_error_msg" => $data->error_msg ) ) );
	}
}

function webim_action_offline() {
	global $imuser, $imclient, $_IMC;
	webim_validate_presence( "ticket" );
    $imclient->offline();
	echo webim_callback( "ok"  );
}

function webim_action_message() {
	global $imuser, $imclient, $_IMC;
	webim_validate_presence( "ticket", "type", "to", "body" );
	$type = webim_gp("type");
	$offline = webim_gp("offline");
	$to = webim_gp("to");
	$body = webim_gp("body");
	$style = webim_gp("style");
	$send = $offline == "true" || $offline == "1" ? 0 : 1;
	$timestamp = webim_microtime_float() * 1000;
	if( strpos($body, "webim-event:") !== 0 )
		webim_insert_history( $type, $to, $body, $style, $send, $timestamp );
	if($send == 1){
		$imclient->message($type, $to, $body, $style, $timestamp);
	}
	echo webim_callback( "ok" );
}

function webim_action_logmsg() {
	global $imuser, $imclient, $_IMC, $imdb;
	webim_validate_presence( "ticket", "type", "to", "body" );
	$msg = array(
		"send" => 1,
		"type" => webim_gp("type"),
		"body" => webim_gp("body"),
		"from" => webim_gp("from"),
		"to" => webim_gp("to"),
		"nick" => webim_gp("nick"),
		"style" => webim_gp("style"),
		"timestamp" => webim_gp("timestamp"),
		"created_at" => date( 'Y-m-d H:i:s' ),
	);
	$q =  $imdb->prepare( "SELECT id FROM $imdb->webim_histories WHERE `type`=%s and `from`=%s and `to`=%s and `nick`=%s and `timestamp`=%s", $msg['type'], $msg['from'], $msg['to'], $msg['nick'], $msg['timestamp'] );
	$m = $imdb->get_var( $q );
	if ( empty( $m ) ) {
		$imdb->insert( $imdb->webim_histories, $msg );
	}
	echo webim_callback( "ok" );
}

function webim_action_presence() {
	global $imuser, $imclient, $_IMC;
	webim_validate_presence( "ticket", "show" );
    $imclient->presence( webim_gp("show"), webim_gp("status") );
	echo webim_callback( "ok" ) ;
}

function webim_action_history() {
	global $imuser, $imclient, $_IMC;
	webim_validate_presence( "id", "type" );
	echo webim_callback( webim_get_history( webim_gp("id"), webim_gp("type") ) );
}

function webim_action_status() {
	global $imuser, $imclient, $_IMC;
	webim_validate_presence( "ticket", "show", "to" );
    $imclient->status( webim_gp("to"), webim_gp("show") );
	echo webim_callback( "ok" ) ;
}

function webim_action_members() {
	global $imuser, $imclient, $_IMC;
	webim_validate_presence( "ticket", "id" );
	$re = $imclient->members( webim_gp( "id" ) );
	echo $re ? webim_callback( $re ) : "Not Found";
}

function webim_action_join() {
	global $imuser, $imclient, $_IMC;
	webim_validate_presence( "ticket", "id" );
	$id = webim_gp("id");
	$room = webim_get_rooms( $id );
	if( $room && count($room) ) {
		$room = $room[0];
	} else {
		$room = (object)array(
			"id" => $id,
			"nick" => webim_gp("nick"),
			"temporary" => true,
			"pic_url" => (webim_urlpath() . "static/images/chat.png"),
		);
	}
	if($room){
		$re = $imclient->join($id);
		if($re){
			$room->count = $re->count;
			echo webim_callback( $room );
		}else{
			header("HTTP/1.0 404 Not Found");
			echo "Can't join this room right now";
		}
	}else{
		header("HTTP/1.0 404 Not Found");
		echo "Can't found this room";
	}
}

function webim_action_leave() {
	global $imuser, $imclient, $_IMC;
	webim_validate_presence( "ticket", "id" );
    $imclient->leave( webim_gp("id") );
	echo webim_callback( "ok" );
}

function webim_action_buddies() {
	global $imuser, $imclient, $_IMC;
	webim_validate_presence( "ids" );
	echo webim_callback( webim_get_buddies( webim_gp("ids") ) );
}

function webim_action_rooms() {
	global $imuser, $imclient, $_IMC;
	webim_validate_presence( "ids" );
	echo webim_callback( webim_get_rooms( webim_gp("ids") ) );
}

function webim_action_refresh() {
	global $imuser, $imclient, $_IMC;
	webim_validate_presence( "ticket" );
    $imclient->offline();
	echo webim_callback( "ok" );
}

function webim_action_clear_history() {
	global $imuser, $imclient, $_IMC;
	webim_validate_presence( "id" );
	webim_clear_history( webim_gp("id") );
	echo webim_callback( "ok" );
}

function webim_action_download_history() {
	global $imuser, $imclient, $_IMC;
	webim_validate_presence( "id", "type" );
	$histories = webim_get_history( webim_gp("id"), webim_gp("type"), 1000 );
	include( WEBIM_PATH . "lib/templates.history.php" );
}

function webim_action_setting() {
	global $imuser, $imclient, $_IMC;
	webim_validate_presence( 'data' );
	webim_update_settings( stripslashes( webim_gp( 'data' ) ) );
	echo webim_callback( 'ok' );
}

function webim_action_notifications() {
	echo webim_callback( webim_get_notifications() );
}

function webim_action_openchat() {
	global $imuser, $imclient, $_IMC;
	webim_validate_presence( "ticket", "group_id", "nick" );
	echo webim_callback( $imclient->openchat( webim_gp("group_id"), webim_gp("nick") ) );
}

function webim_action_closechat() {
	global $imuser, $imclient, $_IMC;
	webim_validate_presence( "ticket", "group_id" );
	echo webim_callback( $imclient->closechat( webim_gp("group_id"), webim_gp("buddy_id") ) );
}

?>
