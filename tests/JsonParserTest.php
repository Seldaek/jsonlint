<?php

/*
 * This file is part of the JSON Lint package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;

class JsonParserTest extends PHPUnit_Framework_TestCase
{
    protected $json = array(
        '42', '42.3', '0.3', '-42', '-42.3', '-0.3',
        '2e1', '2E1', '-2e1', '-2E1', '2E+2', '2E-2', '-2E+2', '-2E-2',
        'true', 'false', 'null', '""', '[]', '{}', '"string"',
        '["a", "sdfsd"]',
        '{"foo":"bar", "bar":"baz"}',
        '"\u00c9v\u00e9nement"',
        '"http:\/\/foo.com"',
        '"zo\\\\mg"',
        '{"test":"\u00c9v\u00e9nement"}',
        '["\u00c9v\u00e9nement"]',
        '"foo/bar"',
        '{"test":"http:\/\/foo\\\\zomg"}',
        '["http:\/\/foo\\\\zomg"]',
        '{"":"foo"}',
        '{"a":"b", "b":"c"}',
    );

    /**
     * @dataProvider provideValidStrings
     */
    public function testParsesValidStrings($input)
    {
        $parser = new JsonParser();
        $this->assertEquals(json_decode($input), $parser->parse($input));
    }

    public function provideValidStrings()
    {
        $strings = array();
        foreach ($this->json as $input) {
            $strings[] = array($input);
        }

        return $strings;
    }

    public function testParsesMultiInARow()
    {
        $parser = new JsonParser();
        foreach ($this->json as $input) {
            $this->assertEquals(json_decode($input), $parser->parse($input));
        }
    }

    public function testDetectsKeyOverrides()
    {
        $parser = new JsonParser();

        try {
            $parser->parse('{"a":"b", "a":"c"}', JsonParser::DETECT_KEY_CONFLICTS);
            $this->fail('Duplicate keys should not be allowed');
        } catch (ParsingException $e) {
            $this->assertContains('Duplicate key: a', $e->getMessage());
        }
    }
    public function testDuplicateKeys()
    {
        $parser = new JsonParser();

        $result = $parser->parse('{"a":"b", "a":"c", "a":"d"}', JsonParser::ALLOW_DUPLICATE_KEYS);
        $this->assertThat($result,
            $this->logicalAnd(
                $this->arrayHasKey('a'),
                $this->arrayHasKey('a.1'),
                $this->arrayHasKey('a.2')
            )
        );
    }
}
