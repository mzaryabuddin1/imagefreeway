<?php

namespace App\Support;

/**
 * Calculated index fields: + - * / ( ) with numbers and field names, e.g. "quantity * unit_price".
 * A small parser, never eval(): the formula is data and can only do arithmetic.
 */
class Formula
{
    private array $tok = [];

    private int $pos = 0;

    private array $vars = [];

    private bool $missing = false;

    /** Field keys the formula uses, or null if it is not valid arithmetic. */
    public static function fields(string $formula): ?array
    {
        $t = self::tokens($formula);
        if ($t === null || ! $t) {
            return null;
        }
        $f = new self;
        $f->tok = $t;
        try {
            $f->expr();
            if ($f->pos !== count($t)) {
                return null;
            }
        } catch (\RuntimeException) {
            return null;
        }

        return array_values(array_unique(array_map(fn ($x) => $x[1], array_filter($t, fn ($x) => $x[0] === 'id'))));
    }

    /** Result rounded to 2 places, or null if a value is missing / not a number / divides by zero. */
    public static function evaluate(string $formula, array $values): ?string
    {
        $t = self::tokens($formula);
        if ($t === null) {
            return null;
        }
        $f = new self;
        $f->tok = $t;
        $f->vars = $values;
        try {
            $v = $f->expr();
        } catch (\RuntimeException) {
            return null;
        }

        return ($f->missing || $f->pos !== count($t) || ! is_finite($v)) ? null : rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    private static function tokens(string $s): ?array
    {
        $out = [];
        $n = strlen($s);
        for ($i = 0; $i < $n;) {
            $c = $s[$i];
            if (ctype_space($c)) {
                $i++;
            } elseif (strpos('+-*/()', $c) !== false) {
                $out[] = ['op', $c];
                $i++;
            } elseif (ctype_digit($c) || $c === '.') {
                preg_match('/\G\d*\.?\d+/', $s, $m, 0, $i) ?: preg_match('/\G\d+/', $s, $m, 0, $i);
                if (! $m) {
                    return null;
                }
                $out[] = ['num', (float) $m[0]];
                $i += strlen($m[0]);
            } elseif (ctype_alpha($c) || $c === '_') {
                preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $s, $m, 0, $i);
                $out[] = ['id', $m[0]];
                $i += strlen($m[0]);
            } else {
                return null;
            }
        }

        return $out;
    }

    private function peek(): ?array
    {
        return $this->tok[$this->pos] ?? null;
    }

    private function expr(): float
    {
        $v = $this->term();
        while (($t = $this->peek()) && $t[0] === 'op' && in_array($t[1], ['+', '-'], true)) {
            $this->pos++;
            $r = $this->term();
            $v = $t[1] === '+' ? $v + $r : $v - $r;
        }

        return $v;
    }

    private function term(): float
    {
        $v = $this->factor();
        while (($t = $this->peek()) && $t[0] === 'op' && in_array($t[1], ['*', '/'], true)) {
            $this->pos++;
            $r = $this->factor();
            if ($t[1] === '/' && $r == 0.0) {
                $this->missing = true;
                $r = 1.0;
            }
            $v = $t[1] === '*' ? $v * $r : $v / $r;
        }

        return $v;
    }

    private function factor(): float
    {
        $t = $this->peek();
        if (! $t) {
            throw new \RuntimeException('end');
        }
        $this->pos++;
        if ($t[0] === 'num') {
            return $t[1];
        }
        if ($t[0] === 'id') {
            $raw = $this->vars[$t[1]] ?? null;
            if ($raw === null || $raw === '' || ! is_numeric($raw)) {
                $this->missing = true;

                return 0.0;
            }

            return (float) $raw;
        }
        if ($t[1] === '-') {
            return -$this->factor();
        }
        if ($t[1] === '(') {
            $v = $this->expr();
            $c = $this->peek();
            if (! $c || $c[1] !== ')') {
                throw new \RuntimeException('paren');
            }
            $this->pos++;

            return $v;
        }
        throw new \RuntimeException('unexpected');
    }
}
