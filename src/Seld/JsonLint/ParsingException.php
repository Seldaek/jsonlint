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
 *
 */
class ParsingException extends \Exception
{
    /**
     * @var array
     */
    protected $_details;

    /**
     * @param string $message
     * @param array  $details
     */
    public function __construct($message, $details = array())
    {
        $this->_details = $details;
        parent::__construct($message);
    }

    /**
     * @return array
     */
    public function getDetails()
    {
        return $this->_details;
    }
}