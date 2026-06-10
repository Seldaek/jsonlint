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
    const DETECT_KEY_CONFLICTS = 1;
    const ALLOW_DUPLICATE_KEYS = 2;
    const PARSE_TO_ASSOC = 4;
    const ALLOW_COMMENTS = 8;
    const ALLOW_DUPLICATE_KEYS_TO_ARRAY = 16;
    const VALIDATE_UTF8_ENCODING = 32;

    /** @var Lexer */
    private $lexer;

    /**
     * @var int
     * @phpstan-var int-mask-of<self::*>
     */
    private $flags;
    /** @var list<int> */
    private $stack;
    /** @var list<stdClass|array<mixed>|int|bool|float|string|null> */
    private $vstack; // semantic value stack
    /** @var list<array{first_line: int, first_column: int, last_line: int, last_column: int}> */
    private $lstack; // location stack

    /**
     * @phpstan-var array<string, int>
     */
    private $symbols = array(
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
     * @phpstan-var array<int, string>
     * @const
     */
    private $terminals_ = array(
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
     * @phpstan-var array<int<1,21>, array{int, int}>
     * @const
     */
    private $productions_ = array(
        1 => array(3, 1),
        2 => array(5, 1),
        3 => array(7, 1),
        4 => array(9, 1),
        5 => array(9, 1),
        6 => array(12, 2),
        7 => array(13, 1),
        8 => array(13, 1),
        9 => array(13, 1),
        10 => array(13, 1),
        11 => array(13, 1),
        12 => array(13, 1),
        13 => array(15, 2),
        14 => array(15, 3),
        15 => array(20, 3),
        16 => array(19, 1),
        17 => array(19, 3),
        18 => array(16, 2),
        19 => array(16, 3),
        20 => array(25, 1),
        21 => array(25, 3)
    );

    /**
     * @var array<int<0, 31>, array<int, array<int>|int>> List of stateID=>symbolID=>actionIDs|actionID
     * @const
     */
    private $table = array(
        0 => array( 3 => 5, 4 => array(1,12), 5 => 6, 6 => array(1,13), 7 => 3, 8 => array(1,9), 9 => 4, 10 => array(1,10), 11 => array(1,11), 12 => 1, 13 => 2, 15 => 7, 16 => 8, 17 => array(1,14), 23 => array(1,15)),
        1 => array( 1 => array(3)),
        2 => array( 14 => array(1,16)),
        3 => array( 14 => array(2,7), 18 => array(2,7), 22 => array(2,7), 24 => array(2,7)),
        4 => array( 14 => array(2,8), 18 => array(2,8), 22 => array(2,8), 24 => array(2,8)),
        5 => array( 14 => array(2,9), 18 => array(2,9), 22 => array(2,9), 24 => array(2,9)),
        6 => array( 14 => array(2,10), 18 => array(2,10), 22 => array(2,10), 24 => array(2,10)),
        7 => array( 14 => array(2,11), 18 => array(2,11), 22 => array(2,11), 24 => array(2,11)),
        8 => array( 14 => array(2,12), 18 => array(2,12), 22 => array(2,12), 24 => array(2,12)),
        9 => array( 14 => array(2,3), 18 => array(2,3), 22 => array(2,3), 24 => array(2,3)),
        10 => array( 14 => array(2,4), 18 => array(2,4), 22 => array(2,4), 24 => array(2,4)),
        11 => array( 14 => array(2,5), 18 => array(2,5), 22 => array(2,5), 24 => array(2,5)),
        12 => array( 14 => array(2,1), 18 => array(2,1), 21 => array(2,1), 22 => array(2,1), 24 => array(2,1)),
        13 => array( 14 => array(2,2), 18 => array(2,2), 22 => array(2,2), 24 => array(2,2)),
        14 => array( 3 => 20, 4 => array(1,12), 18 => array(1,17), 19 => 18, 20 => 19 ),
        15 => array( 3 => 5, 4 => array(1,12), 5 => 6, 6 => array(1,13), 7 => 3, 8 => array(1,9), 9 => 4, 10 => array(1,10), 11 => array(1,11), 13 => 23, 15 => 7, 16 => 8, 17 => array(1,14), 23 => array(1,15), 24 => array(1,21), 25 => 22 ),
        16 => array( 1 => array(2,6)),
        17 => array( 14 => array(2,13), 18 => array(2,13), 22 => array(2,13), 24 => array(2,13)),
        18 => array( 18 => array(1,24), 22 => array(1,25)),
        19 => array( 18 => array(2,16), 22 => array(2,16)),
        20 => array( 21 => array(1,26)),
        21 => array( 14 => array(2,18), 18 => array(2,18), 22 => array(2,18), 24 => array(2,18)),
        22 => array( 22 => array(1,28), 24 => array(1,27)),
        23 => array( 22 => array(2,20), 24 => array(2,20)),
        24 => array( 14 => array(2,14), 18 => array(2,14), 22 => array(2,14), 24 => array(2,14)),
        25 => array( 3 => 20, 4 => array(1,12), 20 => 29 ),
        26 => array( 3 => 5, 4 => array(1,12), 5 => 6, 6 => array(1,13), 7 => 3, 8 => array(1,9), 9 => 4, 10 => array(1,10), 11 => array(1,11), 13 => 30, 15 => 7, 16 => 8, 17 => array(1,14), 23 => array(1,15)),
        27 => array( 14 => array(2,19), 18 => array(2,19), 22 => array(2,19), 24 => array(2,19)),
        28 => array( 3 => 5, 4 => array(1,12), 5 => 6, 6 => array(1,13), 7 => 3, 8 => array(1,9), 9 => 4, 10 => array(1,10), 11 => array(1,11), 13 => 31, 15 => 7, 16 => 8, 17 => array(1,14), 23 => array(1,15)),
        29 => array( 18 => array(2,17), 22 => array(2,17)),
        30 => array( 18 => array(2,15), 22 => array(2,15)),
        31 => array( 22 => array(2,21), 24 => array(2,21)),
    );

    /**
     * @var array{16: array{2, 6}}
     * @const
     */
    private $defaultActions = array(
        16 => array(2, 6)
    );

    /**
     * @param  string                $input JSON string
     * @param  int                   $flags Bitmask of parse/lint options (see constants of this class)
     * @return null|ParsingException null if no error is found, a ParsingException containing all details otherwise
     *
     * @phpstan-param int-mask-of<self::*> $flags
     */
    public function lint($input, $flags = 0)
    {
        try {
            $this->parse($input, $flags);
        } catch (ParsingException $e) {
            return $e;
        }
        return null;
    }

    /**
     * @param  string           $input JSON string
     * @param  int              $flags Bitmask of parse/lint options (see constants of this class)
     * @return mixed
     * @throws ParsingException
     *
     * @phpstan-param int-mask-of<self::*> $flags
     */
    public function parse($input, $flags = 0)
    {
        if (($flags & self::ALLOW_DUPLICATE_KEYS_TO_ARRAY) && ($flags & self::ALLOW_DUPLICATE_KEYS)) {
            throw new \InvalidArgumentException('Only one of ALLOW_DUPLICATE_KEYS and ALLOW_DUPLICATE_KEYS_TO_ARRAY can be used, you passed in both.');
        }
        if ($flags & self::VALIDATE_UTF8_ENCODING) {
            $this->validateUTF8Encoding($input);
        }

        $this->failOnBOM($input);

        $this->flags = $flags;

        $this->stack = array(0);
        $this->vstack = array(null);
        $this->lstack = array();

        $yytext = '';
        $yylineno = 0;
        $yyleng = 0;
        /** @var int<0,3> */
        $recovering = 0;

        $this->lexer = new Lexer($flags);
        $this->lexer->setInput($input);

        $yyloc = $this->lexer->yylloc;
        $this->lstack[] = $yyloc;

        $symbol = null;
        $preErrorSymbol = null;
        $action = null;
        $a = null;
        $r = null;
        $p = null;
        $len = null;
        $newState = null;
        $expected = null;
        /** @var string|null */
        $errStr = null;

        while (true) {
            // retrieve state number from top of stack
            $state = $this->stack[\count($this->stack)-1];

            // use default actions if available
            if (isset($this->defaultActions[$state])) {
                $action = $this->defaultActions[$state];
            } else {
                if ($symbol === null) {
                    $symbol = $this->lexer->lex();
                }
                // read action for current state and first input
                /** @var array<int, int>|false */
                $action = isset($this->table[$state][$symbol]) ? $this->table[$state][$symbol] : false;
            }

            // handle parse error
            if (!$action || !$action[0]) {
                assert(isset($symbol));
                if (!$recovering) {
                    // Report error
                    $expected = array();
                    foreach ($this->table[$state] as $p => $ignore) {
                        if (isset($this->terminals_[$p]) && $p > 2) {
                            $expected[] = "'" . $this->terminals_[$p] . "'";
                        }
                    }

                    $message = null;
                    if (\in_array("'STRING'", $expected) && \in_array(substr($this->lexer->match, 0, 1), array('"', "'"))) {
                        $message = "Invalid string";
                        if ("'" === substr($this->lexer->match, 0, 1)) {
                            $message .= ", it appears you used single quotes instead of double quotes";
                        } elseif (preg_match('{".+?(\\\\[^"bfnrt/\\\\u](...)?)}', $this->lexer->getFullUpcomingInput(), $match)) {
                            $message .= ", it appears you have an unescaped backslash at: ".$match[1];
                        } elseif (preg_match('{"(?:[^"]+|\\\\")*$}m', $this->lexer->getFullUpcomingInput())) {
                            $message .= ", it appears you forgot to terminate a string, or attempted to write a multiline string which is invalid";
                        }
                    }

                    $errStr = 'Parse error on line ' . ($yylineno+1) . ":\n";
                    $errStr .= $this->lexer->showPosition() . "\n";
                    if ($message) {
                        $errStr .= $message;
                    } else {
                        $errStr .= (\count($expected) > 1) ? "Expected one of: " : "Expected: ";
                        $errStr .= implode(', ', $expected);
                    }

                    if (',' === substr(trim($this->lexer->getPastInput()), -1)) {
                        $errStr .= " - It appears you have an extra trailing comma";
                    }

                    $this->parseError($errStr, array(
                        'text' => $this->lexer->match,
                        'token' => isset($this->terminals_[$symbol]) ? $this->terminals_[$symbol] : $symbol,
                        'line' => $this->lexer->yylineno,
                        'loc' => $yyloc,
                        'expected' => $expected,
                    ));
                }

                // just recovered from another error
                if ($recovering == 3) {
                    if ($symbol === Lexer::EOF) {
                        throw new ParsingException($errStr ?: 'Parsing halted.');
                    }

                    // discard current lookahead and grab another
                    $yyleng = $this->lexer->yyleng;
                    $yytext = $this->lexer->yytext;
                    $yylineno = $this->lexer->yylineno;
                    $yyloc = $this->lexer->yylloc;
                    $symbol = $this->lexer->lex();
                }

                // try to recover from error
                while (true) {
                    // check for error recovery rule in this state
                    if (\array_key_exists(Lexer::T_ERROR, $this->table[$state])) {
                        break;
                    }
                    if ($state == 0) {
                        throw new ParsingException($errStr ?: 'Parsing halted.');
                    }
                    $this->popStack(1);
                    $state = $this->stack[\count($this->stack)-1];
                }

                $preErrorSymbol = $symbol; // save the lookahead token
                $symbol = Lexer::T_ERROR;         // insert generic error symbol as new lookahead
                $state = $this->stack[\count($this->stack)-1];
                /** @var array<int, int>|false */
                $action = isset($this->table[$state][Lexer::T_ERROR]) ? $this->table[$state][Lexer::T_ERROR] : false;
                if ($action === false) {
                    throw new \LogicException('No table value found for '.$state.' => '.Lexer::T_ERROR);
                }
                $recovering = 3; // allow 3 real symbols to be shifted before reporting a new error
            }

            // this shouldn't happen, unless resolve defaults are off
            if (\is_array($action[0]) && \count($action) > 1) {
                throw new ParsingException('Parse Error: multiple actions possible at state: ' . $state . ', token: ' . $symbol);
            }

            switch ($action[0]) {
                case 1: // shift
                    assert(isset($symbol));
                    $this->stack[] = $symbol;
                    $this->vstack[] = $this->lexer->yytext;
                    $this->lstack[] = $this->lexer->yylloc;
                    $this->stack[] = $action[1]; // push state
                    $symbol = null;
                    if (!$preErrorSymbol) { // normal execution/no error
                        $yyleng = $this->lexer->yyleng;
                        $yytext = $this->lexer->yytext;
                        $yylineno = $this->lexer->yylineno;
                        $yyloc = $this->lexer->yylloc;
                        if ($recovering > 0) {
                            $recovering--;
                        }
                    } else { // error just occurred, resume old lookahead from before error
                        $symbol = $preErrorSymbol;
                        $preErrorSymbol = null;
                    }
                    break;

                case 2: // reduce
                    $len = $this->productions_[$action[1]][1];

                    // perform semantic action
                    $currentToken = $this->vstack[\count($this->vstack) - $len]; // default to $$ = $1
                    // default location, uses first token for firsts, last for lasts
                    $position = array( // _$ = store
                        'first_line' => $this->lstack[\count($this->lstack) - ($len ?: 1)]['first_line'],
                        'last_line' => $this->lstack[\count($this->lstack) - 1]['last_line'],
                        'first_column' => $this->lstack[\count($this->lstack) - ($len ?: 1)]['first_column'],
                        'last_column' => $this->lstack[\count($this->lstack) - 1]['last_column'],
                    );
                    list($newToken, $actionResult) = $this->performAction($currentToken, $yytext, $yyleng, $yylineno, $action[1]);

                    if (!$actionResult instanceof Undefined) {
                        return $actionResult;
                    }

                    if ($len) {
                        $this->popStack($len);
                    }

                    $this->stack[] = $this->productions_[$action[1]][0];    // push nonterminal (reduce)
                    $this->vstack[] = $newToken;
                    $this->lstack[] = $position;
                    /** @var int */
                    $newState = $this->table[$this->stack[\count($this->stack)-2]][$this->stack[\count($this->stack)-1]];
                    $this->stack[] = $newState;
                    break;

                case 3: // accept

                    return true;
            }
        }
    }

    /**
     * @param  string $str
     * @param  array{text: string, token: string|int, line: int, loc: array{first_line: int, first_column: int, last_line: int, last_column: int}, expected: string[]}|null $hash
     * @return never
     */
    protected function parseError($str, $hash = null)
    {
        throw new ParsingException($str, $hash ?: array());
    }

    /**
     * @param  stdClass|array<mixed>|int|bool|float|string|null $currentToken
     * @param  string   $yytext
     * @param  int      $yyleng
     * @param  int      $yylineno
     * @param  int      $yystate
     * @return array{stdClass|array<mixed>|int|bool|float|string|null, stdClass|array<mixed>|int|bool|float|string|null|Undefined}
     */
    private function performAction($currentToken, $yytext, $yyleng, $yylineno, $yystate)
    {
        $token = $currentToken;

        $len = \count($this->vstack) - 1;
        switch ($yystate) {
        case 1:
            $yytext = preg_replace_callback('{(?:\\\\["bfnrt/\\\\]|\\\\u[a-fA-F0-9]{4})}', array($this, 'stringInterpolation'), $yytext);
            $token = $yytext;
            break;
        case 2:
            if (strpos($yytext, 'e') !== false || strpos($yytext, 'E') !== false) {
                $token = \floatval($yytext);
            } else {
                $token = strpos($yytext, '.') === false ? \intval($yytext) : \floatval($yytext);
            }
            break;
        case 3:
            $token = null;
            break;
        case 4:
            $token = true;
            break;
        case 5:
            $token = false;
            break;
        case 6:
            $token = $this->vstack[$len-1];

            return array($token, $token);
        case 13:
            if ($this->flags & self::PARSE_TO_ASSOC) {
                $token = array();
            } else {
                $token = new stdClass;
            }
            break;
        case 14:
            $token = $this->vstack[$len-1];
            break;
        case 15:
            $token = array($this->vstack[$len-2], $this->vstack[$len]);
            break;
        case 16:
            assert(\is_array($this->vstack[$len]));
            if (PHP_VERSION_ID < 70100) {
                $property = $this->vstack[$len][0] === '' ? '_empty_' : $this->vstack[$len][0];
            } else {
                $property = $this->vstack[$len][0];
            }
            if ($this->flags & self::PARSE_TO_ASSOC) {
                $token = array();
                $token[$property] = $this->vstack[$len][1];
            } else {
                $token = new stdClass;
                $token->$property = $this->vstack[$len][1];
            }
            break;
        case 17:
            assert(\is_array($this->vstack[$len]));
            if ($this->flags & self::PARSE_TO_ASSOC) {
                assert(\is_array($this->vstack[$len-2]));
                $token =& $this->vstack[$len-2];
                $key = $this->vstack[$len][0];
                if (($this->flags & self::DETECT_KEY_CONFLICTS) && isset($this->vstack[$len-2][$key])) {
                    $errStr = 'Parse error on line ' . ($yylineno+1) . ":\n";
                    $errStr .= $this->lexer->showPosition() . "\n";
                    $errStr .= "Duplicate key: ".$this->vstack[$len][0];
                    throw new DuplicateKeyException($errStr, $this->vstack[$len][0], array('line' => $yylineno+1));
                }
                if (($this->flags & self::ALLOW_DUPLICATE_KEYS) && isset($this->vstack[$len-2][$key])) {
                    $duplicateCount = 1;
                    do {
                        $duplicateKey = $key . '.' . $duplicateCount++;
                    } while (isset($this->vstack[$len-2][$duplicateKey]));
                    $this->vstack[$len-2][$duplicateKey] = $this->vstack[$len][1];
                } elseif (($this->flags & self::ALLOW_DUPLICATE_KEYS_TO_ARRAY) && isset($this->vstack[$len-2][$key])) {
                    if (!isset($this->vstack[$len-2][$key]['__duplicates__']) || !is_array($this->vstack[$len-2][$key]['__duplicates__'])) {
                        $this->vstack[$len-2][$key] = array('__duplicates__' => array($this->vstack[$len-2][$key]));
                    }
                    $this->vstack[$len-2][$key]['__duplicates__'][] = $this->vstack[$len][1];
                } else {
                    $this->vstack[$len-2][$key] = $this->vstack[$len][1];
                }
            } else {
                assert($this->vstack[$len-2] instanceof stdClass);
                $token = $this->vstack[$len-2];
                if (PHP_VERSION_ID < 70100) {
                    $key = $this->vstack[$len][0] === '' ? '_empty_' : $this->vstack[$len][0];
                } else {
                    $key = $this->vstack[$len][0];
                }
                if (($this->flags & self::DETECT_KEY_CONFLICTS) && isset($this->vstack[$len-2]->$key)) {
                    $errStr = 'Parse error on line ' . ($yylineno+1) . ":\n";
                    $errStr .= $this->lexer->showPosition() . "\n";
                    $errStr .= "Duplicate key: ".$this->vstack[$len][0];
                    throw new DuplicateKeyException($errStr, $this->vstack[$len][0], array('line' => $yylineno+1));
                }
                if (($this->flags & self::ALLOW_DUPLICATE_KEYS) && isset($this->vstack[$len-2]->$key)) {
                    $duplicateCount = 1;
                    do {
                        $duplicateKey = $key . '.' . $duplicateCount++;
                    } while (isset($this->vstack[$len-2]->$duplicateKey));
                    $this->vstack[$len-2]->$duplicateKey = $this->vstack[$len][1];
                } elseif (($this->flags & self::ALLOW_DUPLICATE_KEYS_TO_ARRAY) && isset($this->vstack[$len-2]->$key)) {
                    if (!isset($this->vstack[$len-2]->$key->__duplicates__)) {
                        $this->vstack[$len-2]->$key = (object) array('__duplicates__' => array($this->vstack[$len-2]->$key));
                    }
                    $this->vstack[$len-2]->$key->__duplicates__[] = $this->vstack[$len][1];
                } else {
                    $this->vstack[$len-2]->$key = $this->vstack[$len][1];
                }
            }
            break;
        case 18:
            $token = array();
            break;
        case 19:
            $token = $this->vstack[$len-1];
            break;
        case 20:
            $token = array($this->vstack[$len]);
            break;
        case 21:
            assert(\is_array($this->vstack[$len-2]));
            $this->vstack[$len-2][] = $this->vstack[$len];
            $token = $this->vstack[$len-2];
            break;
        }

        return array($token, new Undefined());
    }

    /**
     * @param  string $match
     * @return string
     */
    private function stringInterpolation($match)
    {
        switch ($match[0]) {
        case '\\\\':
            return '\\';
        case '\"':
            return '"';
        case '\b':
            return \chr(8);
        case '\f':
            return \chr(12);
        case '\n':
            return "\n";
        case '\r':
            return "\r";
        case '\t':
            return "\t";
        case '\/':
            return "/";
        default:
            return html_entity_decode('&#x'.ltrim(substr($match[0], 2), '0').';', ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * @param  int $n
     * @return void
     */
    private function popStack($n)
    {
        $this->stack = \array_slice($this->stack, 0, - (2 * $n));
        $this->vstack = \array_slice($this->vstack, 0, - $n);
        $this->lstack = \array_slice($this->lstack, 0, - $n);
    }

    /**
     * @param  string $input
     * @return void
     */
    private function failOnBOM($input)
    {
        // UTF-8 ByteOrderMark sequence
        $bom = "\xEF\xBB\xBF";

        if (substr($input, 0, 3) === $bom) {
            $this->parseError("BOM detected, make sure your input does not include a Unicode Byte-Order-Mark");
        }
    }

    /**
     * @param  string $input
     * @return void
     */
    private function validateUTF8Encoding($input)
    {
        //Fast-path
        if (function_exists("mb_check_encoding")) {
            if(mb_check_encoding($input, 'UTF-8')){
                return;
            }
        } else {
            if (preg_match('//u', $input) === 1) {
                return;
            }
        }

        $iCurrentOctet = null;
        $iContinuationOctetNeeded = 0;
        $iOffsetInOctetsFromStringStart = 0;
        $iOffsetInCharactersFromStringStart = -1;
        $iCharacterStartPositionFromStringStart = 0;
        $iCurrentLineNumber = 1;
        $iOffsetInOctetsFromLineStart = -1;
        $iOffsetInCharactersFromLineStart = -1;
        $iCharacterStartPositionFromLineStart = -1;

        // For the second octet of a character, hence first continuation octet,
        // further restriction may apply.
        $I_CONTINUATION_OCTET_MINIMUM = 128;
        $I_CONTINUATION_OCTET_MAXIMUM = 191;
        $iCurrentContinuationOctetMinimum = $I_CONTINUATION_OCTET_MINIMUM;
        $iCurrentContinuationOctetMaximum = $I_CONTINUATION_OCTET_MAXIMUM;

        for ($i = 0, $iMax = strlen($input); $i < $iMax; ++$i) {
            $iCurrentOctet = ord($input[$i]);
            $iOffsetInOctetsFromStringStart = $i;
            ++$iOffsetInOctetsFromLineStart;

            /*
            The octet values C0, C1, F5 to FF never appear.
            C0 = 12*16      = 192 = 11000000
            C1 = 12*16 + 1  = 193 = 11000001
            F5 = 15*16 + 5  = 245 = 11110101
            FF = 15*16 + 15 = 255 = 11111111
            */
            if (
                $iCurrentOctet === 192
                || $iCurrentOctet === 193
                || $iCurrentOctet === 245
                || $iCurrentOctet === 255
            ) {
                throw new InvalidEncodingException(
                    "Non-UTF8 character found on line "
                    .$iCurrentLineNumber
                    ."; the octet "
                    .($iOffsetInOctetsFromLineStart + 1)
                    .", part of the character "
                    .($iOffsetInCharactersFromLineStart + 1)
                    .", has value "
                    .$iCurrentOctet
                    ." which is one of the four forbidden values (C0, C1, F5, FF)."
                    ." This character starts at octet "
                    .($iCharacterStartPositionFromLineStart + 1)
                    ." of the current line."
                    ." (Sequential positions without line splitting:"
                    ." This is at character "
                    .($iOffsetInCharactersFromStringStart + 1)
                    ." and octet "
                    .($iOffsetInOctetsFromStringStart + 1)
                    ."."
                    ." This character starts at octet "
                    .($iCharacterStartPositionFromStringStart + 1)
                    .".)",
                    (string) $iCurrentOctet,
                    array(
                        'current_octet' => $iCurrentOctet,
                        'continuation_octet_needed' => $iContinuationOctetNeeded,
                        'offset_in_octets_from_string_start' => $iOffsetInOctetsFromStringStart,
                        'offset_in_characters_from_string_start' => $iOffsetInCharactersFromStringStart,
                        'character_start_position_from_string_start' => $iCharacterStartPositionFromStringStart,
                        'line' => $iCurrentLineNumber,
                        'offset_in_octets_from_line_start' => $iOffsetInOctetsFromLineStart,
                        'offset_in_characters_from_line_start' => $iOffsetInCharactersFromLineStart,
                        'character_start_position_from_line_start' => $iCharacterStartPositionFromLineStart,
                        'current_continuation_octet_minimum' => $iCurrentContinuationOctetMinimum,
                        'current_continuation_octet_maximum' => $iCurrentContinuationOctetMaximum,
                    )
                );
            }

            /*
            UTF8-octets = *( UTF8-char )
            UTF8-char   = UTF8-1 / UTF8-2 / UTF8-3 / UTF8-4
            UTF8-1      = %x00-7F
            UTF8-2      = %xC2-DF UTF8-tail
              Notice that values C0 and C1 are forbidden, hence %xC2
            UTF8-3      = %xE0 %xA0-BF UTF8-tail / %xE1-EC 2( UTF8-tail ) /
                          %xED %x80-9F UTF8-tail / %xEE-EF 2( UTF8-tail )
              Notice that E0 = 224 adds a restriction on second octet.
              Notice that ED = 237 adds a restriction on second octet.
            UTF8-4      = %xF0 %x90-BF 2( UTF8-tail ) / %xF1-F3 3( UTF8-tail ) /
                          %xF4 %x80-8F 2( UTF8-tail )
              Notice that F0 = 240 adds a restriction on second octet.
              Notice that F4 = 244 adds a restriction on second octet.
            UTF8-tail   = %x80-BF
            */
            if ($iContinuationOctetNeeded > 0) {
                if(
                    $iCurrentOctet < $iCurrentContinuationOctetMinimum
                    || $iCurrentOctet > $iCurrentContinuationOctetMaximum
                ){
                    throw new InvalidEncodingException(
                        "Non-UTF8 character found on line "
                        .$iCurrentLineNumber
                        ."; the octet "
                        .($iOffsetInOctetsFromLineStart + 1)
                        .", part of the character "
                        .($iOffsetInCharactersFromLineStart + 1)
                        .", has value "
                        .$iCurrentOctet
                        ." which is not a continuation octet."
                        ." This character starts at octet "
                        .($iCharacterStartPositionFromLineStart + 1)
                        ." of the current line."
                        ." (Sequential positions without line splitting:"
                        ." This is at character "
                        .($iOffsetInCharactersFromStringStart + 1)
                        ." and octet "
                        .($iOffsetInOctetsFromStringStart + 1)
                        ."."
                        ." This character starts at octet "
                        .($iCharacterStartPositionFromStringStart + 1)
                        .".)",
                        (string) $iCurrentOctet,
                        array(
                            'current_octet' => $iCurrentOctet,
                            'continuation_octet_needed' => $iContinuationOctetNeeded,
                            'offset_in_octets_from_string_start' => $iOffsetInOctetsFromStringStart,
                            'offset_in_characters_from_string_start' => $iOffsetInCharactersFromStringStart,
                            'character_start_position_from_string_start' => $iCharacterStartPositionFromStringStart,
                            'line' => $iCurrentLineNumber,
                            'offset_in_octets_from_line_start' => $iOffsetInOctetsFromLineStart,
                            'offset_in_characters_from_line_start' => $iOffsetInCharactersFromLineStart,
                            'character_start_position_from_line_start' => $iCharacterStartPositionFromLineStart,
                            'current_continuation_octet_minimum' => $iCurrentContinuationOctetMinimum,
                            'current_continuation_octet_maximum' => $iCurrentContinuationOctetMaximum,
                        )
                    );
                }
                --$iContinuationOctetNeeded;
                $iCurrentContinuationOctetMinimum = $I_CONTINUATION_OCTET_MINIMUM;
                $iCurrentContinuationOctetMaximum = $I_CONTINUATION_OCTET_MAXIMUM;
                continue;
            }

            ++$iOffsetInCharactersFromStringStart;
            ++$iOffsetInCharactersFromLineStart;
            $iCharacterStartPositionFromStringStart = $iOffsetInOctetsFromStringStart;
            $iCharacterStartPositionFromLineStart = $iOffsetInOctetsFromLineStart;

            if ($iCurrentOctet < 128) { // 0xxxxxxx ASCII
                // if ($input[$i] === "\n") {
                if ($iCurrentOctet === 10) {
                    ++$iCurrentLineNumber;
                    $iOffsetInOctetsFromLineStart = -1;
                    $iOffsetInCharactersFromLineStart = -1;
                }
                continue;
            }

            if (/*$iCurrentOctet >= 128 &&*/$iCurrentOctet < 192) {
                throw new InvalidEncodingException(
                    "Non-UTF8 character found on line "
                    .$iCurrentLineNumber
                    ."; the octet "
                    .($iOffsetInOctetsFromLineStart + 1)
                    .", part of the character "
                    .($iOffsetInCharactersFromLineStart + 1)
                    .", has value "
                    .$iCurrentOctet
                    ." which is a continuation octet."
                    ." This character starts at octet "
                    .($iCharacterStartPositionFromLineStart + 1)
                    ." of the current line."
                    ." (Sequential positions without line splitting:"
                    ." This is at character "
                    .($iOffsetInCharactersFromStringStart + 1)
                    ." and octet "
                    .($iOffsetInOctetsFromStringStart + 1)
                    ."."
                    ." This character starts at octet "
                    .($iCharacterStartPositionFromStringStart + 1)
                    .".)",
                    (string) $iCurrentOctet,
                    array(
                        'current_octet' => $iCurrentOctet,
                        'continuation_octet_needed' => $iContinuationOctetNeeded,
                        'offset_in_octets_from_string_start' => $iOffsetInOctetsFromStringStart,
                        'offset_in_characters_from_string_start' => $iOffsetInCharactersFromStringStart,
                        'character_start_position_from_string_start' => $iCharacterStartPositionFromStringStart,
                        'line' => $iCurrentLineNumber,
                        'offset_in_octets_from_line_start' => $iOffsetInOctetsFromLineStart,
                        'offset_in_characters_from_line_start' => $iOffsetInCharactersFromLineStart,
                        'character_start_position_from_line_start' => $iCharacterStartPositionFromLineStart,
                        'current_continuation_octet_minimum' => $iCurrentContinuationOctetMinimum,
                        'current_continuation_octet_maximum' => $iCurrentContinuationOctetMaximum,
                    )
                );
            }

            /*
            The definition of UTF-8 prohibits encoding character numbers between
            U+D800 and U+DFFF.
            D8 = 13*16 + 8  = 216 = 11010100
            DF = 13*16 + 15 = 223 = 11010101
            */
            if ($iCurrentOctet >= 216 && $iCurrentOctet <= 223) {
                throw new InvalidEncodingException(
                    "Non-UTF8 character found on line "
                    .$iCurrentLineNumber
                    ."; the octet "
                    .($iOffsetInOctetsFromLineStart + 1)
                    .", part of the character "
                    .($iOffsetInCharactersFromLineStart + 1)
                    .", has value "
                    .$iCurrentOctet
                    ." which is into a forbidden range of values for first octet of character."
                    ." This character starts at octet "
                    .($iCharacterStartPositionFromLineStart + 1)
                    ." of the current line."
                    ." (Sequential positions without line splitting:"
                    ." This is at character "
                    .($iOffsetInCharactersFromStringStart + 1)
                    ." and octet "
                    .($iOffsetInOctetsFromStringStart + 1)
                    ."."
                    ." This character starts at octet "
                    .($iCharacterStartPositionFromStringStart + 1)
                    .".)",
                    (string) $iCurrentOctet,
                    array(
                        'current_octet' => $iCurrentOctet,
                        'continuation_octet_needed' => $iContinuationOctetNeeded,
                        'offset_in_octets_from_string_start' => $iOffsetInOctetsFromStringStart,
                        'offset_in_characters_from_string_start' => $iOffsetInCharactersFromStringStart,
                        'character_start_position_from_string_start' => $iCharacterStartPositionFromStringStart,
                        'line' => $iCurrentLineNumber,
                        'offset_in_octets_from_line_start' => $iOffsetInOctetsFromLineStart,
                        'offset_in_characters_from_line_start' => $iOffsetInCharactersFromLineStart,
                        'character_start_position_from_line_start' => $iCharacterStartPositionFromLineStart,
                        'current_continuation_octet_minimum' => $iCurrentContinuationOctetMinimum,
                        'current_continuation_octet_maximum' => $iCurrentContinuationOctetMaximum,
                    )
                );
            }

            if (/*$iCurrentOctet >= 192 &&*/$iCurrentOctet < 224) {
                // 110xxxxx 10xxxxxx
                $iContinuationOctetNeeded = 1;
                continue;
            }
            if (/*$iCurrentOctet >= 224 &&*/$iCurrentOctet < 240) {
                // 1110xxxx 10xxxxxx 10xxxxxx
                $iContinuationOctetNeeded = 2;
                /*
                UTF8-3 = %xE0 %xA0-BF UTF8-tail / %xE1-EC 2( UTF8-tail ) /
                         %xED %x80-9F UTF8-tail / %xEE-EF 2( UTF8-tail )
                Notice that E0 = 224 adds a restriction on second octet.
                Notice that ED = 237 adds a restriction on second octet.
                */
                if($iCurrentOctet === 224){
                    $iCurrentContinuationOctetMinimum = 160;
                    $iCurrentContinuationOctetMaximum = 191;  // Normal value
                }
                if($iCurrentOctet === 237){
                    $iCurrentContinuationOctetMinimum = 128;  // Normal value
                    $iCurrentContinuationOctetMaximum = 159;
                }
                continue;
            }
            if (/*$iCurrentOctet >= 240 &&*/$iCurrentOctet < /*248*/ 245) {
                // 11110xxx 10xxxxxx 10xxxxxx 10xxxxxx
                $iContinuationOctetNeeded = 3;
                /*
                UTF8-4 = %xF0 %x90-BF 2( UTF8-tail ) / %xF1-F3 3( UTF8-tail ) /
                         %xF4 %x80-8F 2( UTF8-tail )
                Notice that F0 = 240 adds a restriction on second octet.
                Notice that F4 = 244 adds a restriction on second octet.
                */
                if($iCurrentOctet === 240){
                  $iCurrentContinuationOctetMinimum = 144;
                  $iCurrentContinuationOctetMaximum = 191;  // Normal value
                }
                if($iCurrentOctet === 244){
                  $iCurrentContinuationOctetMinimum = 128;  // Normal value
                  $iCurrentContinuationOctetMaximum = 143;
                }
                continue;
            }
            throw new InvalidEncodingException(
                "Non-UTF8 character found on line "
                .$iCurrentLineNumber
                ."; the octet "
                .($iOffsetInOctetsFromLineStart + 1)
                .", part of the character "
                .($iOffsetInCharactersFromLineStart + 1)
                .", has value "
                .$iCurrentOctet
                ." which is invalid."
                ." This character starts at octet "
                .($iCharacterStartPositionFromLineStart + 1)
                ." of the current line."
                ." (Sequential positions without line splitting:"
                ." This is at character "
                .($iOffsetInCharactersFromStringStart + 1)
                ." and octet "
                .($iOffsetInOctetsFromStringStart + 1)
                ."."
                ." This character starts at octet "
                .($iCharacterStartPositionFromStringStart + 1)
                .".)",
                (string) $iCurrentOctet,
                array(
                    'current_octet' => $iCurrentOctet,
                    'continuation_octet_needed' => $iContinuationOctetNeeded,
                    'offset_in_octets_from_string_start' => $iOffsetInOctetsFromStringStart,
                    'offset_in_characters_from_string_start' => $iOffsetInCharactersFromStringStart,
                    'character_start_position_from_string_start' => $iCharacterStartPositionFromStringStart,
                    'line' => $iCurrentLineNumber,
                    'offset_in_octets_from_line_start' => $iOffsetInOctetsFromLineStart,
                    'offset_in_characters_from_line_start' => $iOffsetInCharactersFromLineStart,
                    'character_start_position_from_line_start' => $iCharacterStartPositionFromLineStart,
                    'current_continuation_octet_minimum' => $iCurrentContinuationOctetMinimum,
                    'current_continuation_octet_maximum' => $iCurrentContinuationOctetMaximum,
                )
            );
        }
        if ($iContinuationOctetNeeded > 0) {
            throw new InvalidEncodingException(
                "Non-UTF8 character found on line "
                .$iCurrentLineNumber
                ."; at octet "
                .($iOffsetInOctetsFromLineStart + 1)
                .", part of the character "
                .($iOffsetInCharactersFromLineStart + 1)
                .", end of string was found instead of a continuation octet."
                ." This character starts at octet "
                .($iCharacterStartPositionFromLineStart + 1)
                ." of the current line."
                ." (Sequential positions without line splitting:"
                ." This is at character "
                .($iOffsetInCharactersFromStringStart + 1)
                ." and octet "
                .($iOffsetInOctetsFromStringStart + 1)
                ."."
                ." This character starts at octet "
                .($iCharacterStartPositionFromStringStart + 1)
                .".)",
                "0",
                array(
                    'current_octet' => $iCurrentOctet,
                    'continuation_octet_needed' => $iContinuationOctetNeeded,
                    'offset_in_octets_from_string_start' => $iOffsetInOctetsFromStringStart,
                    'offset_in_characters_from_string_start' => $iOffsetInCharactersFromStringStart,
                    'character_start_position_from_string_start' => $iCharacterStartPositionFromStringStart,
                    'line' => $iCurrentLineNumber,
                    'offset_in_octets_from_line_start' => $iOffsetInOctetsFromLineStart,
                    'offset_in_characters_from_line_start' => $iOffsetInCharactersFromLineStart,
                    'character_start_position_from_line_start' => $iCharacterStartPositionFromLineStart,
                    'current_continuation_octet_minimum' => $iCurrentContinuationOctetMinimum,
                    'current_continuation_octet_maximum' => $iCurrentContinuationOctetMaximum,
                )
            );
        }
    }
}
