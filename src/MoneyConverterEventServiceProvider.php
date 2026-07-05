<?php

namespace BrightCreations\MoneyConverter;

use BrightCreations\MoneyConverter\Events\ConversionPerformed;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;

class MoneyConverterEventServiceProvider extends EventServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        ConversionPerformed::class => [],
    ];

    public function register(): void
    {
        $this->listen[ConversionPerformed::class] = config('money-converter.events.listeners', []);

        parent::register();
    }
}
