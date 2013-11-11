(function()
{
	CKEDITOR.plugins.add('smiliebox',
	{
		init : function(editor)
		{
			var smiliebox = YAHOO.util.Dom.getElementsByClassName('smiliebox');
			if (smiliebox.length > 0)
			{
				YAHOO.util.Dom.getElementsBy(function(element){
					return true;
				}, 'img', smiliebox[0], function(element){
					YAHOO.util.Event.addListener(element, "click", function(e){
						editor.insertHtml(YAHOO.util.Dom.getAttribute(element, 'alt'));
					});
				});
			}
		}
	});
})();