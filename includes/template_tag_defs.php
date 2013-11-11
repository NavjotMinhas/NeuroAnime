<?php

// ##########################################################################

/**
* Abstract class for handling tags found by vB_TemplateParser.
*/
class vB_TemplateParser_Tag
{
	/**
	* Validate the use of this tag. Can validate any aspects of the tag,
	* including attributes, siblings, parents, and children
	*
	* @param	object	DOM Node of type text
	* @param	object	vB_TemplateParser object
	*
	* @return	array	Array of errors
	*/
	public static function validate(vB_DomNode $main_node, vB_TemplateParser $parser)
	{
		return array();
	}

	/**
	* Compile this tag. Note that you must account for children as well.
	*
	* @param	object	DOM Node of type text
	* @param	object	vB_TemplateParser object
	*
	* @return	string	Evalable string
	*/
	public static function compile(vB_DomNode $main_node, vB_TemplateParser $parser)
	{
		return $parser->_parse_nodes($main_node->childNodes());
	}
}

// ##########################################################################

class vB_TemplateParser_TagIf extends vB_TemplateParser_Tag
{
	private static $safe_functions = null;

	public static function validate(vB_DomNode $main_node, vB_TemplateParser $parser)
	{
		$errors = array();
		if ($main_node->attributes['condition'] === '' OR !isset($main_node->attributes['condition']))
		{
			$errors[] = 'empty_if_condition';
		}

		self::validate_condition($main_node->attributes['condition'], $errors);
		return $errors;
	}

	private static function get_safe_functions()
	{
		if (!is_array(self::$safe_functions))
		{
			$safe_functions = array(
				// logical stuff
				0 => 'and',                   // logical and
				1 => 'or',                    // logical or
				2 => 'xor',                   // logical xor

				// built-in variable checking functions
				'in_array',                   // used for checking
				'is_array',                   // used for checking
				'is_numeric',                 // used for checking
				'isset',                      // used for checking
				'empty',                      // used for checking
				'defined',                    // used for checking
				'array',                      // used for checking
				'gmdate',                     // used by ad manager
				'mktime',                     // used by ad manager
				'gmmktime',                   // used by ad manager

				// vBulletin-defined functions
				'can_moderate',               // obvious one
				'can_moderate_calendar',      // another obvious one
				'exec_switch_bg',             // harmless function that we use sometimes
				'is_browser',                 // function to detect browser and versions
				'is_member_of',               // function to check if $user is member of $usergroupid
				'is_came_from_search_engine', // function to check whether or not user came from search engine for ad manager
				'vbdate',                     // function to check date range for ad manager
			);

			($hook = vBulletinHook::fetch_hook('template_safe_functions')) ? eval($hook) : false;

			self::$safe_functions = $safe_functions;
		}

		return self::$safe_functions;
	}

	private static function validate_condition($condition_value, &$errors)
	{
		global $vbphrase;
		$safe_functions = self::get_safe_functions();

		if (strpos($condition_value, '`') !== false)
		{
			$errors[] =  array('expression_contains_backticks_x_please_rewrite_without',
				htmlspecialchars('<if condition="' . stripslashes($condition_value) . '">')
			);
			return;
		}

		if (preg_match_all('#([a-z0-9_\x7f-\xff\\\\{}$>-\\]]+)(\s|/\*.*\*/|(\#|//)[^\r\n]*(\r|\n))*\(#si', $condition_value, $matches))
		{
			$functions = array();
			foreach($matches[1] AS $key => $match)
			{
				if (!in_array(strtolower($match), $safe_functions))
				{
					$funcpos = strpos($condition_value, $matches[0]["$key"]);
					$functions[] = array(
						'func' => stripslashes($match),
						'usage' => stripslashes(substr($condition_value, $funcpos, (strpos($condition_value, ')', $funcpos) - $funcpos + 1))),
					);
				}
			}

			if (!empty($functions))
			{
				unset($safe_functions[0], $safe_functions[1], $safe_functions[2]);

				$errormsg = "
				$vbphrase[template_condition_contains_functions]:<br /><br />
				<code>" . htmlspecialchars('<if condition="' . stripslashes($condition_value) . '">') . '</code><br /><br />
				<table cellpadding="4" cellspacing="1" width="100%">
				<tr>
					<td class="thead">' . $vbphrase['function_name'] . '</td>
					<td class="thead">' . $vbphrase['usage_in_expression'] . '</td>
				</tr>';

				foreach($functions AS $error)
				{
					$errormsg .= "<tr><td class=\"alt2\"><code>" . htmlspecialchars($error['func']) . "</code></td><td class=\"alt2\"><code>" . htmlspecialchars($error['usage']) . "</code></td></tr>\n";
				}

				$errormsg .= "
				</table>
				<br />$vbphrase[with_a_few_exceptions_function_calls_are_not_permitted]<br />
				<code>". implode('() ', $safe_functions) . '()</code>';

				$errors[] = $errormsg;
			}
		}
	}

	public static function compile(vB_DomNode $main_node, vB_TemplateParser $parser)
	{
		$true_value = '';
		$else_value = '';
		$elseif_conditions = array();
		$write_location = 'true';

		$child_nodes = $main_node->childNodes();
		foreach (array_keys($child_nodes) AS $key)
		{
			// find the 3 types of conditional values: if (true), elseif, else (false)
			// use a switch with references so we can write to a single variable for each case
			$node =& $child_nodes["$key"];
			switch ($write_location)
			{
				case 'true':
					$location_ref =& $true_value;
					break;

				case 'else':
					$location_ref =& $else_value;
					break;

				case 'elseif':
					$keys = array_keys($elseif_conditions);
					$key = end($keys);
					$location_ref =& $elseif_conditions[$key]['value'];
			}

			if ($node->type == 'text')
			{
				$location_ref .= $parser->_escape_string($node->value);
			}
			else if ($node->type == 'tag')
			{
				switch ($node->value)
				{
					case 'elseif':
						// found an elseif tag, make a new entry
						$elseif_conditions[] = array(
							'condition' => $node->attributes['condition'],
							'value' => ''
						);
						$write_location = 'elseif';
						break;

					case 'else':
						// move to else
						$write_location = 'else';
						break;

					default:
						// any other tag -- this isn't related to this if
						$location_ref .= $parser->_default_node_handler($node);
						break;
				}
			}
			else if ($node->type == 'curly')
			{
				$location_ref .= $parser->_default_node_handler($node);
			}
		}

		// merge the 2 types with conditions (true, elseif) together
		$conditions = array_merge(array(
			0 => array(
				'condition' => $main_node->attributes['condition'],
				'value' => $true_value
		)), $elseif_conditions);

		// now loop through these conditions. Elseif can not be handled by a ternary
		// operator, so emulate it via if{}else{if{}else{}.
		$output = "'';";

		if (!function_exists('replace_template_variables'))
		{
			require_once(DIR . '/includes/functions_misc.php');
		}

		$condition_value = replace_template_variables($condition_value, true);

		foreach ($conditions AS $condition)
		{
			$output .= ' if (' . replace_template_variables($condition['condition'], true) . ') {
					' .$parser->outputVar . " .= '" . $condition['value'] . "';" . '
				} else';
		}
		$output .= ' {
			' . $parser->outputVar . " .= '" . $else_value . "';" . '
		}';

		$output .= $parser->outputVar . " .= ''";

		return $output;
	}
}

// ##########################################################################

class vB_TemplateParser_TagElseIf extends vB_TemplateParser_Tag
{
	public static function validate(vB_DomNode $main_node, vB_TemplateParser $parser)
	{
		$errors = array();

		$parent = $main_node->parentNode();
		if ($parent->value != 'if')
		{
			$errors[] = 'elseif_not_child_of_if';
		}

		$errors = array_merge($errors, vB_TemplateParser_TagIf::validate($main_node, $parser));

		return $errors;
	}
}

// ##########################################################################

class vB_TemplateParser_TagElse extends vB_TemplateParser_Tag
{
	public static function validate(vB_DomNode $main_node, vB_TemplateParser $parser)
	{
		$errors = array();

		$parent = $main_node->parentNode();
		if ($parent->value != 'if')
		{
			$errors[] = 'else_not_child_of_if';
		}

		$walk =& $main_node;
		while ($walk = $walk->nextSibling())
		{
			if ($walk->type == 'tag' AND $walk->value == 'elseif')
			{
				$errors[] = 'elseif_after_else';
				break;
			}
		}

		return $errors;
	}
}

// ##########################################################################

class vB_TemplateParser_TagEach extends vB_TemplateParser_Tag
{
	public static function validate(vB_DomNode $main_node, vB_TemplateParser $parser)
	{
		$errors = array();

		if (empty($main_node->attributes['from']))
		{
			$errors[] = 'each_missing_from';
		}

		if (empty($main_node->attributes['value']))
		{
			$errors[] = 'each_missing_value';
		}

		return $errors;
	}

	public static function compile(vB_DomNode $main_node, vB_TemplateParser $parser)
	{
		$children = $parser->_parse_nodes($main_node->childNodes());
		return "''" . '; if (is_array($' . $main_node->attributes['from'] . ') || $' . $main_node->attributes['from'] . ' instanceof ArrayAccess) { foreach ($' . $main_node->attributes['from'] . ' AS $' . (($main_node->attributes['key']) ? $main_node->attributes['key'] . ' => $' : '') . $main_node->attributes['value'] . ') {' . $parser->outputVar . " .= '" . $children . "'; } }" . $parser->outputVar . " .= ''";  
	}
}

// ##########################################################################

class vB_TemplateParser_TagComment extends vB_TemplateParser_Tag
{
	public static function validate(vB_DomNode $main_node, vB_TemplateParser $parser)
	{
		return array();
	}

	public static function compile(vB_DomNode $main_node, vB_TemplateParser $parser)
	{
		return "''";
	}
}

// ##########################################################################

class vB_TemplateParser_TagLiteral extends vB_TemplateParser_Tag
{
	public static function validate(vB_DomNode $main_node, vB_TemplateParser $parser)
	{
		return array();
	}

	public static function compile(vB_DomNode $main_node, vB_TemplateParser $parser)
	{
		return "'" . $parser->_parse_nodes($main_node->childNodes()) . "'";
	}
}

// ##########################################################################

class vB_TemplateParser_TagInclude extends vB_TemplateParser_Tag
{
	public static function validate(vB_DomNode $main_node, vB_TemplateParser $parser)
	{
		return array();
	}

	/*public static function compile(vB_DomNode $main_node, vB_TemplateParser $parser)
	{

	}*/
}

// ##########################################################################

class vB_TemplateParser_TagPhrase extends vB_TemplateParser_Tag
{
	public static function validate(vB_DomNode $main_node, vB_TemplateParser $parser)
	{
		return array();
	}

	public static function compile(vB_DomNode $main_node, vB_TemplateParser $parser)
	{
		$argument_list = array();
		foreach ($main_node->attributes AS $name => $attribute)
		{
			if (strval(intval($name)) == $name)
			{
				$argument_list["$name"] = $attribute;
			}
		}
		ksort($argument_list, SORT_NUMERIC);

		$arguments = '';
		for ($i = 1; isset($argument_list["$i"]); $i++)
		{
			$arguments .= ', "' . $argument_list["$i"] . '"';
		}

		$children = $parser->_parse_nodes($main_node->childNodes());

		return 'vB_Template_Runtime::parsePhrase("' . "$children\"$arguments" . ')';
	}
}
