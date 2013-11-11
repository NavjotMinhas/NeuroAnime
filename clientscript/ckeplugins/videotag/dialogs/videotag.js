(function(){
	var exampleDialog = function(editor){
		var phrase = editor.config.vbulletin.phrase;
		
		CKEDITOR.on('dialogDefinition', function(ev){
			var dialogName = ev.data.name;
			var dialogDefinition = ev.data.definition;
			
			if (dialogName == 'videotag')
			{
				var dialog = CKEDITOR.dialog.getCurrent();
				
				var postData = {
					ajax: 1,
					'do': 'getvideoproviders',
					securitytoken:  editor.config.vbulletin.securitytoken
				};
				
				var responseXML = CKEDITOR.vbajax.open({
					url: 'ajax.php',
					type: 'POST',
					data: postData,
					async: false,
					dataType: 'xml'
				}).responseXML;
				
				var dialog_html = '';
				var providers = responseXML.getElementsByTagName('provider');
				for (var i = 0; i < providers.length; i++)
				{
					var provider = providers[i];
					var name = provider.getAttribute('provider');
					var url = provider.getAttribute('url');
					dialog_html += '<a target="_blank" href="'+url+'">'+name+'</a>&nbsp;';
				}
				dialogDefinition.getContents('videotag').elements[2].html = dialog_html;
			}
		});
		
		return {
			title: phrase.insert_video_clip,
			onOk: function(event){
				var url = this.getValueOf('videotag', 'urlInput');
				if (PHP.trim(url) != '')
				{
					editor.insertText('[VIDEO]' + url + '[/VIDEO]');
				}
			},
			onShow: function(){
				
			},
			minWidth : '300',
			minHeight: '200',
			contents: [{
				id: 'videotag',
        elements:[{
        	type: 'text',
          id: 'urlInput',
          label: phrase.enter_video_url_below,
          labelLayout: 'vertical'
        },{
        	type: 'html',
        	html: '<div>'+phrase.example_video_url+'</div><br/>' +
        				'<div>'+phrase.supported_videos+'</div>'
        },{
        	type: 'html',
        	id: 'providerslist',
        	html: ''
        }]
			}]
		}
	};
	
	CKEDITOR.dialog.add('videotag', function(editor) {
		return exampleDialog(editor);
	});
		
})();