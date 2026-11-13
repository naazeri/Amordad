<?php

namespace MongoLite\Expression;

/**
 * Type expression operators
 */
class TypeOps {

    public static function type($operand, array $doc): string {
        $val = Evaluator::resolveOperand($operand, $doc);

        if ($val === null) return 'null';
        if (\is_bool($val)) return 'bool';
        if (\is_int($val)) return 'int';
        if (\is_float($val)) return 'double';
        if (\is_string($val)) return 'string';
        if (\is_array($val)) {
            return \array_keys($val) === \range(0, \count($val) - 1) ? 'array' : 'object';
        }
        return 'unknown';
    }

    public static function toString($operand, array $doc): ?string {
        $val = Evaluator::resolveOperand($operand, $doc);
        return $val === null ? null : (string)$val;
    }

    public static function toInt($operand, array $doc): ?int {
        $val = Evaluator::resolveOperand($operand, $doc);
        return $val === null ? null : (int)$val;
    }

    public static function toLong($operand, array $doc): ?int {
        $val = Evaluator::resolveOperand($operand, $doc);
        return $val === null ? null : (int)$val;
    }

    public static function toDouble($operand, array $doc): ?float {
        $val = Evaluator::resolveOperand($operand, $doc);
        return $val === null ? null : (float)$val;
    }

    public static function toBool($operand, array $doc): ?bool {
        $val = Evaluator::resolveOperand($operand, $doc);
        return $val === null ? null : (bool)$val;
    }

    public static function toDate($operand, array $doc): ?int {
        $val = Evaluator::resolveOperand($operand, $doc);

        if ($val === null) {
            return null;
        }

        if (\is_numeric($val)) {
            // Already a timestamp - ensure it's in milliseconds
            return $val > 9999999999 ? (int)$val : (int)$val * 1000;
        } elseif (\is_string($val)) {
            $timestamp = \strtotime($val);
            return $timestamp !== false ? $timestamp * 1000 : null;
        }

        return null;
    }

    public static function isArray($operand, array $doc): bool {
        $val = Evaluator::resolveOperand($operand, $doc);
        return \is_array($val) && (\array_keys($val) === \range(0, \count($val) - 1) || empty($val));
    }

    public static function isNumber($operand, array $doc): bool {
        $val = Evaluator::resolveOperand($operand, $doc);
        return \is_int($val) || \is_float($val);
    }

    public static function isString($operand, array $doc): bool {
        return \is_string(Evaluator::resolveOperand($operand, $doc));
    }

    public static function isObject($operand, array $doc): bool {
        $val = Evaluator::resolveOperand($operand, $doc);
        return \is_array($val) && !empty($val) && \array_keys($val) !== \range(0, \count($val) - 1);
    }
}
