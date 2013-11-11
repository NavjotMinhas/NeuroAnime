<?php
	$title=$_GET['t'];
	$description=$_GET['d'];
	$image=$_GET['image'];
	var_dump($_GET);
	require_once(dirname(getcwd()).'/modules.php');
	$bool=modules::getQueryResults('SELECT * FROM anime WHERE title=\'%s\'',$title);
	if($bool){
		die('Anime title already exists in the database');
	}
	$bool=modules::addData(array('Title','ImgPicture', 'Genre','Rating','summary','year','Views'),array($title,$image,NULL,5,$description,0,0),'anime');
	if(!$bool){
		die('Did not get added to database');
	}
	modules::closeConnection();
?>