<?php
	$title=$_GET['t'];
	$episodeNumber=$_GET['ep'];
	if(strlen($episodeNumber)==1){
		$episodeNumber='0'.$episodeNumber;
	}
	if($title && $episodeNumber){
		require_once(dirname(getcwd()).'/modules.php');
		$array=modules::getQueryResults('SELECT anime_id FROM anime WHERE title=\'%s\' LIMIT 1',$title);
		if(!$array){
			die('The episode could not be added because the specified anime title does not exsit');
		}
		$doesEpisodeExsit=modules::getQueryResults('SELECT episode.episode_id FROM episode INNER JOIN anime ON episode.anime_id=anime.anime_id WHERE anime.title=\'%s\' AND episode.episode_number=\'%s\'', array($title,$episodeNumber));
		if($doesEpisodeExsit){
			die('The episode already exsits');
		}
		$bool=modules::addData(array('episode_id', 'anime_id', 'episode_number', 'date','sort_id'),array(null,$array['anime_id'],$episodeNumber,date('Y-n-d'),$episodeNumber),'episode');
		modules::closeConnection();
		 if(!$bool){
			die('could not add the episode');
		 }
	}
?>