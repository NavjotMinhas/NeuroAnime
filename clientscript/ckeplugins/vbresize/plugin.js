/*
Copyright (c) 2003-2010, CKSource - Frederico Knabben. All rights reserved.
For licensing, see LICENSE.html or http://ckeditor.com/license
*/

CKEDITOR.plugins.add( 'vbresize',
{
	init : function(editor)
	{
		if (editor.config.resize_enabled !== false)
		{
			editor.on('resize', function() {

				var contents = CKEDITOR.document.getById('cke_contents_' + editor.name);
				var height = contents.getStyle("height");
				if (height && editor.config.vbtype)
				{
					expires = new Date();
					expires.setTime(expires.getTime() + (1000 * 86400 * 365));
					set_subcookie('editor_height', editor.config.vbtype, height, expires);
				}
			});
		}
	}
});
