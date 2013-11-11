<?php if (!defined('VB_ENTRY')) die('Access denied.');
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
* Implementation of table BB code parsing outside of the normal class hierarchy.
* This is primarily to allow reuse in multiple places in the BB code parsing
* tree (eg, regular parsing and WYSIWYG parsing).
*
* @package	vBulletin
*/
class vBForum_BBCodeHelper_Table
{
	/**
	* Defines the list of paramaters that can be used in a table BB code.
	* Key is the name of the parameter. Its value is an array with any of these options:
	*	* regex - regex to validate against; ignored if not matched
	*	* callback - a callback function to validate against; can modify the param value
	*	* default - the default value; if specified, the attribute won't be added if its value equals the default
	*	* attribute - name of html attribute (defaults to key name)
	*	* css - name of css attribute; if specified, no html attribute will be outputted
	* Note that when a class is specified, a permutation of its name will be
	* "pushed down" to the levels below (eg, tr and td).
	*
	* @var	array
	*/
	protected $table_param_list = array(
		'width' => array(
			'regex' => '#^(\d+%?)$#'
		),
		'class' => array(
			'callback' => array('$this', 'validateTableClass')
		),
		'align' => array(
			'regex' => '#^(center|right|left)$#i'
		),
	);

	/**
	* Defines a list of params that can be used in a tr BB code.
	* Format is the same as $table_param_list.
	*
	* @var	array
	*/
	protected $tr_param_list = array(
		'bgcolor' => array(
			'regex' => '/^(#[0-9a-f]{3,6}|[a-z]+)$/i',
			'css' => 'background-color'
		),
		'class' => array(
			'callback' => array('$this', 'validateTableClass')
		)
	);

	/**
	* Defines a list of params that can be used in a td BB code.
	* Format is the same as $table_param_list.
	*
	* @var	array
	*/
	protected $td_param_list = array(
		'width' => array(
			'regex' => '#^(\d+%?)$#'
		),
		'colspan' => array(
			'default' => 1,
			'regex' => '#^\d+$#'
		),
		'bgcolor' => array(
			'regex' => '/^(#[0-9a-f]{3,6}|[a-z]+)$/i',
			'css' => 'background-color'
		),
		'class' => array(
			'callback' => array('$this', 'validateTableClass')
		),
		'align' => array(
			'regex' => '#^(center|right|left)$#i'
		),
	);

	/**
	* Prefix to apply to all classes used by the table/tr/td tags.
	* This prevents people from using completely arbitrary classes.
	*
	* @var	string
	*/
	protected $table_class_prefix = 'cms_table_';

	/**
	* Whether the output should include non-significant whitespace to aid
	* in formatting the HTML output. This will have no difference on the
	* displayed output.
	*
	* @var	bool
	*/
	protected $add_formatting_whitespace = true;

	/**
	*	Whether to wrap the output table with a div for markup purposes
	*
	*/
	protected $wrap_table = true;

	/**
	* The BB code parser invoking this object.
	*
	* @var	vB_BBCodeParser
	*/
	protected $parser = null;

	/**
	* Constructor
	*
	* @param	vB_BBCodeParser	Invoking BB code parser
	*/
	public function __construct(vB_BBCodeParser $parser)
	{
		$this->parser = $parser;
	}

	/**
	* Parses the [table] tag and returns the necessary HTML representation.
	* TRs and TDs are parsed by this function (they are not real BB codes).
	* Classes are pushed down to inner tags (TRs and TDs) and TRs are automatically
	* valigned top.
	*
	* @param	string	Content within the table tag
	* @param	string	Optional set of parameters in an unparsed format. Parses "param: value, param: value" form.
	*
	* @return	string	HTML representation of the table and its contents.
	*/
	public function parseTableTag($content, $params = '')
	{
		$table_params = $this->resolveNamedParams($params, $this->table_param_list, 'table');
		$table_params = $this->modifyTableParams($table_params);

		$output = $this->parseTableRows($content, $this->getClassList($table_params));
		if ($output === '')
		{
			return '';
		}

		if (!$table_params['attributes']['class'])
		{
			$table_params['attributes']['class'] = preg_replace('#_$#si', '', $this->table_class_prefix);
		}

		$table_params_html = $this->convertParamsToHtml($table_params['css'], $table_params['attributes']);

		$table = "<table$table_params_html>$output</table>";
		if ($this->wrap_table)
		{
			$table = "<div class=\"cms_table\">$table</div>";
		}

		return $table . ($this->add_formatting_whitespace ? "\n" : '');
	}

	/**
	* Helper method to allow modification of the paramaters for a table tag
	* before they are used in child tags or outputted.
	*
	* @param	array	Table parameters (in format of resolveNamedParams)
	*
	* @return	array	Table parameters modified if necessary
	*/
	protected function modifyTableParams(array $table_params)
	{
		return $table_params;
	}

	/**
	* Parses the rows of a table
	*
	* @param	string	Content within the table BB code
	* @param	array	List of classes applied to the table BB code (from getClassList)
	*
	* @return	string	HTML output of the rows
	*/
	protected function parseTableRows($content, array $table_classes)
	{
		if (!preg_match_all('#\[tr(=(&quot;|"|\'|)(?P<params>.*)\\2)?\](?P<content>.*)\[/tr]#siU', $content, $rows, PREG_SET_ORDER))
		{
			return '';
		}

		$output = '';

		foreach ($rows AS $row)
		{
			$row_params = $this->resolveNamedParams($row['params'], $this->tr_param_list, 'tr');
			$row_classes = $this->getClassList($row_params);
			$row_params = $this->appendClassList($row_params, $table_classes, 'tr');

			$cell_class_pushdown = array_merge($table_classes, $row_classes);

			$cell_output = $this->parseTableCells($row['content'], $cell_class_pushdown);

			if (!$row_params['attributes']['class'])
			{
				$row_params['attributes']['class'] = $this->table_class_prefix . 'tr';
			}

			$row_params_html = $this->convertParamsToHtml($row_params['css'], $row_params['attributes']);
			$output .= "<tr valign=\"top\"$row_params_html>$cell_output</tr>" . ($this->add_formatting_whitespace ? "\n" : '');
		}

		return $output;
	}

	/**
	* Parses the cells of a table row
	*
	* @param	string	Content within the tr BB code
	* @param	array	List of classes applied to the table and tr BB codes (from getClassList)
	*
	* @return	string	HTML output of the cells
	*/
	protected function parseTableCells($content, array $row_classes)
	{
		if (!preg_match_all('#\[td(=(&quot;|"|\'|)(?P<params>.*)\\2)?\](?P<content>.*)\[/td]#siU', $content, $cells, PREG_SET_ORDER))
		{
			return '';
		}

		$cell_output = '';

		foreach ($cells AS $cell)
		{
			$cell_params = $this->resolveNamedParams($cell['params'], $this->td_param_list, 'td');
			$cell_params = $this->appendClassList($cell_params, $row_classes, 'td');

			if (!$cell_params['attributes']['class'])
			{
				$cell_params['attributes']['class'] = $this->table_class_prefix . 'td';
			}

			$cell_params_html = $this->convertParamsToHtml($cell_params['css'], $cell_params['attributes']);

			$content = $this->modifyCellContent($cell['content']);

			$cell_output .= "<td$cell_params_html>$content</td>" . ($this->add_formatting_whitespace ? "\n" : '');
		}

		return $cell_output;
	}

	/**
	* Helper method to modify the cell content before it is placed in the HTML.
	*
	* @param	string	Cell content
	*
	* @return	string	Modified cell content
	*/
	protected function modifyCellContent($content)
	{
		// ignore one line break on either side of the td content
		return $this->parser->strip_front_back_whitespace($content, 1);
	}

	/**
	* Get the class list from a param list in the format returned by resolveNamedParams.
	*
	* @param	array	Param set as returned by resolveNamedParams
	* @param	string	Optional context info (primarily used in child classes)
	*
	* @return	array	Array of classes
	*/
	public function getClassList(array $params, $context = '')
	{
		if (!isset($params['attributes']['class']) OR $params['attributes']['class'] === '')
		{
			return array();
		}
		else
		{
			return preg_split('#\s+#', $params['attributes']['class'], -1, PREG_SPLIT_NO_EMPTY);
		}
	}

	/**
	* Appends an array of classes to a resolveNamedParams-formatted param list.
	* Optitionally takes a context which will append "_$context" to the appended classes.
	*
	* @param	array	resolveNamedParams-formatted param list
	* @param	array	Array of classes to append
	* @param	string	Optional context, will be appended to class names that are added
	*
	* @return	array	resolveNamedParams-formatted param list with extra classes appended
	*/
	public function appendClassList(array $params, array $classes, $context = '')
	{
		if (!$classes)
		{
			return $params;
		}

		if (!isset($params['attributes']['class']))
		{
			$params['attributes']['class'] = '';
		}

		if ($context)
		{
			$context = "_$context";
		}

		if ($params['attributes']['class'] !== '')
		{
			$params['attributes']['class'] .= ' ';
		}

		$params['attributes']['class'] .= implode("$context ", $classes) . $context;

		return $params;
	}

	/**
	* Resolves a "param: value, param: value" formatted string of named parameters.
	* This validates against a list of allowed parameters; only valid params will be returned.
	* Returns array with 2 keys (css, attributes) each with param => value arrays as values.
	*
	* @param	string	Unparsed string of named params
	* @param	array	A list of valid params. See $table_param_list for format.
	* @param	string	Optional context that can be used to help validation methods.
	*
	* @return	array	array('css' => array(<param> => <value>...), 'attributes' => array(<param> => <value>...))
	*/
	public function resolveNamedParams($param_string, array $param_list, $context = '')
	{
		$param_input = $this->parseNamedParams($param_string);

		$attributes = array();
		$css = array();

		foreach ($param_input AS $name => $value)
		{
			if (!isset($param_list[$name]))
			{
				continue;
			}

			$config = $param_list[$name];

			if (!$this->isValidNamedParam($value, $config, $context))
			{
				continue;
			}

			if (!empty($config['css']))
			{
				$css[$config['css']] = $value;
			}
			else
			{
				$attribute_name = (!empty($config['attribute']) ? $config['attribute'] : $name);
				$attributes[$attribute_name] = $value;
			}
		}

		return array(
			'css' => $css,
			'attributes' => $attributes
		);
	}

	/**
	* Converts arrays of CSS and HTML attributes to the correct HTML format.
	*
	* @param	array	Key-value array of CSS attributes
	* @param	array	Key-value array of HTML attributes
	*
	* @return	string	HTML attributes that can be applied to a tag.
	*/
	public function convertParamsToHtml(array $css, array $attributes)
	{
		$html_output = '';
		foreach ($attributes AS $attribute => $value)
		{
			$html_output .= " $attribute=\"" . str_replace('"', '&quot;', $value) . '"';
		}

		if ($css)
		{
			$style_output = '';
			foreach ($css AS $attribute => $value)
			{
				if ($style_output)
				{
					$style_output .= '; ';
				}

				$style_output .= "$attribute: " . str_replace(';', '', $value);
			}

			$html_output .= ' style="' . str_replace('"', '&quot;', $style_output) . '"';
		}

		return $html_output;
	}

	/**
	* General code to validate a particular parameter for a tag that takes named params.
	*
	* @param	string	Value for the parameter
	* @param	array	Configuration for the validation of the param
	* @param	string	Context value (for a validation callback)
	*
	* @return	bool
	*/
	public function isValidNamedParam(&$value, $config, $context = '')
	{
		if (!is_array($config))
		{
			return false;
		}

		if (isset($config['default']) AND $config['default'] == $value)
		{
			return false;
		}

		if (!empty($config['regex']) AND !preg_match($config['regex'], $value))
		{
			return false;
		}

		if (!empty($config['callback']))
		{
			if (is_array($config['callback']) AND $config['callback'][0] == '$this')
			{
				$config['callback'][0] = $this;
			}

			if (!call_user_func_array($config['callback'], array(&$value, $context)))
			{
				return false;
			}
		}

		return true;
	}

	/**
	* Parses a string of "key: value, key: value" parameters.
	*
	* @param	string	Parameters to parse
	*
	* @return	array	Key-value formatted array
	*/
	public function parseNamedParams($params_raw)
	{
		if (!$params_raw)
		{
			return array();
		}

		$params_split = array();
		foreach (explode(',', $params_raw) AS $param)
		{
			$param = trim($param);

			$name_pos = strpos($param, ':');
			if ($name_pos !== false)
			{
				$params_split[strtolower(substr($param, 0, $name_pos))] = trim(substr($param, $name_pos + 1));
			}
		}

		return $params_split;
	}

	/**
	* Validates the class parameter for a table/tr/td tag. The classes passed in
	* will be modified to be prefixed by $table_class_prefix.
	*
	* @param	string	List of classes. Modified to be prefixed by $table_class_prefix
	* @param	string	Optional context. Unused at this time.
	*
	* @return	bool
	*/
	protected function validateTableClass(&$class_string, $context = '')
	{
		if ($class_string === '')
		{
			return false;
		}
		else if (!preg_match('#^([a-z0-9_-]+( )*)+$#i', $class_string))
		{
			return false;
		}
		else
		{
			// prefix the class name
			$classes = preg_split('#\s+#', $class_string, -1, PREG_SPLIT_NO_EMPTY);
			$class_string = $this->table_class_prefix . implode(' ' . $this->table_class_prefix, $classes);

			return true;
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 29533 $
|| ####################################################################
\*======================================================================*/