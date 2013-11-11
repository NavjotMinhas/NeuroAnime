(function()
{
	function getAlignment( element, useComputedState )
	{
		useComputedState = useComputedState === undefined || useComputedState;

		var align;
		if ( useComputedState )
			align = element.getComputedStyle( 'text-align' );
		else
		{
			while ( !element.hasAttribute || !( element.hasAttribute( 'align' ) || element.getStyle( 'text-align' ) ) )
			{
				var parent = element.getParent();
				if ( !parent )
					break;
				element = parent;
			}
			align = element.getStyle( 'text-align' ) || element.getAttribute( 'align' ) || '';
		}

		align && ( align = align.replace( /-moz-|-webkit-|start|auto/i, '' ) );

		!align && useComputedState && ( align = element.getComputedStyle( 'direction' ) == 'rtl' ? 'right' : 'left' );

		return align;
	}
	
	CKEDITOR.plugins.add('vbjustify',
	{
		requires : [ 'justify' ],
		
		init : function(editor)
		{
			var justifyCommand = function(){
				var selection = editor.getSelection(),
					enterMode = editor.config.enterMode;
	
				if ( !selection )
					return;
	
				var bookmarks = selection.createBookmarks(),
					ranges = selection.getRanges( true );
	
				var cssClassName = this.cssClassName,
					iterator,
					block;
	
				var useComputedState = editor.config.useComputedState;
				useComputedState = useComputedState === undefined || useComputedState;
	
				for ( var i = ranges.length - 1 ; i >= 0 ; i-- )
				{
					iterator = ranges[ i ].createIterator();
					iterator.enlargeBr = enterMode != CKEDITOR.ENTER_BR;
	
					while ( ( block = iterator.getNextParagraph( enterMode == CKEDITOR.ENTER_P ? 'p' : 'div' ) ) )
					{
						block.removeAttribute( 'align' );
						block.removeStyle( 'text-align' );
	
						// Remove any of the alignment classes from the className.
						var className = cssClassName && ( block.$.className =
							CKEDITOR.tools.ltrim( block.$.className.replace( this.cssClassRegex, '' ) ) );
	
						var apply =
							( this.state == CKEDITOR.TRISTATE_OFF ) &&
							( !useComputedState || ( getAlignment( block, true ) != this.value ) );
	
						if ( cssClassName )
						{
							// Append the desired class name.
							if ( apply )
								block.addClass( cssClassName );
							else if ( !className )
								block.removeAttribute( 'class' );
						}
						else if ( apply )
						{
							if (block.getName() == 'li')
							{
								var paragraph = new CKEDITOR.dom.element('p');
								paragraph.setStyle('text-align', this.value);
								paragraph.setStyle('margin', 0);
								block.moveChildren(paragraph);
								block.append(paragraph);
							}
							else
							{
								block.setStyle( 'text-align', this.value );
							}
						}
					}
	
				}
	
				editor.focus();
				editor.forceNextSelectionCheck();
				selection.selectBookmarks( bookmarks );
			};
		
			var left = editor.getCommand( 'justifyleft' ),
			center = editor.getCommand( 'justifycenter' ),
			right = editor.getCommand( 'justifyright' ),
			justify = editor.getCommand( 'justifyblock' );
			
			if (left)
			{
				left.exec = justifyCommand;
			}
			if (center)
			{
				center.exec = justifyCommand;
			}
			if (right)
			{
				right.exec = justifyCommand;
			}
			if (justify)
			{
				justify.exec = justifyCommand;
			}
	
			editor.on('mode', function()
			{
				if (editor.mode == 'wysiwyg')
				{
					if (left)
					{
						editor.addCommand( 'justifyleft', left );
					}
					if (center)
					{
						editor.addCommand( 'justifycenter', center );
					}
					if (right)
					{
						editor.addCommand( 'justifyright', right );
					}
					if (justify)
					{
						editor.addCommand( 'justifyblock', justify );
					}
				}
			});
		}
	});
	
})();