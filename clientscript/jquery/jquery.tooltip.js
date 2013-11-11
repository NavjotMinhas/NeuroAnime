/*
 * jQuery Tooltip plugin 1.3
 *
 * http://bassistance.de/jquery-plugins/jquery-plugin-tooltip/
 * http://docs.jquery.com/Plugins/Tooltip
 *
 * Copyright (c) 2006 - 2008 Jörn Zaefferer
 *
 * $Id: jquery.tooltip.js 5741 2008-06-21 15:22:16Z joern.zaefferer $
 *
 * Dual licensed under the MIT and GPL licenses:
 *   http://www.opensource.org/licenses/mit-license.php
 *   http://www.gnu.org/licenses/gpl.html
 */

;(function($) {

		// the tooltip element
	var helper = {},
		// the current tooltipped element
		current,
		// the title of the current element, used for restoring
		title,
		// timeout id for delayed tooltips
		tID,
		// IE 5.5 or 6
		IE = $.browser.msie && /MSIE\s(5\.5|6\.)/.test(navigator.userAgent),
		// flag for mouse tracking
		track = false;

	$.tooltip = {
		blocked: false,
		defaults: {
			delay: 200,
			fade: false,
			showURL: true,
			extraClass: "",
			top: 15,
			left: 15,
			id: "tooltip"
		},
		block: function() {
			$.tooltip.blocked = !$.tooltip.blocked;
		}
	};

	$.fn.extend({
		tooltip: function(settings) {
			settings = $.extend({}, $.tooltip.defaults, settings);
			createHelper(settings);
			return this.each(function() {
					$.data(this, "tooltip", settings);
					this.tOpacity = helper.parent.css("opacity");
					// copy tooltip into its own expando and remove the title
					this.tooltipText = this.title;
					$(this).removeAttr("title");
					// also remove alt attribute to prevent default tooltip in IE
					this.alt = "";
				})
				.mouseover(save)
				.mouseout(hide)
				.click(hide);
		},
// change
		tooltipOff : function() {
			helper.parent.hide();
			$(document.body).unbind('mousemove', update);
			return this.each(function() {
					$(this).attr("title",this.tooltipText);
				})
				.unbind('mouseover',save)
				.unbind('mouseout',hide)
				.unbind('click',hide);
		},
// end
		fixPNG: IE ? function() {
			return this.each(function () {
				var image = $(this).css('backgroundImage');
				if (image.match(/^url\(["']?(.*\.png)["']?\)$/i)) {
					image = RegExp.$1;
					$(this).css({
						'backgroundImage': 'none',
						'filter': "progid:DXImageTransform.Microsoft.AlphaImageLoader(enabled=true, sizingMethod=crop, src='" + image + "')"
					}).each(function () {
						var position = $(this).css('position');
						if (position != 'absolute' && position != 'relative')
							$(this).css('position', 'relative');
					});
				}
			});
		} : function() { return this; },
		unfixPNG: IE ? function() {
			return this.each(function () {
				$(this).css({'filter': '', backgroundImage: ''});
			});
		} : function() { return this; },
		hideWhenEmpty: function() {
			return this.each(function() {
				$(this)[ $(this).html() ? "show" : "hide" ]();
			});
		},
		url: function() {
			return this.attr('href') || this.attr('src');
		}
	});

	function createHelper(settings) {
		// there can be only one tooltip helper
		if( helper.parent )
			return;
		// create the helper, h3 for title, div for url
		helper.parent = $('<div id="' + settings.id + '"><h3></h3><div class="body"></div><div class="url"></div></div>')
			// add to document
			.appendTo(document.body)
			// hide it at first
			.hide();

		// apply bgiframe if available
		if ( $.fn.bgiframe )
			helper.parent.bgiframe();

		// save references to title and url elements
		helper.title = $('h3', helper.parent);
		helper.body = $('div.body', helper.parent);
		helper.url = $('div.url', helper.parent);
	}

	function settings(element) {
		return $.data(element, "tooltip");
	}

	// main event handler to start showing tooltips
	function handle(event) {
		// show helper, either with timeout or on instant
		if( settings(this).delay )
			tID = setTimeout(show, settings(this).delay);
		else
			show();

		// if selected, update the helper position when the mouse moves
		track = !!settings(this).track;
		$(document.body).bind('mousemove', update);

		// update at least once
		update(event);
	}

	// save elements title before the tooltip is displayed
	function save() {
		// if this is the current source, or it has no title (occurs with click event), stop
		if ( $.tooltip.blocked || this == current || (!this.tooltipText && !settings(this).bodyHandler) )
			return;

		// save current
		current = this;
		title = this.tooltipText;

		if ( settings(this).bodyHandler ) {
			helper.title.hide();
			var bodyContent = settings(this).bodyHandler.call(this);
			if (bodyContent.nodeType || bodyContent.jquery) {
				helper.body.empty().append(bodyContent)
			} else {
				helper.body.html( bodyContent );
			}
			helper.body.show();
		} else if ( settings(this).showBody ) {
			var parts = title.split(settings(this).showBody);
			helper.title.html(parts.shift()).show();
			helper.body.empty();
			for(var i = 0, part; (part = parts[i]); i++) {
				if(i > 0)
					helper.body.append("<br/>");
				helper.body.append(part);
			}
			helper.body.hideWhenEmpty();
		} else {
			helper.title.html(title).show();
			helper.body.hide();
		}

		// if element has href or src, add and show it, otherwise hide it
		if( settings(this).showURL && $(this).url() )
			helper.url.html( $(this).url().replace('http://', '') ).show();
		else
			helper.url.hide();

		// add an optional class for this tip
		helper.parent.addClass(settings(this).extraClass);

		// fix PNG background for IE
		if (settings(this).fixPNG )
			helper.parent.fixPNG();

		handle.apply(this, arguments);
	}

	// delete timeout and show helper
	function show() {
		tID = null;
		if ((!IE || !$.fn.bgiframe) && settings(current).fade) {
			if (helper.parent.is(":animated"))
				helper.parent.stop().show().fadeTo(settings(current).fade, current.tOpacity);
			else
				helper.parent.is(':visible') ? helper.parent.fadeTo(settings(current).fade, current.tOpacity) : helper.parent.fadeIn(settings(current).fade);
		} else {
			helper.parent.show();
		}
		update();
	}

	/**
	 * callback for mousemove
	 * updates the helper position
	 * removes itself when no current element
	 */
	function update(event)	{
		if($.tooltip.blocked)
			return;

		if (event && event.target.tagName == "OPTION") {
			return;
		}

		// stop updating when tracking is disabled and the tooltip is visible
		if ( !track && helper.parent.is(":visible")) {
			$(document.body).unbind('mousemove', update)
		}

		// if no current element is available, remove this listener
		if( current == null ) {
			$(document.body).unbind('mousemove', update);
			return;
		}

		// remove position helper classes
		helper.parent.removeClass("viewport-right").removeClass("viewport-bottom");

		var
			h = helper.parent[0],
			viewport_region = getClientRegion();

		var left = helper.parent[0].offsetLeft;
		var top = helper.parent[0].offsetTop;

		if (event)
		{
			// position the helper 15 pixel to bottom right, starting from mouse position

			// diff is for ie7 RTL
			var diff = document.body.scrollWidth - document.body.clientWidth;
			if (document.documentElement.dir  == "rtl" && diff > 0)
			{
				left = event.pageX - diff - diff +  settings(current).left
			}
			else
			{
				left = event.pageX + settings(current).left;
			}
			top = event.pageY + settings(current).top;
			var right = document.documentElement.dir  == "rtl" ? 'auto' : '';
			helper.parent.css({
				left: left,
				right: right,
				top: top + "px"
			});
		}

		var menu_width = parseInt($('#tooltip').css('padding-left'), 10) + parseInt($('#tooltip').css('padding-right'), 10) + parseInt($('#tooltip').css('width'), 10)
		var menu_region = {
			left: left, //h.offsetLeft,
			top: h.offsetTop,
			right: left + menu_width, //h.offsetWidth + h.offsetLeft,
			width: menu_width, //h.offsetWidth,
			bottom: h.offsetTop + h.offsetHeight,
			height: h.offsetHeight
		};

		/*
		var foo = '';
		foo = "mRight:" + menu_region["right"] + "\r\nvRight: " + viewport_region["right"] + "\r\nmLeft: " + menu_region["left"] + "\r\nvLeft: " + viewport_region["left"] + "\r\nmWidth: " + menu_region["width"];
		if (event)
		{
			foo += "\r\nleft: " + left + "\r\nscrollWidth: " + document.body.scrollWidth + "\r\nclientWidth: " + document.body.clientWidth + "\r\nscrollLeft: " + $(window).scrollLeft() + "\r\nevent.pageX: " + event.pageX + "\r\nevent.pageY: " + event.pageY;
		}
		else
		{
			foo += "\r\nleft: " + left + "\r\nscrollWidth: " + document.body.scrollWidth + "\r\nclientWidth: " + document.body.clientWidth + "\r\nscrollLeft: " + $(window).scrollLeft();
		}
		alert(foo);
		*/

		// horizontal handler
		if (menu_region["width"] > 0 && menu_region["right"] > viewport_region["right"])
		{
			console.log("Set RTL");
			menu_region["left"] = viewport_region["right"] - menu_region["width"];
			helper.parent.css({
				left: menu_region["left"] + "px"
			});
		}

		if (menu_region["width"] > 0 && menu_region["left"] < viewport_region["left"])
		{
			console.log("Set LTR");
			menu_region["left"] = viewport_region["left"];
			helper.parent.css({
				left: menu_region["left"] + "px"
			});
		}

		//alert("mBottom:" + menu_region["bottom"] + "\r\nvBottom:" + viewport_region["bottom"] + "\r\nmHeight:" + menu_region["height"]);

		// vertical handler
		if (menu_region["height"] > 0 && menu_region["bottom"] > viewport_region["bottom"])
		{
			menu_region["top"] = top - menu_region["height"] - settings(current).top;

			helper.parent.css({
				top: menu_region["top"] + "px"
			});
		}

		if (menu_region["height"] > 0 && menu_region["top"] < viewport_region["top"])
		{
			menu_region["top"] = viewport_region["bottom"] + menu_region["height"];

			helper.parent.css({
				top: menu_region["top"] + "px"
			});
		}

		/*
		var r = getClientRegion();
		var c = YAHOO.util.Dom.getClientRegion();
  	var foo = "top:" + r.top + " y:" + r.y + " right:" + r.right + " bottom:" + r.bottom + " left:" + r.left + " x:" + r.x + " width:" + r.width + " height:" + r.height;
  	foo += "\r\ntop:" + c.top + " y:" + c.y + " right:" + c.right + " bottom:" + c.bottom + " left:" + c.left + " x:" + c.x + " width:" + c.width + " height:" + c.height;
  	alert(foo);
  	*/
	}

	function region(C,D,A,B)
	{
		return {
			top: C,
			y:C,
			right:D,
			bottom:A,
			left:B,
			x:B,
			width: D - B,
			height: A - C
		}
	}

	function getClientRegion()
	{
		var
			x = $(window).scrollTop(),
			Y = $(window).scrollLeft(),
			y = $(window).width() + Y,
			G = $(window).height() + x;

			var diff = document.body.scrollWidth - document.body.clientWidth;
			if (document.documentElement.dir  == "rtl" && diff > 0)
			{
				y = y - (diff * 2);
				Y = Y - (diff * 2);
			}

			return region(x,y,G,Y);
	}

	function viewport() {
		return {
			x: $(window).scrollLeft(),
			y: $(window).scrollTop(),
			cx: $(window).width(),
			cy: $(window).height()
		};
	}

	// hide helper and restore added classes and the title
	function hide(event) {
		if($.tooltip.blocked)
			return;
		// clear timeout if possible
		if(tID)
			clearTimeout(tID);
		// no more current element
		current = null;

		var tsettings = settings(this);
		function complete() {
			helper.parent.removeClass( tsettings.extraClass ).hide().css("opacity", "");
		}
		if ((!IE || !$.fn.bgiframe) && tsettings.fade) {
			if (helper.parent.is(':animated'))
				helper.parent.stop().fadeTo(tsettings.fade, 0, complete);
			else
				helper.parent.stop().fadeOut(tsettings.fade, complete);
		} else
			complete();

		if( settings(this).fixPNG )
			helper.parent.unfixPNG();
	}

})(jQuery);
