<?php

namespace MongoLite\Expression;

/**
 * Arithmetic expression operators
 */
class Arithmetic {

    public static function add(array $operands, array $doc): mixed {
        $result = 0;
        foreach ($operands as $operand) {
            $result += Evaluator::resolveOperand($operand, $doc);
        }
        return $result;
    }

    public static function subtract(array $operands, array $doc): mixed {
        return Evaluator::resolveOperand($operands[0], $doc) -
               Evaluator::resolveOperand($operands[1], $doc);
    }

    public static function multiply(array $operands, array $doc): mixed {
        $result = 1;
        foreach ($operands as $operand) {
            $result *= Evaluator::resolveOperand($operand, $doc);
        }
        return $result;
    }

    public static function divide(array $operands, array $doc): mixed {
        $divisor = Evaluator::resolveOperand($operands[1], $doc);
        if ($divisor == 0) {
            return null;
        }
        return Evaluator::resolveOperand($operands[0], $doc) / $divisor;
    }

    public static function mod(array $operands, array $doc): mixed {
        $dividend = Evaluator::resolveOperand($operands[0], $doc);
        $divisor = Evaluator::resolveOperand($operands[1], $doc);
        if ($divisor == 0) {
            return null;
        }
        return $dividend % $divisor;
    }

    public static function abs($operand, array $doc): mixed {
        $val = Evaluator::resolveOperand($operand, $doc);
        return $val === null ? null : \abs($val);
    }

    public static function ceil($operand, array $doc): mixed {
        $val = Evaluator::resolveOperand($operand, $doc);
        return $val === null ? null : (int)\ceil($val);
    }

    public static function floor($operand, array $doc): mixed {
        $val = Evaluator::resolveOperand($operand, $doc);
        return $val === null ? null : (int)\floor($val);
    }

    public static function round($args, array $doc): mixed {
        if (\is_array($args) && isset($args[0])) {
            $val = Evaluator::resolveOperand($args[0], $doc);
            $places = isset($args[1]) ? (int)Evaluator::resolveOperand($args[1], $doc) : 0;
        } else {
            $val = Evaluator::resolveOperand($args, $doc);
            $places = 0;
        }
        return $val === null ? null : \round($val, $places);
    }

    public static function pow(array $args, array $doc): mixed {
        $base = Evaluator::resolveOperand($args[0], $doc);
        $exp = Evaluator::resolveOperand($args[1], $doc);
        if ($base === null || $exp === null) {
            return null;
        }
        return \pow($base, $exp);
    }

    public static function sqrt($operand, array $doc): mixed {
        $val = Evaluator::resolveOperand($operand, $doc);
        if ($val === null || $val < 0) {
            return null;
        }
        return \sqrt($val);
    }

    public static function log(array $args, array $doc): mixed {
        $num = Evaluator::resolveOperand($args[0], $doc);
        $base = Evaluator::resolveOperand($args[1], $doc);
        if ($num === null || $base === null || $num <= 0 || $base <= 0 || $base == 1) {
            return null;
        }
        return \log($num, $base);
    }

    public static function log10($operand, array $doc): mixed {
        $val = Evaluator::resolveOperand($operand, $doc);
        if ($val === null || $val <= 0) {
            return null;
        }
        return \log10($val);
    }

    public static function ln($operand, array $doc): mixed {
        $val = Evaluator::resolveOperand($operand, $doc);
        if ($val === null || $val <= 0) {
            return null;
        }
        return \log($val);
    }

    public static function exp($operand, array $doc): mixed {
        $val = Evaluator::resolveOperand($operand, $doc);
        if ($val === null) {
            return null;
        }
        return \exp($val);
    }

    public static function trunc($args, array $doc): mixed {
        if (\is_array($args) && isset($args[0])) {
            $val = Evaluator::resolveOperand($args[0], $doc);
            $places = isset($args[1]) ? (int)Evaluator::resolveOperand($args[1], $doc) : 0;
        } else {
            $val = Evaluator::resolveOperand($args, $doc);
            $places = 0;
        }
        if ($val === null) {
            return null;
        }
        if ($places === 0) {
            return (int)$val;
        }
        $multiplier = \pow(10, $places);
        return (int)($val * $multiplier) / $multiplier;
    }
}
