<?php

use App\Lib\Util;

test('displayAmount renders stored minor units with two decimals', function () {
    expect(Util::displayAmount(1234567))->toBe('12,345.67')
        ->and(Util::displayAmount(0))->toBe('0.00')
        ->and(Util::displayAmount(null))->toBe('0.00')
        ->and(Util::displayAmount(-5050))->toBe('-50.50');
});

test('amounts convert between major and minor units', function () {
    expect(Util::toMinorUnits('12.50'))->toBe(1250)
        ->and(Util::toMinorUnits(0.1))->toBe(10)
        ->and(Util::toMinorUnits(null))->toBe(0)
        ->and(Util::toMajorUnits(1250))->toBe('12.50');
});
