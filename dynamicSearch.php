<?php
require_once ('modules.php');
$searchVal = modules::sanitize($_GET['searchVal']);
$isComplete = modules::sanitize($_GET['isComplete']);
$sort = modules::sanitize($_GET['sort']);
$genres = modules::sanitize($_GET['genres']);

$likeClause;
if($genres) {
	$array = explode(',', $genres);
	foreach($array as $genreType) {
		if(!$likeClause) {
			$likeClause = 'Genre LIKE "%' . $genreType . '%" ';
		} else {
			$likeClause .= ' OR Genre LIKE "%' . $genreType . '%" ';
		}
	}
}
$orderByClause;
if(!$sort) {
	$orderByClause = 'ORDER BY title ASC';
} else {
	$orderByClause = 'ORDER BY title DESC';
}
if($likeClause) {
	$results = modules::getQueryResults('SELECT * FROM anime where Title like "%' . $searchVal . '%" AND ' . $likeClause . $orderByClause);

} else {
	$results = modules::getQueryResults('SELECT * FROM anime where Title like "%s" ' . $orderByClause,'%'.$searchVal.'%');

}

foreach($results as $row) {
	echo '<div class="result">';
	echo '<img class="clip" src="images/thumbnails/' . $row['ImgPicture'] . '" />';
	echo '<div class="description">';
	echo '<a href="anime.php?t='.$row['Title'].'"><h3>'.$row['Title'].'</h3></a>';
	/*echo '<h4>Genre: ';
	foreach(explode(',', $row['Genre']) as $g) {
		echo '<a class="genreLink" href="series.php?g=' . $g . '">' . $g . '</a> , ';
	}
	echo '</h4>';*/
	for($i = 0; $i < $row['Rating']; $i++) {
		echo '<img src="images/stars-01.png" />';
	}
	echo '<p id="dec">'.substr($row['Summary'], 0, 512).'...'.'</p>';
	echo '</div></div>';
}
modules::closeConnection();
?>