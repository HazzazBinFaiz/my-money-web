<?php

use App\Lib\MathExpression;

test('it evaluates plain numbers and arithmetic', function () {
    expect(MathExpression::evaluate('12'))->toBe(12.0)
        ->and(MathExpression::evaluate('12.50'))->toBe(12.5)
        ->and(MathExpression::evaluate('10 * 2'))->toBe(20.0)
        ->and(MathExpression::evaluate('(4 * 4)+54'))->toBe(70.0)
        ->and(MathExpression::evaluate('100 / 4 - 5'))->toBe(20.0)
        ->and(MathExpression::evaluate('-5 + 8'))->toBe(3.0)
        ->and(MathExpression::evaluate('2 + 3 * 4'))->toBe(14.0);
});

test('it rejects anything that is not arithmetic', function (string $expression) {
    expect(MathExpression::tryEvaluate($expression))->toBeNull();
})->with([
    'phpinfo()',
    '1 + ',
    '(1 + 2',
    '1 / 0',
    '',
    'abc',
    '1 2 3 +',
]);
