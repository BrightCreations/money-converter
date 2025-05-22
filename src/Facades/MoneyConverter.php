<?php

namespace BrightCreations\MoneyConverter\Facades;

use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;
use Illuminate\Support\Facades\Facade;

class MoneyConverter extends Facade
{
    public const ON_FAIL_THROW_EXCEPTION = 1;
    public const ON_FAIL_FETCH_EXCHANGE_RATES = 2;

    protected static function getFacadeAccessor(): string
    {
        return MoneyConverterInterface::class;
    }
}
