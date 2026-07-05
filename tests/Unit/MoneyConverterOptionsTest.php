<?php

use BrightCreations\MoneyConverter\Contracts\MoneyConverterInterface;
use BrightCreations\MoneyConverter\DTOs\MoneyConverterOptions;

it('returns default option values', function () {
    $options = MoneyConverterOptions::defaults();

    expect($options->extrapolate)->toBeFalse();
    expect($options->interpolate)->toBeFalse();
    expect($options->needsFresh)->toBeFalse();
    expect($options->onFail)->toBe(MoneyConverterInterface::ON_FAIL_THROW_EXCEPTION);
});

it('returns new instances from withers without mutating the original', function () {
    $original = MoneyConverterOptions::defaults();

    $modified = $original
        ->withExtrapolate()
        ->withInterpolate()
        ->withNeedsFresh()
        ->withFetchOnFail();

    expect($original->extrapolate)->toBeFalse();
    expect($original->interpolate)->toBeFalse();
    expect($original->needsFresh)->toBeFalse();
    expect($original->onFail)->toBe(MoneyConverterInterface::ON_FAIL_THROW_EXCEPTION);

    expect($modified->extrapolate)->toBeTrue();
    expect($modified->interpolate)->toBeTrue();
    expect($modified->needsFresh)->toBeTrue();
    expect($modified->onFail)->toBe(MoneyConverterInterface::ON_FAIL_FETCH_EXCHANGE_RATES);
});

it('rejects invalid on fail values', function () {
    MoneyConverterOptions::defaults()->withOnFail(99);
})->throws(InvalidArgumentException::class);

it('supports withThrowOnFail', function () {
    $options = MoneyConverterOptions::defaults()->withFetchOnFail()->withThrowOnFail();

    expect($options->onFail)->toBe(MoneyConverterInterface::ON_FAIL_THROW_EXCEPTION);
});

it('builds options from config array', function () {
    $options = MoneyConverterOptions::fromConfig([
        'extrapolate' => true,
        'interpolate' => true,
        'needs_fresh' => true,
        'on_fail' => 'fetch',
    ]);

    expect($options->extrapolate)->toBeTrue();
    expect($options->interpolate)->toBeTrue();
    expect($options->needsFresh)->toBeTrue();
    expect($options->onFail)->toBe(MoneyConverterInterface::ON_FAIL_FETCH_EXCHANGE_RATES);
});

it('returns defaults for null or empty config', function () {
    $defaults = MoneyConverterOptions::defaults();

    expect(MoneyConverterOptions::fromConfig(null)->onFail)->toBe($defaults->onFail);
    expect(MoneyConverterOptions::fromConfig([])->interpolate)->toBe($defaults->interpolate);
});

it('rejects invalid on_fail string in config', function () {
    MoneyConverterOptions::fromConfig(['on_fail' => 'invalid']);
})->throws(InvalidArgumentException::class);
