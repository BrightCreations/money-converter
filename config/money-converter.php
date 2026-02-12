<?php

use BrightCreations\MoneyConverter\Enums\ExchangeRateProvidersEnum;
use Brick\Math\RoundingMode;

return [

    'exchange_rates' => [
        'pdo' => [
            // PDO configuartions
            'table' => env('EXCHANGE_RATE_DB_TABLE', 'currency_exchange_rates'),
            'column' => env('EXCHANGE_RATE_DB_COLUMN', 'exchange_rate'),
            'source_currency_column' => env('EXCHANGE_RATE_DB_SOURCE_CURRENCY_COLUMN', 'base_currency_code'),
            'target_currency_column' => env('EXCHANGE_RATE_DB_TARGET_CURRENCY_COLUMN', 'target_currency_code'),
        ],
    ],

    // defaults
    'default_provider' => ExchangeRateProvidersEnum::PDO->value,

    'extrapolate_currency_code' => 'USD',

    /*
    |--------------------------------------------------------------------------
    | Rounding mode for currency conversion
    |--------------------------------------------------------------------------
    |
    | This controls how Brick\Money will round values when the result of a
    | conversion has more decimal places than the currency scale allows.
    | The default is to round down (truncate) which favors the payer.
    |
    | Available modes: RoundingMode::UP, ::DOWN, ::CEILING, ::FLOOR,
    | ::HALF_UP, ::HALF_DOWN, ::HALF_EVEN, ::UNNECESSARY
    |
    */
    'rounding_mode' => RoundingMode::Down,

];
