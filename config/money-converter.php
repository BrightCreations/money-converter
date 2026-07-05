<?php

use Brick\Math\RoundingMode;
use BrightCreations\MoneyConverter\Enums\ExchangeRateProvidersEnum;

return [

    'exchange_rates' => [
        'pdo' => [
            // PDO configuartions
            'table' => env('EXCHANGE_RATE_DB_TABLE', 'currency_exchange_rates'),
            'column' => env('EXCHANGE_RATE_DB_COLUMN', 'exchange_rate'),
            'source_currency_column' => env('EXCHANGE_RATE_DB_SOURCE_CURRENCY_COLUMN', 'base_currency_code'),
            'target_currency_column' => env('EXCHANGE_RATE_DB_TARGET_CURRENCY_COLUMN', 'target_currency_code'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Configurable provider rates (local development)
        |--------------------------------------------------------------------------
        |
        | Used when default_provider is ExchangeRateProvidersEnum::CONFIGURABLE.
        | Nested map: base currency => [target currency => rate string].
        |
        */
        'configurable' => [
            'rates' => [
                'EUR' => [
                    'USD' => '1.0987',
                    'GBP' => '0.86',
                    'EGP' => '0.86',
                ],
                'USD' => [
                    'EUR' => '0.9123',
                    'AED' => '3.65',
                    'GBP' => '0.78',
                    'EGP' => '0.78',
                ],
                'AED' => [
                    'USD' => '0.27',
                    'GBP' => '4.65',
                    'EGP' => '4.65',
                ],
                'GBP' => [
                    'AED' => '0.215',
                    'BHD' => '0.476',
                    'EUR' => '1.16',
                    'CAD' => '1.77',
                    'CHF' => '1.13',
                    'USD' => '1.28',
                ],
                'EGP' => [
                    'AED' => '0.215',
                    'BHD' => '0.476',
                    'EUR' => '1.16',
                    'CAD' => '1.77',
                    'CHF' => '1.13',
                    'USD' => '1.28',
                ],
                'BHD' => [
                    'GBP' => '2.1',
                    'EGP' => '2.1',
                ],
                'CAD' => [
                    'GBP' => '0.56',
                    'EGP' => '0.56',
                ],
                'CHF' => [
                    'GBP' => '0.88',
                    'EGP' => '0.88',
                ],
            ],
        ],
    ],

    // defaults
    'default_provider' => ExchangeRateProvidersEnum::PDO->value,

    'proxy_currency_code' => 'USD',

    /*
    |--------------------------------------------------------------------------
    | Rounding mode for currency conversion
    |--------------------------------------------------------------------------
    |
    | This controls how Brick\Money will round values when the result of a
    | conversion has more decimal places than the currency scale allows.
    | The default is to round down (truncate) which favors the payer.
    |
    | Intermediate cross-rate and interpolation math uses HALF_UP at 10 decimal
    | places to align with brightcreations/exchange-rates. This setting applies
    | only to the final conversion into minor currency units.
    |
    | Available modes: RoundingMode::UP, ::DOWN, ::CEILING, ::FLOOR,
    | ::HALF_UP, ::HALF_DOWN, ::HALF_EVEN, ::UNNECESSARY
    |
    */
    'rounding_mode' => RoundingMode::DOWN,

];
