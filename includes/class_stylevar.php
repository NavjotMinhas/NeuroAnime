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

/**
* Class to handle style variable storage
*
* @package	vBulletin
* @version	$Revision: 35346 $
* @date		$Date: 2010-02-05 14:51:56 -0800 (Fri, 05 Feb 2010) $
*/

abstract class vB_StyleVar
{
	public $registry;

	public $stylevarid;

	protected $definition;
	protected $value;
	protected $inherited = 0;		// used to set the color, 0 = unchanged, 1 = inherited from parent, -1 = customized in this style

	private $styleid = -1;

	// static variables for printing color input rows
	protected static $need_colorpicker = true;
	protected static $count = 0;

	// only output the background preview js once
	protected static $need_background_preview_js = true;

	//abstract public function validate();
	function validate()
	{
		return true;
	}

	public function print_editor()
	{
		global $vbulletin, $vbphrase;

		$header = $vbphrase["stylevar_{$this->stylevarid}_name"] ? $vbphrase["stylevar_{$this->stylevarid}_name"] : $this->stylevarid;

		$addbit = false;
		if ($vbulletin->GPC['dostyleid'] == -1)
		{
			$header .= ' - <span class="smallfont">' . construct_link_code($vbphrase['edit'], "stylevar.php?" . $vbulletin->session->vars['sessionurl'] . "do=dfnedit&amp;stylevarid=" . $this->stylevarid);;
			$addbit = true;
		}

		if ($this->inherited == -1)
		{
			if (!$addbit)
			{
				$header .= ' - <span class="smallfont">';
			}
			else
			{
				$header .= ' - ';
			}
			$header .= (count($header) == 1 ? '<span class="smallfont">' : '') . construct_link_code($vbphrase['revert'], "stylevar.php?" . $vbulletin->session->vars['sessionurl'] . "do=confirmrevert&amp;dostyleid=" . $vbulletin->GPC['dostyleid'] . "&stylevarid=" . $this->stylevarid . "&rootstyle=-1");
		}

		if ($addbit)
		{
			$header .= '</span>';
		}

		print_table_header($header);

		if ($vbphrase["stylevar_{$this->stylevarid}_description"])
		{
			print_description_row($vbphrase["stylevar_{$this->stylevarid}_description"], false, 2);
		}

		// once we have LSB change this to self::
		$this->print_editor_form();
	}

	abstract public function print_editor_form();

	public function set_value($value)
	{
		$this->value = $value;
		//$this->validate();
	}

	public function set_definition($definition)
	{
		$this->definition = $definition;
	}

	public function set_inherited($inherited)
	{
		$this->inherited = $inherited;
	}

	public function set_stylevarid($stylevarid)
	{
		$this->stylevarid = $stylevarid;
	}

	public function set_styleid($styleid)
	{
		$this->styleid = $styleid;
	}

	public function get()
	{
		return ($this->value);
	}

	protected function fetch_inherit_color()
	{
		switch($this->inherited)
		{
			case 0:
				$class = 'col-g';
				break;

			case 1:
				$class = 'col-i';
				break;

			case -1:
			default:
				$class = 'col-c';
				break;
		}
		return $class;
	}

	public function build()
	{
		if (!is_array($this->value))
		{
			$this->value = array($this->value);
		}

		$value = serialize($this->value);
		$this->registry->db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "stylevar
			(stylevarid, styleid, value, dateline, username)
			VALUE
			(
				'" . $this->registry->db->escape_string($this->stylevarid) . "',
				" . intval($this->styleid) . ",
				'" . $this->registry->db->escape_string($value) . "',
				" . TIMENOW . ",
				'" . $this->registry->db->escape_string($this->registry->userinfo['username']) . "'
			)
		");
	}

	public function print_units($current_units)
	{
		global $vbphrase;
		$svunitsarray = array(
			'' => '',
			'%' => '%',
			'px' => 'px',
			'pt' => 'pt',
			'em' => 'em',
			'ex' => 'ex',
			'pc' => 'pc',
			'in' => 'in',
			'cm' => 'cm',
			'mm' => 'mm'
		);

		print_select_row($vbphrase['units'], 'stylevar[' . $this->stylevarid . '][units]', $svunitsarray, $current_units);
	}

	protected function print_color_input_row($title, $name, $value)
	{
		$cp = "";

		//only include the colorpicker on the first color element.
		if (self::$need_colorpicker)
		{
			//construct all of the markup/javascript for the color picker.
			global $vbulletin;

			//set from construct_color_picker
			global $colorPickerWidth, $colorPickerType;

			$cp = '<script type="text/javascript" src="' . $vbulletin->options['bburl'] .
				'/clientscript/vbulletin_cpcolorpicker.js?v=' .
				$vbulletin->options['simpleversion'] . '"></script>' . "\n";
			$cp .= construct_color_picker(11);

			$js_phrases[] = array();
			foreach (array(
				'css_value_invalid',
				'color_picker_not_ready',
			) AS $phrasename)
			{
				$js_phrases[] = "vbphrase.$phrasename = \"" . fetch_js_safe_string($vbphrase["$phrasename"]) . "\"";
			}

			$js_phrases = implode(";\r\n\t", $js_phrases) . ";\r\n";

			$cp .= '
					<script type="text/javascript">
					<!--
					var bburl = "' . $vbulletin->options['bburl'] .'";
					var cpstylefolder = "' . $vbulletin->options['cpstylefolder'] .'";
					var colorPickerWidth = ' . intval($colorPickerWidth) . ';
					var colorPickerType = ' . intval($colorPickerType) . ';
					if(vbphrase == undefined) {vbphrase = new Object();}
					' . $js_phrases . '

					vBulletin.events.systemInit.subscribe(function()
					{
						init_color_preview();
					});
					var cleargifurl = "' .$vbulletin->options['cleargifurl'] .'";
					//-->
				</script>';

			self::$need_colorpicker = false;
		}

		$id = 'color_'. self::$count;

		$color_preview = '<div id="preview_' . self::$count .
			'" class="colorpreview" onclick="open_color_picker(' . self::$count . ', event)"></div>';

		$title_attr = ($vbulletin->debug ? " title=\"name=&quot;$name&quot;\"" : '');
		$cell =
			"<div id=\"ctrl_$name\" class=\"color_input_container\">" .
				"<input type=\"text\" name=\"$name\" id=\"$id\" " .
					"value=\"$value\" " .
					"tabindex=\"1\" $title_attr />" .
			"</div>";

		print_label_row(
			$title,
			$cp . $cell . $color_preview,
			'', 'top', $name
		);

		self::$count++;
	}

	protected function print_background_output()
	{
		global $vbphrase;
		$image = $this->value['image'];

		if ($this->value['image'] != "" && !strpos($image,'http://'))  /* see if image location is relative or not */
		{
			 /* this assumes that the images folder is stored in root directory might need to fix this one day */
			$image = str_replace('(','(../',$image);
		}

		$background_preview_js = '';
		if (self::$need_background_preview_js)
		{
			$background_preview_js = '
				<script type="text/javascript">
				<!--
					function previewBackground(stylevar)
					{
						/**
						 * @param	string	name of the stylevar
						 * @param	string	the item you want to fetch (color, background image, repeat, etc)
						 * @return	string	the value from the form element
						 */
						var fetch_form_element_value = function(stylevar, item)
						{
							var wrapperid = "ctrl_stylevar[" + stylevar + "][" + item + "]";
							var wrapper = YAHOO.util.Dom.get(wrapperid);

							// input for color, image, and offsets
							var formel = wrapper.getElementsByTagName("input");
							if (formel && formel[0])
							{
								return formel[0].value;
							}

							// select for background repeat and units
							formel = wrapper.getElementsByTagName("select");
							if (formel && formel[0])
							{
								return formel[0].value;
							}
						};

						var backgroundString = "";
						backgroundString += fetch_form_element_value(stylevar, "color");

						// Fix the image path. This assumes the images folder is stored in root directory
						var image_path = fetch_form_element_value(stylevar, "image");
						var abs_image_path = image_path.match(/^https?:\/\//i);
						if (!abs_image_path)
						{
							image_path = image_path.replace("(", "(../");
						}
						backgroundString += " " + image_path;

						backgroundString += " " + fetch_form_element_value(stylevar, "repeat");

						var offset_units = fetch_form_element_value(stylevar, "units");
						backgroundString += " " + fetch_form_element_value(stylevar, "x") + offset_units;
						backgroundString += " " + fetch_form_element_value(stylevar, "y") + offset_units;

						YAHOO.util.Dom.get("preview_bg_" + stylevar).style.background = backgroundString;
					}
				-->
				</script>';
			self::$need_background_preview_js = false;
		}

		$cell = "
			<div id=\"preview_bg_" . $this->stylevarid . "\" style=\"
				background: " . $this->value['color'] .
				" " . $image .
				" " . $this->value['repeat'] .
				" " . $this->value['x'] . $this->value['units'].
				" " . $this->value['y'] . $this->value['units'].
				";width:100%;height:30px;border:1px solid #000000;\">
			</div>";

		$label = '<a href="javascript:previewBackground(\'' . $this->stylevarid . '\');">'. $vbphrase['click_here_to_preview'] .' </a>';
		print_label_row($label, $background_preview_js . $cell);
	}
}

class vB_StyleVar_default extends vB_StyleVar
{
	private $datatype;

	public function __construct($datatype)
	{
		$this->datatype = $datatype;
	}

	public function print_editor_form()
	{
		global $vbphrase;

		// imagedir, url, path, and string are technically all just strings
		switch ($this->datatype)
		{
			case 'string':
				print_input_row($vbphrase['string'], 'stylevar[' . $this->stylevarid . ']', $this->value[$this->datatype], true);
			break;

			case 'url':
				print_input_row($vbphrase['url'], 'stylevar[' . $this->stylevarid . ']', $this->value[$this->datatype], true);
			break;

			case 'imagedir':
				print_input_row($vbphrase['image_path'], 'stylevar[' . $this->stylevarid . ']', $this->value[$this->datatype], true);
			break;

			case 'path':
				print_input_row($vbphrase['path'], 'stylevar[' . $this->stylevarid . ']', $this->value[$this->datatype], true);
			break;

			case 'numeric':
				print_input_row($vbphrase['numeric'], 'stylevar[' . $this->stylevarid . ']', $this->value[$this->datatype], true);
			break;

			case 'size':
				$this->print_units($this->value['units']);
				print_input_row($vbphrase['size'], 'stylevar[' . $this->stylevarid . '][size]', $this->value['size'], true);
			break;

			case 'fontlist':
				print_textarea_row($vbphrase['fontlist'], 'stylevar[' . $this->stylevarid . ']', $this->value[$this->datatype], 20, 40, true);
			break;
		}
	}
}

class vB_StyleVar_padding extends vB_StyleVar
{
	public function print_editor_form()
	{
		global $vbphrase;
		$this->print_units($this->value['units']);
		print_yes_no_row($vbphrase['use_same_padding_margin'], 'stylevar[' . $this->stylevarid . '][same]', $this->value['same']);
		print_input_row($vbphrase['top'], 'stylevar[' . $this->stylevarid . '][top]', $this->value['top'], true);
		print_input_row($vbphrase['right'], 'stylevar[' . $this->stylevarid . '][right]', $this->value['right'], true);
		print_input_row($vbphrase['bottom'], 'stylevar[' . $this->stylevarid . '][bottom]', $this->value['bottom'], true);
		print_input_row($vbphrase['left'], 'stylevar[' . $this->stylevarid . '][left]', $this->value['left'], true);
	}
}

class vB_StyleVar_margin extends vB_StyleVar
{
	public function print_editor_form()
	{
		global $vbphrase;

		$this->print_units($this->value['units']);
		print_yes_no_row($vbphrase['use_same_padding_margin'], 'stylevar[' . $this->stylevarid . '][same]', $this->value['same']);
		print_input_row($vbphrase['top'], 'stylevar[' . $this->stylevarid . '][top]', $this->value['top'], true);
		print_input_row($vbphrase['right'], 'stylevar[' . $this->stylevarid . '][right]', $this->value['right'], true);
		print_input_row($vbphrase['bottom'], 'stylevar[' . $this->stylevarid . '][bottom]', $this->value['bottom'], true);
		print_input_row($vbphrase['left'], 'stylevar[' . $this->stylevarid . '][left]', $this->value['left'], true);
	}
}

class vB_StyleVar_textdecoration extends vB_StyleVar
{
	public function print_editor_form()
	{
		global $vbphrase;
		// needs checked
		print_yes_no_row($vbphrase['none'], 'stylevar[' . $this->stylevarid . '][none]', $this->value['none']);
		print_yes_no_row($vbphrase['underline'], 'stylevar[' . $this->stylevarid . '][underline]', $this->value['underline']);
		print_yes_no_row($vbphrase['overline'], 'stylevar[' . $this->stylevarid . '][overline]', $this->value['overline']);
		print_yes_no_row($vbphrase['linethrough'], 'stylevar[' . $this->stylevarid . '][line-through]', $this->value['line-through']);
		print_yes_no_row($vbphrase['blink'], 'stylevar[' . $this->stylevarid . '][blink]', $this->value['blink']);
	}
}

class vB_StyleVar_font extends vB_StyleVar
{
	public function print_editor_form()
	{
		global $vbphrase;

		$font_weights = array(
			'' => '',
			'normal' => $vbphrase['normal'],
			'bold' => $vbphrase['bold'],
			'bolder' => $vbphrase['bolder'],
			'lighter' => $vbphrase['lighter'],
		);

		$font_styles = array(
			'' => '',
			'normal' => $vbphrase['normal'],
			'italic' => $vbphrase['italic'],
			'oblique' => $vbphrase['oblique'],
		);

		$font_variants = array(
			'' => '',
			'normal' => $vbphrase['normal'],
			'small-caps' => $vbphrase['small_caps'],
		);

		// TODO: add repeat, offset
		print_input_row($vbphrase['font_family'], 'stylevar[' . $this->stylevarid . '][family]', $this->value['family'], true);

		$this->print_units($this->value['units']);
		print_input_row($vbphrase['font_size'], 'stylevar[' . $this->stylevarid . '][size]', $this->value['size'], true);

		print_select_row($vbphrase['font_weight'], 'stylevar[' . $this->stylevarid . '][weight]', $font_weights, $this->value['weight']);
		print_select_row($vbphrase['font_style'], 'stylevar[' . $this->stylevarid . '][style]', $font_styles, $this->value['style']);
		print_select_row($vbphrase['font_variant'], 'stylevar[' . $this->stylevarid . '][variant]', $font_variants, $this->value['variant']);
	}
}

class vB_StyleVar_background extends vB_StyleVar
{

	public function print_editor_form()
	{
		global $vbphrase;

		$values = array(
			'' => '',
			'repeat' => $vbphrase['repeat'],
			'repeat-x' => $vbphrase['repeat_x'],
			'repeat-y' => $vbphrase['repeat_y'],
			'no-repeat' => $vbphrase['no_repeat'],
		);

		// TODO: add repeat, offset -- Offset Done
		$this->print_color_input_row($vbphrase['background_color'], 'stylevar[' . $this->stylevarid . '][color]', $this->value['color']);
		print_input_row($vbphrase['background_image'], 'stylevar[' . $this->stylevarid . '][image]', $this->value['image'], true);
		print_select_row($vbphrase['background_repeat'], 'stylevar[' . $this->stylevarid . '][repeat]', $values, $this->value['repeat']);
		$this->print_units($this->value['units']);
		print_input_row($vbphrase['background_position_x'], 'stylevar['.$this->stylevarid.'][x]',$this->value['x'],true);
		print_input_row($vbphrase['background_position_y'], 'stylevar['.$this->stylevarid.'][y]',$this->value['y'],true);
		$this->print_background_output();
	}

}

class vB_StyleVar_dimension extends vB_StyleVar
{
	public function print_editor_form()
	{
		global $vbphrase;

		$this->print_units($this->value['units']);
		print_input_row($vbphrase['width'], 'stylevar[' . $this->stylevarid . '][width]', $this->value['width'], true);
		print_input_row($vbphrase['height'], 'stylevar[' . $this->stylevarid . '][height]', $this->value['height'], true);
	}
}

class vB_StyleVar_border extends vB_StyleVar
{
	public function print_editor_form()
	{
		global $vbphrase;

		$this->print_units($this->value['units']);
		print_input_row($vbphrase['width'], 'stylevar[' . $this->stylevarid . '][width]', $this->value['width'], true);
		print_input_row($vbphrase['border_style'], 'stylevar[' . $this->stylevarid . '][style]', $this->value['style'], true);
		//print_input_row($vbphrase['color'], 'stylevar[' . $this->stylevarid . '][color]', $this->value['color'], true);
		$this->print_color_input_row($vbphrase['color'], 'stylevar[' . $this->stylevarid . '][color]', $this->value['color']);
	}
}

class vB_StyleVar_color extends vB_StyleVar
{
	public function print_editor_form()
	{
		global $vbphrase;

		$this->print_color_input_row($vbphrase['color'], 'stylevar[' . $this->stylevarid . '][color]', $this->value['color']);
	}
}

class vB_StyleVar_factory
{
	/**
	 * Creates a stylevar.
	 *
	 * @param string $type
	 * @return vB_StyleVar
	 */
	public static function create($type)
	{
		// not really a good factory, in fact, this is a dumb factory
		$stylevarobj = null;
		switch ($type)
		{
			case 'numeric':
			case 'string':
			case 'url':
			case 'imagedir':
			case 'image':
			case 'path':
			case 'fontlist':
			case 'size':
				$stylevarobj = new vB_StyleVar_default($type);
				break;

			case 'color':
				$stylevarobj = new vB_StyleVar_color();
				break;

			case 'background':
				$stylevarobj = new vB_StyleVar_background();
				break;

			case 'textdecoration':
				$stylevarobj = new vB_StyleVar_textdecoration();
				break;

			case 'font':
				$stylevarobj = new vB_StyleVar_font();
				break;

			case 'dimension':
				$stylevarobj = new vB_StyleVar_dimension();
				break;

			case 'border':
				$stylevarobj = new vB_StyleVar_border();
				break;

			case 'padding':
				$stylevarobj = new vB_StyleVar_padding();
				break;

			case 'margin':
				$stylevarobj = new vB_StyleVar_margin();
				break;

			default:
				trigger_error("Unknown Data Type ( Type: " . $type . ")", E_USER_ERROR);
		}
		return $stylevarobj;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35346 $
|| ####################################################################
\*======================================================================*/