<?php

namespace BrightCreations\MoneyConverter\Tests;

use Brick\Money\CurrencyConverter;
use BrightCreations\ExchangeRates\ExchangeRatesServiceProvider;
use BrightCreations\MoneyConverter\Concretes\MoneyConverter;
use BrightCreations\MoneyConverter\MoneyConverterServiceProvider;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function setUp(): void
    {
        if (file_exists(__DIR__.'/../.env')) {
            Dotenv::createImmutable(__DIR__.'/../')->load();
        }

        parent::setUp();

        $this->app->boot();
        $this->createTables();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ExchangeRatesServiceProvider::class,
            MoneyConverterServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function createTables(): void
    {
        if (! Schema::hasTable('currency_exchange_rates')) {
            Schema::create('currency_exchange_rates', function ($table) {
                $table->id();
                $table->string('base_currency_code', 3);
                $table->string('target_currency_code', 3);
                $table->string('exchange_rate', 20);
                $table->string('provider', 100)->nullable();
                $table->timestamp('last_update_date')->default(now());
                $table->unique(['base_currency_code', 'target_currency_code'], 'currency_exchange_rates_unique');
            });
        }

        if (! Schema::hasTable('currency_exchange_rates_history')) {
            Schema::create('currency_exchange_rates_history', function ($table) {
                $table->id();
                $table->string('base_currency_code', 3);
                $table->string('target_currency_code', 3);
                $table->string('exchange_rate', 20);
                $table->string('provider', 100)->nullable();
                $table->timestamp('date_time');
                $table->timestamp('last_update_date')->default(now());
                $table->unique(['base_currency_code', 'target_currency_code', 'date_time'], 'currency_exchange_rates_history_unique');
            });
        }
    }

    protected function makeMoneyConverter(): MoneyConverter
    {
        return new MoneyConverter($this->app->make(CurrencyConverter::class));
    }
}
