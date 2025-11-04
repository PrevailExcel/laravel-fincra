<?php

namespace PrevailExcel\Fincra\Exceptions;

use Exception;

class IsNullException extends Exception
{
    /**
     * Create a new IsNullException instance.
     *
     * @param string $message
     */
    public function __construct($message = "A required parameter is missing")
    {
        parent::__construct($message);
    }
}