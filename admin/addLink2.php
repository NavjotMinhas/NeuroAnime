<?php
	$link=$_POST['link'];
	$episodeNumber=$_POST['ep'];
	$title=$_POST['title'];
	$language=$_POST['lang'];
	$description=$_POST['description'];
	require_once(dirname(getcwd()).'/modules.php');
	if(strlen($episodeNumber)==1){
		$episodeNumber='0'.$episodeNumber;
	}
	$ep=modules::getQueryResults('SELECT episode.episode_id FROM episode INNER JOIN anime ON episode.anime_id=anime.anime_id WHERE anime.title=\'%s\' AND episode.episode_number=\'%s\' LIMIT 1', array($title,$episodeNumber));
	$episodeid=$ep['episode_id'];
	$array=explode(',',$link);
	$addData=false;
	foreach($array as $row){
		$pattern = '/(?<=http:\/\/).*?(?=\/)/';
		preg_match($pattern, $row, $matches);
		$addData=modules::addData(array('episode_id','video_link', 'video_description', 'language'),array($episodeid,$row,$description.$matches[0],$language),'videos');
		if(!$addData){
			break;
		}
	}
	
	if($addData){
		echo $episodeNumber.' 200';
	}else{
		echo $episodeNumber.' 404';
	}
?>
