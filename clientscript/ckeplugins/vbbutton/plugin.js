(function()
{
	var disabled = {};
	var hookedup = {};
	
	function clickLimitButtonHookup(button)
	{
		button.button.click = CKEDITOR.tools.override(button.button.click, function(original)
		{
			return function(editor)
			{
				if (!disabled[button.id] || new Date().getTime() - disabled[button.id] > 1500)
				{
					disabled[button.id] = new Date().getTime();
					original.call(button.button, editor);
				}
			}
		});
	}
	
	function clickLimit()
	{
		for (var index in CKEDITOR.ui.button._.instances)
		{
			if (!hookedup[CKEDITOR.ui.button._.instances[index].id])
			{
				clickLimitButtonHookup(CKEDITOR.ui.button._.instances[index]);
				hookedup[CKEDITOR.ui.button._.instances[index].id] = 1;
			}
		}
	}
	
	CKEDITOR.plugins.add('vbbutton',
	{
		requires : [ 'button'],
		
		init : function(editor)
		{
			editor.on('instanceReady', clickLimit, this);
			
			if (CKEDITOR.env.ie)
			{
				CKEDITOR.ui.button.prototype.render = function( editor, output )
				{
					var env = CKEDITOR.env,
						id = this._.id = CKEDITOR.tools.getNextId(),
						classes = '',
						command = this.command, // Get the command name.
						clickFn,
						index;
		
					this._.editor = editor;
		
					var instance =
					{
						id : id,
						button : this,
						editor : editor,
						focus : function()
						{
							var element = CKEDITOR.document.getById( id );
							element.focus();
						},
						execute : function()
						{
							this.button.click( editor );
						}
					};
		
					instance.clickFn = clickFn = CKEDITOR.tools.addFunction( instance.execute, instance );
		
					instance.index = index = CKEDITOR.ui.button._.instances.push( instance ) - 1;

					// Indicate a mode sensitive button.
					if ( this.modes )
					{
						var modeStates = {};
						editor.on( 'beforeModeUnload', function()
							{
								modeStates[ editor.mode ] = this._.state;
							}, this );

						editor.on( 'mode', function()
							{
								var mode = editor.mode;
								// Restore saved button state.
								this.setState( this.modes[ mode ] ?
									modeStates[ mode ] != undefined ? modeStates[ mode ] :
										CKEDITOR.TRISTATE_OFF : CKEDITOR.TRISTATE_DISABLED );
							}, this);
					}
					else if ( command )
					{
						// Get the command instance.
						command = editor.getCommand( command );
		
						if ( command )
						{
							command.on( 'state', function()
								{
									this.setState( command.state );
								}, this);
		
							classes += 'cke_' + (
								command.state == CKEDITOR.TRISTATE_ON ? 'on' :
								command.state == CKEDITOR.TRISTATE_DISABLED ? 'disabled' :
								'off' );
						}
					}
		
					if ( !command )
						classes	+= 'cke_off';
		
					if ( this.className )
						classes += ' ' + this.className;
		
					output.push(
						'<span class="cke_button' + ( this.icon && this.icon.indexOf( '.png' ) == -1 ? ' cke_noalphafix' : '' ) + '">',
						'<a id="', id, '"' +
							' class="', classes, '"',
							env.gecko && env.version >= 10900 && !env.hc  ? '' : '" href="javascript:void(\''+ ( this.title || '' ).replace( "'", '' )+ '\')"',
							' title="', this.title, '"' +
							' tabindex="-1"' +
							' hidefocus="true"' +
						    ' role="button"' +
							' aria-labelledby="' + id + '_label"' +
							( this.hasArrow ?  ' aria-haspopup="true"' : '' ) );
		
					// Some browsers don't cancel key events in the keydown but in the
					// keypress.
					// TODO: Check if really needed for Gecko+Mac.
					if ( env.opera || ( env.gecko && env.mac ) )
					{
						output.push(
							' onkeypress="return false;"' );
					}
		
					// With Firefox, we need to force the button to redraw, otherwise it
					// will remain in the focus state.
					if ( env.gecko )
					{
						output.push(
							' onblur="this.style.cssText = this.style.cssText;"' );
					}
		
					output.push(
							' onkeydown="return CKEDITOR.ui.button._.keydown(', index, ', event);"' +
							' onfocus="return CKEDITOR.ui.button._.focus(', index, ', event);"' +
							' onclick="CKEDITOR.tools.callFunction(', clickFn, ', this); return false;">' +
								'<span class="cke_icon"' );
		
					if ( this.icon )
					{
						var offset = ( this.iconOffset || 0 ) * -16;
						output.push( ' style="background-image:url(', CKEDITOR.getUrl( this.icon ), ');background-position:0 ' + offset + 'px;"' );
						output.push(
									'><span class="cke_icon_image custom">&nbsp;</span></span>' +
									'<span id="', id, '_label" class="cke_label">', this.label, '</span>' );
					}
					else
					{					
						output.push(
									'><span class="cke_icon_image">&nbsp;</span></span>' +
									'<span id="', id, '_label" class="cke_label">', this.label, '</span>' );
					}
		
					if ( this.hasArrow )
					{
						output.push(
								'<span class="cke_buttonarrow">'
								// BLACK DOWN-POINTING TRIANGLE
								+ ( CKEDITOR.env.hc ? '&#9660;' : '&nbsp;' )
								+ '</span>' );
					}
		
					output.push(
						'</a>',
						'</span>' );
		
					if ( this.onRender )
						this.onRender();
		
					return instance;
				};
			}
		}
	});
	
})();