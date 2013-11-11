$(document).ready( function() {

			//When page loads...
			$(".tab_content").hide(); //Hide all content
			$("ul.tabs li:first").addClass("active").show(); //Activate first tab
			$(".tab_content:first").show(); //Show first tab content

			//On Click Event
			$("ul.tabs li").click( function() {

				$("ul.tabs li").removeClass("active"); //Remove any "active" class
				$(this).addClass("active"); //Add "active" class to selected tab
				$(".tab_content").hide(); //Hide all tab content

				var activeTab = $(this).find("a").attr("href"); //Find the href attribute value to identify the active tab + content
				$(activeTab).fadeIn(); //Fade in the active ID content
				return false;
			});
			$('.videoLink').click( function (e) {
				var videoLink=$(this).attr('href');
				$('#player').attr('src',videoLink);
				e.preventDefault();
				$('.highlighted').removeClass('highlighted');
				$(this).addClass('highlighted');

				$("ul.tabs li").removeClass("active"); //Remove any "active" class
				$('.left').addClass("active"); //Add "active" class to selected tab
				$(".tab_content").hide(); //Hide all tab content

				var activeTab = '#tab1';
				$(activeTab).fadeIn(); //Fade in the active ID content
				return false;
			});
		});