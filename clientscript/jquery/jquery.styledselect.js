/*
 * jQuery.styledSelect - <select> replacement plugin
 *
 * Copyright (c) 2009 Petr Stanicek (pixy@pixy.cz)
 * version 1.1, January 12, 2009
 * 
 *
 *	Change log:
 *	1.1 - Firefox 2 and older disabled due to wrong inline-block support.
 *
 
- usage: $('#anyselect').styledSelect(options);
- options are optional 

CSS:	.select-replace: border, padding, background, font style
		.select-replace-cover: additional background
		select (the original element): font-size, width

Note:	Try to keep same size of original select and the replacing box - primary by adjusting
		font-sizes and padding, you'd better not to set height/width of those boxes.

Tip:	Use options {opacity:0.1} (or similar low value) to reveal the original select to fit the sizes
		while debugging. Don't forget to set it back to zero when you done.

Note:	If the original select is hidden while caling this function, you must trigger its resize handler
		after you show it first time, e.g.: $('#myselect').trigger('resize')

*/


$.fn.styledSelect = function(options) {
	var isFF2 = $.browser.mozilla && $.browser.version.indexOf('1.8.')==0;
	var prefs = {
		coverClass : 'select-replace-cover',
		innerClass : 'select-replace',
		adjustPosition : { top:0, left:0 },
		selectOpacity : 0
		}
	if (options) $.extend(prefs,options);
	return this.each( function() {
		if (isFF2) return false;
		var selElm = $(this);
		selElm.wrap('<span><'+'/span>');
		selElm.after('<span><'+'/span>');
		var selReplace = selElm.next();
		var selCover = selElm.parent();
		selElm.css({
			'opacity':prefs.selectOpacity,
			'visibility':'visible',
			'position':'absolute',
			'top':0,
			'left':0,
			'display':'inline',
			'z-index':1
			});
		selCover.addClass(prefs.coverClass).css({
			'display':'inline-block',
			'position':'relative',
			'top':prefs.adjustPosition.top,
			'left':prefs.adjustPosition.left,
			'z-index':0,
			'vertical-align':'middle',
			'text-align':'left'
			});
		selReplace.addClass(prefs.innerClass).css({
			'display':'block',
			'white-space':'nowrap'
			});

		selElm.bind('change',function() {
			$(this).next().text(this.options[this.selectedIndex].text);
			}).bind('resize',function() {
			$(this).parent().width( $(this).width()+'px' );
			});
		selElm.trigger('change').trigger('resize');
		});
	}