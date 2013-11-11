(function(){
	
	var VbImage = function(editor)
	{
		this.editor = editor;
	};
	VbImage.prototype = 
	{

		tryMoveFlashObj: function(iteration)
		{
			var me = this;
			if (!iteration)
			{
				iteration = 5;
			};

			if (this.moveFlashObj() || iteration == 0)
			{
				return;
			}

			setTimeout(
				function()
				{
					me.tryMoveFlashObj(iteration - 1);
				},
				500
			);
		},

		moveFlashObj: function()
		{
			// move flash holder to where it needs to be!

			var region = YAHOO.util.Dom.getRegion("yui_selectfilestext");
			if (!region || region.height == 0)
			{
				return false;
			}

			YAHOO.util.Dom.setStyle("yui_selectfiles", "width", region.width - 2 + "px");
			YAHOO.util.Dom.setStyle("yui_selectfiles", "height", region.height - 2 + "px");
			var xy = YAHOO.util.Dom.getXY("yui_selectfilestext");
			YAHOO.util.Dom.setXY("yui_selectfiles", xy);

			if (is_moz)
			{	// Don't even ask!
				YAHOO.util.Dom.setStyle("yui_selectfiles", "overflow-x", "");
				YAHOO.util.Dom.setStyle("yui_selectfiles", "overflow-y", "");
				YAHOO.util.Dom.setStyle("yui_selectfiles", "overflow", "");
				YAHOO.util.Dom.setStyle("yui_selectfiles", "overflow", "none");
			}

			return true;
		},

		getImageUploadHtml: function()
		{
			var postData = {
				ajax: 1,
				'do': 'fetchhtml',
				template: 'editor_upload_overlay',
				securitytoken:  this.editor.config.vbulletin.securitytoken
			};
			
			var responseXML = CKEDITOR.vbajax.open({
				url: 'ajax.php',
				type: 'POST',
				data: postData,
				async: false,
				dataType: 'xml'
			}).responseXML;
			
			var html = responseXML.getElementsByTagName('html')[0].firstChild.nodeValue;
			
			return '<form id="' + this.editor.editorid + '_dialog" style="position: absoulte; z-index:999999; ">' + html + '</form>';
		},
		
		insertImageReady: function()
		{
			var imageuploadobj = new vB_ImageUpload(this.editor.editorid, this.editor);
			imageuploadobj.events.complete.subscribe(this.insertImageComplete, this);
			imageuploadobj.events.uploaddone.subscribe(this.insertImageUploadOne, this);
		},
		
		insertImageComplete: function (event, args, me)
		{
			// close dialog
			CKEDITOR.dialog.getCurrent().hide();
		},
		
		insertImageUploadOne: function (event, args, me)
		{
			var attachmentid = args[0], contenttypeid = args[1], thumbnail = args[2];
			if (thumbnail != 0)
			{
				me.editor.insert_attachment(attachmentid);
			}
			else
			{
				me.editor.insert_attachment(attachmentid, false);
			}
		},
		
		doAttachmentFromUrl: function(dialog)
		{
			var url = dialog.getContentElement('from_url', 'url').getValue();
			var remote = dialog.getContentElement('from_url', 'remote_file');
			if ((remote.isVisible() && remote.getValue()) || this.editor.config.vbulletin.no_img_bbcode)
			{
				var postData = {
					ajax: 1,
					'do': 'manageattach',
					flash: 1,
					upload: 1,
					posthash: this.editor.config.vbulletin.attachinfo.posthash,
					poststarttime: this.editor.config.vbulletin.attachinfo.poststarttime,
					contenttypeid: this.editor.config.vbulletin.attachinfo.contenttypeid,
					'attachmenturl[]': url,
					securitytoken: this.editor.config.vbulletin.securitytoken
				};
				
				var responseText = CKEDITOR.vbajax.open({
					url: 'newattachment.php',
					type: 'POST',
					data: CKEDITOR.vbajax.parseRequestData(postData) + this.editor.config.vbulletin.attachinfo.valuestring,
					async: false
				}).responseText;
				
				var response = responseText.split(' - ');
				if (response[0] == 'ok')
				{
					this.editor.insert_attachment(response[1]);
				}
				else
				{
					alert(this.editor.lang.vbulletin.invalidurl);
					return false;
				}
			}
			else
			{
				this.editor.insertHtml('[IMG]' + url + '[/IMG]');
			}
			return true;
		},
		
		getImageDialog: function()
		{
			var me = this;

			return {
				title: me.editor.lang.vbulletin.insert_image,
				onOk: function(event)
				{
					return me.doAttachmentFromUrl(CKEDITOR.dialog.getCurrent());
				},
				onLoad: function(){
					var dialog = CKEDITOR.dialog.getCurrent();
					dialog.on('selectPage', function(event)
					{
						if (event.data.page == 'from_computer')
						{
							dialog.parts.footer.setStyle('display', 'none');
							// the 'selectPage' event is fired before the page is actually displayed so moveFlashObj() will
							// fail until yui_selectfilestest is visible
							me.tryMoveFlashObj();
						}
						else
						{
							dialog.parts.footer.setStyle('display', 'block');
							YAHOO.util.Dom.setStyle("yui_selectfiles", "width", "1px");
							YAHOO.util.Dom.setStyle("yui_selectfiles", "height", "1px");
						}
					});

					if (me.editor.config.vbulletin.attachinfo && me.editor.config.vbulletin.attachinfo.advimagepopup != 0 && me.editor.config.vbulletin.attachinfo.contenttypeid != 0)
					{
						YAHOO.vBulletin.LoadCss("css.php?sheet=yuiupload.css");
						YAHOO.vBulletin.LoadScript("clientscript/yui/combo/imageupload.js?v=" + SIMPLEVERSION, function() {
							YAHOO.vBulletin.LoadScript("clientscript/vbulletin_yuiupload.js?v=" + SIMPLEVERSION, function() {
								YAHOO.vBulletin.LoadScript("clientscript/vbulletin_imageup.js?v=" + SIMPLEVERSION, function() { 
									me.insertImageReady.call(me);
									
									dialog._.currentTabId = null;
									dialog.selectPage('from_computer');							
								});
							});
						});
					}
					else
					{
						dialog.selectPage('from_url');
						dialog.hidePage('from_computer')
						var remote = dialog.getContentElement('from_url', 'remote_file');
						remote.getElement().hide();
					}

					if (me.editor.config.vbulletin.no_img_bbcode)
					{
						var remote = dialog.getContentElement('from_url', 'remote_file');
						remote.getElement().hide();
					}
				},
				onShow: function(){
					var node1 = YAHOO.util.Dom.get("uploadtabs0");
					var node2 = YAHOO.util.Dom.get("_uploadtabs0");
					if (node2)
					{
						if (node1)
						{
							node1.parentNode.replaceChild(node2, node1);
						}
						node2.id = "uploadtabs0";
					}
					this.setupContent();
				},
				onHide: function(){
					YAHOO.util.Dom.setStyle("yui_selectfiles", "width", "1px");
					YAHOO.util.Dom.setStyle("yui_selectfiles", "height", "1px");
					YAHOO.util.Dom.get("uploadtabs0").id = '_uploadtabs0';
				},
				minWidth: '410',
				minHeight: '220',
				//buttons: [],
				contents: [{
					id: 'from_computer',
					label: me.editor.lang.vbulletin.from_computer,
	        elements:[{
	        	type: 'html',
	          html: me.getImageUploadHtml()
	        }]
				},{
					id: 'from_url',
					label: me.editor.lang.vbulletin.from_url,
	        elements:[{
	        	type: 'html',
	        	html: '<h2>' + me.editor.lang.vbulletin.add_an_image_from_url + '</h2>' +
	        				'<div>' + me.editor.lang.vbulletin.allowed_filetypes + ': jpg, jpeg, png, gif</div>'
	        },{
	        	type: 'text',
	          id: 'url',
	          label: me.editor.lang.common.url,
	          labelLayout: 'vertical'
	        },{
	        	type: 'checkbox',
	          id: 'remote_file',
	          label: me.editor.lang.vbulletin.retrieve_remote_file_and_ref_local,
	          labelLayout: 'horizontal',
	          setup: function() {
		        	if (me.editor.config.vbulletin.attachinfo)
					{
		        		this.setValue(true);
					}
	        	}
	        }]
				}]
			}
		}
	};
	
	CKEDITOR.dialog.add('vbimage', function(editor) {
		var vbimage = new VbImage(editor);
		return vbimage.getImageDialog();
	});
		
})();