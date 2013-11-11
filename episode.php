<?php
	if(!$_GET['v']){
		die();
	}
// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################

define('THIS_SCRIPT', 'episode');
define('CSRF_PROTECTION', true);  
// change this depending on your filename

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array('episode',
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
$pagetitle = 'Episode';
$metatag='<META NAME="Description" CONTENT="';
$episodeID=$_GET['v'];
$player;
$links;
$array=modules::getQueryResults('SELECT * FROM (episode INNER JOIN videos ON episode.episode_id=videos.episode_id) INNER JOIN anime ON anime.anime_id=episode.anime_id WHERE videos.episode_id=%d',$episodeID);
$metatag.=$array[0]['Title'].' epsiode: '.$array[0]['episode_number'].' '.$array[0]['Summary'].'">';
$player='<iframe id="player" width="790" height="345" src="'.$array[0]['video_link'].'" frameborder="0" allowfullscreen></iframe>';
foreach($array as $row){
	$links.= '<a class="videoLink" href="'.$row['video_link'].'">'.$row['Title'].' '.$row['episode_number'].' ['.$row['video_description'].'] </a>';
}				

// ###### NOW YOUR TEMPLATE IS BEING RENDERED ######

$templater = vB_Template::create('episode');
$templater->register_page_templates();
$templater->register('pagetitle', $pagetitle);
$templater->register('metatag', $metatag);
$templater->register('player', $player);
$templater->register('links', $links);
print_output($templater->render());

?>
