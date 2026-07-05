<?php

use BrightCreations\MoneyConverter\DTOs\MoneyConversionResult;
use BrightCreations\MoneyConverter\Enums\ConversionStrategy;
use BrightCreations\MoneyConverter\Events\ConversionPerformed;
use BrightCreations\MoneyConverter\Listeners\LogConversionPerformed;
use BrightCreations\MoneyConverter\Tests\Concerns\SeedsExchangeRates;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

uses(SeedsExchangeRates::class);

it('logs conversion details when the package listener handles ConversionPerformed', function () {
    Log::shouldReceive('info')
        ->once()
        ->withArgs(function (string $message, array $context) {
            return $message === '[MoneyConverter] Conversion performed'
                && $context['input_amount'] === 10000
                && $context['from'] === 'USD'
                && $context['to'] === 'EUR'
                && $context['date'] === '2024-01-15'
                && $context['output_amount'] === 8800
                && $context['rate'] === '0.88'
                && $context['strategy'] === 'exact_db'
                && $context['provider'] === 'ecb';
        });

    $event = new ConversionPerformed(
        10000,
        'USD',
        'EUR',
        Carbon::parse('2024-01-15'),
        new MoneyConversionResult(8800, '0.88', ConversionStrategy::ExactDb, 'ecb'),
    );

    app(LogConversionPerformed::class)->handle($event);
});

it('logs conversions end-to-end when events are dispatched', function () {
    Log::shouldReceive('info')
        ->once()
        ->withArgs(fn (string $message) => $message === '[MoneyConverter] Conversion performed');

    $this->seedCurrentRate('USD', 'EUR', 0.85);

    $this->makeMoneyConverter()->convertWithResult(10000, 'USD', 'EUR');
});
