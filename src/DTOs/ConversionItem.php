<?php

namespace BrightCreations\MoneyConverter\DTOs;

use Carbon\CarbonInterface;

final class ConversionItem
{
    public function __construct(
        public readonly int $amount,
        public readonly string $from,
        public readonly string $to,
        public readonly ?CarbonInterface $date = null,
    ) {}
}
