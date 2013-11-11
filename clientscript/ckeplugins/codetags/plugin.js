CKEDITOR.dom.range.prototype.wrapSelectionWith = function(start, end)
{
	var startNode, endNode;
	var clone;

	// make sure we are including bold/italic/underline/etc tags
	this.enlarge(CKEDITOR.ENLARGE_ELEMENT);

	startNode = CKEDITOR.dom.element.createFromHtml(start, this.document);
	endNode = CKEDITOR.dom.element.createFromHtml(end, this.document);

	// insert closing tag
	clone = this.clone();
	clone.collapse();
	clone.insertNode(endNode);
	if (this.collapsed)
	{
		this.moveToPosition(endNode, CKEDITOR.POSITION_BEFORE_START);
	}

	// insert start tag
	clone = this.clone();
	clone.collapse(true);
	clone.insertNode(startNode);
	if (this.collapsed)
	{
		this.moveToPosition( startNode, CKEDITOR.POSITION_AFTER_END );
	}
	
	// reselect ranges
	if (!this.collapsed)
	{
		this.setEndBefore(endNode);
		this.setStartAfter(startNode);
	}
}

CKEDITOR.editor.prototype.getSelectedText = function()
{
	if (CKEDITOR.env.ie)
	{
		return this.getSelection().document.$.selection.createRange().text;
	}
	else
	{
		return this.getSelection().getNative();
	}	
}

CKEDITOR.editor.prototype.wrapSelectionWith = function(start, end)
{
	if (this.mode == "wysiwyg")
	{
		if (CKEDITOR.env.ie)
		{
			this.focus();
		}
		var ranges = this.getSelection().getRanges();
		for (var i = 0; i < ranges.length; i++)
		{
			ranges[i].wrapSelectionWith(start, end);
		}
		this.getSelection().selectRanges(ranges);
	}
	else if (this.mode == "enhancedsource")
	{
		this.esWrapAroundSelection(start, end);
	}
}

CKEDITOR.plugins.add( 'codetags',
{
	requires : [ 'button' ],
	
	init : function( editor )
	{
		var phrase = editor.config.vbulletin.phrase;
		
		// All buttons use the same code to register. So, to avoid
		// duplications, let's use this tool function.
		var addButtonCommand = function(buttonName, buttonLabel, tagName, option, image)
		{
			editor.addCommand(buttonName, 
			{
				exec: function(editor)
				{
					if (option == '1')
					{
						var optionString = prompt(editor.lang.vbulletin.enter_tag_option.replace(/\%1\$s/,'[' + tagName + ']'));
						if (typeof optionString == 'string' && optionString.length > 0)
						{
							editor.wrapSelectionWith('[' + tagName + '=' + optionString + ']', '[/' + tagName + ']');
						}
						else
						{
							editor.wrapSelectionWith('[' + tagName + ']', '[/' + tagName + ']');
						}
					}
					else
					{
						editor.wrapSelectionWith('[' + tagName + ']', '[/' + tagName + ']');
					}
				}
			});
			
			var buttonDefinition = {label : buttonLabel, command : buttonName};
			if (image)
			{
				buttonDefinition.icon = image;
			}

			editor.ui.addButton(buttonName, buttonDefinition);
		};
		
		var previewBreakCommand = function(buttonName, buttonLabel, tagName)
		{
			editor.addCommand(buttonName, 
			{
				exec: function(editor){					
					if (editor.mode == 'wysiwyg')
					{
						var hr_tags = editor.document.getElementsByTag('hr');
						for (var i = 0; i < hr_tags.count(); i++)
						{
							var tag = hr_tags.getItem(i);
							if (tag.hasClass('previewbreak'))
							{
								alert(phrase.this_message_already_contains_a_preview_break);
								return;
							}
						}
					}
					editor.insertHtml('[' + tagName + '][/' + tagName + ']');
				}
			});

			editor.ui.addButton(buttonName,
			{
				label : buttonLabel,
				command : buttonName
			});
		};

		var pageBreakCommand = function(buttonName, buttonLabel, tagName)
		{
			editor.addCommand(buttonName,
			{
				exec: function(editor)
				{
					editor.insertHtml('[' + tagName + ']' + editor.getSelectedText() + '[/' + tagName + ']');
				}
			});

			editor.ui.addButton(buttonName,
			{
				label : buttonLabel,
				command : buttonName
			});
		};

		var iespellCommand = function(buttonName, buttonLabel)
		{
			editor.addCommand(buttonName,
			{
				exec: function(editor)
				{
					try
					{
						// attempt to instantiate ieSpell
						eval("new A" + "ctiv" + "eX" + "Ob" + "ject('ieSpell." + "ieSpellExt" + "ension').CheckD" + "ocumentNode(editor.document.$.body);");
					}
					catch(e)
					{
						// ask if user wants to download ieSpell
						if (e.number == -2146827859 && confirm(editor.lang.vbulletin.iespell_not_installed))
						{
							// ooh they do...
							window.open('http://www.iespell.com/download.ph' + 'p');
						}
					}
				}
			});

			editor.ui.addButton(buttonName,
			{
				label   : buttonLabel,
				command : buttonName
			});
		}

		var emailCommand = function(buttonName, buttonLabel)
		{
			editor.addCommand(buttonName,
			{
				exec: function(editor)
				{ }
			});

			editor.ui.addButton(buttonName,
			{
				label   : buttonLabel,
				command : buttonName
			});
		}

		var phrase = editor.config.vbulletin.phrase;

		addButtonCommand('Quote', phrase.wrap_quote_tags, 'QUOTE');
		addButtonCommand('Code', phrase.wrap_code_tags, 'CODE');
		addButtonCommand('Html', phrase.wrap_html_tags, 'HTML');
		addButtonCommand('Php', phrase.wrap_php_tags, 'PHP');
		previewBreakCommand('PreviewBreak', phrase.insert_preview_break, 'PRBREAK');
		pageBreakCommand('PageBreak', phrase.insert_page_break, 'PAGE');
		iespellCommand('iespell', editor.lang.vbulletin.spell_check);
		emailCommand('Email', editor.lang.vbulletin.email);

		for (var i in editor.config.vbulletin.bbcodecache)
		{
			var bbcode = editor.config.vbulletin.bbcodecache[i];
			var resource = bbcode.buttonimage;

			// By default addButtonCommand will end up appending ckeditor's basepath to any bbcode images that are relative
			// so we are preemptive here by adding baseHref
			// 
			// If this is not a full or absolute path.
			if (resource.indexOf(':/') == -1 && resource.indexOf( '/' ) !== 0 )
			{
				resource = editor.config.baseHref + resource;
			}
			
			addButtonCommand(bbcode.title, phrase.wrap_x_tags.replace(/\%1\$s/, bbcode.bbcodetag), bbcode.bbcodetag.toUpperCase(), bbcode.twoparams, resource);
		}
	}
});
