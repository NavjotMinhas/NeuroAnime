<?php
	require_once('authenticator.php');
	$id=$_GET['id'];
	if($id){
		require_once(dirname(getcwd()).'/modules.php');
		modules::deleteRow('DELETE FROM mod_links WHERE video_id ="'.$id.'"');
		modules::closeConnection();
	}
	header("Location: linkmoderator.php");
	exit();
?>