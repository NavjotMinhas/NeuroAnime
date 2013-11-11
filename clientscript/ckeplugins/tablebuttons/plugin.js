(function()
{
	CKEDITOR.plugins.add('tablebuttons',
	{
		requires: ['tabletools'],
		
		init: function(editor)
		{
			var lang = editor.lang.table;

			editor.ui.addButton('InsertRowBefore',
			{
				label: lang.row.insertBefore,
				command: 'vbRowInsertBefore'
			});

			editor.addCommand('vbRowInsertBefore',
				{
					exec : function(editor)
					{
						var selection = editor.getSelection(),
							startElement = selection && selection.getStartElement(),
							table = startElement && startElement.getAscendant( 'table', 1 );

						if (!table)
						{
							return;
						}
						
						editor.getCommand('rowInsertBefore').exec();
					}
			});
			
			editor.ui.addButton('InsertRowAfter',
			{
				label: lang.row.insertAfter,
				command: 'vbRowInsertAfter'
			});

			editor.addCommand('vbRowInsertAfter',
				{
					exec : function(editor)
					{
						var selection = editor.getSelection(),
							startElement = selection && selection.getStartElement(),
							table = startElement && startElement.getAscendant( 'table', 1 );

						if (!table)
						{
							return;
						}

						editor.getCommand('rowInsertAfter').exec();
					}
			});

			editor.ui.addButton('DeleteRow',
			{
				label: lang.row.deleteRow,
				command: 'vbRowDelete'
			});

			editor.addCommand('vbRowDelete',
				{
					exec : function(editor)
					{
						var selection = editor.getSelection(),
							startElement = selection && selection.getStartElement(),
							table = startElement && startElement.getAscendant( 'table', 1 );

						if (!table)
						{
							return;
						}
						
						editor.getCommand('rowDelete').exec();
					}
			});

			editor.ui.addButton('InsertColumnBefore',
			{
				label: lang.column.insertBefore,
				command: 'vbColumnInsertBefore'
			});

			editor.addCommand('vbColumnInsertBefore',
				{
					exec : function(editor)
					{
						var selection = editor.getSelection(),
							startElement = selection && selection.getStartElement(),
							table = startElement && startElement.getAscendant( 'table', 1 );

						if (!table)
						{
							return;
						}
						
						editor.getCommand('columnInsertBefore').exec();
					}
			});

			editor.ui.addButton('InsertColumnAfter',
			{
				label: lang.column.insertAfter,
				command: 'vbColumnInsertAfter'
			});

			editor.addCommand('vbColumnInsertAfter',
				{
					exec : function(editor)
					{
						var selection = editor.getSelection(),
							startElement = selection && selection.getStartElement(),
							table = startElement && startElement.getAscendant( 'table', 1 );

						if (!table)
						{
							return;
						}
						
						editor.getCommand('columnInsertAfter').exec();
					}
			});

			editor.ui.addButton('DeleteColumn',
			{
				label: lang.column.deleteColumn,
				command: 'vbColumnDelete'
			});

			editor.addCommand('vbColumnDelete',
				{
					exec : function(editor)
					{
						var selection = editor.getSelection(),
							startElement = selection && selection.getStartElement(),
							table = startElement && startElement.getAscendant( 'table', 1 );

						if (!table)
						{
							return;
						}
						
						editor.getCommand('columnDelete').exec();
					}
			});

			editor.addCommand('vbTableProperties',
				{
					exec : function(editor)
					{
						if (CKEDITOR.env.ie)
						{
							editor.selectedTable = null;
							var selection = editor.getSelection(),
								selectedTable = null;

							if (selection)
							{
								var ranges = selection.getRanges();

								if ((selectedTable = selection.getSelectedElement()))
								{
									editor.selectedTable = selectedTable.getAscendant('table', true);
								}
								else if (ranges && ranges.length > 0)
								{
									// Webkit could report the following range on cell selection (#4948):
									// <table><tr><td>[&nbsp;</td></tr></table>]
									if ( CKEDITOR.env.webkit )
									{
										ranges[ 0 ].shrink( CKEDITOR.NODE_ELEMENT );
									}

									var rangeRoot = ranges[0].getCommonAncestor(true);
									editor.selectedTable = rangeRoot.getAscendant('table', true);
								}
							}
						}
						editor.getCommand('tableProperties').exec();
					}
			});

			editor.ui.addButton('TableProperties',
			{
				label: lang.menu,
				command: 'vbTableProperties'
			});

			editor.ui.addButton('DeleteTable',
			{
				label: lang.deleteTable,
				command: 'tableDelete'
			});
		}
	});
})();