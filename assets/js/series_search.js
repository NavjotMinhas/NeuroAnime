var url='dynamicSearch.php';
var searchURL;

/*Filter Parameters*/
var searchVal;
var genre=0;
var isComplete=0;

/*sort paramters*/
var sort=1;

function constructURL() {
	searchURL=url.concat('?','searchVal=',searchVal,'&','genres=',genre,'&','isComplete=',isComplete,'&','sort=',sort);
}

function setIsComplete() {
	var element=document.getElementById('isComplete');
	if(element.checked) {
		isComplete=1;
	} else {
		isComplete=0;
	}
}

function setGenre() {
	var elements=document.getElementsByTagName('input');
	var tempGenre='';
	var i;
	for(i=0;i<elements.length;i++) {
		if(elements[i].getAttribute('class')== 'genre') {
			if(elements[i].checked==true) {
				if(tempGenre=='') {
					tempGenre=elements[i].getAttribute('name');
				} else {
					tempGenre+=','+elements[i].getAttribute('name');
				}
			}
		}
	}
	genre=tempGenre;
	search();
}

function setSort() {
	if(sort) {
		document.getElementById('order').setAttribute('value','DESC');
		sort=0;
	} else {
		document.getElementById('order').setAttribute('value','ASC');
		sort=1;
	}
	search();
}

function search() {
	if(searchVal) {
		constructURL();
		$.get(searchURL, function(returnData) {
			if (!returnData) {

				$('#results').html('<p style="padding:5px;">Search term entered does not return any data.</p>');
			} else {
				$('#results').html(returnData);
			}
		});
	}
}

$(document).ready( function() {

	/*installs a listener for the textbox and when to fire the callback function*/
	$('#searchData').keyup( function() {

		searchVal = $(this).val();

		/*
		 * Since this is dynamic programming we need to differiate the difference between 0, false and null
		 */
		if(searchVal !== ''&& searchVal.length>2) {
			search();

		}

	});
});