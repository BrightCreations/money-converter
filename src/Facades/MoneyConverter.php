<?php

namespace BrightCreations\MoneyConverter\Facades;

use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;
use Illuminate\Support\Facades\Facade;

class MoneyConverter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MoneyConverterInterface::class;
    }
}
