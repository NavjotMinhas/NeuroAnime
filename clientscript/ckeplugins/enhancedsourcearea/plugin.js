CKEDITOR.editor.prototype.esInsertText = function(html)
{
	if (this.mode == 'enhancedsource')
	{
		this.focus();
		this.fire( 'saveSnapshot' );

		if (this.savedselection)
		{
			this.savedselection.select();
			delete this.savedselection;
		}

		this.esInsertAtCursor(html);

		CKEDITOR.tools.setTimeout(function()
		{
			this.fire( 'saveSnapshot' );
		}, 0, this);
	}
}

CKEDITOR.editor.prototype.esInsertAtCursor = function(text)
{
	var textarea = this.textarea.$;
	var scrollPos = textarea.scrollTop;
	// ie
	if (CKEDITOR.env.ie)
	{
		textarea.focus();
		sel = document.selection.createRange();
		sel.text = text;
		sel.select();
	}
	else if (textarea.selectionStart || textarea.selectionStart == '0')
	{
		var startPos = textarea.selectionStart;
		var endPos = textarea.selectionEnd;
		textarea.value = textarea.value.substring(0, startPos) + text + textarea.value.substring(endPos, textarea.value.length);
		if (startPos == endPos)
		{
			this.esSetSelectionRange(startPos + text.length, endPos + text.length);
		}
		else
		{
			this.esSetCaretToPos(startPos + text.length);
		}		
	}
	else
	{
		textarea.value += text;
	}
	textarea.scrollTop = scrollPos;
}

CKEDITOR.editor.prototype.esWrapAroundSelection = function(start, end)
{
	var textarea = this.textarea.$;
	if (this.savedselection)
	{
		this.savedselection.select();
	}
	var scrollPos = textarea.scrollTop;
	if (CKEDITOR.env.ie)
	{
		textarea.focus();
		sel = document.selection.createRange();		
		sel.text = start + sel.text + end;
		sel.moveStart('character', -end.length);
		sel.moveEnd('character', -end.length);
		sel.select();
	}
	else if (textarea.selectionStart || textarea.selectionStart == '0')
	{
		var startPos = textarea.selectionStart;
		var endPos = textarea.selectionEnd;
		textarea.value = textarea.value.substring(0, startPos) + start + textarea.value.substring(startPos, endPos) + end + textarea.value.substring(endPos, textarea.value.length);
		if (startPos == endPos)
		{
			this.esSetCaretToPos(startPos + start.length);
		}
		else
		{
			this.esSetSelectionRange(startPos + start.length, endPos + start.length);
		}		
	}
	textarea.scrollTop = scrollPos;
}

CKEDITOR.editor.prototype.esGetSelectionText = function()
{
	var textarea = this.textarea.$;
	if (CKEDITOR.env.ie)
	{
		textarea.focus();
		sel = document.selection.createRange();		
		return sel.text;
	}
	else if (textarea.selectionStart || textarea.selectionStart == '0')
	{
		var startPos = textarea.selectionStart;
		var endPos = textarea.selectionEnd;
		return textarea.value.substring(startPos, endPos);
	}
}

CKEDITOR.editor.prototype.esDeleteSelection = function()
{
	var textarea = this.textarea.$;
	var scrollPos = textarea.scrollTop;
	textarea.focus();
	if (CKEDITOR.env.ie)
	{
		var rng = document.selection.createRange();
		document.selection.clear();
	}
	else if (textarea.selectionStart || textarea.selectionStart == '0')
	{
		var startPos = textarea.selectionStart;
		var endPos = textarea.selectionEnd;
		textarea.value = textarea.value.substring(0, startPos) + textarea.value.substring(endPos, textarea.value.length);
		this.esSetCaretToPos(startPos);
	}
	textarea.scrollTop = scrollPos;
}

CKEDITOR.editor.prototype.esSetSelectionRange = function(selectionStart, selectionEnd)
{
	var textarea = this.textarea.$;
	if (textarea.setSelectionRange)
	{
		textarea.focus();
		textarea.setSelectionRange(selectionStart, selectionEnd);
	}
	else if (textarea.createTextRange)
	{
		var range = textarea.createTextRange();
		range.collapse(true);
		range.moveEnd('character', selectionEnd);
		range.moveStart('character', selectionStart);
		range.select();
	}
}

CKEDITOR.editor.prototype.esSetCaretToPos = function(pos)
{
  this.esSetSelectionRange(pos, pos);
}

function getButton(editor, type, label)
{
	for (var i = 0; i < editor.toolbox.toolbars.length; i++)
	{
		var toolbar = editor.toolbox.toolbars[i];
		for (var j = 0; j < toolbar.items.length; j++)
		{
			var item = toolbar.items[j];
			if ((type == 'button' && item.button && item.button.label == label) ||
				(type == 'combo' && item.combo && item.combo.label == label))
			{
				return item;
			}
		}
	}
}

function overrideButtonTitle(editor, oldphrase, newphrase)
{
	var sourceButton = getButton(editor, 'button', oldphrase);
	if (sourceButton)
	{
		var buttonElement = CKEDITOR.document.getById(sourceButton.id);
		buttonElement.setAttribute('title', newphrase);
	}
}

function toggleEmailButton(editor, title, state)
{
	var emailButton = getButton(editor, 'button', title);
	if (emailButton)
	{
		YAHOO.util.Dom.setStyle(emailButton.id, 'display', state);
	}
}

function setupSmileyOverride(editor)
{
	CKEDITOR.on('dialogDefinition', function(ev){
		var dialogName = ev.data.name;
		var dialogDefinition = ev.data.definition;
		
		if (dialogName == 'smiley')
		{
			//Replacing src for smilies with full_path (#VBIV-11970)
			dialogDefinition.getContents('tab1').elements[0].html = dialogDefinition.getContents('tab1').elements[0].html.replace(/src="\.\/([a-z]+:\/\/)/gi, 'src="$1');
			
			var onClick = dialogDefinition.getContents('tab1').elements[0].onClick;
			dialogDefinition.getContents('tab1').elements[0].onClick = CKEDITOR.tools.override(onClick, function(original)
			{
				return function(evt)
				{
					var target = evt.data.getTarget();
					var targetName = target.getName();
					if ( targetName == 'a' ){
						target = target.getChild(0);}
					else if ( targetName != 'img')
					{
						return;
					}
					var title = target.getAttribute('title');

					ev.editor.insertHtml(title);

					this._.dialog.hide();
					evt.data.preventDefault();
				}
			});
		}
	});
}

CKEDITOR.plugins.add( 'enhancedsourcearea',
{
	requires : [ 'editingblock', 'wysiwygarea' ],

	saveSelection : function(event, editor)
	{
	/*	if (event.type == "select")
		{*/
			editor.savedselection = document.selection.createRange();
		/*}
		else if (document.activeElement == editor.textarea.$)	// mouseout
		{
			editor.savedselection = document.selection.createRange();
		}	*/
	},

	init : function( editor )
	{
		var enhancedsourcearea = CKEDITOR.plugins.enhancedsourcearea,
			textarea,
			win = CKEDITOR.document.getWindow();

		// Create the source area <textarea>.
		editor.textarea = textarea = new CKEDITOR.dom.element( 'textarea' );

		if (CKEDITOR.env.ie)
		{
			YAHOO.util.Event.on(editor.textarea.$, "keyup", this.saveSelection, editor);
			YAHOO.util.Event.on(editor.textarea.$, "click", this.saveSelection, editor);
			YAHOO.util.Event.on(editor.textarea.$, "select", this.saveSelection, editor);
			/*YAHOO.util.Event.on(editor.textarea.$, "mouseout", this.saveSelection, editor);*/
		}

		editor.getSelection = CKEDITOR.tools.override(editor.getSelection, function(original)
		{
			return function()
			{
				if (this.mode != 'enhancedsourcearea')
				{
					return original.call(this);
				}
				else
				{
					return new CKEDITOR.dom.selection(document);
				}
			};
		});
		
		setupSmileyOverride(editor);

		editor.on('editingBlockReady', function()
		{
			var onResize,
				defaultCommands = {};

			// Change the default title of the source button to something else..
			/*
			var sourceButton = getButton(editor, 'button', editor.lang.source);
			if (sourceButton)
			{
				var buttonElement = CKEDITOR.document.getById(sourceButton.id);
				buttonElement.setAttribute('title', 'Hey You');
			}
			*/
	
		   overrideButtonTitle(editor, editor.lang.source, editor.lang.vbulletin.switch_editor_mode_source);
		   toggleEmailButton(editor, editor.lang.vbulletin.email, 'none');

			editor.addMode( 'enhancedsource',
			{
				load : function( holderElement, data )
				{
					toggleEmailButton(editor, editor.lang.vbulletin.email, '');
					overrideButtonTitle(editor, editor.lang.source, editor.lang.vbulletin.switch_editor_mode_wysiwyg);
					if (YAHOO.util.Dom.get(editor.editorid + '_mode'))
					{
						YAHOO.util.Dom.get(editor.editorid + '_mode').value = 0;
					}

					if ( CKEDITOR.env.ie && CKEDITOR.env.version < 8 )
						holderElement.setStyle( 'position', 'relative' );

					textarea.setAttributes(
						{
							dir : editor.config.contentsLangDirection,
							tabIndex : CKEDITOR.env.webkit ? -1 : editor.tabIndex,
							'role' : 'textbox',
							'aria-label' : editor.lang.editorTitle.replace( '%1', editor.name )
						});
					textarea.addClass( 'cke_source' );
					textarea.addClass( 'cke_enable_context_menu' );

					this.setupCommandOverrides();

					var styles =
					{
						// IE7 has overflow the <textarea> from wrapping table cell.
						width	: CKEDITOR.env.ie7Compat ?  '99%' : '100%',
						height	: '100%',
						'min-width' : '100%',
						'max-width' : '100%',
						resize	: 'none',
						outline	: 'none',
						'text-align' : editor.config.contentsLangDirection == 'ltr' ? 'left' : 'right'
					};

					// Having to make <textarea> fixed sized to conque the following bugs:
					// 1. The textarea height/width='100%' doesn't constraint to the 'td' in IE6/7.
					// 2. Unexpected vertical-scrolling behavior happens whenever focus is moving out of editor
					// if text content within it has overflowed. (#4762)
					if ( CKEDITOR.env.ie )
					{
						styles.width = holderElement.$.clientHeight + 'px';
						styles.height = holderElement.$.clientWidth + 'px';
						onResize = function()
						{
							// Holder rectange size is stretched by textarea,
							// so hide it just for a moment.
							textarea.hide();
							textarea.setStyle( 'height', holderElement.$.clientHeight + 'px' );
							textarea.setStyle( 'width', holderElement.$.clientWidth + 'px' );
							// When we have proper holder size, show textarea again.
							textarea.show();
						};

						editor.on( 'resize', onResize );
						win.on( 'resize', onResize );
						setTimeout( onResize, 0 );
					}
					else
					{
						// By some yet unknown reason, we must stop the
						// mousedown propagation for the textarea,
						// otherwise it's not possible to place the caret
						// inside of it (non IE).
						textarea.on( 'mousedown', function( evt )
							{
								evt.data.stopPropagation();
							} );
					}

					// Reset the holder element and append the
					// <textarea> to it.
					holderElement.setHtml( '' );
					holderElement.append( textarea );
					textarea.setStyles( styles );

					editor.fire( 'ariaWidget', textarea );

					textarea.on( 'blur', function()
						{
							editor.focusManager.blur();
						});

					textarea.on( 'focus', function()
						{
							editor.focusManager.focus();
						});

					// The editor data "may be dirty" after this point.
					editor.mayBeDirty = true;

					// Set the <textarea> value.
					this.loadData( data );

					var keystrokeHandler = editor.keystrokeHandler;
					if ( keystrokeHandler )
						keystrokeHandler.attach( textarea );

					setTimeout( function()
					{
						editor.mode = 'enhancedsource';
						editor.getCommand( 'enhancedsource' ).setState(
							editor.mode == 'enhancedsource' ?
								CKEDITOR.TRISTATE_ON :
								CKEDITOR.TRISTATE_OFF );
						editor.fire( 'mode' );
					},
					( CKEDITOR.env.gecko || CKEDITOR.env.webkit ) ? 100 : 0 );
				},

				loadData : function( data )
				{
					textarea.setValue( data );
					editor.fire( 'dataReady' );
				},

				loadSnapshotData : function ( data )
				{
					textarea.setValue(data);
				},

				getData : function()
				{
					return textarea.getValue();
				},

				getSnapshotData : function()
				{
					return textarea.getValue();
				},

				unload : function( holderElement )
				{
					overrideButtonTitle(editor, editor.lang.source, editor.lang.vbulletin.switch_editor_mode_source);
					if (YAHOO.util.Dom.get(editor.editorid + '_mode'))
					{
						YAHOO.util.Dom.get(editor.editorid + '_mode').value = 1;
					}
					toggleEmailButton(editor, editor.lang.vbulletin.email, 'none');
					this.restoreDefaultCommands();

					textarea.clearCustomData();
					textarea.remove();
					//editor.textarea = textarea = null;

					if ( onResize )
					{
						editor.removeListener( 'resize', onResize );
						win.removeListener( 'resize', onResize );
					}

					if ( CKEDITOR.env.ie && CKEDITOR.env.version < 8 )
						holderElement.removeStyle( 'position' );
				},

				focus : function()
				{
					textarea.focus();
				},

				setupCommandOverrides : function()
				{

					this.overrideCommands =
					{
						bold: function(editor)
						{
							if (vB_Editor[editor.editorid].get_button('bold'))
							{
								editor.esWrapAroundSelection('[B]', '[/B]');
							}
						},
						italic: function(editor)
						{
							if (vB_Editor[editor.editorid].get_button('italic'))
							{
								editor.esWrapAroundSelection('[I]', '[/I]');
							}
						},
						underline: function(editor)
						{
							if (vB_Editor[editor.editorid].get_button('underline'))
							{
								editor.esWrapAroundSelection('[U]', '[/U]');
							}
						},
						justifyleft: function(editor)
						{
							editor.esWrapAroundSelection('[LEFT]', '[/LEFT]');
						},
						justifycenter: function(editor)
						{
							editor.esWrapAroundSelection('[CENTER]', '[/CENTER]');
						},
						justifyright: function(editor)
						{
							editor.esWrapAroundSelection('[RIGHT]', '[/RIGHT]');
						},
						indent: function(editor)
						{
							editor.esWrapAroundSelection('[INDENT]', '[/INDENT]');
						},
						Quote: function(editor)
						{
							editor.esWrapAroundSelection('[QUOTE]', '[/QUOTE]');
						},
						Code: function(editor)
						{
							editor.esWrapAroundSelection('[CODE]', '[/CODE]');
						},
						Html: function(editor)
						{
							editor.esWrapAroundSelection('[HTML]', '[/HTML]');
						},
						Php: function(editor)
						{
							editor.esWrapAroundSelection('[PHP]', '[/PHP]');
						},
						subscript: function(editor)
						{
							editor.esWrapAroundSelection('[SUB]', '[/SUB]');
						},
						superscript: function(editor)
						{
							editor.esWrapAroundSelection('[SUP]', '[/SUP]');
						},
						horizontalrule: function(editor)
						{
							editor.esInsertAtCursor('[HR][/HR]');
						},
						PageBreak: function(editor)
						{
							editor.esInsertAtCursor('[PAGE][/PAGE]');
						},
						PreviewBreak: function(editor)
						{
							editor.esInsertAtCursor('[PRBREAK][/PRBREAK]');
						},
						vbimage: function(editor)
						{
							var original = defaultCommands['vbimage'];
							if (CKEDITOR.env.ie)
							{
								editor.savedselection = document.selection.createRange();
								original.exec(editor);
							}
							else
							{
								original.exec(editor);
							}
						},
						iespell: function(editor)
						{
							try
							{
								// attempt to instantiate ieSpell
								eval("new A" + "ctiv" + "eX" + "Ob" + "ject('ieSpell." + "ieSpellExt" + "ension').CheckD" + "ocumentNode(editor.textarea.$);");
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
						},
						videotag: function(editor)
						{
							var original = defaultCommands['videotag'];
							if (CKEDITOR.env.ie)
							{
								editor.savedselection = document.selection.createRange();
								original.exec(editor);
							}
							else
							{
								original.exec(editor);
							}
						},
						numberedlist: function(editor)
						{
							var selection = editor.esGetSelectionText();
							editor.esInsertAtCursor("[LIST=1]" + "\n");

							if (selection)
							{
								var lines = selection.split("\n");
								for (var i in lines)
								{
									editor.esInsertAtCursor("[*]" + lines[i] + "\n");
								}
							}
							else
							{
								do
								{
									var line = prompt(editor.config.vbulletin.phrase.enter_list_item, '');
									if (typeof line == 'string')
									{
										editor.esInsertAtCursor("[*]" + line + "\n");
									}
								} while (line);
							}
							editor.esInsertAtCursor("[/LIST]" + "\n");
						},
						bulletedlist: function(editor)
						{
							var selection = editor.esGetSelectionText();
							editor.esInsertAtCursor("[LIST]" + "\n");
							
							if (selection)
							{
								var lines = selection.split("\n");
								for (var i in lines)
								{
									editor.esInsertAtCursor("[*]" + lines[i] + "\n");
								}
							}
							else
							{
								do
								{
									var line = prompt(editor.config.vbulletin.phrase.enter_list_item, '');
									if (typeof line == 'string')
									{
										editor.esInsertAtCursor("[*]" + line + "\n");
									}
								} while (line);
							}
							editor.esInsertAtCursor("[/LIST]" + "\n");
						},
						link: function(editor)
						{
							var line = prompt(editor.lang.vbulletin.enter_link_url, 'http://');
							if (typeof line == 'string' && line.length > 0 && line != 'http://')
							{
								var selection = editor.esGetSelectionText();
								if (selection)
								{
									editor.esWrapAroundSelection('[URL="' + line + '"]', '[/URL]');
								}
								else
								{
									editor.esInsertAtCursor('[URL="' + line + '"]' + line + '[/URL]');
								}
							}
						},
						Email: function(editor)
						{
							var line = prompt(editor.lang.vbulletin.enter_the_email_address);
							if (typeof line == 'string' && line.length > 0)
							{
								var selection = editor.esGetSelectionText();
								if (selection)
								{
									editor.esWrapAroundSelection('[EMAIL="' + line + '"]', '[/EMAIL]');
								}
								else
								{
									editor.esInsertAtCursor('[EMAIL="' + line + '"]' + line + '[/EMAIL]');
								}
							}
						}
					};
					defaultCommands['insertHtml'] = editor.insertHtml;
					editor.insertHtml = editor.esInsertText;
					defaultCommands['insertText'] = editor.insertText;
					editor.insertText = editor.esInsertText;

					for (var command in this.overrideCommands)
					{
						defaultCommands[command] = editor.getCommand(command);
						editor.addCommand(command,
						{
							modes: {enhancedsource: 1},
							exec: this.overrideCommands[command]
						});
					}

					for (var i in editor.config.vbulletin.bbcodecache)
					{
						var bbcode = editor.config.vbulletin.bbcodecache[i];
						
						defaultCommands[bbcode.title] = editor.getCommand(bbcode.title);
						editor.addCommand(bbcode.title,
						{
							tagname: bbcode.bbcodetag.toUpperCase(),
							twoparams: bbcode.twoparams,
							modes: {enhancedsource: 1},
							exec: function(editor)
							{
								if (this.twoparams == '1')
								{
									var option = prompt(editor.lang.vbulletin.enter_tag_option.replace(/\%1\$s/,'[' + this.tagname + ']'));
									if (typeof option == 'string' && option.length > 0)
									{
										editor.esWrapAroundSelection('[' + this.tagname + '=' + option + ']', '[/' + this.tagname + ']');
									}
									else
									{
										editor.esWrapAroundSelection('[' + this.tagname + ']', '[/' + this.tagname + ']');
									}
								}
								else
								{
									editor.esWrapAroundSelection('[' + this.tagname + ']', '[/' + this.tagname + ']');
								}
							}
						});
					}

					var font = getButton(editor, 'combo', editor.lang.font.label);
					if (font)
					{
						font.combo.modes.enhancedsource = 1;
						defaultCommands['font'] = font.combo.onClick;
						font.combo.onClick = function(value, marked)
						{
							if (editor.savedselection)
							{
								editor.savedselection.select();
							}
							editor.esWrapAroundSelection('[FONT=' + value + ']', '[/FONT]');
						}
					}

					var size = getButton(editor, 'combo', editor.lang.fontSize.label);
					if (size)
					{
						size.combo.modes.enhancedsource = 1;
						defaultCommands['size'] = size.combo.onClick;
						size.combo.onClick = function(value, marked)
						{
							if (editor.savedselection)
							{
								editor.savedselection.select();
							}
							editor.esWrapAroundSelection('[SIZE=' + value + ']', '[/SIZE]');
						}
					}

					this.setupColorOverride();
					editor.getCommand('smiley').modes.enhancedsource = 1;
					editor.getCommand('openAttachmentWindow').modes.enhancedsource = 1;
					editor.getCommand('vbimage').modes.enhancedsource = 1;
					editor.getCommand('videotag').modes.enhancedsource = 1;
					editor.getCommand('undo').modes.enhancedsource = 1;
					editor.getCommand('redo').modes.enhancedsource = 1;
					editor.getCommand('iespell').modes.enhancedsource = 1;
				},

				setupColorOverride : function()
				{
					var color = getButton(editor, 'button', editor.lang.colorButton.textColorTitle);
					if (color)
					{
						color.button.modes.enhancedsource = 1;
						delete color.button._.panel;

						CKEDITOR.vbtextcolor.applyLastColor = CKEDITOR.tools.override(CKEDITOR.vbtextcolor.applyLastColor, function(original)
						{
							return function(event)
							{
								if (editor.mode == 'enhancedsource' && CKEDITOR.vbtextcolor.lastColor)
								{
									editor.esWrapAroundSelection('[COLOR="' + CKEDITOR.vbtextcolor.lastColor + '"]', '[/COLOR]');
									event.cancel();
									event.stop();
									event.data.preventDefault(true);
									return false;
								}
								else
								{
									original.call(this, event);
								}
							}
						});
						var buttonElement = CKEDITOR.document.getById(color.id);
						var icon = buttonElement.getChild(0);
						icon.on('click', CKEDITOR.vbtextcolor.applyLastColor, null, null, 0);
						color.button.onBlock = CKEDITOR.tools.override(color.button.onBlock, function(original)
						{
							return function(panel, block)
							{
								original.call(this, panel, block);
								if (editor.mode == 'enhancedsource')
								{
									var newClickFunction = CKEDITOR.tools.addFunction(function(color, type)
									{
										if (color != '?')
										{
											if (editor.savedselection)
											{
												editor.savedselection.select();
											}
											CKEDITOR.vbtextcolor.setLastColor(color);
										}
										else
										{
											var applyColorStyle = arguments.callee;
											function onColorDialogClose(evt)
											{
												this.removeListener('ok', onColorDialogClose);
												this.removeListener('cancel', onColorDialogClose);

												if (evt.name == 'ok')
												{
													var color = this.getContentElement( 'picker', 'selectedColor' ).getValue();
													editor.esWrapAroundSelection('[COLOR="'+color+'"]', '[/COLOR]');
												}
											}

											editor.openDialog( 'colordialog', function()
											{
												this.on( 'ok', onColorDialogClose );
												this.on( 'cancel', onColorDialogClose );
											});

											return;
										}
										if (color != null)
										{
											editor.esWrapAroundSelection('[COLOR="'+color+'"]', '[/COLOR]');
										}
									});
									var html = block.element.getHtml();
									html = html.replace(/callFunction\(\d+/g, 'callFunction(' + newClickFunction);
									block.element.setHtml(html);
								}
							}
						});
					}
				},

				restoreDefaultCommands : function()
				{
					for (var command in defaultCommands)
					{
						if (command == 'insertHtml')
						{
							editor.insertHtml = defaultCommands[command];
						}
						else if (command == 'insertText')
						{
							editor.insertText = defaultCommands[command];
						}
						else if (command == 'font')
						{
							getButton(editor, 'combo', editor.lang.font.label).combo.onClick = defaultCommands['font'];
						}
						else if (command == 'size')
						{
							getButton(editor, 'combo', editor.lang.fontSize.label).combo.onClick = defaultCommands['size'];
						}
						else
						{
							editor.addCommand(command, defaultCommands[command]);
						}
					}

					var color = getButton(editor, 'button', editor.lang.colorButton.textColorTitle);
					if (color)
					{
						delete color.button._.panel;
					}
				}				
			});
		});

		editor.addCommand( 'enhancedsource', enhancedsourcearea.commands.enhancedsource );

		if ( editor.ui.addButton )
		{
			editor.ui.addButton( 'EnhancedSource',
			{
				label : editor.lang.source,
				command : 'enhancedsource'
			});
		}

		editor.on( 'mode', function()
		{
			editor.getCommand( 'enhancedsource' ).setState(
			editor.mode == 'enhancedsource' ?
				CKEDITOR.TRISTATE_ON :
				CKEDITOR.TRISTATE_OFF );
		});

		setTimeout( function()
		{
			editor.fire('enhancedReady');
		}, 0);
	}
});

/**
 * Holds the definition of commands an UI elements included with the enhancedsourcearea
 * plugin.
 * @example
 */
CKEDITOR.plugins.enhancedsourcearea =
{
	commands :
	{
		enhancedsource :
		{
			modes : { wysiwyg:1, enhancedsource:1 },
			editorFocus : false,

			exec : function( editor )
			{
				if (editor.mode == 'wysiwyg')
				{
					editor.fire('saveSnapshot');
				}
				editor.getCommand('enhancedsource').setState(CKEDITOR.TRISTATE_DISABLED);
				editor.setMode(editor.mode == 'enhancedsource' ? 'wysiwyg' : 'enhancedsource');
			},

			canUndo : false
		}
	}
};