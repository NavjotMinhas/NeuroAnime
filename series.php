<?php

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################

define('THIS_SCRIPT', 'series');
define('CSRF_PROTECTION', true);  
// change this depending on your filename

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array('series',
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
$pagetitle = 'Series';

$searchAlpha;			
for($i = 65; $i <= 90; $i++) {
	$searchAlpha.='<a href="series.php?series=' . chr($i) . '" >' . chr($i) . '</a>';
}
$result;				
$alpha=$_GET['series'];
$genre=$_GET['g'];
if($alpha){
	$array=modules::getQueryResults('SELECT * FROM anime WHERE Title like "%s" ORDER BY Title ASC',$alpha.'%');
	foreach($array as $row){
		$result.= '<div class="result">';
		$result.= '<img class="clip" src="images/thumbnails/'.$row['ImgPicture'].'" />';
		$result.= '<div class="description">';
		$result.= '<a href="anime.php?t='.$row['Title'].'"><h3>'.$row['Title'].'</h3></a>';
		/*$result.='<h4>Genre: ';
		foreach (explode(',', $row['Genre']) as $g){
			$result.= '<a class="genreLink" href="series.php?g='.$g.'">'.$g.'</a> , ';
		}
		$result.='</h4>';*/
		for($i=0;$i<$row['Rating'];$i++){
			$result.= '<img src="images/stars-01.png" />';
		}
		$result.= '<p id="dec">'.substr($row['Summary'], 0, 512).'...'.'</p>';
		$result.= '</div></div>';
	}
	modules::closeConnection();
}else if($genre){
	$array=modules::getQueryResults('SELECT * FROM anime WHERE Genre like "%'.$genre.'%" ORDER BY Title ASC',false);
	foreach($array as $row){
		$result.= '<div class="result">';
		$result.= '<img class="clip" src="images/thumbnails/'.$row['ImgPicture'].'" />';
		$result.= '<div class="description">';
		$result.= '<a href="anime.php?t='.$row['Title'].'"><h3>'.$row['Title'].'</h3></a>';
		$result.='<h4>Genre: ';
		foreach (explode(',', $row['Genre']) as $g){
			$result.= '<a class="genreLink" href="series.php?g='.$g.'">'.$g.'</a> , ';
		}
		$result.='</h4>';
		for($i=0;$i<$row['Rating'];$i++){
			$result.= '<img src="images/stars-01.png" />';
		}
		$result.= '<p id="dec">'.substr($row['Summary'], 0, 512).'...'.'</p>';
		$result.= '</div></div>';
	}
	modules::closeConnection();
}else{
    $array=modules::getQueryResults('SELECT Title FROM anime',null);
    $num_of_title=count($array);
    $col_size=0;
    if($num_of_title%3==0){
        $col_size=$num_of_title/3;
    }else{
        $col_size=ceil($num_of_title/3);
    }
    $result.='<div style="margin-left:auto;margin-right:auto;width:900px"><table>';
    for($i=0;$i<$col_size;$i++){
        $result.= '<tr><td style="width:300px"><a href="anime.php?t='.$array[$i]['Title'].'"><h3>'.$array[$i]['Title'].'</h3></a></td><td style="width:300px"><a href="anime.php?t='.$array[$i+$col_size]['Title'].'"><h3>'.$array[$i+$col_size]['Title'].'</h3></a></td>';
        $x=$num_of_title-$i+$col_size*2;
        if($x>=0){
           $result.='<td style="width:300px"><a href="anime.php?t='.$array[$i+$col_size*2]['Title'].'"><h3>'.$array[$i+$col_size*2]['Title'].'</h3></a></td></tr>';
        }else{
            $result.='<td></td></tr>';
        }
        
    }
    $result.='</table></div>';
}

// ###### NOW YOUR TEMPLATE IS BEING RENDERED ######

$templater = vB_Template::create('series');
$templater->register_page_templates();
$templater->register('pagetitle', $pagetitle);
$templater->register('searchAlpha', $searchAlpha);
$templater->register('results', $result);
print_output($templater->render());

?>
