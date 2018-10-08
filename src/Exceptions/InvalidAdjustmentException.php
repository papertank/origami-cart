<?php

namespace Origami\Cart\Exceptions;

use Exception;

class InvalidAdjustmentException extends Exception
{
    protected $errors;

    public function __construct($message, $errors)
    {
        $this->errors = $errors;
        parent::__construct($message);
    }
}
