<?php

namespace App\Lib;

use InvalidArgumentException;

/**
 * Evaluates the small arithmetic expressions typed into amount fields,
 * e.g. "10 * 2", "(4 * 4)+54", "12.50".
 *
 * Hand written tokenizer + shunting-yard, so nothing is ever passed to eval().
 */
class MathExpression
{
    private const OPERATORS = [
        '+' => ['precedence' => 1, 'associativity' => 'left'],
        '-' => ['precedence' => 1, 'associativity' => 'left'],
        '*' => ['precedence' => 2, 'associativity' => 'left'],
        '/' => ['precedence' => 2, 'associativity' => 'left'],
    ];

    /**
     * @throws InvalidArgumentException when the expression is not valid arithmetic
     */
    public static function evaluate(string $expression): float
    {
        $tokens = self::tokenize($expression);

        if ($tokens === []) {
            throw new InvalidArgumentException('Empty expression.');
        }

        return self::evaluatePostfix(self::toPostfix($tokens));
    }

    public static function tryEvaluate(string $expression): ?float
    {
        try {
            return self::evaluate($expression);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @return list<string|float>
     */
    private static function tokenize(string $expression): array
    {
        $expression = str_replace([' ', ',', "\t"], '', $expression);

        if ($expression === '') {
            return [];
        }

        if (preg_match('/[^0-9.+\-*\/()]/', $expression)) {
            throw new InvalidArgumentException('Unsupported character in expression.');
        }

        $tokens = [];
        $length = strlen($expression);
        $position = 0;

        while ($position < $length) {
            $character = $expression[$position];

            if (ctype_digit($character) || $character === '.') {
                $number = '';

                while ($position < $length && (ctype_digit($expression[$position]) || $expression[$position] === '.')) {
                    $number .= $expression[$position];
                    $position++;
                }

                if (! is_numeric($number)) {
                    throw new InvalidArgumentException('Malformed number.');
                }

                $tokens[] = (float) $number;

                continue;
            }

            // A leading +/- (or one right after '(' or another operator) is a sign, not an operation.
            if (($character === '-' || $character === '+')
                && (($tokens === []) || (is_string($previous = end($tokens)) && $previous !== ')'))) {
                $tokens[] = 0.0;
            }

            $tokens[] = $character;
            $position++;
        }

        return $tokens;
    }

    /**
     * @param  list<string|float>  $tokens
     * @return list<string|float>
     */
    private static function toPostfix(array $tokens): array
    {
        $output = [];
        $stack = [];

        foreach ($tokens as $token) {
            if (is_float($token)) {
                $output[] = $token;

                continue;
            }

            if ($token === '(') {
                $stack[] = $token;

                continue;
            }

            if ($token === ')') {
                while ($stack !== [] && end($stack) !== '(') {
                    $output[] = array_pop($stack);
                }

                if ($stack === []) {
                    throw new InvalidArgumentException('Unbalanced parentheses.');
                }

                array_pop($stack);

                continue;
            }

            if (! isset(self::OPERATORS[$token])) {
                throw new InvalidArgumentException('Unknown operator.');
            }

            while ($stack !== [] && end($stack) !== '('
                && self::OPERATORS[end($stack)]['precedence'] >= self::OPERATORS[$token]['precedence']) {
                $output[] = array_pop($stack);
            }

            $stack[] = $token;
        }

        while ($stack !== []) {
            $operator = array_pop($stack);

            if ($operator === '(') {
                throw new InvalidArgumentException('Unbalanced parentheses.');
            }

            $output[] = $operator;
        }

        return $output;
    }

    /**
     * @param  list<string|float>  $postfix
     */
    private static function evaluatePostfix(array $postfix): float
    {
        $stack = [];

        foreach ($postfix as $token) {
            if (is_float($token)) {
                $stack[] = $token;

                continue;
            }

            $right = array_pop($stack);
            $left = array_pop($stack);

            if ($right === null || $left === null) {
                throw new InvalidArgumentException('Malformed expression.');
            }

            $stack[] = match ($token) {
                '+' => $left + $right,
                '-' => $left - $right,
                '*' => $left * $right,
                '/' => $right == 0.0
                    ? throw new InvalidArgumentException('Division by zero.')
                    : $left / $right,
                default => throw new InvalidArgumentException('Unknown operator.'),
            };
        }

        if (count($stack) !== 1) {
            throw new InvalidArgumentException('Malformed expression.');
        }

        return (float) $stack[0];
    }
}
