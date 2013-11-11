<?php
class vbTemplateParser
{
	const CURLY_START = 1;
	const CURLY_END = 2;
	const STRING_VALUE = 3;
	const TOKEN = 4;
	const LITERAL = 5;
	const SIMPLE_VAR = 6;
	const LITERAL_KEY = 7;
	const EQUAL = 8;
	const NOT_EQUAL = 9;
	const SMALLER_OR_EQUAL = 10;
	const GREATER_OR_EQUAL = 11;
	const BOOLEAN_OR = 12;
	const BOOLEAN_AND = 13;
	const LOGICAL_OR = 14;
	const LOGICAL_AND = 15;
	const WHITESPACE = 16;
}

class vbTemplateLexer
{
    private $data;
    private $N;
    public $value;
    private $line;
    private $state = 1;
    public $token;
	public $currentValue;
	private $tokenValue;

	function getCurrentPosition()
	{
		return $this->N;
	}

    function __construct($data)
    {
        $this->data = $data;
        $this->N = 0;
        $this->line = 1;
    }


    private $_yy_state = 1;
    private $_yy_stack = array();

    function yylex()
    {
        return $this->{'yylex' . $this->_yy_state}();
    }

    function yypushstate($state)
    {
        array_push($this->_yy_stack, $this->_yy_state);
        $this->_yy_state = $state;
    }

    function yypopstate()
    {
        $this->_yy_state = array_pop($this->_yy_stack);
    }

    function yybegin($state)
    {
        $this->_yy_state = $state;
    }



    function yylex1()
    {
        $tokenMap = array (
              1 => 0,
              2 => 0,
              3 => 0,
              4 => 1,
            );
        if ($this->N >= strlen($this->data)) {
            return false; // end of input
        }
        $yy_global_pattern = "/^(\\{)|^(\\})|^([ \n\r\t]+)|^((.))/";

        do {
            if (preg_match($yy_global_pattern, substr($this->data, $this->N), $yymatches)) {
                $yysubmatches = $yymatches;
                $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                if (!count($yymatches)) {
                    throw new Exception('Error: lexing failed because a rule matched' .
                        'an empty string.  Input "' . substr($this->data,
                        $this->N, 5) . '... state START');
                }
                next($yymatches); // skip global match
                $this->token = key($yymatches); // token number
                if ($tokenMap[$this->token]) {
                    // extract sub-patterns for passing to lex function
                    $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                        $tokenMap[$this->token]);
                } else {
                    $yysubmatches = array();
                }
                $this->value = current($yymatches); // token value
				$this->currentValue = $this->value;
                $r = $this->{'yy_r1_' . $this->token}($yysubmatches);
                if ($r === null) {
                    $this->N += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    // accept this token
                    return true;
                } elseif ($r === true) {
                    // we have changed state
                    // process this token in the new state
                    return $this->yylex();
                } elseif ($r === false) {
                    $this->N += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    if ($this->N >= strlen($this->data)) {
                        return false; // end of input
                    }
                    // skip this token
                    continue;
                } else {                    $yy_yymore_patterns = array(
        1 => array(0, "^(\\})|^([ \n\r\t]+)|^((.))"),
        2 => array(0, "^([ \n\r\t]+)|^((.))"),
        3 => array(0, "^((.))"),
        4 => array(1, ""),
    );

                    // yymore is needed
                    do {
                        if (!strlen($yy_yymore_patterns[$this->token][1])) {
                            throw new Exception('cannot do yymore for the last token');
                        }
                        $yysubmatches = array();
                        if (preg_match('/' . $yy_yymore_patterns[$this->token][1] . '/',
                              substr($this->data, $this->N), $yymatches)) {
                            $yysubmatches = $yymatches;
                            $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                            next($yymatches); // skip global match
                            $this->token += key($yymatches) + $yy_yymore_patterns[$this->token][0]; // token number
                            $this->value = current($yymatches); // token value
							$this->currentValue = $this->value;
                            $this->line = substr_count($this->value, "\n");
                            if ($tokenMap[$this->token]) {
                                // extract sub-patterns for passing to lex function
                                $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                                    $tokenMap[$this->token]);
                            } else {
                                $yysubmatches = array();
                            }
                        }
                    	$r = $this->{'yy_r1_' . $this->token}($yysubmatches);
                    } while ($r !== null && !is_bool($r));
			        if ($r === true) {
			            // we have changed state
			            // process this token in the new state
			            return $this->yylex();
                    } elseif ($r === false) {
                        $this->N += strlen($this->value);
                        $this->line += substr_count($this->value, "\n");
                        if ($this->N >= strlen($this->data)) {
                            return false; // end of input
                        }
                        // skip this token
                        continue;
			        } else {
	                    // accept
	                    $this->N += strlen($this->value);
	                    $this->line += substr_count($this->value, "\n");
	                    return true;
			        }
                }
            } else {
                throw new Exception('Unexpected input at line' . $this->line .
                    ': ' . $this->data[$this->N]);
            }
            break;
        } while (true);

    } // end function


    const START = 1;
    function yy_r1_1($yy_subpatterns)
    {

    $this->token = vbTemplateParser::CURLY_START;
    $this->yypushstate(self::CURLY);
    }
    function yy_r1_2($yy_subpatterns)
    {

    $this->token = vbTemplateParser::CURLY_END;
	$this->yypopstate();
    }
    function yy_r1_3($yy_subpatterns)
    {

    $this->token = vbTemplateParser::WHITESPACE;
    }
    function yy_r1_4($yy_subpatterns)
    {

    $this->token = vbTemplateParser::LITERAL;
    }


    function yylex2()
    {
        $tokenMap = array (
              1 => 0,
              2 => 0,
              3 => 0,
              4 => 0,
              5 => 0,
              6 => 0,
              7 => 0,
              8 => 0,
              9 => 0,
              10 => 0,
              11 => 0,
              12 => 0,
              13 => 0,
              14 => 0,
              15 => 0,
              16 => 0,
            );
        if ($this->N >= strlen($this->data)) {
            return false; // end of input
        }
        $yy_global_pattern = "/^(\\{)|^(\\})|^([ \n\r\t]+)|^(\"[^\"]*\"|'[^']*')|^(==)|^(!=)|^(<=)|^(>=)|^(\\|\\|)|^(&&)|^(OR)|^(AND)|^(\\$[a-zA-Z0-9_]+)|^([a-zA-Z0-9_]+)|^([;:,.[\]()|^&+-\/*=%!~$<>?@])|^([a-zA-Z]+)/";

        do {
            if (preg_match($yy_global_pattern, substr($this->data, $this->N), $yymatches)) {
                $yysubmatches = $yymatches;
                $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                if (!count($yymatches)) {
                    throw new Exception('Error: lexing failed because a rule matched' .
                        'an empty string.  Input "' . substr($this->data,
                        $this->N, 5) . '... state CURLY');
                }
                next($yymatches); // skip global match
                $this->token = key($yymatches); // token number
                if ($tokenMap[$this->token]) {
                    // extract sub-patterns for passing to lex function
                    $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                        $tokenMap[$this->token]);
                } else {
                    $yysubmatches = array();
                }
                $this->value = current($yymatches); // token value
				$this->currentValue = $this->value;
                $r = $this->{'yy_r2_' . $this->token}($yysubmatches);
                if ($r === null) {
                    $this->N += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    // accept this token
                    return true;
                } elseif ($r === true) {
                    // we have changed state
                    // process this token in the new state
                    return $this->yylex();
                } elseif ($r === false) {
                    $this->N += strlen($this->value);
                    $this->line += substr_count($this->value, "\n");
                    if ($this->N >= strlen($this->data)) {
                        return false; // end of input
                    }
                    // skip this token
                    continue;
                } else {                    $yy_yymore_patterns = array(
        1 => array(0, "^(\\})|^([ \n\r\t]+)|^(\"[^\"]*\"|'[^']*')|^(==)|^(!=)|^(<=)|^(>=)|^(\\|\\|)|^(&&)|^(OR)|^(AND)|^(\\$[a-zA-Z0-9_]+)|^([a-zA-Z0-9_]+)|^([;:,.[\]()|^&+-\/*=%!~$<>?@])|^([a-zA-Z]+)"),
        2 => array(0, "^([ \n\r\t]+)|^(\"[^\"]*\"|'[^']*')|^(==)|^(!=)|^(<=)|^(>=)|^(\\|\\|)|^(&&)|^(OR)|^(AND)|^(\\$[a-zA-Z0-9_]+)|^([a-zA-Z0-9_]+)|^([;:,.[\]()|^&+-\/*=%!~$<>?@])|^([a-zA-Z]+)"),
        3 => array(0, "^(\"[^\"]*\"|'[^']*')|^(==)|^(!=)|^(<=)|^(>=)|^(\\|\\|)|^(&&)|^(OR)|^(AND)|^(\\$[a-zA-Z0-9_]+)|^([a-zA-Z0-9_]+)|^([;:,.[\]()|^&+-\/*=%!~$<>?@])|^([a-zA-Z]+)"),
        4 => array(0, "^(==)|^(!=)|^(<=)|^(>=)|^(\\|\\|)|^(&&)|^(OR)|^(AND)|^(\\$[a-zA-Z0-9_]+)|^([a-zA-Z0-9_]+)|^([;:,.[\]()|^&+-\/*=%!~$<>?@])|^([a-zA-Z]+)"),
        5 => array(0, "^(!=)|^(<=)|^(>=)|^(\\|\\|)|^(&&)|^(OR)|^(AND)|^(\\$[a-zA-Z0-9_]+)|^([a-zA-Z0-9_]+)|^([;:,.[\]()|^&+-\/*=%!~$<>?@])|^([a-zA-Z]+)"),
        6 => array(0, "^(<=)|^(>=)|^(\\|\\|)|^(&&)|^(OR)|^(AND)|^(\\$[a-zA-Z0-9_]+)|^([a-zA-Z0-9_]+)|^([;:,.[\]()|^&+-\/*=%!~$<>?@])|^([a-zA-Z]+)"),
        7 => array(0, "^(>=)|^(\\|\\|)|^(&&)|^(OR)|^(AND)|^(\\$[a-zA-Z0-9_]+)|^([a-zA-Z0-9_]+)|^([;:,.[\]()|^&+-\/*=%!~$<>?@])|^([a-zA-Z]+)"),
        8 => array(0, "^(\\|\\|)|^(&&)|^(OR)|^(AND)|^(\\$[a-zA-Z0-9_]+)|^([a-zA-Z0-9_]+)|^([;:,.[\]()|^&+-\/*=%!~$<>?@])|^([a-zA-Z]+)"),
        9 => array(0, "^(&&)|^(OR)|^(AND)|^(\\$[a-zA-Z0-9_]+)|^([a-zA-Z0-9_]+)|^([;:,.[\]()|^&+-\/*=%!~$<>?@])|^([a-zA-Z]+)"),
        10 => array(0, "^(OR)|^(AND)|^(\\$[a-zA-Z0-9_]+)|^([a-zA-Z0-9_]+)|^([;:,.[\]()|^&+-\/*=%!~$<>?@])|^([a-zA-Z]+)"),
        11 => array(0, "^(AND)|^(\\$[a-zA-Z0-9_]+)|^([a-zA-Z0-9_]+)|^([;:,.[\]()|^&+-\/*=%!~$<>?@])|^([a-zA-Z]+)"),
        12 => array(0, "^(\\$[a-zA-Z0-9_]+)|^([a-zA-Z0-9_]+)|^([;:,.[\]()|^&+-\/*=%!~$<>?@])|^([a-zA-Z]+)"),
        13 => array(0, "^([a-zA-Z0-9_]+)|^([;:,.[\]()|^&+-\/*=%!~$<>?@])|^([a-zA-Z]+)"),
        14 => array(0, "^([;:,.[\]()|^&+-\/*=%!~$<>?@])|^([a-zA-Z]+)"),
        15 => array(0, "^([a-zA-Z]+)"),
        16 => array(0, ""),
    );

                    // yymore is needed
                    do {
                        if (!strlen($yy_yymore_patterns[$this->token][1])) {
                            throw new Exception('cannot do yymore for the last token');
                        }
                        $yysubmatches = array();
                        if (preg_match('/' . $yy_yymore_patterns[$this->token][1] . '/',
                              substr($this->data, $this->N), $yymatches)) {
                            $yysubmatches = $yymatches;
                            $yymatches = array_filter($yymatches, 'strlen'); // remove empty sub-patterns
                            next($yymatches); // skip global match
                            $this->token += key($yymatches) + $yy_yymore_patterns[$this->token][0]; // token number
                            $this->value = current($yymatches); // token value
							$this->currentValue = $this->value;
                            $this->line = substr_count($this->value, "\n");
                            if ($tokenMap[$this->token]) {
                                // extract sub-patterns for passing to lex function
                                $yysubmatches = array_slice($yysubmatches, $this->token + 1,
                                    $tokenMap[$this->token]);
                            } else {
                                $yysubmatches = array();
                            }
                        }
                    	$r = $this->{'yy_r2_' . $this->token}($yysubmatches);
                    } while ($r !== null && !is_bool($r));
			        if ($r === true) {
			            // we have changed state
			            // process this token in the new state
			            return $this->yylex();
                    } elseif ($r === false) {
                        $this->N += strlen($this->value);
                        $this->line += substr_count($this->value, "\n");
                        if ($this->N >= strlen($this->data)) {
                            return false; // end of input
                        }
                        // skip this token
                        continue;
			        } else {
	                    // accept
	                    $this->N += strlen($this->value);
	                    $this->line += substr_count($this->value, "\n");
	                    return true;
			        }
                }
            } else {
                throw new Exception('Unexpected input at line' . $this->line .
                    ': ' . $this->data[$this->N]);
            }
            break;
        } while (true);

    } // end function


    const CURLY = 2;
    function yy_r2_1($yy_subpatterns)
    {

    $this->token = vbTemplateParser::CURLY_START;
	$this->yypushstate(self::CURLY);
    }
    function yy_r2_2($yy_subpatterns)
    {

    $this->token = vbTemplateParser::CURLY_END;
	$this->yypopstate();
    }
    function yy_r2_3($yy_subpatterns)
    {

	return false;
    }
    function yy_r2_4($yy_subpatterns)
    {

	$this->currentValue = substr($this->value, 1, -1);
    $this->token = vbTemplateParser::STRING_VALUE;
    }
    function yy_r2_5($yy_subpatterns)
    {

    $this->token = vbTemplateParser::EQUAL;
    }
    function yy_r2_6($yy_subpatterns)
    {

    $this->token = vbTemplateParser::NOT_EQUAL;
    }
    function yy_r2_7($yy_subpatterns)
    {

	 $this->token = vbTemplateParser::SMALLER_OR_EQUAL;
    }
    function yy_r2_8($yy_subpatterns)
    {

	 $this->token = vbTemplateParser::GREATER_OR_EQUAL;
    }
    function yy_r2_9($yy_subpatterns)
    {

	$this->token = vbTemplateParser::BOOLEAN_OR;
    }
    function yy_r2_10($yy_subpatterns)
    {

	$this->token = vbTemplateParser::BOOLEAN_AND;
    }
    function yy_r2_11($yy_subpatterns)
    {

	$this->token = vbTemplateParser::LOGICAL_OR;
    }
    function yy_r2_12($yy_subpatterns)
    {

	$this->token = vbTemplateParser::LOGICAL_AND;
    }
    function yy_r2_13($yy_subpatterns)
    {

	$this->token = vbTemplateParser::SIMPLE_VAR;
    }
    function yy_r2_14($yy_subpatterns)
    {

	$this->token = vbTemplateParser::LITERAL_KEY;
    }
    function yy_r2_15($yy_subpatterns)
    {

	$this->token = vbTemplateParser::TOKEN;
    }
    function yy_r2_16($yy_subpatterns)
    {

    $this->token = vbTemplateParser::LITERAL;
    }

}
