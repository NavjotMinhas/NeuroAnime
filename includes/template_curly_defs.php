<?php

// ##########################################################################

require_once(DIR . '/vb/exception.php');
require_once(DIR . '/vb/exception/templatefatalerror.php');

/**
* Abstract class for handling tags found by vB_TemplateParser.
*/
class vB_TemplateParser_Curly
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
	public static function validate(vB_Node $main_node, vB_TemplateParser $parser)
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
	public static function compile(vB_Node $main_node, vB_TemplateParser $parser)
	{
		return $parser->_parse_nodes($main_node->childNodes());
	}

	/**
	* Converts a single attribute to an argument string
	*
	* @param	array	the attribute
	* @param	object	the parser object
	*
	* @return	string	string version of attribute
	*/
	protected static function attributeToString($attribute, $parser)
	{
		if ($attribute instanceof vB_CurlyNode AND in_array($attribute->value, array('raw', 'var')))
		{
			//The substr is used to strip the two single quotes and period before and after the variable
			//'' . $block_data['pageinfo_vcard'] . '')' changed to $block_data['pageinfo_vcard']
			//This allows for an array in an array to be passed in through the link function
			//{vb:link member, {vb:raw array_variable}, {vb:raw array_variable.array_variable}}
			return substr($parser->_default_node_handler($attribute), 4, -4);
		}
		else
		{
			return '"' . $attribute . '"';

			//TODO: change to the following line when we want to enforce
			//no explicit variables as curly brace parameters
			//	$arguments .= "'" . $attribute . "'";
		}
	}

	/**
	* Compiles an array of attributes into an escaped argument string
	* for use by the template eval engine at runtime
	*
	* @param	array	list of attributes
	* @param	object	the parser object
	*
	* @return	string	argument portion for runtime engine call
	*/
	protected static function getArgumentsFromAttributes($attribute_list, $parser)
	{
		$arguments = '';
		foreach ($attribute_list AS $attribute)
		{
			$arguments .= self::attributeToString($attribute, $parser) . ', ';
		}
		// remove trailing comma and space
		$arguments = substr_replace($arguments ,'',-2);

		return $arguments;
	}
}

// ##########################################################################

class vB_TemplateParser_CurlyDate extends vB_TemplateParser_Curly
{
	public static function validate(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$errors = array();

		if (!array_key_exists(0, $main_node->attributes))
		{
			$errors[] = 'no_timestamp_specified';
		}

		if (sizeof($main_node->attributes) > 2)
		{
			$errors[] = 'too_many_attributes';
		}

		return $errors;
	}

	public static function compile(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$arguments = self::getArgumentsFromAttributes($main_node->attributes, $parser);

		return 'vB_Template_Runtime::date(' .$arguments . ')';
	}
}

// ##########################################################################

class vB_TemplateParser_CurlyEscapeJS extends vB_TemplateParser_Curly
{
	public static function validate(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$errors = array();

		if (!array_key_exists(0, $main_node->attributes))
		{
			$errors[] = 'no_variable_specified';
		}

		if (sizeof($main_node->attributes) > 1)
		{
			$errors[] = 'too_many_attributes';
		}
		return $errors;
	}

	public static function compile(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$arguments = self::getArgumentsFromAttributes($main_node->attributes, $parser);

		return 'vB_Template_Runtime::escapeJS(' .$arguments . ')';
	}
}

// ##########################################################################

class vB_TemplateParser_CurlyIf extends vB_TemplateParser_Curly
{
	public static function validate(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$errors = array();

		if (!array_key_exists(0, $main_node->attributes))
		{
			$errors[] = 'no_condition_specified';
		}

		if (!array_key_exists(1, $main_node->attributes))
		{
			$errors[] = 'no_true_expression_specified';
		}

		if (sizeof($main_node->attributes) > 3)
		{
			$errors[] = 'too_many_attributes';
		}
		return $errors;
	}

	public static function compile(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$trueval = self::attributeToString($main_node->attributes[1], $parser);
		$falseval = (!empty($main_node->attributes[2]) ? self::attributeToString($main_node->attributes[2], $parser) : '""');

		return '((' . $main_node->attributes[0] . ') ? ' . $trueval . ' : ' . $falseval . ') ';
	}
}

// ##########################################################################

class vB_TemplateParser_CurlyLink extends vB_TemplateParser_Curly
{
	public static function validate(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$errors = array();

		if (!array_key_exists(0, $main_node->attributes))
		{
			$errors[] = 'no_type_specified';
		}

//we've added a link that doesn't need info
//		if (!array_key_exists(1, $main_node->attributes))
//		{
//			$errors[] = 'no_info_specified';
//		}

		$argument_list = array_slice($main_node->attributes, 1);
		foreach ($argument_list AS $attribute)
		{
			if ($attribute instanceof vB_CurlyNode AND !in_array($attribute->value, array('raw', 'var')))
			{
				$errors[] = 'link_only_accepts_vars';
			}
		}

		if (sizeof($main_node->attributes) > 5)
		{
			$errors[] = 'too_many_attributes';
		}

		return $errors;
	}

	public static function compile(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$argument_list = array_slice($main_node->attributes, 1);
		$arguments = '';
		foreach ($argument_list AS $attribute)
		{
			if ($attribute instanceof vB_CurlyNode)
			{
				$arguments .= ", " .  self::attributeToString($attribute, $parser);
			}
			else if ($attribute === NULL)
			{
				$arguments .= ', NULL';
			}
			else
			{
				$arguments .= ", '" . $attribute . "'";
			}
		}

		return 'vB_Template_Runtime::linkBuild("' . $main_node->attributes[0] . "\"$arguments" . ')';
	}
}

// ##########################################################################

class vB_TemplateParser_CurlyNumber extends vB_TemplateParser_Curly
{
	public static function validate(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$errors = array();

		if (!array_key_exists(0, $main_node->attributes))
		{
			$errors[] = 'no_number_specified';
		}

		if (sizeof($main_node->attributes) > 2)
		{
			$errors[] = 'too_many_attributes';
		}

		return $errors;
	}

	public static function compile(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$arguments = self::getArgumentsFromAttributes($main_node->attributes, $parser);

		return 'vB_Template_Runtime::numberFormat(' .$arguments . ')';
	}
}

// ##########################################################################

class vB_TemplateParser_CurlyPhrase extends vB_TemplateParser_Curly
{
	public static function validate(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$errors = array();

		if (!array_key_exists(0, $main_node->attributes))
		{
			$errors[] = 'no_phrase_specified';
		}

		return $errors;
	}

	public static function compile(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$argument_list = array_slice($main_node->attributes, 1);
		$arguments = '';
		foreach ($argument_list AS $attribute)
		{
			if ($attribute instanceof vB_CurlyNode)
			{
				$attribute = $parser->_default_node_handler($attribute);
			}
			$arguments .= ", htmlspecialchars('" . $attribute . "')";
		}

		if ($main_node->attributes[0] instanceof vB_CurlyNode AND in_array($main_node->attributes[0]->value, array('raw', 'var')))
		{
			$string = '$' . $main_node->attributes[0]->attributes[0];
		}
		else
		{
			$string = $main_node->attributes[0];
		}
		return 'vB_Template_Runtime::parsePhrase("' . $string . "\"$arguments" . ')';
	}
}


// ##########################################################################

class vB_TemplateParser_CurlyRawPhrase extends vB_TemplateParser_Curly
{
	public static function validate(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$errors = array();

		if (!array_key_exists(0, $main_node->attributes))
		{
			$errors[] = 'no_phrase_specified';
		}

		return $errors;
	}

	public static function compile(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$argument_list = array_slice($main_node->attributes, 1);
		$arguments = '';
		foreach ($argument_list AS $attribute)
		{
			if ($attribute instanceof vB_CurlyNode)
			{
				$arguments .= ", '" . $parser->_default_node_handler($attribute) . "'";
			}
			else
			{
				$arguments .= ", '" . $attribute . "'";
			}
		}

		if ($main_node->attributes[0] instanceof vB_CurlyNode AND in_array($main_node->attributes[0]->value, array('raw', 'var')))
		{
			$string = $main_node->attributes[0]->attributes[0];
			$string = ($string[0] !== '$' ? '$' : '') . $string;
		}
		else
		{
			$string = $main_node->attributes[0];
		}

		return 'vB_Template_Runtime::parsePhrase("' . $string . "\"$arguments" . ')';
	}
}

// ##########################################################################

class vB_TemplateParser_CurlyTime extends vB_TemplateParser_Curly
{
	public static function validate(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$errors = array();

		if (!array_key_exists(0, $main_node->attributes))
		{
			$errors[] = 'no_time_specified';
		}

		if (sizeof($main_node->attributes) > 1)
		{
			$errors[] = 'too_many_attributes';
		}

		return $errors;
	}

	public static function compile(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$arguments = self::getArgumentsFromAttributes($main_node->attributes, $parser);

		return 'vB_Template_Runtime::time(' .$arguments . ')';
	}
}

// ##########################################################################

class vB_TemplateParser_CurlyUrlencode extends vB_TemplateParser_Curly
{
	public static function validate(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$errors = array();

		if (!array_key_exists(0, $main_node->attributes))
		{
			$errors[] = 'no_variable_specified';
		}

		if (sizeof($main_node->attributes) > 1)
		{
			$errors[] = 'too_many_attributes';
		}

		return $errors;
	}

	public static function compile(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$arguments = self::getArgumentsFromAttributes($main_node->attributes, $parser);

		return 'vB_Template_Runtime::urlEncode(' .$arguments . ')';
	}
}

// ##########################################################################

class vB_TemplateParser_CurlyStylevar extends vB_TemplateParser_Curly
{
	public static function validate(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$errors = array();

		if (!array_key_exists(0, $main_node->attributes))
		{
			$errors[] = 'no_variable_specified';
		}

		if (sizeof($main_node->attributes) > 1)
		{
			$errors[] = 'too_many_attributes';
		}

		return $errors;
	}

	public static function compile(vB_Node $main_node, vB_TemplateParser $parser)
	{
		return 'vB_Template_Runtime::fetchStylevar("' . $main_node->attributes[0] . '")';
	}
}

// ##########################################################################

class vB_TemplateParser_CurlyCssfile extends vB_TemplateParser_Curly
{
	public static function validate(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$errors = array();

		if (!array_key_exists(0, $main_node->attributes))
		{
			$errors[] = 'no_variable_specified';
		}

		return $errors;
	}

	public static function compile(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$argument_list = $main_node->attributes;
		$arguments = $csslinks = array();
		foreach ($argument_list AS $attribute)
		{
			if ($attribute instanceof vB_CurlyNode)
			{
				$attribute = $parser->_default_node_handler($attribute);
			}
			else
			{
				// We do nothing to $attribute
				//$attribute = $attribute;
			}
			$arguments[] = $attribute;
			$csslinks[] = '<link rel="stylesheet" type="text/css" href="\' . $vbcsspath . \'' . $attribute . '?d=\' . $style[\'dateline\'] . \'" />';
		}
		$arguments = implode(',', $arguments);

		$output = "'';";


		$csslink2 = '<link rel="stylesheet" type="text/css" href="\' . $vbcsspath . \'' . $arguments . '" />';

		$output .= 'if ($GLOBALS[\'vbulletin\']->options[\'storecssasfile\'])
			{
				$final_rendered .= \'' . implode("\r\n\t", $csslinks) . '\';
			}
			else
			{
				$final_rendered .= \'' . $csslink2 . '\';
			}
			$final_rendered .= \'\'';

		return $output;
	}
}

// ##########################################################################

class vB_TemplateParser_CurlyMath extends vB_TemplateParser_Curly
{
	public static function validate(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$errors = array();

		if (!array_key_exists(0, $main_node->attributes))
		{
			$errors[] = 'no_variable_specified';
		}

		return $errors;
	}

	public static function compile(vB_Node $main_node, vB_TemplateParser $parser)
	{

		$argument_list = $main_node->attributes;
		$arguments = array();
		foreach ($argument_list AS $attribute)
		{
			if ($attribute instanceof vB_CurlyNode)
			{
				$attribute = "'" . $parser->_default_node_handler($attribute) . "'";
			}
			else
			{
				$attribute = "'$attribute'";
			}
			$arguments[] =  $attribute;
		}
		$arguments = implode(".", $arguments);
		return 'vB_Template_Runtime::runMaths(' . $arguments . ')';
	}
}

// ##########################################################################

class vB_TemplateParser_CurlyVar extends vB_TemplateParser_Curly
{
	public static function validate(vB_Node $main_node, vB_TemplateParser $parser)
	{
		return vB_TemplateParser_CurlyRaw::validate($main_node, $parser);
	}

	public static function compile(vB_Node $main_node, vB_TemplateParser $parser)
	{
		return 'htmlspecialchars(' . vB_TemplateParser_CurlyRaw::compile($main_node, $parser) . ')';
	}
}

class vB_TemplateParser_CurlyRaw extends vB_TemplateParser_Curly
{
	public static function validate(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$errors = array();

		if (!array_key_exists(0, $main_node->attributes))
		{
			$errors[] = 'no_variable_specified';
		}

		//"(" isn't a valid php identifier character nor anything that we should be adding
		//for our template markup.  All it can do is cause a php function call which we don't want.
		else if(strpos($main_node->attributes[0], '(') !== false)
		{
			throw new vB_Exception_TemplateFatalError('template_text_not_safe');
		}

		//this doesn't do what we think thought it does.  It looks like it matches against something of the form
		//x.y.z according to php identifier rules.  However, since it isn't anchored it will match anything that
		//has a substring that fits the pattern, which is nearly anything with an alpha character.
		//
		//Unfortunately tightening the match causes existing templates to fail validation -- including some things
		//that actually work.  Leaving this as is doesn't make anything worse so we'll need to handle this another
		//time
		else if (!preg_match('#\$?([a-z_][a-z0-9_]*)(\.([a-z0-9_]+|\$([a-z_][a-z0-9_]*))+)*#i', $main_node->attributes[0]))
		{
			$errors[] = 'invalid_variable_name';
		}

		return $errors;
	}

	public static function compile(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$parts = explode('.', $main_node->attributes[0]);
		// first part is always the variable or array value
		$output = $parts[0];

		if (isset($parts[1]))
		{
			if ($main_node->attributes[1] instanceof vB_CurlyNode AND in_array($main_node->attributes[1]->value, array('raw', 'var')))
			{
				$parts[1] = '$' . $main_node->attributes[1]->attributes[0];
			}
    	for ($i = 1; $i < sizeof($parts); $i++)
    	{
				if ($parts[$i][0] == '$')
				{
	        		$output .= '[' . $parts[$i] . ']';
				}
				else if (strpos($parts[$i], '$') !== false)
				{
	        		$output .= '["' . $parts[$i] . '"]';
				}
				else
				{
	        		$output .= "['" . $parts[$i] . "']";
				}
    	}
		}

		return ($output[0] !== '$' ? '$' : '') . $output;
	}
}

/*
//keep this in our back pocket --
//The curly parser currently requires arguments to be either a string or a curly
//tag.  However in some cases, most notably params to phrases, we'd like to be
//able to pass such a combination.  This fakes it by converting a combination into
//a single curly tag.
class vB_TemplateParser_CurlyCat extends vB_TemplateParser_Curly
{
	public static function compile(vB_Node $main_node, vB_TemplateParser $parser)
	{
		$string = "";
		foreach ($main_node->attributes AS $attribute)
		{
			if ($attribute instanceof vB_CurlyNode)
			{
				$string .= $parser->_default_node_handler($attribute);
			}
			else
			{
				$string .= $attribute;
			}
		}
		return "'$string'";
	}
}
*/

