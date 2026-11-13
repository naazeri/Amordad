<?php

namespace MongoLite\Expression;

/**
 * Array expression operators
 */
class ArrayOps {

    public static function size($operand, array $doc): ?int {
        $arr = Evaluator::resolveOperand($operand, $doc);
        return \is_array($arr) ? \count($arr) : null;
    }

    public static function arrayElemAt(array $args, array $doc): mixed {
        $arr = Evaluator::resolveOperand($args[0], $doc);
        $idx = (int)Evaluator::resolveOperand($args[1], $doc);

        if (!\is_array($arr)) {
            return null;
        }

        if ($idx < 0) {
            $idx = \count($arr) + $idx;
        }

        return $arr[$idx] ?? null;
    }

    public static function first($operand, array $doc): mixed {
        $arr = Evaluator::resolveOperand($operand, $doc);
        if (!\is_array($arr) || empty($arr)) {
            return null;
        }
        return \reset($arr);
    }

    public static function last($operand, array $doc): mixed {
        $arr = Evaluator::resolveOperand($operand, $doc);
        if (!\is_array($arr) || empty($arr)) {
            return null;
        }
        return \end($arr);
    }

    public static function reverseArray($operand, array $doc): ?array {
        $arr = Evaluator::resolveOperand($operand, $doc);
        return \is_array($arr) ? \array_reverse($arr) : null;
    }

    public static function in(array $args, array $doc): bool {
        $val = Evaluator::resolveOperand($args[0], $doc);
        $arr = Evaluator::resolveOperand($args[1], $doc);
        return \is_array($arr) && \in_array($val, $arr);
    }

    public static function concatArrays(array $operands, array $doc): array {
        $result = [];
        foreach ($operands as $operand) {
            $arr = Evaluator::resolveOperand($operand, $doc);
            if (\is_array($arr)) {
                $result = \array_merge($result, $arr);
            }
        }
        return $result;
    }

    public static function map(array $args, array $doc): ?array {
        $input = Evaluator::resolveOperand($args['input'], $doc);
        if (!\is_array($input)) {
            return null;
        }

        $as = $args['as'] ?? 'this';
        $inExpr = $args['in'];

        $result = [];
        foreach ($input as $item) {
            $localDoc = $doc;
            $localDoc[$as] = $item;
            $result[] = Evaluator::resolveOperand($inExpr, $localDoc);
        }
        return $result;
    }

    public static function filter(array $args, array $doc): ?array {
        $input = Evaluator::resolveOperand($args['input'], $doc);
        if (!\is_array($input)) {
            return null;
        }

        $as = $args['as'] ?? 'this';
        $cond = $args['cond'];
        $limit = isset($args['limit'])
            ? (int)Evaluator::resolveOperand($args['limit'], $doc)
            : null;

        $result = [];
        $count = 0;

        foreach ($input as $item) {
            $localDoc = $doc;
            $localDoc[$as] = $item;
            if (Evaluator::evaluate($cond, $localDoc)) {
                $result[] = $item;
                $count++;
                if ($limit !== null && $count >= $limit) {
                    break;
                }
            }
        }
        return $result;
    }

    public static function reduce(array $args, array $doc): mixed {
        $input = Evaluator::resolveOperand($args['input'], $doc);
        if (!\is_array($input)) {
            return null;
        }

        $initialValue = Evaluator::resolveOperand($args['initialValue'], $doc);
        $inExpr = $args['in'];

        $value = $initialValue;
        foreach ($input as $item) {
            $localDoc = $doc;
            $localDoc['value'] = $value;
            $localDoc['this'] = $item;
            $value = Evaluator::resolveOperand($inExpr, $localDoc);
        }
        return $value;
    }

    public static function range(array $args, array $doc): ?array {
        $start = (int)Evaluator::resolveOperand($args[0], $doc);
        $end = (int)Evaluator::resolveOperand($args[1], $doc);
        $step = isset($args[2]) ? (int)Evaluator::resolveOperand($args[2], $doc) : 1;

        if ($step === 0) {
            return null;
        }
        if ($step > 0 && $start >= $end) {
            return [];
        }
        if ($step < 0 && $start <= $end) {
            return [];
        }

        $result = [];
        if ($step > 0) {
            for ($i = $start; $i < $end; $i += $step) {
                $result[] = $i;
            }
        } else {
            for ($i = $start; $i > $end; $i += $step) {
                $result[] = $i;
            }
        }
        return $result;
    }

    public static function slice(array $args, array $doc): ?array {
        $arr = Evaluator::resolveOperand($args[0], $doc);
        if (!\is_array($arr)) {
            return null;
        }

        if (\count($args) === 2) {
            // [array, n] - first n elements (or last |n| if negative)
            $n = (int)Evaluator::resolveOperand($args[1], $doc);
            if ($n >= 0) {
                return \array_slice($arr, 0, $n);
            } else {
                return \array_slice($arr, $n);
            }
        } elseif (\count($args) >= 3) {
            // [array, position, n] - n elements starting at position
            $position = (int)Evaluator::resolveOperand($args[1], $doc);
            $n = (int)Evaluator::resolveOperand($args[2], $doc);
            if ($position < 0) {
                $position = \max(0, \count($arr) + $position);
            }
            return \array_slice($arr, $position, $n);
        }

        return $arr;
    }

    public static function sortArray(array $args, array $doc): ?array {
        $input = Evaluator::resolveOperand($args['input'], $doc);
        $sortBy = $args['sortBy'];

        if (!\is_array($input)) {
            return null;
        }

        $result = $input;

        if (\is_int($sortBy) || (\is_array($sortBy) && empty($sortBy))) {
            // Simple sort: 1 for ascending, -1 for descending
            $direction = \is_int($sortBy) ? $sortBy : 1;
            if ($direction === 1) {
                \sort($result);
            } else {
                \rsort($result);
            }
        } elseif (\is_array($sortBy)) {
            // Sort by field(s)
            \usort($result, function($a, $b) use ($sortBy) {
                foreach ($sortBy as $field => $order) {
                    $direction = ($order === -1) ? -1 : 1;
                    $valA = \is_array($a) ? ($a[$field] ?? null) : null;
                    $valB = \is_array($b) ? ($b[$field] ?? null) : null;
                    if ($valA < $valB) return -1 * $direction;
                    if ($valA > $valB) return 1 * $direction;
                }
                return 0;
            });
        }

        return $result;
    }

    public static function zip(array $args, array $doc): array {
        $inputs = $args['inputs'];
        $useLongestLength = $args['useLongestLength'] ?? false;
        $defaults = $args['defaults'] ?? null;

        $arrays = [];
        $maxLen = 0;
        $minLen = PHP_INT_MAX;

        foreach ($inputs as $input) {
            $arr = Evaluator::resolveOperand($input, $doc);
            if (!\is_array($arr)) {
                $arr = [];
            }
            $arrays[] = $arr;
            $maxLen = \max($maxLen, \count($arr));
            $minLen = \min($minLen, \count($arr));
        }

        if (empty($arrays)) {
            return [];
        }

        $len = $useLongestLength ? $maxLen : $minLen;
        $result = [];

        for ($i = 0; $i < $len; $i++) {
            $tuple = [];
            foreach ($arrays as $idx => $arr) {
                if (isset($arr[$i])) {
                    $tuple[] = $arr[$i];
                } elseif ($defaults !== null && isset($defaults[$idx])) {
                    $tuple[] = $defaults[$idx];
                } else {
                    $tuple[] = null;
                }
            }
            $result[] = $tuple;
        }

        return $result;
    }
}
