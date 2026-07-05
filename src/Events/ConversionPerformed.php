<?php

namespace BrightCreations\MoneyConverter\Events;

use BrightCreations\MoneyConverter\DTOs\MoneyConversionResult;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ConversionPerformed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $inputAmount,
        public readonly string $from,
        public readonly string $to,
        public readonly ?CarbonInterface $date,
        public readonly MoneyConversionResult $result,
    ) {}
}
