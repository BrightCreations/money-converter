<?php

use BrightCreations\MoneyConverter\Enums\ExchangeRateProvidersEnum;

return [

    // PDO configuartions
    'table' => env('EXCHANGE_RATE_DB_TABLE', 'currency_exchange_rates'),
    'column' => env('EXCHANGE_RATE_DB_COLUMN', 'exchange_rate'),
    'source_currency_column' => env('EXCHANGE_RATE_DB_SOURCE_CURRENCY_COLUMN', 'source_currency_code'),
    'target_currency_column' => env('EXCHANGE_RATE_DB_TARGET_CURRENCY_COLUMN', 'target_currency_code'),

    // defaults
    'default_provider' => ExchangeRateProvidersEnum::PDO->value,

    'exchange_rates_service' => "ExchangeRateServiceInterface", // the accessor for the exchange rate service

];
