<?php

namespace MongoLite\Expression;

/**
 * Comparison expression operators
 */
class Comparison {

    public static function eq(array $args, array $doc): bool {
        return Evaluator::resolveOperand($args[0], $doc) == Evaluator::resolveOperand($args[1], $doc);
    }

    public static function ne(array $args, array $doc): bool {
        return Evaluator::resolveOperand($args[0], $doc) != Evaluator::resolveOperand($args[1], $doc);
    }

    public static function gt(array $args, array $doc): bool {
        return Evaluator::resolveOperand($args[0], $doc) > Evaluator::resolveOperand($args[1], $doc);
    }

    public static function gte(array $args, array $doc): bool {
        return Evaluator::resolveOperand($args[0], $doc) >= Evaluator::resolveOperand($args[1], $doc);
    }

    public static function lt(array $args, array $doc): bool {
        return Evaluator::resolveOperand($args[0], $doc) < Evaluator::resolveOperand($args[1], $doc);
    }

    public static function lte(array $args, array $doc): bool {
        return Evaluator::resolveOperand($args[0], $doc) <= Evaluator::resolveOperand($args[1], $doc);
    }

    public static function cmp(array $args, array $doc): int {
        $val1 = Evaluator::resolveOperand($args[0], $doc);
        $val2 = Evaluator::resolveOperand($args[1], $doc);
        return $val1 <=> $val2;
    }

    public static function strcasecmp(array $args, array $doc): int {
        $str1 = (string)Evaluator::resolveOperand($args[0], $doc);
        $str2 = (string)Evaluator::resolveOperand($args[1], $doc);
        return \strcasecmp($str1, $str2) <=> 0;
    }
}
