(function()
{
	CKEDITOR.plugins.add( 'vbremoveformat',
	{
		requires : [ 'selection' ],

		init : function( editor )
		{
			var command = editor.addCommand( 'removeFormat', CKEDITOR.plugins.removeformat.commands.removeformat );
			command.modes = { wysiwyg:1, enhancedsource:1 };
			editor.ui.addButton( 'RemoveFormat',
				{
					label : editor.lang.removeFormat,
					command : 'removeFormat'
				});

			editor._.removeFormat = { filters: [] };
		}
	});

	CKEDITOR.plugins.removeformat =
	{
		commands :
		{
			removeformat :
			{
				exec : function( editor )
				{
					if (editor.mode == 'wysiwyg')
					{
						var tagsRegex = editor._.removeFormatRegex ||
							( editor._.removeFormatRegex = new RegExp( '^(?:' + editor.config.removeFormatTags.replace( /,/g,'|' ) + ')$', 'i' ) );

						var removeAttributes = editor._.removeAttributes ||
							( editor._.removeAttributes = editor.config.removeFormatAttributes.split( ',' ) );

						var filter = CKEDITOR.plugins.removeformat.filter;
						var ranges = editor.getSelection().getRanges( 1 ),
							iterator = ranges.createIterator(),
							range;

						while ( ( range = iterator.getNextRange() ) )
						{
							if ( !range.collapsed )
								range.enlarge( CKEDITOR.ENLARGE_ELEMENT);

							// Bookmark the range so we can re-select it after processing.
							var bookmark = range.createBookmark(),
								// The style will be applied within the bookmark boundaries.
								startNode	= bookmark.startNode,
								endNode		= bookmark.endNode,
								currentNode;

							// We need to check the selection boundaries (bookmark spans) to break
							// the code in a way that we can properly remove partially selected nodes.
							// For example, removing a <b> style from
							//		<b>This is [some text</b> to show <b>the] problem</b>
							// ... where [ and ] represent the selection, must result:
							//		<b>This is </b>[some text to show the]<b> problem</b>
							// The strategy is simple, we just break the partial nodes before the
							// removal logic, having something that could be represented this way:
							//		<b>This is </b>[<b>some text</b> to show <b>the</b>]<b> problem</b>

							var breakParent = function( node )
							{
								// Let's start checking the start boundary.
								var path = new CKEDITOR.dom.elementPath( node ),
									pathElements = path.elements;

								for ( var i = 1, pathElement ; pathElement = pathElements[ i ] ; i++ )
								{
									if ( pathElement.equals( path.block ) || pathElement.equals( path.blockLimit ) )
										break;

									// If this element can be removed (even partially).
									if ( tagsRegex.test( pathElement.getName() ) && filter( editor, pathElement ) )
										node.breakParent( pathElement );
								}
							};

							breakParent( startNode );
							if ( endNode )
							{
								breakParent( endNode );

								// Navigate through all nodes between the bookmarks.
								currentNode = startNode.getNextSourceNode( true, CKEDITOR.NODE_ELEMENT );

								while ( currentNode )
								{
									// If we have reached the end of the selection, stop looping.
									if ( currentNode.equals( endNode ) )
										break;

									// Cache the next node to be processed. Do it now, because
									// currentNode may be removed.
									var nextNode = currentNode.getNextSourceNode( false, CKEDITOR.NODE_ELEMENT );

									// This node must not be a fake element.
									if ( !( currentNode.getName() == 'img'
										&& currentNode.data( 'cke-realelement' ) )
										&& filter( editor, currentNode ) )
									{
										// Remove elements nodes that match with this style rules.
										if ( tagsRegex.test( currentNode.getName() ) )
											currentNode.remove( 1 );
										else
										{
											currentNode.removeAttributes( removeAttributes );
											editor.fire( 'removeFormatCleanup', currentNode );
										}
									}

									currentNode = nextNode;
								}
							}

							range.moveToBookmark( bookmark );
						}

						editor.getSelection().selectRanges( ranges );
					}
					else
					{	// enhancedsource
						var setSelectionRange = function(input, selectionStart, selectionEnd)
						{
						  if (input.setSelectionRange)
						  {
							input.focus();
							input.setSelectionRange(selectionStart, selectionEnd);
						  }
						  else if (input.createTextRange)
						  {
							var range = input.createTextRange();
							range.collapse(true);
							range.moveEnd('character', selectionEnd);
							range.moveStart('character', selectionStart);
							range.select();
						  }
						};

						var setCaretToPos = function(input, pos)
						{
							setSelectionRange(input, pos, pos);
						};
						
						var strip_complex = function(tag, str, iterations)
						{
							var opentag = '[' + tag + '=';
							var closetag = '[/' + tag + ']';

							if (typeof iterations == 'undefined')
							{
								iterations = -1;
							}

							while ((startindex = PHP.stripos(str, opentag)) !== false && iterations != 0)
							{
								iterations --;
								if ((stopindex = PHP.stripos(str, closetag)) !== false)
								{
									var openend = PHP.stripos(str, ']', startindex);
									if (openend !== false && openend > startindex && openend < stopindex)
									{
										var text = str.substr(openend + 1, stopindex - openend - 1);
										str = str.substr(0, startindex) + text + str.substr(stopindex + closetag.length);
									}
									else
									{
										break;
									}
								}
								else
								{
									break;
								}
							}

							return str;
						};


						var strip_simple = function(tag, str, iterations)
						{
							var opentag = '[' + tag + ']';
							var closetag = '[/' + tag + ']';

							if (typeof iterations == 'undefined')
							{
								iterations = -1;
							}

							while ((startindex = PHP.stripos(str, opentag)) !== false && iterations != 0)
							{
								iterations --;
								if ((stopindex = PHP.stripos(str, closetag)) !== false)
								{
									var text = str.substr(startindex + opentag.length, stopindex - startindex - opentag.length);
									str = str.substr(0, startindex) + text + str.substr(stopindex + closetag.length);
								}
								else
								{
									break;
								}
							}

							return str;
						};

						var removebbcode = function(str)
						{
							var simplestrip = new Array('b', 'i', 'u');
							var complexstrip = new Array('font', 'color', 'size');

							if (!str)
							{
								return str;
							}

							var tag;
							// simple stripper
							for (tag in simplestrip)
							{
								if (YAHOO.lang.hasOwnProperty(simplestrip, tag))
								{
									str = strip_simple(simplestrip[tag], str);
								}
							}

							// complex stripper
							for (tag in complexstrip)
							{
								if (YAHOO.lang.hasOwnProperty(complexstrip, tag))
								{
									str = strip_complex(complexstrip[tag], str);
								}
							}

							return str;
						};

						var textarea = editor.textarea.$;
						if (CKEDITOR.env.ie)
						{
							textarea.focus();
							sel = document.selection.createRange();
							var text = sel.text;
							var newtext = removebbcode(text);
							sel.text = newtext;
							sel.moveStart('character', -newtext.length);
							sel.select();
						}
						else if (textarea.selectionStart || textarea.selectionStart == '0')
						{
							var startPos = textarea.selectionStart;
							var endPos = textarea.selectionEnd;
							var text = textarea.value.substring(startPos, endPos);
							var newtext = removebbcode(text);
							textarea.value =
								textarea.value.substring(0, startPos) +
								newtext +
								textarea.value.substring(endPos, textarea.value.length);
							if (startPos == endPos)
							{
								setCaretToPos(textarea, startPos);
							}
							else
							{
								setSelectionRange(textarea, startPos, startPos + newtext.length);
							}
						}
					}
				}
			}
		},

		/**
		 * Perform the remove format filters on the passed element.
		 * @param {CKEDITOR.editor} editor
		 * @param {CKEDITOR.dom.element} element
		 */
		filter : function ( editor, element )
		{
			var filters = editor._.removeFormat.filters;
			for ( var i = 0; i < filters.length; i++ )
			{
				if ( filters[ i ]( element ) === false )
					return false;
			}
			return true;
		}
	};
})();

/**
 * Add to a collection of functions to decide whether a specific
 * element should be considered as formatting element and thus
 * could be removed during <b>removeFormat</b> command,
 * Note: Only available with the existence of 'removeformat' plugin.
 * @since 3.3
 * @param {Function} func The function to be called, which will be passed a {CKEDITOR.dom.element} element to test.
 * @example
 *  // Don't remove empty span
 *  editor.addRemoveFormatFilter.push( function( element )
 *		{
 *			return !( element.is( 'span' ) && CKEDITOR.tools.isEmpty( element.getAttributes() ) );
 *		});
 */
CKEDITOR.editor.prototype.addRemoveFormatFilter = function( func )
{
	this._.removeFormat.filters.push( func );
};

/**
 * A comma separated list of elements to be removed when executing the "remove
 " format" command. Note that only inline elements are allowed.
 * @type String
 * @default 'b,big,code,del,dfn,em,font,i,ins,kbd,q,samp,small,span,strike,strong,sub,sup,tt,u,var'
 * @example
 */
CKEDITOR.config.removeFormatTags = 'b,big,code,del,dfn,em,font,i,ins,kbd,q,samp,small,span,strike,strong,sub,sup,tt,u,var';

/**
 * A comma separated list of elements attributes to be removed when executing
 * the "remove format" command.
 * @type String
 * @default 'class,style,lang,width,height,align,hspace,valign'
 * @example
 */
CKEDITOR.config.removeFormatAttributes = 'class,style,lang,width,height,align,hspace,valign';

/**
 * Fired after an element was cleaned by the removeFormat plugin.
 * @name CKEDITOR#removeFormatCleanup
 * @event
 * @param {Object} data.element The element that was cleaned up.
 */