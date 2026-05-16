<?php

namespace App\Providers;

use Illuminate\Validation\ValidationException;

class DiceParsingService
{
    public function parse(string $formula): array
    {
        $expression = preg_replace('/\s+/', '', $formula ?? '');

        if (! is_string($expression) || $expression === '') {
            throw ValidationException::withMessages([
                'formula' => ['Dice formula is required.'],
            ]);
        }

        if (! preg_match('~^[0-9d+\-*/().]+$~', $expression)) {
            throw ValidationException::withMessages([
                'formula' => ['Dice formula contains unsupported characters.'],
            ]);
        }

        $tokens = $this->tokenize($expression);
        $rpn = $this->toReversePolishNotation($tokens);

        return $this->evaluateReversePolishNotation($rpn);
    }

    protected function tokenize(string $expression): array
    {
        preg_match_all('/\d*d\d+|\d+|[()+\-*\/]/', $expression, $matches);
        $tokens = $matches[0] ?? [];

        if (implode('', $tokens) !== $expression) {
            throw ValidationException::withMessages([
                'formula' => ['Dice formula could not be parsed.'],
            ]);
        }

        return $this->normalizeUnarySigns($tokens);
    }

    protected function normalizeUnarySigns(array $tokens): array
    {
        $result = [];

        foreach ($tokens as $index => $token) {
            if (
                in_array($token, ['+', '-'], true)
                && ($index === 0 || in_array($tokens[$index - 1], ['+', '-', '*', '/', '('], true))
            ) {
                $next = $tokens[$index + 1] ?? null;

                if ($next === null || in_array($next, ['+', '-', '*', '/', ')'], true)) {
                    throw ValidationException::withMessages([
                        'formula' => ['Dice formula contains an invalid unary operator.'],
                    ]);
                }

                if ($token === '+') {
                    continue;
                }

                $result[] = '0';
                $result[] = '-';

                continue;
            }

            $result[] = $token;
        }

        return $result;
    }

    protected function toReversePolishNotation(array $tokens): array
    {
        $output = [];
        $stack = [];
        $precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];

        foreach ($tokens as $token) {
            if ($this->isOperand($token)) {
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

                if ($stack === [] || array_pop($stack) !== '(') {
                    throw ValidationException::withMessages([
                        'formula' => ['Dice formula contains mismatched parentheses.'],
                    ]);
                }

                continue;
            }

            while (
                $stack !== []
                && end($stack) !== '('
                && $precedence[end($stack)] >= $precedence[$token]
            ) {
                $output[] = array_pop($stack);
            }

            $stack[] = $token;
        }

        while ($stack !== []) {
            $operator = array_pop($stack);

            if (in_array($operator, ['(', ')'], true)) {
                throw ValidationException::withMessages([
                    'formula' => ['Dice formula contains mismatched parentheses.'],
                ]);
            }

            $output[] = $operator;
        }

        return $output;
    }

    protected function evaluateReversePolishNotation(array $tokens): array
    {
        $stack = [];
        $details = [];

        foreach ($tokens as $token) {
            if ($this->isOperand($token)) {
                $stack[] = $this->resolveOperand($token, $details);
                continue;
            }

            $right = array_pop($stack);
            $left = array_pop($stack);

            if ($left === null || $right === null) {
                throw ValidationException::withMessages([
                    'formula' => ['Dice formula contains invalid operator placement.'],
                ]);
            }

            $stack[] = $this->applyOperator($left, $right, $token);
        }

        if (count($stack) !== 1) {
            throw ValidationException::withMessages([
                'formula' => ['Dice formula could not be evaluated.'],
            ]);
        }

        $result = array_pop($stack);

        return [
            'result' => $result == (int) $result ? (int) $result : round($result, 2),
            'details' => $details,
        ];
    }

    protected function resolveOperand(string $token, array &$details): float
    {
        if (str_contains($token, 'd')) {
            [$count, $sides] = explode('d', $token, 2);
            $rollsCount = $count === '' ? 1 : (int) $count;
            $diceSides = (int) $sides;

            if ($rollsCount < 1 || $diceSides < 1) {
                throw ValidationException::withMessages([
                    'formula' => ['Dice formula contains invalid dice values.'],
                ]);
            }

            $sum = 0;

            for ($index = 0; $index < $rollsCount; $index++) {
                $roll = random_int(1, $diceSides);
                $sum += $roll;
                $details[] = [
                    'type' => $diceSides,
                    'res' => $roll,
                ];
            }

            return $sum;
        }

        $value = (int) $token;
        $details[] = [
            'type' => 'modif',
            'res' => $value,
        ];

        return $value;
    }

    protected function applyOperator(float $left, float $right, string $operator): float
    {
        return match ($operator) {
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            '/' => $right == 0.0
                ? throw ValidationException::withMessages(['formula' => ['Division by zero is not allowed.']])
                : $left / $right,
            default => throw ValidationException::withMessages(['formula' => ['Unsupported arithmetic operator.']]),
        };
    }

    protected function isOperand(string $token): bool
    {
        return preg_match('/^\d*d\d+$|^\d+$/', $token) === 1;
    }
}
