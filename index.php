<?php

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################

define('THIS_SCRIPT', 'index');
define('CSRF_PROTECTION', true);  
// change this depending on your filename

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array('index',
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
$pagetitle = 'Home';

$metatag='<META NAME="Description" CONTENT="Watch Anime Online">';

$div;

$updates;
					$array = modules::getQueryResults('SELECT anime.title, episode.episode_number, episode.episode_id FROM episode INNER JOIN anime ON anime.anime_id=episode.anime_id ORDER BY episode.date DESC,episode.episode_id DESC LIMIT 0,11',false);
					for($i = 0; $i < sizeof($array); $i++) {
						$row = $array[$i];
						$updates.= '
					<li>
						<a href="episode.php?v='.$row['episode_id'].'">' . $row['title'] . ' Ep ' . $row['episode_number'] . '</a>
					</li>';
					$div.='Watch '.$row['title'] . ' Episode ' . $row['episode_number'].' Online.';
					}
					
$slider;
			$array = modules::getQueryResults('SELECT * FROM slider',false);
			if($array){
			foreach($array as $row){
				$slider.= '<a href="'.$row['ClickUrl'].'"> <img src="images/slider/'.$row['Image_File_Name'].'" alt="'.$row['Image_File_Name'].'" width="600" height="278" title="" alt="" rel="<h3>'.$row['Title'].'</h3>'.$row['Description'].'<a class=button href='.$row['ClickUrl'].'><center>Watch Online</center></a> "/> </a> ';
			}
			}


$popularVideos;
					$array = modules::getQueryResults('SELECT * FROM anime ORDER BY Views ASC LIMIT 2',false);
					foreach($array as $row) {
						$metadata = modules::getQueryResults('SELECT * FROM episode INNER JOIN anime ON episode.anime_id=anime.anime_id WHERE anime.title = \'' . $row['Title'] . '\' LIMIT 1', false);
							$popularVideos.= '
					<li>
						<a href="anime.php?t='.$row['Title'].'" ><img class="clip" src="images/thumbnails/' . $row['ImgPicture'] . '" width="96" height="120" /></a>
					</li>';
							$popularVideos.= '
					<li>
						<div class="clipDescription">
							<div class="clipTitle">
								<a href="anime.php?t='.$row['Title'].'" >'. $row['Title'] . '</a>
							</div>
							<font color="#999999">
								Latest Episode:
							</font>' . $metadata['episode_number'] . '
							<br />
							<font color="#999999">
								Date Added:
							</font> ' . date("F-d-Y", strtotime($metadata['date'])) . '
							<br />
							<font color="#999999">
								Description:
							</font> ' . substr($row['Summary'], 0, 128) . '...
						</div>
					</li>';
						
					}
					modules::closeConnection();

// ###### NOW YOUR TEMPLATE IS BEING RENDERED ######

$templater = vB_Template::create('index');
$templater->register_page_templates();
$templater->register('pagetitle', $pagetitle);
$templater->register('content_hidden', $div);
$templater->register('metatag', $metatag);
$templater->register('updates', $updates);
$templater->register('slider', $slider);
$templater->register('popularVideos', $popularVideos);
print_output($templater->render());

?>
