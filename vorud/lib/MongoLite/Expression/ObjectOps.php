<?php

namespace MongoLite\Expression;

/**
 * Object expression operators
 */
class ObjectOps {

    public static function mergeObjects($operands, array $doc): array {
        $result = [];

        // Handle single object vs array of objects
        $items = \is_array($operands) && !isset($operands[0])
            ? [$operands]  // Single object
            : $operands;   // Array of objects

        foreach ($items as $operand) {
            $val = Evaluator::resolveOperand($operand, $doc);
            if (\is_array($val)) {
                $result = \array_merge($result, $val);
            }
        }
        return $result;
    }

    public static function objectToArray($operand, array $doc): ?array {
        $obj = Evaluator::resolveOperand($operand, $doc);
        if (!\is_array($obj)) {
            return null;
        }

        $result = [];
        foreach ($obj as $k => $v) {
            $result[] = ['k' => (string)$k, 'v' => $v];
        }
        return $result;
    }

    public static function arrayToObject($operand, array $doc): ?array {
        $arr = Evaluator::resolveOperand($operand, $doc);
        if (!\is_array($arr)) {
            return null;
        }

        $result = [];
        foreach ($arr as $item) {
            if (\is_array($item)) {
                if (isset($item['k']) && isset($item['v'])) {
                    // {k: "key", v: "value"} format
                    $result[(string)$item['k']] = $item['v'];
                } elseif (\count($item) === 2 && isset($item[0]) && isset($item[1])) {
                    // ["key", "value"] format
                    $result[(string)$item[0]] = $item[1];
                }
            }
        }
        return $result;
    }

    public static function let(array $args, array $doc): mixed {
        $vars = $args['vars'] ?? [];
        $inExpr = $args['in'];

        $localDoc = $doc;
        foreach ($vars as $varName => $varExpr) {
            $localDoc[$varName] = Evaluator::resolveOperand($varExpr, $doc);
        }

        return Evaluator::resolveOperand($inExpr, $localDoc);
    }
}
