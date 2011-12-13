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
    private $EOF = 1;
    private $rules = array(
        0 => '/^\s+/',
        1 => '/^-?([0-9]|[1-9][0-9]+)(\.[0-9]+)?([eE][+-]?[0-9]+)?\b/',
        2 => '{^"(\\\\["bfnrt/\\\\]|\\\\u[a-fA-F0-9]{4}|[^\0-\x09\x0a-\x1f\\\\"])*"}',
        3 => '/^\{/',
        4 => '/^\}/',
        5 => '/^\[/',
        6 => '/^\]/',
        7 => '/^,/',
        8 => '/^:/',
        9 => '/^true\b/',
        10 => '/^false\b/',
        11 => '/^null\b/',
        12 => '/^$/',
        13 => '/^./',
    );

    private $conditions = array(
        "INITIAL" => array(
            "rules" => array(0,1,2,3,4,5,6,7,8,9,10,11,12,13),
            "inclusive" => true,
        ),
    );

    public function lex()
    {
        $r = $this->next();
        if (!$r instanceof Undefined) {
            return $r;
        }
        return $this->lex();
    }

    public function setInput($input)
    {
        $this->_input = $input;
        $this->_more = $this->_less = $this->done = false;
        $this->yylineno = $this->yyleng = 0;
        $this->yytext = $this->matched = $this->match = '';
        $this->conditionStack = array('INITIAL');
        $this->yylloc = array('first_line' => 1, 'first_column' => 0, 'last_line' => 1, 'last_column' => 0);

        return $this;
    }

    public function showPosition()
    {
        $pre = $this->pastInput();
        $c = str_repeat('-', strlen($pre)); // new Array(pre.length + 1).join("-");
        return $pre . $this->upcomingInput() . "\n" . $c . "^";
    }

    protected function parseError($str, $hash)
    {
        throw new \Exception($str);
    }

    private function input()
    {
        $ch = $this->_input[0];
        $this->yytext += $ch;
        $this->yyleng++;
        $this->match += $ch;
        $this->matched += $ch;
        if (strpos($ch, "\n") !== false) {
            $this->yylineno++;
        }
        array_shift($this->_input); // slice(1)
        return $ch;
    }

    private function unput($ch)
    {
        $this->_input = $ch . $this->_input;
        return $this;
    }

    private function more()
    {
        $this->_more = true;
        return $this;
    }

    private function pastInput()
    {
        $past = substr($this->matched, 0, strlen($this->matched) - strlen($this->match));
        return (strlen($past) > 20 ? '...' : '') . str_replace("\n", '', substr($past, -20));
    }

    private function upcomingInput()
    {
        $next = $this->match;
        if (strlen($next) < 20) {
            $next += substr($this->_input, 0, 20 - strlen($next));
        }
        return str_replace("\n", '', substr($next, 0, 20) . (strlen($next) > 20 ? '...' : ''));
    }

    private function next()
    {
        if ($this->done) {
            return $this->EOF;
        }
        if (!$this->_input) {
            $this->done = true;
        }

        $token = null;
        $match = null;
        $col = null;
        $lines = null;

        if (!$this->_more) {
            $this->yytext = '';
            $this->match = '';
        }

        $rules = $this->_currentRules();
        $rulesLen = count($rules);

        for ($i=0; $i < $rulesLen; $i++) {
            if (preg_match($this->rules[$rules[$i]], $this->_input, $match)) {
                preg_match_all('/\n.*/', $match[0], $lines);
                $lines = $lines[0];
                if ($lines) {
                    $this->yylineno += count($lines);
                }

                $this->yylloc = array(
                    'first_line' => $this->yylloc['last_line'],
                    'last_line' => $this->yylineno+1,
                    'first_column' => $this->yylloc['last_column'],
                    'last_column' => $lines ? strlen($lines[count($lines) - 1]) - 1 : $this->yylloc['last_column'] + strlen($match[0]),
                );
                $this->yytext .= $match[0];
                $this->match .= $match[0];
                $this->matches = $match;
                $this->yyleng = strlen($this->yytext);
                $this->_more = false;
                $this->_input = substr($this->_input, strlen($match[0]));
                $this->matched .= $match[0];
                $token = $this->performAction($rules[$i], $this->conditionStack[count($this->conditionStack)-1]);
                if ($token) {
                    return $token;
                }
                return new Undefined();
            }
        }

        if ($this->_input === "") {
            return $this->EOF;
        }

        $this->parseError(
            'Lexical error on line ' . ($this->yylineno+1) . ". Unrecognized text.\n" . $this->showPosition(),
            array(
                'text' => "",
                'token' => null,
                'line' => $this->yylineno,
            )
        );
    }

    private function begin($condition)
    {
        $this->conditionStack[] = $condition;
    }

    private function popState()
    {
        return array_pop($this->conditionStack);
    }

    private function _currentRules()
    {
        return $this->conditions[$this->conditionStack[count($this->conditionStack)-1]]['rules'];
    }

    private function performAction($avoiding_name_collisions, $YY_START)
    {
        $YYSTATE = $YY_START;
        switch ($avoiding_name_collisions) {
        case 0:/* skip whitespace */
            break;
        case 1:
            return 6;
           break;
        case 2:
            $this->yytext = substr($this->yytext, 1, $this->yyleng-2);
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
