<?php

namespace MongoLite\Expression;

/**
 * Main expression evaluator - dispatches to specialized operator classes
 */
class Evaluator {

    /**
     * Evaluate an expression against a document
     *
     * @param array $expr The expression to evaluate
     * @param array $doc The document context
     * @return mixed The result of the expression
     */
    public static function evaluate(array $expr, array $doc): mixed {

        // Comparison operators
        if (isset($expr['$eq'])) {
            return Comparison::eq($expr['$eq'], $doc);
        }
        if (isset($expr['$ne'])) {
            return Comparison::ne($expr['$ne'], $doc);
        }
        if (isset($expr['$gt'])) {
            return Comparison::gt($expr['$gt'], $doc);
        }
        if (isset($expr['$gte'])) {
            return Comparison::gte($expr['$gte'], $doc);
        }
        if (isset($expr['$lt'])) {
            return Comparison::lt($expr['$lt'], $doc);
        }
        if (isset($expr['$lte'])) {
            return Comparison::lte($expr['$lte'], $doc);
        }
        if (isset($expr['$cmp'])) {
            return Comparison::cmp($expr['$cmp'], $doc);
        }
        if (isset($expr['$strcasecmp'])) {
            return Comparison::strcasecmp($expr['$strcasecmp'], $doc);
        }

        // Logical operators
        if (isset($expr['$and'])) {
            return Logical::and($expr['$and'], $doc);
        }
        if (isset($expr['$or'])) {
            return Logical::or($expr['$or'], $doc);
        }
        if (isset($expr['$not'])) {
            return Logical::not($expr['$not'], $doc);
        }

        // Arithmetic operators
        if (isset($expr['$add'])) {
            return Arithmetic::add($expr['$add'], $doc);
        }
        if (isset($expr['$subtract'])) {
            return Arithmetic::subtract($expr['$subtract'], $doc);
        }
        if (isset($expr['$multiply'])) {
            return Arithmetic::multiply($expr['$multiply'], $doc);
        }
        if (isset($expr['$divide'])) {
            return Arithmetic::divide($expr['$divide'], $doc);
        }
        if (isset($expr['$mod'])) {
            return Arithmetic::mod($expr['$mod'], $doc);
        }
        if (isset($expr['$abs'])) {
            return Arithmetic::abs($expr['$abs'], $doc);
        }
        if (isset($expr['$ceil'])) {
            return Arithmetic::ceil($expr['$ceil'], $doc);
        }
        if (isset($expr['$floor'])) {
            return Arithmetic::floor($expr['$floor'], $doc);
        }
        if (isset($expr['$round'])) {
            return Arithmetic::round($expr['$round'], $doc);
        }
        if (isset($expr['$pow'])) {
            return Arithmetic::pow($expr['$pow'], $doc);
        }
        if (isset($expr['$sqrt'])) {
            return Arithmetic::sqrt($expr['$sqrt'], $doc);
        }
        if (isset($expr['$log'])) {
            return Arithmetic::log($expr['$log'], $doc);
        }
        if (isset($expr['$log10'])) {
            return Arithmetic::log10($expr['$log10'], $doc);
        }
        if (isset($expr['$ln'])) {
            return Arithmetic::ln($expr['$ln'], $doc);
        }
        if (isset($expr['$exp'])) {
            return Arithmetic::exp($expr['$exp'], $doc);
        }
        if (isset($expr['$trunc'])) {
            return Arithmetic::trunc($expr['$trunc'], $doc);
        }

        // Conditional operators
        if (isset($expr['$cond'])) {
            return Conditional::cond($expr['$cond'], $doc);
        }
        if (isset($expr['$ifNull'])) {
            return Conditional::ifNull($expr['$ifNull'], $doc);
        }
        if (isset($expr['$switch'])) {
            return Conditional::switch($expr['$switch'], $doc);
        }

        // String operators
        if (isset($expr['$concat'])) {
            return StringOps::concat($expr['$concat'], $doc);
        }
        if (isset($expr['$toLower'])) {
            return StringOps::toLower($expr['$toLower'], $doc);
        }
        if (isset($expr['$toUpper'])) {
            return StringOps::toUpper($expr['$toUpper'], $doc);
        }
        if (isset($expr['$trim'])) {
            return StringOps::trim($expr['$trim'], $doc);
        }
        if (isset($expr['$ltrim'])) {
            return StringOps::ltrim($expr['$ltrim'], $doc);
        }
        if (isset($expr['$rtrim'])) {
            return StringOps::rtrim($expr['$rtrim'], $doc);
        }
        if (isset($expr['$substr'])) {
            return StringOps::substr($expr['$substr'], $doc);
        }
        if (isset($expr['$substrBytes'])) {
            return StringOps::substrBytes($expr['$substrBytes'], $doc);
        }
        if (isset($expr['$substrCP'])) {
            return StringOps::substrCP($expr['$substrCP'], $doc);
        }
        if (isset($expr['$strLenBytes'])) {
            return StringOps::strLenBytes($expr['$strLenBytes'], $doc);
        }
        if (isset($expr['$strLenCP'])) {
            return StringOps::strLenCP($expr['$strLenCP'], $doc);
        }
        if (isset($expr['$split'])) {
            return StringOps::split($expr['$split'], $doc);
        }
        if (isset($expr['$indexOfBytes'])) {
            return StringOps::indexOfBytes($expr['$indexOfBytes'], $doc);
        }
        if (isset($expr['$indexOfCP'])) {
            return StringOps::indexOfCP($expr['$indexOfCP'], $doc);
        }
        if (isset($expr['$regexMatch'])) {
            return StringOps::regexMatch($expr['$regexMatch'], $doc);
        }
        if (isset($expr['$regexFind'])) {
            return StringOps::regexFind($expr['$regexFind'], $doc);
        }
        if (isset($expr['$regexFindAll'])) {
            return StringOps::regexFindAll($expr['$regexFindAll'], $doc);
        }
        if (isset($expr['$replaceOne'])) {
            return StringOps::replaceOne($expr['$replaceOne'], $doc);
        }
        if (isset($expr['$replaceAll'])) {
            return StringOps::replaceAll($expr['$replaceAll'], $doc);
        }

        // Type operators
        if (isset($expr['$type'])) {
            return TypeOps::type($expr['$type'], $doc);
        }
        if (isset($expr['$toString'])) {
            return TypeOps::toString($expr['$toString'], $doc);
        }
        if (isset($expr['$toInt'])) {
            return TypeOps::toInt($expr['$toInt'], $doc);
        }
        if (isset($expr['$toLong'])) {
            return TypeOps::toLong($expr['$toLong'], $doc);
        }
        if (isset($expr['$toDouble'])) {
            return TypeOps::toDouble($expr['$toDouble'], $doc);
        }
        if (isset($expr['$toBool'])) {
            return TypeOps::toBool($expr['$toBool'], $doc);
        }
        if (isset($expr['$toDate'])) {
            return TypeOps::toDate($expr['$toDate'], $doc);
        }
        if (isset($expr['$isArray'])) {
            return TypeOps::isArray($expr['$isArray'], $doc);
        }
        if (isset($expr['$isNumber'])) {
            return TypeOps::isNumber($expr['$isNumber'], $doc);
        }
        if (isset($expr['$isString'])) {
            return TypeOps::isString($expr['$isString'], $doc);
        }
        if (isset($expr['$isObject'])) {
            return TypeOps::isObject($expr['$isObject'], $doc);
        }

        // Array operators
        if (isset($expr['$size'])) {
            return ArrayOps::size($expr['$size'], $doc);
        }
        if (isset($expr['$arrayElemAt'])) {
            return ArrayOps::arrayElemAt($expr['$arrayElemAt'], $doc);
        }
        if (isset($expr['$first'])) {
            return ArrayOps::first($expr['$first'], $doc);
        }
        if (isset($expr['$last'])) {
            return ArrayOps::last($expr['$last'], $doc);
        }
        if (isset($expr['$reverseArray'])) {
            return ArrayOps::reverseArray($expr['$reverseArray'], $doc);
        }
        if (isset($expr['$in'])) {
            return ArrayOps::in($expr['$in'], $doc);
        }
        if (isset($expr['$concatArrays'])) {
            return ArrayOps::concatArrays($expr['$concatArrays'], $doc);
        }
        if (isset($expr['$map'])) {
            return ArrayOps::map($expr['$map'], $doc);
        }
        if (isset($expr['$filter'])) {
            return ArrayOps::filter($expr['$filter'], $doc);
        }
        if (isset($expr['$reduce'])) {
            return ArrayOps::reduce($expr['$reduce'], $doc);
        }
        if (isset($expr['$range'])) {
            return ArrayOps::range($expr['$range'], $doc);
        }
        if (isset($expr['$slice'])) {
            return ArrayOps::slice($expr['$slice'], $doc);
        }
        if (isset($expr['$sortArray'])) {
            return ArrayOps::sortArray($expr['$sortArray'], $doc);
        }
        if (isset($expr['$zip'])) {
            return ArrayOps::zip($expr['$zip'], $doc);
        }

        // Set operators
        if (isset($expr['$setUnion'])) {
            return SetOps::setUnion($expr['$setUnion'], $doc);
        }
        if (isset($expr['$setIntersection'])) {
            return SetOps::setIntersection($expr['$setIntersection'], $doc);
        }
        if (isset($expr['$setDifference'])) {
            return SetOps::setDifference($expr['$setDifference'], $doc);
        }
        if (isset($expr['$setEquals'])) {
            return SetOps::setEquals($expr['$setEquals'], $doc);
        }
        if (isset($expr['$setIsSubset'])) {
            return SetOps::setIsSubset($expr['$setIsSubset'], $doc);
        }
        if (isset($expr['$anyElementTrue'])) {
            return SetOps::anyElementTrue($expr['$anyElementTrue'], $doc);
        }
        if (isset($expr['$allElementsTrue'])) {
            return SetOps::allElementsTrue($expr['$allElementsTrue'], $doc);
        }

        // Object operators
        if (isset($expr['$mergeObjects'])) {
            return ObjectOps::mergeObjects($expr['$mergeObjects'], $doc);
        }
        if (isset($expr['$objectToArray'])) {
            return ObjectOps::objectToArray($expr['$objectToArray'], $doc);
        }
        if (isset($expr['$arrayToObject'])) {
            return ObjectOps::arrayToObject($expr['$arrayToObject'], $doc);
        }
        if (isset($expr['$let'])) {
            return ObjectOps::let($expr['$let'], $doc);
        }
        if (isset($expr['$literal'])) {
            return $expr['$literal'];
        }

        // Date operators
        if (isset($expr['$year'])) {
            return DateOps::year($expr['$year'], $doc);
        }
        if (isset($expr['$month'])) {
            return DateOps::month($expr['$month'], $doc);
        }
        if (isset($expr['$dayOfMonth'])) {
            return DateOps::dayOfMonth($expr['$dayOfMonth'], $doc);
        }
        if (isset($expr['$dayOfWeek'])) {
            return DateOps::dayOfWeek($expr['$dayOfWeek'], $doc);
        }
        if (isset($expr['$dayOfYear'])) {
            return DateOps::dayOfYear($expr['$dayOfYear'], $doc);
        }
        if (isset($expr['$hour'])) {
            return DateOps::hour($expr['$hour'], $doc);
        }
        if (isset($expr['$minute'])) {
            return DateOps::minute($expr['$minute'], $doc);
        }
        if (isset($expr['$second'])) {
            return DateOps::second($expr['$second'], $doc);
        }
        if (isset($expr['$week'])) {
            return DateOps::week($expr['$week'], $doc);
        }
        if (isset($expr['$millisecond'])) {
            return DateOps::millisecond($expr['$millisecond'], $doc);
        }
        if (isset($expr['$isoWeek'])) {
            return DateOps::isoWeek($expr['$isoWeek'], $doc);
        }
        if (isset($expr['$isoWeekYear'])) {
            return DateOps::isoWeekYear($expr['$isoWeekYear'], $doc);
        }
        if (isset($expr['$isoDayOfWeek'])) {
            return DateOps::isoDayOfWeek($expr['$isoDayOfWeek'], $doc);
        }
        if (isset($expr['$dateToString'])) {
            return DateOps::dateToString($expr['$dateToString'], $doc);
        }
        if (isset($expr['$dateFromString'])) {
            return DateOps::dateFromString($expr['$dateFromString'], $doc);
        }
        if (isset($expr['$dateAdd'])) {
            return DateOps::dateAdd($expr['$dateAdd'], $doc);
        }
        if (isset($expr['$dateSubtract'])) {
            return DateOps::dateSubtract($expr['$dateSubtract'], $doc);
        }
        if (isset($expr['$dateDiff'])) {
            return DateOps::dateDiff($expr['$dateDiff'], $doc);
        }

        // Misc operators
        if (isset($expr['$rand'])) {
            return \mt_rand() / \mt_getrandmax();
        }

        // Single element array - evaluate the element
        if (count($expr) == 1 && isset($expr[0])) {
            return self::resolveOperand($expr[0], $doc);
        }

        throw new \InvalidArgumentException('Unrecognized expression operator: ' . json_encode($expr));
    }

    /**
     * Resolve an operand value - handles field paths, variables, nested expressions, and literals
     *
     * @param mixed $operand The operand to resolve
     * @param array $doc The document context
     * @return mixed The resolved value
     */
    public static function resolveOperand($operand, array $doc): mixed {
        // Handle $$variable syntax (local variables from $map, $filter, $reduce, $let)
        // Must be checked BEFORE $field syntax since $$var also starts with $
        if (\is_string($operand) && \strlen($operand) > 2 && $operand[0] === '$' && $operand[1] === '$') {
            $varName = \substr($operand, 2); // Remove leading $$
            // Handle nested access like $$item.field
            if (\str_contains($varName, '.')) {
                $parts = \explode('.', $varName, 2);
                $varValue = $doc[$parts[0]] ?? null;
                if (\is_array($varValue)) {
                    return self::getNestedValue($varValue, $parts[1]);
                }
                return null;
            }
            return $doc[$varName] ?? null; // Direct key lookup
        }

        // If operand is a field path (starts with $)
        if (\is_string($operand) && \strlen($operand) > 1 && $operand[0] === '$') {
            $fieldPath = \substr($operand, 1); // Remove leading $
            return self::getNestedValue($doc, $fieldPath);
        }

        // If operand is an array
        if (\is_array($operand) && \count($operand) > 0) {
            $firstKey = \array_key_first($operand);

            // If first key starts with $, it's an expression operator
            if (\is_string($firstKey) && isset($firstKey[0]) && $firstKey[0] === '$') {
                return self::evaluate($operand, $doc);
            }

            // If it's an associative array (object), recursively resolve each value
            // This handles compound _id expressions like: ['dept' => '$department', 'year' => '$year']
            if (\is_string($firstKey)) {
                $result = [];
                foreach ($operand as $key => $value) {
                    $result[$key] = self::resolveOperand($value, $doc);
                }
                return $result;
            }

            // If it's a numeric array, resolve each element
            // This handles array literals with field references
            if (\is_int($firstKey)) {
                $result = [];
                foreach ($operand as $value) {
                    $result[] = self::resolveOperand($value, $doc);
                }
                return $result;
            }
        }

        // Otherwise, return the literal value
        return $operand;
    }

    /**
     * Get a nested value from an array using dot notation
     *
     * @param array $array The array to search
     * @param string $path The dot-notation path
     * @return mixed The value at the path, or null if not found
     */
    public static function getNestedValue(array $array, string $path): mixed {
        $keys = \explode('.', $path);

        foreach ($keys as $key) {
            if (!\is_array($array) || !\array_key_exists($key, $array)) {
                return null;
            }
            $array = $array[$key];
        }

        return $array;
    }
}
