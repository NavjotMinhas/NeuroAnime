<?php
	require_once('authenticator.php');
	$id=$_GET['id'];
	$imageName=$_GET['imagename'];
	if($id){
		require_once(dirname(getcwd()).'/modules.php');
		modules::deleteRow('DELETE FROM slider WHERE slider_id ="'.$id.'"');
		modules::closeConnection();
		unlink(dirname(getcwd()).'/images/slider/'.$imageName);
	}
	header("Location: slider.php");
	exit();
?>