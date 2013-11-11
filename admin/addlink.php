<?php
	require_once('authenticator.php');
	$episodeid=$_GET['eid'];
	$link=$_GET['link'];
	$language=$_GET['lang'];
	$description=$_GET['desc'];
	$videoid=$_GET['v'];
	
	if($episodeid&&$link&&$language&&$description&&$videoid){
		require_once(dirname(getcwd()).'/modules.php');
		$bool=modules::addData(array('episode_id','video_link', 'video_description', 'language'),array($episodeid,$link,$description,$language),'videos');
		if(!$bool){
			die('Could not add link');
		}
		header("Location: removelink.php?id=".$videoid);
		
	}
	header("Location: linkmoderator.php");
	exit();
?>