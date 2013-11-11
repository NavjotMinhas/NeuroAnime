<?php

class vB_PHP_Generator
{
	private $includeTags;
	private $stateStack = array();
	private $data = '';
	private $eol = "\r\n";

	const STATE_IF = 'if';
	const STATE_FOREACH = 'foreach';

	const ASSIGN_DEFAULT = '=';
	const ASSIGN_APPEND_TEXT = '.=';

	const PARAMETER_VALUE = 1;
	const PARAMETER_VARIABLE = 2;

	public function __construct($includeTags = true)
	{
		$this->includeTags = $includeTags;
	}

	public function generate()
	{
		if (sizeof($this->stateStack) != 0)
		{
			echo "Unfinished Stack State";
			return false;
		}
		return $this->build();
	}

	private function build()
	{
		$output = '';
		if ($this->includeTags)
		{
			$output = '<?php' . $this->eol;
		}

		foreach ($this->data AS $stmt)
		{
			$output .= $stmt;
		}

		if ($this->includeTags)
		{
			$output .= $this->eol . '?>';
		}
		return $output;
	}

	private function add($text)
	{
		$this->data[] = $text;
	}

	public function addValue($name, $value, $type = self::ASSIGN_DEFAULT)
	{
		switch ($type)
		{
			case self::ASSIGN_APPEND_TEXT:
				$this->add("\${$name} .= " . var_export($value, true) . ';' . $this->eol);
				break;
			case self::ASSIGN_DEFAULT:
			default:
				$this->add("\${$name} = " . var_export($value, true) . ';' . $this->eol);
				break;
		}
	}

	public function addVariable($name, $variable, $type = self::ASSIGN_DEFAULT)
	{
		switch ($type)
		{
			case self::ASSIGN_APPEND_TEXT:
				$this->add("\${$name} .= " . '$' . $value . ';' . $this->eol);
				break;
			case self::ASSIGN_DEFAULT:
			default:
				$this->add("\${$name} = " . '$' . $value . ';' . $this->eol);
				break;
		}
	}

	public function addFunctionCall($callback, array $parameters = array(), vB_PHP_Generator_Return_Data $returnData = null)
	{
		$resolved_callback = '';
		if (!is_callable($callback, true, $resolved_callback))
		{
			echo "Callback isn't valid";
			return false;
		}

		$output = '';
		if ($returnData != null)
		{
			switch ($returnData->type)
			{
				case self::ASSIGN_APPEND_TEXT:
					$output = "\${$returnData->name} .= ";
					break;
				case self::ASSIGN_DEFAULT:
				default:
					$output =  "\${$returnData->name} = ";
					break;
			}
		}

		$parameterOutput = array();
		if (!empty($parameters))
		{
			foreach ($parameters AS $parameter)
			{
				switch ($parameter->type)
				{
					case self::PARAMETER_VALUE:
						$parameterOutput[] = var_export($parameter->name, true);
						break;
					case self::PARAMETER_VARIABLE:
					default:
						$parameterOutput[] = "\${$parameter->name}";
						break;
				}
			}
		}

		$parameterOutput = implode(',', $parameterOutput);
		$output .= "$resolved_callback($parameterOutput);" . $this->eol;
		$this->add($output);
	}

	public function addIfBlock($condition)
	{
		$this->add("if ( $condition )" . $this->eol . '{' . $this->eol);
		array_push($this->stateStack, self::STATE_IF);
	}

	public function endIfBlock()
	{
		$this->end(self::STATE_IF);
	}

	public function addElseBlock($condition = '')
	{
		if (array_pop($this->stateStack) == self::STATE_IF)
		{
			array_push($this->stateStack, self::STATE_IF);
			if ($condition != '')
			{
				$condition = 'if ( ' . $condition . ' )';
			}
			$this->add(' }' . "else $condition " . $this->eol . '{' . $this->eol);
		}
		else
		{
			echo "No IF in the state stack";
			return false;
		}
	}

	public function addForeachBlock($array, $value, $key = '__tmp')
	{
		$this->add("foreach ( \${$array} AS \${$key} => \${$value})" .$this->eol . '{' . $this->eol);
		array_push($this->stateStack, self::STATE_FOREACH);
	}

	public function endForeachBlock()
	{
		$this->end(self::STATE_FOREACH);
	}

	private function end($type)
	{
		if (array_pop($this->stateStack) == $type)
		{
			$this->add('}');
		}
		else
		{
			echo "Unexpected state stack";
		}
	}
}

class vB_PHP_Generator_Return_Data
{
	public $name;
	public $type;

	public function __construct($name = '', $type = vB_PHP_Generator::ASSIGN_DEFAULT)
	{
		$this->name = $name;
		$this->type = $type;
	}
}

class vB_PHP_Generator_Parameter
{
	public $name;
	public $type;

	public function __construct($name = '', $type = vB_PHP_Generator::PARAMETER_VALUE)
	{
		$this->name = $name;
		$this->type = $type;
	}
}


/*$output = new vB_PHP_Generator();
$output->addIfBlock('1 == 1');
$output->addValue('test', 'abcdefg');
$output->addFunctionCall(array('Date', 'moo'), array(), new vB_PHP_Generator_Return_Data('abcd'));
$output->endIfBlock();
var_dump($output->generate());
*/