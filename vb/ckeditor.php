<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

require_once DIR . '/clientscript/ckeditor/ckeditor_php5.php';

/**
 * vBulletin wrapper for the ckeditor client
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 37905 $
 * @since $Date: 2010-07-22 17:30:10 -0700 (Thu, 22 Jul 2010) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Ckeditor extends CKEditor
{
	protected static $instances = array();

	protected $vbphrase;
	protected $vbphrasegroup;
	protected $stylevar;
	protected $show;
	protected $editor_type;
	protected $contenttypeid;
	protected $contentid;
	protected $parentcontentid;
	protected $userid;

	public static function getInstance($editorid, $editor_type = null, $contenttypeid = '', $contentid = 0, $parentcontentid = 0, $userid = 0, $toolbartype = 2)
	{
		if (!isset(self::$instances[$editorid]) || !(self::$instances[$editorid] instanceof self))
		{
			self::$instances[$editorid] = new self($editor_type, $contenttypeid, $contentid, $parentcontentid, $userid, $toolbartype);
		}
		return self::$instances[$editorid];
	}

	public function __construct($editor_type, $contenttypeid, $contentid, $parentcontentid, $userid, $toolbartype)
	{
		global $stylevar, $vbphrase, $vbphrasegroup, $show;

		$this->vbphrasegroup = $vbphrasegroup;
		$this->vbphrase = $vbphrase;
		$this->stylevar = $stylevar;
		$this->show = $show;
		$this->contenttypeid = $contenttypeid;
		$this->contentid = $contentid;
		$this->parentcontentid = $parentcontentid;
		$this->userid = $userid;
		$this->editor_type = $editor_type;
		$this->returnOutput = true;
		$this->toolbartype = $toolbartype;

		$this->setGlobalConfig();
		$this->setToolbar($toolbartype);

		// To add plugins, do this in the editor_construct hook:
		//$this->config['_extraPlugins'] .= ',splitquote';
		// To remove plugins:
		//$this->config['_removePlugins'] .= ',contextmenu';

		($hook = vBulletinHook::fetch_hook('editor_construct')) ? eval($hook) : false;

		parent::__construct(vB::$vbulletin->options['bburl'] . '/clientscript/ckeditor/');
	}

	/**
	 * Updates $show toolbar options to display
	 */
	public function setShow($show)
	{
		$this->show = $show;
	}
	
	/**
	 * Toolbar is off
	 */
	public function setNoBbcode()
	{
		$this->config['nobbcode'] = true;		
	}

	/**
	 * Set extra information for the ajax calls
	 */
	public function setAjaxExtra($value)
	{
		if ($value)
		{
			$this->config['ajax_extra'] = $value;
		}
	}
	
	public static function getJsIncludes()
	{
		$out = '<script type="text/javascript" src="clientscript/ckeditor/ckeditor.js?t=A7HG4HT&amp;v=' . vB::$vbulletin->options['simpleversion'] . '"></script>';
		$out .= '<script type="text/javascript" src="clientscript/vbulletin_textedit.js?v=' . vB::$vbulletin->options['simpleversion'] . '"></script>';

		return $out;
	}

	/**
	 * Sets dynamic configuration options that should be used on all vbulletin editors
	 * These options get serialized to JSON and passed to the the editor instance as config options
	 */
	protected function setGlobalConfig()
	{
		$this->config['customConfig'] = vB::$vbulletin->options['bburl'] . '/clientscript/ckeditor_config.js?v=' . vB::$vbulletin->options['simpleversion'];
		$this->config['contentsCss'] = vB::$vbulletin->options['bburl'] . '/' . vB_Template::fetch_css_path() . 'editor_contents.css';
		$this->config['vbulletin'] = array(
			'securitytoken' => vB::$vbulletin->userinfo['securitytoken'],
			'bburl'         => vB::$vbulletin->options['bburl'],
			'bbcodecache'   => vB::$vbulletin->bbcodecache,
		);

		$this->config['vbulletin']['lastphraseupdate'] = intval($this->vbphrasegroup['ckeditor']['maxdateline']);
		$this->config['vbulletin']['contenttypeid'] = $this->contenttypeid;
		$this->config['vbulletin']['contentid'] = intval($this->contentid);
		$this->config['vbulletin']['parentcontentid'] = intval($this->parentcontentid);
		$this->config['vbulletin']['userid'] = intval($this->userid);;

		$postautosave = intval(vB::$vbulletin->options['postautosave']);
		if ($postautosave AND $postautosave < 20)
		{
			$postautosave = 20;
		}
		$this->config['vbulletin']['postautosave'] = $postautosave;

		$this->config['vbulletin']['phrase'] = array(
			'attachments'                                   => $this->vbphrase['attachments'],
			'wrap_quote_tags'                               => $this->vbphrase['wrap_quote_tags'],
			'wrap_html_tags'                                => $this->vbphrase['wrap_html_tags'],
			'wrap_php_tags'                                 => $this->vbphrase['wrap_php_tags'],
			'wrap_code_tags'                                => $this->vbphrase['wrap_code_tags'],
			'wrap_x_tags'                                   => $this->vbphrase['wrap_x_tags'],
			'enter_tag_option'                              => $this->vbphrase['enter_tag_option'],
			'enter_video_url_below'                         => $this->vbphrase['enter_video_url_below'],
			'example_video_url'                             => $this->vbphrase['example_video_url'],
			'insert_video'                                  => $this->vbphrase['insert_video'],
			'insert_video_clip'                             => $this->vbphrase['insert_video_clip'],
			'supported_videos'                              => $this->vbphrase['supported_videos'],
			'insert_preview_break'                          => $this->vbphrase['insert_preview_break'],
			'insert_page_break'                             => $this->vbphrase['insert_page_break'],
			'this_message_already_contains_a_preview_break' => $this->vbphrase['this_message_already_contains_a_preview_break'],
			'switch_editor_mode'                            => $this->vbphrase['switch_editor_mode'],
			'enter_link_url'                                => $this->vbphrase['enter_link_url'],
			'enter_list_item'                               => $this->vbphrase['enter_list_item'],
			'iespell_not_installed'							=> $this->vbphrase['iespell_not_installed'],
		);

		$this->config['language'] = vB::$vbulletin->userinfo['lang_code'];
		if (vB::$vbulletin->stylevars['textdirection']['string'] == 'rtl')
		{
			$this->config['contentsLangDirection'] = 'rtl';
		}

		$this->config['smiley_images'] = array();
		$this->config['smiley_descriptions'] = array();
		$this->config['indentOffset'] = EDITOR_INDENT;

		$this->setEditorMode();
	}

	/**
	 * Configures the attachinfo needed for the editor
	 */
	public function setAttachInfo($attachinfo)
	{
		if (!empty($attachinfo))
		{
			$this->config['vbulletin']['attachinfo'] = $attachinfo;
			$this->config['vbulletin']['attachinfo']['attachlimit'] = vB::$vbulletin->options['attachlimit'];
			$this->config['vbulletin']['attachinfo']['advimagepopup'] = vB::$vbulletin->options['advimagepopup_enable'];
			$this->config['vbulletin']['attachinfo']['values']['theend'] = 'fin';
			foreach ($this->config['vbulletin']['attachinfo']['values'] AS $key => $value)
			{
				$this->config['vbulletin']['attachinfo']['valuestring'] .= '&values[' . $key . ']=' . $value;
			}
		}
	}

	/**
	 * Configures the editor to start up in wysiwyg, source, or basic modes
	 * default is wysiwyg
	 */
	protected function setEditorMode()
	{
		if ($this->toolbartype == 0)
		{
			$this->config['startupMode'] = 'enhancedsource';
			$this->config['toolbarStartupExpanded'] = false;
			$this->config['resize_enabled'] = true;
		}
		else if ($this->toolbartype == 1)
		{
			$this->config['startupMode'] = 'enhancedsource';
		}
		$this->config['editorMode'] = $this->toolbartype;
	}

	protected function addCustomToolbarButtons()
	{
		$rows = count($this->config['toolbar']);
		$custom = array();
		if (vB::$vbulletin->bbcodecache !== null AND $this->show['custom_bbcode'])
		{
			foreach (vB::$vbulletin->bbcodecache as $bbcode)
			{
				if ($bbcode['buttonimage'])
				{
					$custom[] = $bbcode['title'];
				}
			}

			if (count($custom) > 0)
			{
				$this->config['toolbar'][$rows - 1][] = '-';
				foreach($custom as $title)
				{
					$handled = false;
					($hook = vBulletinHook::fetch_hook('editor_custom_toolbar_loop')) ? eval($hook) : false;

					if (!$handled)
					{
						$this->config['toolbar'][$rows - 1][] = $title;
					}
				}
			}

		}
	}

	/**
	 * Sets the toolbar options in the editor based on the editor type requested
	 *
	 * @var	int	Toolbar type 0 - Simple / 1 - Standard / 2 - WYSIWYG
	 */
	protected function setToolbar($toolbartype)
	{
		$toolbar = array();
		$iespell = (is_browser('ie') AND !is_browser('ie64bit') AND !is_browser('mac'));
		$justify = (vB::$vbulletin->stylevars['textdirection']['string'] == 'ltr' ? array('JustifyLeft', 'JustifyCenter', 'JustifyRight') : array('JustifyRight', 'JustifyCenter', 'JustifyLeft'));
		if ($this->editor_type == 'qr')
		{
			$toolbar[] = array('RemoveFormat', '-', 'PasteText', '-', 'Bold', 'Italic', 'Underline', '-', 'Font', 'FontSize', '-', 'TextColor', '-', 'Smiley', '-', 'Link', 'Email', 'Unlink', 'Image', 'Video', '-', 'Quote');
			if ($iespell)
			{
				$toolbar[] = array('iespell');
			}
			$this->config['toolbar'] = $toolbar;
		}
		else if ($this->editor_type == 'qr_small')
		{
			$toolbar[] = array('RemoveFormat', '-', 'PasteText', '-', 'Bold', 'Italic', 'Underline', '-', 'Font', 'FontSize', '-', 'TextColor', '-', 'Smiley', '-', 'Link', 'Email', 'Unlink', 'Image', 'Video');
			if ($iespell)
			{
				$toolbar[] = array('iespell');
			}
			$this->config['toolbar'] = $toolbar;
		}
		else if ($this->editor_type == 'qe')
		{
			$toolbar[] = array('RemoveFormat', '-', 'PasteText', '-', 'Bold', 'Italic', 'Underline', '-', 'Font', 'FontSize', '-', 'TextColor', '-', 'Smiley', '-', 'Link', 'Email', 'Unlink', 'Image', 'Video', '-', 'Quote');
			if ($iespell)
			{
				$toolbar[] = array('iespell');
			}
			$this->config['toolbar'] = $toolbar;
		}
		else if ($this->editor_type == 'cms_article')
		{
			$toolbar[] = array('RemoveFormat', '-', 'PasteText', 'PasteFromWord', '-', 'Font', 'FontSize', '-', 'TextColor', '-', 'Smiley', '-', 'Attach', '-', 'Undo', 'Redo');
			if ($iespell)
			{
				$toolbar[] = array('iespell');
			}
			$toolbar[] = '/';
			$toolbar[] = array('Bold', 'Italic', 'Underline');
			$toolbar[] = $justify;
			$toolbar[] = array('NumberedList', 'BulletedList', 'Outdent', 'Indent');
			$toolbar[] = array('Link', 'Email', 'Unlink', 'Image', 'Video');
			$toolbar[] = array('Quote');
			$toolbar[] = array('Code', 'Html', 'Php');
			$toolbar[] = '/';
			$toolbar[] = array('Table', 'TableProperties', 'DeleteTable', '-', 'InsertRowBefore', 'InsertRowAfter', 'DeleteRow', '-', 'InsertColumnBefore', 'InsertColumnAfter', 'DeleteColumn', '-', 'Subscript', 'Superscript', 'HorizontalRule', 'PageBreak', 'PreviewBreak');

			$this->config['toolbar'] = $toolbar;
			$this->addCustomToolbarButtons();
		}
		else
		{
			$toolbar[] = array('RemoveFormat', '-', 'PasteText', 'PasteFromWord', '-', 'Font', 'FontSize', '-', 'TextColor', '-', 'Smiley', '-', 'Attach', '-', 'Undo', 'Redo');
			if ($iespell)
			{
				$toolbar[] = array('iespell');
			}
			$toolbar[] = '/';
			$toolbar[] = array('Bold', 'Italic', 'Underline');
			$toolbar[] = $justify;
			$toolbar[] = array('NumberedList', 'BulletedList', 'Outdent', 'Indent');
			$toolbar[] = array('Link', 'Email', 'Unlink', 'Image', 'Video');
			$toolbar[] = array('Quote');
			$toolbar[] = array('Code', 'Html', 'Php');
			$toolbar[] = '/';
			$toolbar[] = array('Table', 'TableProperties', 'DeleteTable', '-', 'InsertRowBefore', 'InsertRowAfter', 'DeleteRow', '-', 'InsertColumnBefore', 'InsertColumnAfter', 'DeleteColumn', '-', 'Subscript', 'Superscript', 'HorizontalRule');
			
			$this->config['toolbar'] = $toolbar;
			$this->addCustomToolbarButtons();
		}

		switch ($this->editor_type)
		{
			case 'fe':
			case 'qr':
			case 'qe':
				$editormode = $this->editor_type;
				break;
			case 'qr_small':
				$editormode = 'qr';
				break;
			default:
				$editormode = 'fe';
		}

		// unserialize the option if we need to
		if (!is_array( vB::$vbulletin->options['editormodes_array']))
		{
			 vB::$vbulletin->options['editormodes_array'] = unserialize( vB::$vbulletin->options['editormodes']);
		}

		if ($toolbartype == 2 OR ($toolbartype == 1 AND vB::$vbulletin->options['editormodes_array'][$editormode] == 2))
		{
			array_unshift($this->config['toolbar'][0], '-');
			array_unshift($this->config['toolbar'][0], 'EnhancedSource');
		}
	}

	/**
	 * Sets autofocus of editor on/off
	 *
	 * @param bool
	 */
	public function setEditorAutoFocus($autofocus)
	{
		$this->config['autofocus'] = $autofocus;
	}

	/**
	 * Sets the type of the editor (Quick Reply, Quick Edit, etc)
	 *
	 * @param string	acronym/abbreviation of editor type
	 */
	public function setEditorType($type)
	{
		$this->config['vbtype'] = $type;
	}

	/**
	 * Sets the Content font type
	 *
	 * @param string
	 */
	public function setContentFontType($content)
	{
		$this->config['bodyClass'] = $content;
	}

	/**
	 * Sets the autoloaded text and title
	 *
	 * @param string
	 * @param string
	 * @param string
	 */
	public function setAutoLoad($text, $autoloadtitle, $autoloadtitleid)
	{
		$this->config['autoload'] = $text;
		$this->config['autoloadtitle'] = $autoloadtitle;
		$this->config['autoloadtitleid'] = $autoloadtitleid;
	}

	/**
	 * Sets the parsetype
	 *
	 * @param string
	 */
	public function setEditorParsetype($parsetype)
	{
		$this->config['parsetype'] = $parsetype;
	}

	/**
	 * Sets the height of the editor
	 *
	 * @param $editor_height (int) The height of the editor in pixels
	 */
	public function setEditorHeight($editor_height)
	{
		$this->config['height'] = $editor_height;
	}

	/**
	 * Adds a smilie to the editor
	 *
	 * @param $smilie (array) The smilie row from the database, must contain smiliepath and smilietext
	 */
	public function addSmilie($smilie)
	{
		$this->config['smiley_images'][] = $smilie['smiliepath'];
		$this->config['smiley_descriptions'][] = htmlspecialchars_uni($smilie['smilietext']);
	}

	/**
	 * Adds an array of fonts to the editor
	 *
	 * @param $fonts (array) an array of font names to use in the editor
	 */
	public function addFonts($fonts)
	{
		$this->config['font_names'] = implode(';', $fonts);
	}

	/**
	 * Adds a smilie to the editor
	 *
	 * @param $fonts (array) an array of font names to use in the editor
	 */
	public function addFontSizes($sizes)
	{
		$this->config['fontSize_sizes'] = implode(';', $sizes);
	}

	/**
	 * Creates a %CKEditor instance.
	 * In incompatible browsers %CKEditor will downgrade to plain HTML &lt;textarea&gt; element.
	 *
	 * @param $name (string) Name of the %CKEditor instance (this will be also the "name" attribute of textarea element).
	 * @param $value (string) Initial value (optional).
	 * @param $config (array) The specific configurations to apply to this editor instance (optional).
	 * @param $events (array) Event listeners for this editor instance (optional).
	 */
	public function editor($id, $value = "", $config = array(), $events = array())
	{
		$attr = "";
		foreach ($this->textareaAttributes as $key => $val)
		{
			$attr .= " " . $key . '="' . str_replace('"', '&quot;', $val) . '"';
		}

		$out = $_POST['ajax'] ? '' : self::getJsIncludes();

		$out .= "<textarea id=\"{$id}_editor\" name=\"message\"" . $attr . " tabindex=\"1\">" . htmlspecialchars($value) . "</textarea>\n";
		if (!$this->initialized)
		{
			//$out .= $this->init();
		}

		if (!$_POST['ajax'])
		{
			$_config = $this->configSettings($config, $events);
			$js = $this->returnGlobalEvents();
			if (!empty($_config))
			{
				$js .= "vB_Editor['$id'] = new vB_Text_Editor('" . $id . "', " . $this->jsEncode($_config) . ");";
			}
			else
			{
				$js .= "vB_Editor['$id'] = new vB_Text_Editor('" . $id . "');";
			}

			$out .= $this->script($js);
		}

		($hook = vBulletinHook::fetch_hook('editor_output_html')) ? eval($hook) : false;

		if (!$this->returnOutput)
		{
			print $out;
			$out = "";
		}

		return $out;
	}

	/**
	 * Filters out toolbar options that aren't allowed
	 *
	 */
	protected function filterToolbar()
	{
		$toolbar = $this->config['toolbar'];
		foreach ($toolbar as $i => $row)
		{
			if (is_array($row))
			{
				if (!$this->show['basic_bbcode'])
				{
					$row = array_diff($row, array('Bold', 'Italic', 'Underline', 'Table', 'TableProperties', 'DeleteTable', 'InsertRowBefore', 'InsertRowAfter', 'DeleteRow', 'InsertColumnBefore', 'InsertColumnAfter', 'DeleteColumn', 'Subscript', 'Superscript', 'HorizontalRule', 'PageBreak', 'PreviewBreak'));
				}
				if (!$this->show['font_bbcode'])
				{
					$row = array_diff($row, array('Font'));
				}
				if (!$this->show['size_bbcode'])
				{
					$row = array_diff($row, array('FontSize'));
				}
				if (!$this->show['color_bbcode'])
				{
					$row = array_diff($row, array('TextColor'));
				}
				if (!$this->show['url_bbcode'])
				{
					$row = array_diff($row, array('Link', 'Email', 'Unlink'));
				}
				if (!$this->show['img_bbcode'])
				{
					$this->config['vbulletin']['no_img_bbcode'] = true;
					$row = array_diff($row, array('Video'));
				}
				if ((!$this->config['vbulletin']['attachinfo'] OR !$this->config['vbulletin']['attachinfo']['contenttypeid']) AND !$this->show['img_bbcode'])
				{
					$row = array_diff($row, array('Image'));
				}
				if (!$this->show['quote_bbcode'])
				{
					$row = array_diff($row, array('Blockquote'));
				}
				if (!$this->show['align_bbcode'])
				{
					$row = array_diff($row, array('JustifyLeft', 'JustifyCenter', 'JustifyRight'));
				}
				if (!$this->show['list_bbcode'])
				{
					$row = array_diff($row, array('NumberedList', 'BulletedList'));
				}
				if (!$this->show['align_bbcode'] AND !$this->show['list_bbcode'])
				{
					$row = array_diff($row, array('Outdent', 'Indent'));
				}
				if (!$this->show['wysiwygsmilies'])
				{
					$row = array_diff($row, array('Smiley'));
				}
				if (!$this->show['attach'])
				{
					$row = array_diff($row, array('Attach'));
				}
				if (!$this->show['quote_bbcode'])
				{
					$row = array_diff($row, array('Quote'));
				}
				if (!$this->show['code_bbcode'])
				{
					$row = array_diff($row, array('Code'));
				}
				if (!$this->show['html_bbcode'])
				{
					$row = array_diff($row, array('Html'));
				}
				if (!$this->show['php_bbcode'])
				{
					$row = array_diff($row, array('Php'));
				}

				if ($this->config['nobbcode'])
				{
					$row = array_diff($row, array('PasteText', 'PasteFromWord', 'RemoveFormat', 'EnhancedSource'));
				}

				$toolbar[$i] = array_values($row);
			}
		}

		($hook = vBulletinHook::fetch_hook('editor_toolbar_filter')) ? eval($hook) : false;

		$this->config['toolbar'] = $toolbar;
	}

	/**
	 * Returns an editor that corresponds to the appropriate features required
	 *
	 * @param $id (string) Name of the %CKEditor instance (this will be also the "name" attribute of textarea element).
	 * @param $value
	 */
	public function getEditor($id, $value)
	{
		$this->filterToolbar();
		return $this->editor($id, $value, $config);
	}

	/**
	 * Returns a json encoded string with the config options for this editor
	 */
	public function getConfig()
	{
		return $this->jsEncode($this->config);
	}
}