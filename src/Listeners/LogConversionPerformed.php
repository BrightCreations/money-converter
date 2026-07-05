<?php

namespace BrightCreations\MoneyConverter\Listeners;

use BrightCreations\MoneyConverter\Events\ConversionPerformed;
use Illuminate\Support\Facades\Log;

final class LogConversionPerformed
{
    private const LOG_PREFIX = '[MoneyConverter]';

    public function handle(ConversionPerformed $event): void
    {
        Log::info(self::LOG_PREFIX.' Conversion performed', [
            'input_amount' => $event->inputAmount,
            'from' => $event->from,
            'to' => $event->to,
            'date' => $event->date?->toDateString(),
            'output_amount' => $event->result->amount,
            'rate' => $event->result->rate,
            'strategy' => $event->result->strategy->value,
            'provider' => $event->result->provider,
        ]);
    }
}
