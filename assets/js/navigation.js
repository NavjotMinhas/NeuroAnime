window.onload = function() {
	var url=document.location.href;
	var navBar;
	if(contains(url,"forum.php")) {
		navBar=document.getElementById("ForumsNavBar");
	} else if(contains(url,"series.php")) {
		navBar=document.getElementById("SeriesNavBar");
	} else if(contains(url,"anime.php")) {
		navBar=document.getElementById("SeriesNavBar");
	}  else if(contains(url,"episode.php")) {
		navBar=document.getElementById("SeriesNavBar");
	}else {
		navBar=document.getElementById("HomeNavBar");
	}

	navBar.setAttribute("class", "selectedNavigationButton");
}
function contains(haystack, needle) {
	if(haystack.indexOf(needle)!=-1) {
		return true;
	} else {
		return false;
	}
}