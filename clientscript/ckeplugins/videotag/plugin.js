(function()
{
	CKEDITOR.plugins.add('videotag',
	{
		init : function(editor)
		{
			var pluginName = 'videotag';
			CKEDITOR.dialog.add(pluginName, editor.config.vbPluginPath + 'videotag/dialogs/videotag.js?v=' + SIMPLEVERSION);
	    editor.addCommand(pluginName, new CKEDITOR.dialogCommand(pluginName));
	    editor.ui.addButton('Video',
      {
          label: editor.config.vbulletin.phrase.insert_video,
          command: pluginName
      });
		}
	});
	
})();