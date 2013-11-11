(function()
{
	CKEDITOR.plugins.add('vbimage',
	{
		init : function(editor)
		{
			// since we can't completely disable the default image plugin, we need to prevent its doubleclick event
			editor.on('doubleclick', function( evt )
			{
				evt.cancel();
				evt.stop();
				return false;
			}, null, null, 1);
		
			var pluginName = 'vbimage';
			CKEDITOR.dialog.add(pluginName, editor.config.vbPluginPath + 'vbimage/dialogs/image.js?v=' + SIMPLEVERSION);
			editor.addCommand(pluginName, new CKEDITOR.dialogCommand(pluginName));
			editor.ui.addButton('Image',
			{
				label: editor.lang.common.image,
				command: pluginName
			});
		}
	});
	
})();