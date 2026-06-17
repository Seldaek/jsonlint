<?php

/*
 * This file is part of the JSON Lint package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PHPUnit\Framework\TestCase;
use Seld\JsonLint\Utf8Validator;
use Seld\JsonLint\InvalidEncodingException;

/**
 * @author Laurent Lyaudet <laurent.lyaudet@gmail.com>
 */
class Utf8ValidatorTest extends TestCase
{
    public function testValidUtf8()
    {
        try {
            Utf8Validator::validate('');
            Utf8Validator::validate('abcdé');                  // 2-octet character
            Utf8Validator::validate('euro sign: € and more');  // 3-octet character
            Utf8Validator::validate('musical clef: 𝄞');        // 4-octet character
            Utf8Validator::validate("line 1\nline 2\nline 3"); // newline handling
            $this->addToAssertionCount(1);
        } catch (InvalidEncodingException $e) {
            $this->fail('Valid UTF-8 should pass validation: '.$e->getMessage());
        }
    }

    public function testNotAContinuationOctet()
    {
        try {
            Utf8Validator::validate('"abcd'.chr(233).'"');
            $this->fail('ISO 8859-15 "abcdé" should not pass validation.');
        } catch (InvalidEncodingException $e) {
            $this->assertContains('Non-UTF8 character found', $e->getMessage());
            $this->assertContains(' which is not a continuation octet.', $e->getMessage());
        }
        for ($i = 246; $i < 255; ++$i) { // 245 and 255 already forbidden
            try {
                Utf8Validator::validate('"abcd'.chr(195).chr($i).'"');
                $this->fail('"abcd\d195\d'.$i.'" should not pass validation.');
            } catch (InvalidEncodingException $e) {
                $this->assertContains('Non-UTF8 character found', $e->getMessage());
                $this->assertContains(' which is not a continuation octet.', $e->getMessage());
            }
        }
    }

    public function testPrematureEndOfString()
    {
        try {
            Utf8Validator::validate('"abcd'.chr(233));
            $this->fail('ISO 8859-15 "abcdé should not pass validation.');
        } catch (InvalidEncodingException $e) {
            $this->assertContains('Non-UTF8 character found', $e->getMessage());
            $this->assertContains(
                ', end of string was found instead of a continuation octet.',
                $e->getMessage()
            );
        }
    }

    public function testForbiddenOctets()
    {
        $forbiddenOctets = array(
            192,
            193,
            245,
            255
        );
        foreach ($forbiddenOctets as $forbiddenOctet) {
            try {
                Utf8Validator::validate('"abcd'.chr(233).chr($forbiddenOctet).'"');
                $this->fail('"abcd\d233\d'.$forbiddenOctet.'" should not pass validation.');
            } catch (InvalidEncodingException $e) {
                $this->assertContains('Non-UTF8 character found', $e->getMessage());
                $this->assertContains(
                    ' which is one of the four forbidden values (C0, C1, F5, FF).',
                    $e->getMessage()
                );
            }
            try {
                Utf8Validator::validate('"abcd'.chr($forbiddenOctet).'"');
                $this->fail('"abcd\d'.$forbiddenOctet.'" should not pass validation.');
            } catch (InvalidEncodingException $e) {
                $this->assertContains('Non-UTF8 character found', $e->getMessage());
                $this->assertContains(
                    ' which is one of the four forbidden values (C0, C1, F5, FF).',
                    $e->getMessage()
                );
            }
        }
    }

    public function testUnwantedContinuationOctet()
    {
        try {
            Utf8Validator::validate('"abcd'.chr(129).'"');
            $this->fail('"abcd\d129" should not pass validation.');
        } catch (InvalidEncodingException $e) {
            $this->assertContains('Non-UTF8 character found', $e->getMessage());
            $this->assertContains(
                ' which is a continuation octet.',
                $e->getMessage()
            );
        }
    }

    public function testForbiddenSurrogatePairs()
    {
        for ($i = 160; $i <= 191; ++$i) {
            try {
                Utf8Validator::validate('"abcd'.chr(237).chr($i).chr(129).'"');
                $this->fail('"abcd\d237\d'.$i.'\d129" should not pass validation.');
            } catch (InvalidEncodingException $e) {
                $this->assertContains('Non-UTF8 character found', $e->getMessage());
                $this->assertContains(
                    ' which is into the forbidden range of surrogate pairs.',
                    $e->getMessage()
                );
            }
            try {
                Utf8Validator::validate('"abcd'.chr(237).chr($i).'"');
                $this->fail('"abcd\d237\d'.$i.'" should not pass validation.');
            } catch (InvalidEncodingException $e) {
                $this->assertContains('Non-UTF8 character found', $e->getMessage());
                $this->assertContains(
                    ' which is into the forbidden range of surrogate pairs.',
                    $e->getMessage()
                );
            }
        }
    }

    public function testOtherInvalidOctetSequences()
    {
        for ($i = 246; $i < 255; ++$i) { // 245 and 255 already forbidden
            try {
                Utf8Validator::validate('"abcd'.chr($i).chr(129).chr(129).chr(129).'"');
                $this->fail('"abcd\d'.$i.'\d129\d129\d129" should not pass validation.');
            } catch (InvalidEncodingException $e) {
                $this->assertContains('Non-UTF8 character found', $e->getMessage());
                $this->assertContains(' which is invalid.', $e->getMessage());
            }
            try {
                Utf8Validator::validate('"abcd'.chr($i).'"');
                $this->fail('"abcd\d'.$i.'" should not pass validation.');
            } catch (InvalidEncodingException $e) {
                $this->assertContains('Non-UTF8 character found', $e->getMessage());
                $this->assertContains(' which is invalid.', $e->getMessage());
            }
        }
    }

    public function testExceptionExposesPositionDetails()
    {
        try {
            // 255 (0xFF) is one of the always-forbidden octets, so the failure
            // is reported on the octet itself rather than a following one.
            Utf8Validator::validate('ab'.chr(255));
            $this->fail('Invalid UTF-8 should not pass validation.');
        } catch (InvalidEncodingException $e) {
            $details = $e->getDetails();
            $this->assertSame(255, $details['current_octet']);
            $this->assertSame(1, $details['line']);
            $this->assertSame('255', $e->getKey());
        }
    }
}
