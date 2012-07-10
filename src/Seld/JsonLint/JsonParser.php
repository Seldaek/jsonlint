<?php

/*
 * This file is part of the JSON Lint package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Seld\JsonLint;

use stdClass;

/**
 * Parser class
 *
 * Example:
 *
 * $parser = new JsonParser();
 * // returns null if it's valid json, or an error object
 * $parser->lint($json);
 * // returns parsed json, like json_decode does, but slower, throws exceptions on failure.
 * $parser->parse($json);
 *
 * Ported from https://github.com/zaach/jsonlint
 */
class JsonParser
{
    /**
     *
     */
    const DETECT_KEY_CONFLICTS = 1;

    /**
     * @var
     */
    private $_flags;

    /**
     * @var
     */
    private $_stack;

    /**
     * semantic value stack
     *
     * @var
     */
    private $_vstack;

    /**
     * location stack
     *
     * @var
     */
    private $_lstack;

    /**
     * @var
     */
    private $_yy;

    /**
     * @var array
     */
    private $_symbols
        = array(
            'error'                 => 2,
            'JSONString'            => 3,
            'STRING'                => 4,
            'JSONNumber'            => 5,
            'NUMBER'                => 6,
            'JSONNullLiteral'       => 7,
            'NULL'                  => 8,
            'JSONBooleanLiteral'    => 9,
            'TRUE'                  => 10,
            'FALSE'                 => 11,
            'JSONText'              => 12,
            'JSONValue'             => 13,
            'EOF'                   => 14,
            'JSONObject'            => 15,
            'JSONArray'             => 16,
            '{'                     => 17,
            '}'                     => 18,
            'JSONMemberList'        => 19,
            'JSONMember'            => 20,
            ':'                     => 21,
            ','                     => 22,
            '['                     => 23,
            ']'                     => 24,
            'JSONElementList'       => 25,
            '$accept'               => 0,
            '$end'                  => 1,
        );

    /**
     * @var array
     */
    private $_terminals
        = array(
            2   => "error",
            4   => "STRING",
            6   => "NUMBER",
            8   => "NULL",
            10  => "TRUE",
            11  => "FALSE",
            14  => "EOF",
            17  => "{",
            18  => "}",
            21  => ":",
            22  => ",",
            23  => "[",
            24  => "]",
        );

    /**
     * @var array
     */
    private $_productions
        = array(
            0,
            array(3, 1),
            array(5, 1),
            array(7, 1),
            array(9, 1),
            array(9, 1),
            array(12, 2),
            array(13, 1),
            array(13, 1),
            array(13, 1),
            array(13, 1),
            array(13, 1),
            array(13, 1),
            array(15, 2),
            array(15, 3),
            array(20, 3),
            array(19, 1),
            array(19, 3),
            array(16, 2),
            array(16, 3),
            array(25, 1),
            array(25, 3)
        );

    /**
     * @var array
     */
    private $_table
        = array(
            array(
                3  => 5, 4 => array(1, 12), 5 => 6, 6 => array(1, 13), 7 => 3, 8 => array(1, 9), 9 => 4,
                10 => array(1, 10), 11 => array(1, 11), 12 => 1, 13 => 2, 15 => 7, 16 => 8, 17 => array(1, 14),
                23 => array(1, 15)
            ), array(1 => array(3)), array(14 => array(1, 16)),
            array(14 => array(2, 7), 18 => array(2, 7), 22 => array(2, 7), 24 => array(2, 7)),
            array(14 => array(2, 8), 18 => array(2, 8), 22 => array(2, 8), 24 => array(2, 8)),
            array(14 => array(2, 9), 18 => array(2, 9), 22 => array(2, 9), 24 => array(2, 9)),
            array(14 => array(2, 10), 18 => array(2, 10), 22 => array(2, 10), 24 => array(2, 10)),
            array(14 => array(2, 11), 18 => array(2, 11), 22 => array(2, 11), 24 => array(2, 11)),
            array(14 => array(2, 12), 18 => array(2, 12), 22 => array(2, 12), 24 => array(2, 12)),
            array(14 => array(2, 3), 18 => array(2, 3), 22 => array(2, 3), 24 => array(2, 3)),
            array(14 => array(2, 4), 18 => array(2, 4), 22 => array(2, 4), 24 => array(2, 4)),
            array(14 => array(2, 5), 18 => array(2, 5), 22 => array(2, 5), 24 => array(2, 5)),
            array(14 => array(2, 1), 18 => array(2, 1), 21 => array(2, 1), 22 => array(2, 1), 24 => array(2, 1)),
            array(14 => array(2, 2), 18 => array(2, 2), 22 => array(2, 2), 24 => array(2, 2)),
            array(3 => 20, 4 => array(1, 12), 18 => array(1, 17), 19 => 18, 20 => 19), array(
                3  => 5, 4 => array(1, 12), 5 => 6, 6 => array(1, 13), 7 => 3, 8 => array(1, 9), 9 => 4,
                10 => array(1, 10), 11 => array(1, 11), 13 => 23, 15 => 7, 16 => 8, 17 => array(1, 14),
                23 => array(1, 15), 24 => array(1, 21), 25 => 22
            ), array(1 => array(2, 6)),
            array(14 => array(2, 13), 18 => array(2, 13), 22 => array(2, 13), 24 => array(2, 13)),
            array(18 => array(1, 24), 22 => array(1, 25)), array(18 => array(2, 16), 22 => array(2, 16)),
            array(21 => array(1, 26)),
            array(14 => array(2, 18), 18 => array(2, 18), 22 => array(2, 18), 24 => array(2, 18)),
            array(22 => array(1, 28), 24 => array(1, 27)), array(22 => array(2, 20), 24 => array(2, 20)),
            array(14 => array(2, 14), 18 => array(2, 14), 22 => array(2, 14), 24 => array(2, 14)),
            array(3 => 20, 4 => array(1, 12), 20 => 29), array(
                3  => 5, 4 => array(1, 12), 5 => 6, 6 => array(1, 13), 7 => 3, 8 => array(1, 9), 9 => 4,
                10 => array(1, 10), 11 => array(1, 11), 13 => 30, 15 => 7, 16 => 8, 17 => array(1, 14),
                23 => array(1, 15)
            ), array(14 => array(2, 19), 18 => array(2, 19), 22 => array(2, 19), 24 => array(2, 19)), array(
                3  => 5, 4 => array(1, 12), 5 => 6, 6 => array(1, 13), 7 => 3, 8 => array(1, 9), 9 => 4,
                10 => array(1, 10), 11 => array(1, 11), 13 => 31, 15 => 7, 16 => 8, 17 => array(1, 14),
                23 => array(1, 15)
            ), array(18 => array(2, 17), 22 => array(2, 17)), array(18 => array(2, 15), 22 => array(2, 15)),
            array(22 => array(2, 21), 24 => array(2, 21)),
        );

    /**
     * @var array
     */
    private $_defaultActions
        = array(
            16 => array(2, 6)
        );

    /**
     * @param string $input JSON string
     *
     * @return null|ParsingException null if no error is found, a ParsingException containing all details otherwise
     */
    public function lint($input)
    {
        try {
            $this->parse($input);
            return null;
        } catch (ParsingException $e) {
            return $e;
        }
    }

    /**
     * @param string $input JSON string
     * @param        $flags
     *
     * @return mixed
     * @throws ParsingException
     */
    public function parse($input, $flags = 0)
    {
        $this->_flags = $flags;

        $this->_stack = array(0);
        $this->_vstack = array(null);
        $this->_lstack = array();

        $yytext = '';
        $yylineno = 0;
        $yyleng = 0;
        $recovering = 0;
        $terror = 2;
        $eof = 1;

        $this->lexer = new Lexer();
        $this->lexer->setInput($input);

        $yyloc = $this->lexer->yylloc;
        $this->_lstack[] = $yyloc;

        $symbol = null;
        $preErrorSymbol = null;
        $state = null;
        $action = null;
        $a = null;
        $r = null;
        $yyval = new stdClass;
        $p = null;
        $len = null;
        $newState = null;
        $expected = null;
        $errStr = null;

        while (true) {
            // retreive state number from top of stack
            $state = $this->_stack[count($this->_stack) - 1];

            // use default actions if available
            if (isset($this->_defaultActions[$state])) {
                $action = $this->_defaultActions[$state];
            } else {
                if ($symbol == null) {
                    $symbol = $this->_lex();
                }
                // read action for current state and first input
                $action = isset($this->_table[$state][$symbol]) ? $this->_table[$state][$symbol] : false;
            }

            // handle parse error
            if (!$action || !$action[0]) {
                if (!$recovering) {
                    // Report error
                    $expected = array();
                    foreach ($this->_table[$state] as $p => $ignore) {
                        if (isset($this->_terminals[$p]) && $p > 2) {
                            $expected[] = "'" . $this->_terminals[$p] . "'";
                        }
                    }

                    $errStr = 'Parse error on line ' . ($yylineno + 1) . ":\n";
                    $errStr .= $this->lexer->showPosition() . "\n";
                    $errStr .= (count($expected) > 1) ? "Expected one of: " : "Expected: ";
                    $errStr .= implode(', ', $expected);

                    $this->_parseError(
                        $errStr,
                        array(
                            'text'     => $this->lexer->match,
                            'token'    => !empty($this->_terminals[$symbol]) ? $this->_terminals[$symbol] : $symbol,
                            'line'     => $this->lexer->yylineno,
                            'loc'      => $yyloc,
                            'expected' => $expected,
                        )
                    );
                }

                // just recovered from another error
                if ($recovering == 3) {
                    if ($symbol == $eof) {
                        throw new ParsingException($errStr ? : 'Parsing halted.');
                    }

                    // discard current lookahead and grab another
                    $yyleng = $this->lexer->yyleng;
                    $yytext = $this->lexer->yytext;
                    $yylineno = $this->lexer->yylineno;
                    $yyloc = $this->lexer->yylloc;
                    $symbol = $this->_lex();
                }

                // try to recover from error
                while (true) {
                    // check for error recovery rule in this state
                    if (array_key_exists($terror, $this->_table[$state])) {
                        break;
                    }
                    if ($state == 0) {
                        throw new ParsingException($errStr ? : 'Parsing halted.');
                    }
                    $this->_popStack(1);
                    $state = $this->_stack[count($this->_stack) - 1];
                }

                $preErrorSymbol = $symbol; // save the lookahead token
                $symbol = $terror; // insert generic error symbol as new lookahead
                $state = $this->_stack[count($this->_stack) - 1];
                $action = isset($this->_table[$state][$terror]) ? $this->_table[$state][$terror] : false;
                $recovering = 3; // allow 3 real symbols to be shifted before reporting a new error
            }

            // this shouldn't happen, unless resolve defaults are off
            if (is_array($action[0]) && count($action) > 1) {
                throw new ParsingException(
                    'Parse Error: multiple actions possible at state: ' . $state . ', token: ' . $symbol
                );
            }

            switch ($action[0]) {
            case 1: // shift
                $this->_stack[] = $symbol;
                $this->_vstack[] = $this->lexer->yytext;
                $this->_lstack[] = $this->lexer->yylloc;
                $this->_stack[] = $action[1]; // push state
                $symbol = null;
                if (!$preErrorSymbol) { // normal execution/no error
                    $yyleng = $this->lexer->yyleng;
                    $yytext = $this->lexer->yytext;
                    $yylineno = $this->lexer->yylineno;
                    $yyloc = $this->lexer->yylloc;
                    if ($recovering > 0) {
                        $recovering--;
                    }
                } else { // error just occurred, resume old lookahead f/ before error
                    $symbol = $preErrorSymbol;
                    $preErrorSymbol = null;
                }
                break;

            case 2: // reduce
                $len = $this->_productions[$action[1]][1];

                // perform semantic action
                $yyval->token = $this->_vstack[count($this->_vstack) - $len]; // default to $$ = $1
                // default location, uses first token for firsts, last for lasts
                $yyval->store = array( // _$ = store
                    'first_line'   => $this->_lstack[count($this->_lstack) - ($len ? : 1)]['first_line'],
                    'last_line'    => $this->_lstack[count($this->_lstack) - 1]['last_line'],
                    'first_column' => $this->_lstack[count($this->_lstack) - ($len ? : 1)]['first_column'],
                    'last_column'  => $this->_lstack[count($this->_lstack) - 1]['last_column'],
                );
                $r = $this->_performAction(
                    $yyval, $yytext, $yyleng, $yylineno, $action[1], $this->_vstack, $this->_lstack
                );

                if (!$r instanceof Undefined) {
                    return $r;
                }

                if ($len) {
                    $this->_popStack($len);
                }

                // push nonterminal (reduce)
                $this->_stack[] = $this->_productions[$action[1]][0];

                $this->_vstack[] = $yyval->token;
                $this->_lstack[] = $yyval->store;

                $newState = $this->_getNewState();
                $this->_stack[] = $newState;
                break;

            case 3: // accept
                return true;
            }
        }
        return true;
    }

    /**
     * @return mixed
     */
    protected function _getNewState()
    {
        $stackCount = count($this->_stack);
        return $this->_table[$this->_stack[$stackCount - 2]][$this->_stack[$stackCount - 1]];
    }

    /**
     * @param $str
     * @param $hash
     *
     * @throws ParsingException
     */
    protected function _parseError($str, $hash)
    {
        throw new ParsingException($str, $hash);
    }

    /**
     * $$ = $tokens // needs to be passed by ref?
     * $ = $token
     * _$ removed, useless?
     *
     * @param \stdClass $yyval
     * @param           $yytext
     * @param           $yyleng
     * @param           $yylineno
     * @param           $yystate
     * @param           $tokens
     *
     * @return Undefined
     * @throws ParsingException
     */
    private function _performAction(stdClass $yyval, $yytext, $yyleng, $yylineno, $yystate, &$tokens)
    {
        // $0 = $len
        $len = count($tokens) - 1;
        switch ($yystate) {
            case 1:
                $yytext = preg_replace_callback(
                    '{(?:\\\\["bfnrt/\\\\]|\\\\u[a-fA-F0-9]{4})}', array($this, '_stringInterpolation'), $yytext
                );
                $yyval->token = $yytext;
                break;
            case 2:
                if (strpos($yytext, 'e') !== false || strpos($yytext, 'E') !== false) {
                    $yyval->token = floatval($yytext);
                } else {
                    $yyval->token = strpos($yytext, '.') === false ? intval($yytext) : floatval($yytext);
                }
                break;
            case 3:
                $yyval->token = null;
                break;
            case 4:
                $yyval->token = true;
                break;
            case 5:
                $yyval->token = false;
                break;
            case 6:
                return $yyval->token = $tokens[$len - 1];
            case 13:
                $yyval->token = new stdClass;
                break;
            case 14:
                $yyval->token = $tokens[$len - 1];
                break;
            case 15:
                $yyval->token = array($tokens[$len - 2], $tokens[$len]);
                break;
            case 16:
                $yyval->token = new stdClass;
                $property = $tokens[$len][0] === '' ? '_empty_' : $tokens[$len][0];
                $yyval->token->$property = $tokens[$len][1];
                break;
            case 17:
                $yyval->token = $tokens[$len - 2];
                if (($this->_flags & self::DETECT_KEY_CONFLICTS) && isset($tokens[$len - 2]->{$tokens[$len][0]})) {
                    $errStr = 'Parse error on line ' . ($yylineno + 1) . ":\n";
                    $errStr .= $this->lexer->showPosition() . "\n";
                    $errStr .= "Duplicate key: " . $tokens[$len][0];
                    throw new ParsingException($errStr);
                }
                $tokens[$len - 2]->{$tokens[$len][0]} = $tokens[$len][1];
                break;
            case 18:
                $yyval->token = array();
                break;
            case 19:
                $yyval->token = $tokens[$len - 1];
                break;
            case 20:
                $yyval->token = array($tokens[$len]);
                break;
            case 21:
                $tokens[$len - 2][] = $tokens[$len];
                $yyval->token = $tokens[$len - 2];
                break;
        }

        return new Undefined();
    }

    /**
     * @param $match
     *
     * @return string
     */
    private function _stringInterpolation($match)
    {
        switch ($match[0]) {
        case '\\\\':
            return '\\';
        case '\"':
            return '"';
        case '\b':
            return chr(8);
        case '\f':
            return chr(12);
        case '\n':
            return "\n";
        case '\r':
            return "\r";
        case '\t':
            return "\t";
        case '\/':
            return "/";
        default:
            return html_entity_decode('&#x' . ltrim(substr($match[0], 2), '0') . ';', 0, 'UTF-8');
        }
    }

    /**
     * @param $n
     */
    private function _popStack($n)
    {
        $this->_stack = array_slice($this->_stack, 0, -(2 * $n));
        $this->_vstack = array_slice($this->_vstack, 0, -$n);
        $this->_lstack = array_slice($this->_lstack, 0, -$n);
    }

    /**
     * @return int
     */
    private function _lex()
    {
        $token = $this->lexer->lex() ? : 1; // $end = 1
        // if token isn't its numeric value, convert
        if (!is_numeric($token)) {
            $token = isset($this->_symbols[$token]) ? $this->_symbols[$token] : $token;
        }
        return $token;
    }
}
