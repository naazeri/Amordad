<?php

namespace MongoLite\Expression;

/**
 * Set expression operators
 */
class SetOps {

    public static function setUnion(array $operands, array $doc): array {
        $result = [];
        foreach ($operands as $operand) {
            $arr = Evaluator::resolveOperand($operand, $doc);
            if (!\is_array($arr)) {
                continue;
            }
            foreach ($arr as $item) {
                if (!\in_array($item, $result, true)) {
                    $result[] = $item;
                }
            }
        }
        return $result;
    }

    public static function setIntersection(array $operands, array $doc): array {
        $arrays = [];
        foreach ($operands as $operand) {
            $arr = Evaluator::resolveOperand($operand, $doc);
            if (!\is_array($arr)) {
                return [];
            }
            $arrays[] = $arr;
        }

        if (empty($arrays)) {
            return [];
        }

        $result = $arrays[0];
        for ($i = 1; $i < \count($arrays); $i++) {
            $result = \array_values(\array_filter($result, fn($item) => \in_array($item, $arrays[$i], true)));
        }
        return $result;
    }

    public static function setDifference(array $args, array $doc): ?array {
        $arr1 = Evaluator::resolveOperand($args[0], $doc);
        $arr2 = Evaluator::resolveOperand($args[1], $doc);

        if (!\is_array($arr1)) {
            return null;
        }
        if (!\is_array($arr2)) {
            return $arr1;
        }

        return \array_values(\array_filter($arr1, fn($item) => !\in_array($item, $arr2, true)));
    }

    public static function setEquals(array $operands, array $doc): bool {
        $arrays = [];
        foreach ($operands as $operand) {
            $arr = Evaluator::resolveOperand($operand, $doc);
            if (!\is_array($arr)) {
                return false;
            }
            $arrays[] = $arr;
        }

        if (\count($arrays) < 2) {
            return true;
        }

        $first = $arrays[0];
        \sort($first);

        for ($i = 1; $i < \count($arrays); $i++) {
            $other = $arrays[$i];
            \sort($other);
            if ($first !== $other) {
                return false;
            }
        }
        return true;
    }

    public static function setIsSubset(array $args, array $doc): bool {
        $arr1 = Evaluator::resolveOperand($args[0], $doc);
        $arr2 = Evaluator::resolveOperand($args[1], $doc);

        if (!\is_array($arr1) || !\is_array($arr2)) {
            return false;
        }

        foreach ($arr1 as $item) {
            if (!\in_array($item, $arr2, true)) {
                return false;
            }
        }
        return true;
    }

    public static function anyElementTrue($operand, array $doc): bool {
        // MongoDB format: $anyElementTrue: [ <expression> ] where expression evaluates to array
        $arr = Evaluator::resolveOperand($operand, $doc);
        if (!\is_array($arr)) {
            return false;
        }
        // If it's a single-element array containing another array, unwrap it
        if (\count($arr) === 1 && isset($arr[0]) && \is_array($arr[0])) {
            $arr = $arr[0];
        }
        foreach ($arr as $item) {
            if ($item) {
                return true;
            }
        }
        return false;
    }

    public static function allElementsTrue($operand, array $doc): bool {
        // MongoDB format: $allElementsTrue: [ <expression> ] where expression evaluates to array
        $arr = Evaluator::resolveOperand($operand, $doc);
        if (!\is_array($arr)) {
            return false;
        }
        // If it's a single-element array containing another array, unwrap it
        if (\count($arr) === 1 && isset($arr[0]) && \is_array($arr[0])) {
            $arr = $arr[0];
        }
        foreach ($arr as $item) {
            if (!$item) {
                return false;
            }
        }
        return true;
    }
}
