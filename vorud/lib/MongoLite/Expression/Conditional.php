<?php

namespace MongoLite\Expression;

/**
 * Conditional expression operators
 */
class Conditional {

    public static function cond($args, array $doc): mixed {
        // Handle object format: {if: ..., then: ..., else: ...}
        if (\is_array($args) && isset($args['if'])) {
            $condition = Evaluator::evaluate($args['if'], $doc);
            return $condition
                ? Evaluator::resolveOperand($args['then'], $doc)
                : Evaluator::resolveOperand($args['else'], $doc);
        }

        // Handle array format: [condition, trueValue, falseValue]
        if (\is_array($args) && isset($args[0])) {
            $condition = Evaluator::evaluate($args[0], $doc);
            return $condition
                ? Evaluator::resolveOperand($args[1], $doc)
                : Evaluator::resolveOperand($args[2], $doc);
        }

        return null;
    }

    public static function ifNull(array $operands, array $doc): mixed {
        foreach ($operands as $operand) {
            $val = Evaluator::resolveOperand($operand, $doc);
            if ($val !== null) {
                return $val;
            }
        }
        return null;
    }

    public static function switch(array $args, array $doc): mixed {
        $branches = $args['branches'] ?? [];

        foreach ($branches as $branch) {
            if (Evaluator::evaluate($branch['case'], $doc)) {
                return Evaluator::resolveOperand($branch['then'], $doc);
            }
        }

        return isset($args['default'])
            ? Evaluator::resolveOperand($args['default'], $doc)
            : null;
    }
}
