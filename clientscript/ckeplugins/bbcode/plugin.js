(function()
{
	var Ajax = function(){};
	Ajax.prototype = {
		
		create: function()
		{
			return (window.XMLHttpRequest) ? new XMLHttpRequest() : new ActiveXObject('Microsoft.XMLHTTP');
		},
	
		parseRequestData: function(requestData)
		{
			var data = null;
			if (typeof requestData == 'string')
			{
				data = requestData;
			}
			else if (typeof requestData == 'object')
			{
				data = [];
				for (property in requestData)
				{
					if (requestData[property])
					{
						var encoded_data = PHP.urlencode(requestData[property]);
					}
					else
					{
						var encoded_data = '';
					}
					data.push(property + '=' + encoded_data); 
				}
				data = data.join('&');
			}
			return data;
		},

		open: function(request)
		{
			var xmlHttp = this.create();
			xmlHttp.open(request.type, request.url, request.async);
			
			if (request.type && request.type.toUpperCase() == 'POST')
			{
				xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
			}
			else
			{
				request.type = 'GET';
			}
			
			if ((typeof request.async == 'undefined' || request.async != false) && request.callback)
			{
				xmlHttp.onreadystatechange = request.callback;
			}
			xmlHttp.send(this.parseRequestData(request.data));
			
			return xmlHttp;
		}
	};
	CKEDITOR.vbajax = new Ajax();
	
	function unescapeResponse(response)
	{
		try
		{
			response = decodeURI(response);
		}
		catch (e)
		{
			// decode failed for some reason
		}
		
		var matches = response.match(/&#([0-9]+);/g);
		if (matches)
		{
			for (var i = 0; typeof matches[i] != 'undefined'; i++)
			{
				if (submatch = matches[i].match(/^&#([0-9]+);$/))
				{
					response = response.replace(submatch[0], String.fromCharCode(submatch[1]));
				}
			}
		}
		
		return response;
	}
	
	CKEDITOR.plugins.add('bbcode',
	{
		init : function(editor)
		{
			var dataProcessor = editor.dataProcessor = new CKEDITOR.bbcodeDataProcessor(editor);
		}
	});

	CKEDITOR.bbcodeDataProcessor = function(editor)
	{
		this.editor = editor;

		this.writer = new CKEDITOR.htmlWriter();
		this.dataFilter = new CKEDITOR.htmlParser.filter();
		this.htmlFilter = new CKEDITOR.htmlParser.filter();
	};

	CKEDITOR.bbcodeDataProcessor.prototype = {
		doServerParse: function(message, wysiwyg)
		{
			// only hit the server if we have something to translate
			if (message.length > 0)
			{/*
				if (wysiwyg)
				{
					message = PHP.htmlspecialchars(message);
				}*/
				var postData = {
					towysiwyg: wysiwyg,
					message: message,
					'do': 'editorswitch',
					allowsmilie: 1,
					parsetype: PHP.htmlspecialchars(this.editor.config.parsetype),
					securitytoken: this.editor.config.vbulletin.securitytoken
				};
				//if there's extra info for the AJAX call VBIV-12041
				if (this.editor.config.ajax_extra)
				{
					for (id in this.editor.config.ajax_extra)
					{
						postData[id] = this.editor.config.ajax_extra[id];
					}
				}
				
				var responseXML = CKEDITOR.vbajax.open({
					url: 'ajax.php',
					type: 'POST',
					data: postData,
					async: false,
					dataType: 'xml'
				}).responseXML;
				
				var messageTag = fetch_tags(responseXML, 'message')[0];
				if (messageTag && messageTag.firstChild)
				{
					var response = unescapeResponse(responseXML.getElementsByTagName('message')[0].firstChild.nodeValue);
				}
				else
				{
					response = '';
				}
			}
			else
			{
				var response = message;
			}

			return response;
		},
		
		toHtml : function(data, fixForBody)
		{
			if (this.editor.hitServer != false)
			{
				data = this.doServerParse(data, 1);
			}

			// fix table width
			data = data.replace(/<table(.*?)width=(["'])(.+?)\2/gi, function(targetString, first, second, width){
				if (!width.match(/%/))
				{
					width = width + 'px';
				}
				return '<table ' + first + ' style="width: '+ width + '"';
			});
			
			data = data.replace(/<b>/gi, '<strong>');
			data = data.replace(/<\/b>/gi, '</strong>');
			data = data.replace(/<i>/gi, '<em>');
			data = data.replace(/<\/i>/gi, '</em>');
	
			return data;
		},
	
		toDataFormat : function(html, fixForBody, skipServerParse)
		{
			// strip divs
			html = html.replace(/<div.*>(.+?)<\/div>/gi, '');
			
			// take care of p/br tags
			if (fixForBody == 'p')
			{
				html = html.replace(/<br><\/p>/gi, '<br>');
				html = html.replace(/<p>/gi, '');
			}
			
			// fix table width
			var tables = this.editor.document.getElementsByTag('table');
			for (var i = 0; i < tables.count(); i++)
			{
				var table = tables.getItem(i);
				table.setAttribute('width', table.getStyle('width').replace(/px/, ''));
			}
			html = this.editor.document.getBody().getHtml();
			
			// fix a tags
			html = html.replace(/<a .*?_cke_saved_href=(["'])(.+?)\1.*?>(.+?)<\/a>/gi, '<a href="$2">$3</a>');

			if (!skipServerParse)
			{
				html = this.doServerParse(html, 0);

				// fix smilies
				for (var i = 0; i < this.editor.config.smiley_images.length; i++)
				{
					path = this.editor.config.smiley_images[i].replace(/\//g, '\\/');
					text = this.editor.config.smiley_descriptions[i];
					html = html.replace(new RegExp('\\[IMG\\].*?' + PHP.preg_quote(path) + '\\[\\/IMG\\]', 'gi'), text);
				}
			}
	
			return html;
		},

		getAutoSaveData : function ()
		{
			var emptyParagraphRegexp = /(^|<body\b[^>]*>)\s*<(p|div|address|h\d|center)[^>]*>\s*(?:<br[^>]*>|&nbsp;|\u00A0|&#160;)?\s*(:?<\/\2>)?\s*(?=$|<\/body>)/gi;
			
			var fixForBody = (this.editor.config.enterMode != CKEDITOR.ENTER_BR)
				? this.editor.config.enterMode == CKEDITOR.ENTER_DIV ? 'div' : 'p' : false;

			var data = this.editor.document.getBody().getHtml();

			// BR at the end of document is bogus node for Mozilla. (#5293).
			if (CKEDITOR.env.gecko)
			{
				data = data.replace( /<br>(?=\s*(:?$|<\/body>))/, '' );
			}

			data = this.toDataFormat(data, fixForBody, true);

			// Reset empty if the document contains only one empty paragraph.
			if (this.editor.config.ignoreEmptyParagraph)
			{
				data = data.replace( emptyParagraphRegexp, function( match, lookback ) { return lookback; } );
			}

			return data;			
		}
	};
})();