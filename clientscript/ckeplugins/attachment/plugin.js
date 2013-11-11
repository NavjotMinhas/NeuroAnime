(function()
{
	function getButton(editor, type, label)
	{
		for (var i = 0; i < editor.toolbox.toolbars.length; i++)
		{
			var toolbar = editor.toolbox.toolbars[i];
			for (var j = 0; j < toolbar.items.length; j++)
			{
				var item = toolbar.items[j];
				if ((type == 'button' && item.button && item.button.label == label))
				{
					return item;
				}
			}
		}
	}
	
	function AttachmentConfig(editor, path)
	{
		this.editor = editor;
		
		CKEDITOR.dialog.add('attachment', path + 'dialogs/attachment.js?v=' + SIMPLEVERSION);
		editor.addCommand('openAttachmentConfig', new CKEDITOR.dialogCommand('attachment'));
	};
	
	function AttachmentWindowCommand(editor, attachmentConfig)
	{
		this.editor = editor;
		this.attachmentConfig = attachmentConfig;
	};
	
	AttachmentWindowCommand.prototype = {
		windows: new Array(),
		
		exec: function(editor)
		{
			this.content = CKEDITOR.config.content;
			if (AJAX_Compatible && this.content.enhanced == 1)
			{
				var insertinline = !editor.config.nobbcode ? this.content.insertinline : 0;
				this.open_window("newattachment.php?" +  SESSIONURL + "do=assetmanager&" + PHP.unhtmlspecialchars(this.content.values) + "&contenttypeid=" + this.content.contenttypeid + "&poststarttime=" + this.content.poststarttime + "&posthash=" + this.content.posthash + "&insertinline=" + insertinline, 725, 520, this.content.contenttypeid + "_" + this.content.posthash);
			}
			else
			{
				this.open_window("newattachment.php?" +  SESSIONURL + PHP.unhtmlspecialchars(this.content.values) + "&contenttypeid=" + this.content.contenttypeid + "&poststarttime=" + this.content.poststarttime + "&posthash=" + this.content.posthash, 480, 480, this.content.posthash);
			}
		},
		
		open_window: function(url, width, height, hash)
		{
			if (this.windows[hash] && this.windows[hash].closed == false)
			{
				this.windows[hash].focus();
			}
			else
			{
				this.windows[hash] = openWindow(url, width, height, 'Attach' + hash);
			}
		},
		
		insert_attachment: function(attachment_id, thumbnail)
		{
			this.editor.insert_attachment(attachment_id, thumbnail);
		}
	};
	
	CKEDITOR.editor.prototype.insert_attachment = function(attachment_id, thumbnail)
	{
		var insertText = (thumbnail != 0) ? '[ATTACH=CONFIG]' + attachment_id + '[/ATTACH]' : '[ATTACH]' + attachment_id + '[/ATTACH]';
		// workaround for firefox bug, please remove this after no one uses 3.6.10 anymore, see jira issue VBIV-8982
		if (this.mode == 'wysiwyg' && CKEDITOR.env.gecko && parseInt(navigator.productSub) <= 20100924 && parseInt(navigator.productSub) >= 20100721)
		{
			var html = this.dataProcessor.toHtml(insertText);
			var selection = this.getSelection();
			var bookmarks = selection.createBookmarks();
			var container = new CKEDITOR.dom.element( 'span' );
			container.setHtml(html);
			container.insertBefore(bookmarks[0].startNode);
			container.remove(true);
			selection.selectBookmarks(bookmarks);
		}
		else
		{
			this.insertHtml(insertText);
		}
	};
	
	CKEDITOR.plugins.add('attachment',
	{
		init : function(editor)
		{
			var block;
			var panel;
			var attachmentConfig = new AttachmentConfig(editor, editor.config.vbPluginPath + 'attachment/');
			var attachmentWindowCommand = new AttachmentWindowCommand(editor, attachmentConfig);
			window.ckeattachment = attachmentWindowCommand;
			
			editor.addCommand('openAttachmentWindow', attachmentWindowCommand);
			var buttonClick = function(event)
			{
				editor.execCommand('openAttachmentWindow');
				event.cancel();
				event.stop();
				event.data.preventDefault(true);
				return false;
			};
			
			var icon;
			var arrow;
			var attachmentButton;
			
			editor.on('instanceReady', function(editorEvent)
			{
				attachmentButton = getButton(editor, 'button', editor.config.vbulletin.phrase.attachments);
				if (attachmentButton)
				{
					var buttonElement = CKEDITOR.document.getById(attachmentButton.id);
					
					icon = buttonElement.getChild(0);
					icon.on('click', buttonClick, null, null, 1);
					arrow = buttonElement.getChild(2);
					arrow.on('click', function(event)
					{
						if (vB_Attachments.attachments.length == 0 || editor.config.nobbcode)
						{
							buttonClick(event);
						}
					}, null, null, 1);
					
					attachmentButton.button.onOpen = function()
					{
						if (block)
						{
							block.element.setHtml(renderAttachmentList());
							panel.element.getFirst().addClass('cke_attach');
						}
					}
				}
			}, this);
			
			var insertClickFn = CKEDITOR.tools.addFunction(function(attachment_id)
			{
				editor.insert_attachment(attachment_id);
			});
			
			var insertAllClickFn = CKEDITOR.tools.addFunction(function()
			{
				if (vB_Attachments)
				{
					for (var id in vB_Attachments.attachments)
					{
						editor.insert_attachment(id);
					}
				}
			});

			function renderAttachmentList()
			{
				var output = [];
				if (vB_Attachments)
				{
					var count = 0;
					for (var id in vB_Attachments.attachments)
					{
						count++;
						output.push('<a href="" onclick="CKEDITOR.tools.callFunction(', insertClickFn, ', ', id ,');return false;">');
						output.push(vB_Attachments.attachments[id]["html"]);
						output.push('</a>');
					}
					if (count > 1)
					{
						output.push('<div class="attach_insert_all" onclick="CKEDITOR.tools.callFunction(', insertAllClickFn, ')">', editor.lang.vbulletin.insert_all, '</div>');
					}
				}
				return output.join('');
			}
			
			editor.ui.add( 'Attach', CKEDITOR.UI_PANELBUTTON,
			{
				label : editor.config.vbulletin.phrase.attachments,
				title : editor.config.vbulletin.phrase.attachments,
				className : 'cke_button_openAttachmentWindow',
				modes : { wysiwyg : 1, enhancedsource : 1 },
				
				panel :
				{

					css : editor.config.vbulletin.bburl + '/css.php?sheet=bbcode.css,editor.css,popupmenu.css,reset-fonts.css,vbulletin.css,vbulletin-chrome.css,vbulletin-formcontrols.css',
					attributes : { role : 'listbox', 'aria-label' : editor.config.vbulletin.phrase.attachments },
					onLoad: function() {
						panel = attachmentButton.button._.panel;
						if (panel)
						{
							panel.element.getFirst().addClass('cke_attach');
						}
					}
				},

				onBlock : function( temppanel, tempblock )
				{
					panel = temppanel;
					block = tempblock;
					block.autoSize = true;
					block.element.addClass( 'editorpopupbody' );
					panel.element.getFirst().addClass('cke_attach');
					/* vBulletin -- end */
					// The block should not have scrollbars (#5933, #6056)

					// vBulletin - we need a scrollbar here for the attachment popup
					block.element.getDocument().getBody().setStyle( 'overflow', 'hidden' );
					if (block.element.getDocument().getHead().getChild(0))
					{
						block.element.getDocument().getHead().getChild(0).remove();
					}

					var keys = block.keys;
					var rtl = editor.lang.dir == 'rtl';
					keys[ rtl ? 37 : 39 ]	= 'next';					// ARROW-RIGHT
					keys[ 40 ]	= 'next';					// ARROW-DOWN
					keys[ 9 ]	= 'next';					// TAB
					keys[ rtl ? 39 : 37 ]	= 'prev';					// ARROW-LEFT
					keys[ 38 ]	= 'prev';					// ARROW-UP
					keys[ CKEDITOR.SHIFT + 9 ]	= 'prev';	// SHIFT + TAB
					keys[ 32 ]	= 'click';					// SPACE
				}
			});	
		}
	});
})();