<?php
	require_once('modules.php');
	$title=$_GET['t'];
	if(!$title){
		die();
	}

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################

define('THIS_SCRIPT', 'anime');
define('CSRF_PROTECTION', true);  
// change this depending on your filename

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array('anime',
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
// if your page is outside of your normal vb forums directory, you should change directories by uncommenting the next line
// chdir ('/path/to/your/forums');
require_once('./global.php');
require_once('modules.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// ###### YOUR CUSTOM CODE GOES HERE #####
$pagetitle = 'Anime '.$title;
$metatag='<META NAME="Description" CONTENT="';
$information;


				$result= modules::getQueryResults('SELECT * FROM anime WHERE Title="%s" LIMIT 1',$title);
				$information.= '<div class="result"><img class="clip" src="images/thumbnails/'.$result['ImgPicture'].'" />';
				$information.= '<div class="description"><p><h3>Title: '.$result['Title'].'</h3></p>';
				$information.= '<br /><p><h3>Rating:</h3>';
				for($i=0;$i<$result['Rating'];$i++){
					$information.= '<img src="images/stars-01.png" />';
				}
				/*$information.= '</p><br /><p><h3>Genre: </h3>';
				foreach (explode(',', $result['Genre']) as $g){
					$information.= '<a class="genreLink" href="series.php?g='.$g.'">'.$g.'</a> , ';
				}*/
				$information.= '</p><br /><p><h3>Summary: </h3>'.$result['Summary'].'</p></div></div>';
				$metatag.=$result['Title'].': '.$result['Summary'].'">';
$episodeList;	
				$array= modules::getQueryResults('SELECT * FROM episode INNER JOIN  anime ON episode.anime_id=anime.anime_id WHERE Title="%s" ORDER BY sort_id DESC',$title);
				if($array){
					foreach ($array as $row){
						$episodeList.='<tr><td><a href="episode.php?v='.$row['episode_id'].'">'.$row['title'].' Episode '.$row['episode_number'].'</a></td></tr>';
					}
				}

// ###### NOW YOUR TEMPLATE IS BEING RENDERED ######

$templater = vB_Template::create('anime');
$templater->register_page_templates();
$templater->register('pagetitle', $pagetitle);
$templater->register('metatag', $metatag);
$templater->register('information', $information);
$templater->register('episodeList', $episodeList);
print_output($templater->render());

?>
