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

/**
 * Lexer class
 *
 * Ported from https://github.com/zaach/jsonlint
 */
class Lexer
{
    /**
     * @var int
     */
    const EOF = 1;

    /**
     * @var array
     */
    private $_rules
        = array(
            0  => '/^\s+/',
            1  => '/^-?([0-9]|[1-9][0-9]+)(\.[0-9]+)?([eE][+-]?[0-9]+)?\b/',
            2  => '{^"(\\\\["bfnrt/\\\\]|\\\\u[a-fA-F0-9]{4}|[^\0-\x09\x0a-\x1f\\\\"])*"}',
            3  => '/^\{/',
            4  => '/^\}/',
            5  => '/^\[/',
            6  => '/^\]/',
            7  => '/^,/',
            8  => '/^:/',
            9  => '/^true\b/',
            10 => '/^false\b/',
            11 => '/^null\b/',
            12 => '/^$/',
            13 => '/^./',
        );

    /**
     * @var
     */
    private $_conditionStack;

    /**
     * @var array
     */
    private $_conditions
        = array(
            "INITIAL" => array(
                "rules"     => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13),
                "inclusive" => true,
            ),
        );

    /**
     * @var
     */
    private $_inputBuffer;

    /**
     * @var bool
     */
    public $more;

    /**
     * @var bool
     */
    public $less;

    /**
     * @var bool
     */
    public $done;

    /**
     * @var string
     */
    public $matched;

    /**
     * @var string
     */
    public $match;

    /**
     * @var int
     */
    public $yylineno;

    /**
     * @var int
     */
    public $yyleng;

    /**
     * @var string
     */
    public $yytext;

    /**
     * @var array
     */
    public $yylloc;

    /**
     * @return int|null|Undefined|string
     */
    public function lex()
    {
        $r = $this->_next();
        if (!$r instanceof Undefined) {
            return $r;
        }
        return $this->lex();
    }

    /**
     * @param $input
     *
     * @return Lexer
     */
    public function setInput($input)
    {
        $this->_inputBuffer = $input;

        $this->more = false;
        $this->less = false;
        $this->done = false;

        $this->yylineno = 0;
        $this->yyleng = 0;

        $this->yytext = '';
        $this->matched = '';
        $this->match = '';

        $this->_conditionStack = array(
            'INITIAL'
        );

        $this->yylloc = array(
            'first_line'   => 1,
            'first_column' => 0,
            'last_line'    => 1,
            'last_column'  => 0
        );

        return $this;
    }

    /**
     * @return string
     */
    public function showPosition()
    {
        $pre = $this->_pastInput();
        $c = str_repeat('-', strlen($pre)); // new Array(pre.length + 1).join("-");
        return $pre . $this->_upcomingInput() . "\n" . $c . "^";
    }

    /**
     * @param $str
     * @param $hash
     *
     * @throws \Exception
     */
    protected function _parseError($str, $hash)
    {
        throw new \Exception($str);
    }

    /**
     * @return mixed
     */
    private function _input()
    {
        $ch = $this->_inputBuffer[0];
        $this->yytext .= $ch;
        $this->yyleng++;
        $this->match .= $ch;
        $this->matched .= $ch;
        if (strpos($ch, "\n") !== false) {
            $this->yylineno++;
        }
        array_shift($this->_inputBuffer); // slice(1)
        return $ch;
    }

    /**
     * @param $ch
     *
     * @return Lexer
     */
    private function _unput($ch)
    {
        $this->_inputBuffer = $ch . $this->_inputBuffer;
        return $this;
    }

    /**
     * @return Lexer
     */
    private function more()
    {
        $this->more = true;
        return $this;
    }

    /**
     * @return string
     */
    private function _pastInput()
    {
        $past = substr($this->matched, 0, strlen($this->matched) - strlen($this->match));
        return (strlen($past) > 20 ? '...' : '') . str_replace("\n", '', substr($past, -20));
    }

    /**
     * @return mixed
     */
    private function _upcomingInput()
    {
        $next = $this->match;
        if (strlen($next) < 20) {
            $next .= substr($this->_inputBuffer, 0, 20 - strlen($next));
        }
        return str_replace("\n", '', substr($next, 0, 20) . (strlen($next) > 20 ? '...' : ''));
    }

    /**
     * @return int|null|Undefined|string
     */
    private function _next()
    {
        if ($this->done) {
            return self::EOF;
        }
        if (!$this->_inputBuffer) {
            $this->done = true;
        }

        $token = null;
        $match = null;
        $col = null;
        $lines = null;

        if (!$this->more) {
            $this->yytext = '';
            $this->match = '';
        }

        $rules = $this->_getCurrentRules();
        $rulesLen = count($rules);

        for ($i = 0; $i < $rulesLen; $i++) {
            if (preg_match($this->_rules[$rules[$i]], $this->_inputBuffer, $match)) {
                preg_match_all('/\n.*/', $match[0], $lines);
                $lines = $lines[0];
                if ($lines) {
                    $this->yylineno += count($lines);
                }

                $this->yylloc = array(
                    'first_line'   => $this->yylloc['last_line'],
                    'last_line'    => $this->yylineno + 1,
                    'first_column' => $this->yylloc['last_column'],
                    'last_column'  => $lines ? strlen($lines[count($lines) - 1]) - 1
                        : $this->yylloc['last_column'] + strlen($match[0]),
                );
                $this->yytext .= $match[0];
                $this->match .= $match[0];
                $this->matches = $match;
                $this->yyleng = strlen($this->yytext);
                $this->more = false;
                $this->_inputBuffer = substr($this->_inputBuffer, strlen($match[0]));
                $this->matched .= $match[0];
                $token = $this->_performAction($rules[$i], $this->_conditionStack[count($this->_conditionStack) - 1]);
                if ($token) {
                    return $token;
                }
                return new Undefined();
            }
        }

        if ($this->_inputBuffer === "") {
            return self::EOF;
        }

        $this->_parseError(
            'Lexical error on line ' . ($this->yylineno + 1) . ". Unrecognized text.\n" . $this->showPosition(),
            array(
                'text'  => "",
                'token' => null,
                'line'  => $this->yylineno,
            )
        );
    }

    /**
     * @param $condition
     */
    private function _begin($condition)
    {
        $this->_conditionStack[] = $condition;
    }

    /**
     * @return mixed
     */
    private function _popState()
    {
        return array_pop($this->_conditionStack);
    }

    /**
     * @return mixed
     */
    private function _getCurrentRules()
    {
        return $this->_conditions[$this->_conditionStack[count($this->_conditionStack) - 1]]['rules'];
    }

    /**
     * @param $avoidingNameCollisions
     * @param $yyStart
     *
     * @return int|string
     */
    private function _performAction($avoidingNameCollisions, $yyStart)
    {
        //this line is probably useless
        $state = $yyStart;

        switch ($avoidingNameCollisions) {
            case 0:
                //skip whitespace
                break;
            case 1:
                return 6;
                break;
            case 2:
                $this->yytext = substr($this->yytext, 1, $this->yyleng - 2);
                return 4;
            case 3:
                return 17;
            case 4:
                return 18;
            case 5:
                return 23;
            case 6:
                return 24;
            case 7:
                return 22;
            case 8:
                return 21;
            case 9:
                return 10;
            case 10:
                return 11;
            case 11:
                return 8;
            case 12:
                return 14;
            case 13:
                return 'INVALID';
        }
    }
}
