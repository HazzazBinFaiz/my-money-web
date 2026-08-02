<?php

use App\Lib\Util;

test('displayAmount formats amounts with thousand separators', function () {
    expect(Util::displayAmount(1234567))->toBe('1,234,567')
        ->and(Util::displayAmount(0))->toBe('0')
        ->and(Util::displayAmount(null))->toBe('0')
        ->and(Util::displayAmount(-50))->toBe('-50');
});
