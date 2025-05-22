<?php

namespace BrightCreations\MoneyConverter\Exceptions;

class MoneyConversionException extends \Exception
{

    public function __construct(string $message)
    {
        parent::__construct($message);
    }

}
