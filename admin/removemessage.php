<?php
	require_once('authenticator.php');
	$id=$_GET['id'];
	if($id){
		require_once(dirname(getcwd()).'/modules.php');
		modules::deleteRow('DELETE FROM admin_messages WHERE message_id ="'.$id.'"');
		modules::closeConnection();
	}
	header("Location: index.php");
	exit();
?>