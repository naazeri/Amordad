<?php

namespace MongoLite\Expression;

/**
 * Logical expression operators
 */
class Logical {

    public static function and(array $expressions, array $doc): bool {
        foreach ($expressions as $subExpr) {
            if (!Evaluator::evaluate($subExpr, $doc)) {
                return false;
            }
        }
        return true;
    }

    public static function or(array $expressions, array $doc): bool {
        foreach ($expressions as $subExpr) {
            if (Evaluator::evaluate($subExpr, $doc)) {
                return true;
            }
        }
        return false;
    }

    public static function not($expr, array $doc): bool {
        return !Evaluator::evaluate($expr, $doc);
    }
}
