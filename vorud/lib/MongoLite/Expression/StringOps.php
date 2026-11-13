<?php

namespace MongoLite\Expression;

/**
 * String expression operators
 */
class StringOps {

    public static function concat(array $operands, array $doc): string {
        $result = '';
        foreach ($operands as $operand) {
            $val = Evaluator::resolveOperand($operand, $doc);
            if ($val !== null) {
                $result .= (string)$val;
            }
        }
        return $result;
    }

    public static function toLower($operand, array $doc): ?string {
        $val = Evaluator::resolveOperand($operand, $doc);
        return $val === null ? null : \mb_strtolower((string)$val, 'UTF-8');
    }

    public static function toUpper($operand, array $doc): ?string {
        $val = Evaluator::resolveOperand($operand, $doc);
        return $val === null ? null : \mb_strtoupper((string)$val, 'UTF-8');
    }

    public static function trim($input, array $doc): ?string {
        // Handle both simple operand and {input: ..., chars: ...} format
        if (\is_array($input) && isset($input['input'])) {
            $val = Evaluator::resolveOperand($input['input'], $doc);
            $chars = isset($input['chars']) ? Evaluator::resolveOperand($input['chars'], $doc) : " \t\n\r\0\x0B";
        } else {
            $val = Evaluator::resolveOperand($input, $doc);
            $chars = " \t\n\r\0\x0B";
        }
        return $val === null ? null : \trim((string)$val, $chars);
    }

    public static function ltrim($input, array $doc): ?string {
        // Handle both simple operand and {input: ..., chars: ...} format
        if (\is_array($input) && isset($input['input'])) {
            $val = Evaluator::resolveOperand($input['input'], $doc);
            $chars = isset($input['chars']) ? Evaluator::resolveOperand($input['chars'], $doc) : " \t\n\r\0\x0B";
        } else {
            $val = Evaluator::resolveOperand($input, $doc);
            $chars = " \t\n\r\0\x0B";
        }
        return $val === null ? null : \ltrim((string)$val, $chars);
    }

    public static function rtrim($input, array $doc): ?string {
        // Handle both simple operand and {input: ..., chars: ...} format
        if (\is_array($input) && isset($input['input'])) {
            $val = Evaluator::resolveOperand($input['input'], $doc);
            $chars = isset($input['chars']) ? Evaluator::resolveOperand($input['chars'], $doc) : " \t\n\r\0\x0B";
        } else {
            $val = Evaluator::resolveOperand($input, $doc);
            $chars = " \t\n\r\0\x0B";
        }
        return $val === null ? null : \rtrim((string)$val, $chars);
    }

    public static function substr(array $args, array $doc): ?string {
        $str = Evaluator::resolveOperand($args[0], $doc);
        $start = (int)Evaluator::resolveOperand($args[1], $doc);
        $len = (int)Evaluator::resolveOperand($args[2], $doc);
        return $str === null ? null : \substr((string)$str, $start, $len);
    }

    public static function substrBytes(array $args, array $doc): ?string {
        $str = Evaluator::resolveOperand($args[0], $doc);
        $start = (int)Evaluator::resolveOperand($args[1], $doc);
        $len = (int)Evaluator::resolveOperand($args[2], $doc);
        return $str === null ? null : \substr((string)$str, $start, $len);
    }

    public static function substrCP(array $args, array $doc): ?string {
        $str = Evaluator::resolveOperand($args[0], $doc);
        $start = (int)Evaluator::resolveOperand($args[1], $doc);
        $len = (int)Evaluator::resolveOperand($args[2], $doc);
        return $str === null ? null : \mb_substr((string)$str, $start, $len, 'UTF-8');
    }

    public static function strLenBytes($operand, array $doc): ?int {
        $val = Evaluator::resolveOperand($operand, $doc);
        return $val === null ? null : \strlen((string)$val);
    }

    public static function strLenCP($operand, array $doc): ?int {
        $val = Evaluator::resolveOperand($operand, $doc);
        return $val === null ? null : \mb_strlen((string)$val, 'UTF-8');
    }

    public static function split(array $args, array $doc): ?array {
        $str = Evaluator::resolveOperand($args[0], $doc);
        $delimiter = Evaluator::resolveOperand($args[1], $doc);
        if ($str === null || $delimiter === null) {
            return null;
        }
        return \explode((string)$delimiter, (string)$str);
    }

    public static function indexOfBytes(array $args, array $doc): int {
        $str = (string)Evaluator::resolveOperand($args[0], $doc);
        $substr = (string)Evaluator::resolveOperand($args[1], $doc);
        $start = isset($args[2]) ? (int)Evaluator::resolveOperand($args[2], $doc) : 0;
        $end = isset($args[3]) ? (int)Evaluator::resolveOperand($args[3], $doc) : null;

        if ($end !== null) {
            $str = \substr($str, 0, $end);
        }
        $pos = \strpos($str, $substr, $start);
        return $pos === false ? -1 : $pos;
    }

    public static function indexOfCP(array $args, array $doc): int {
        $str = (string)Evaluator::resolveOperand($args[0], $doc);
        $substr = (string)Evaluator::resolveOperand($args[1], $doc);
        $start = isset($args[2]) ? (int)Evaluator::resolveOperand($args[2], $doc) : 0;

        $pos = \mb_strpos($str, $substr, $start, 'UTF-8');
        return $pos === false ? -1 : $pos;
    }

    public static function regexMatch(array $args, array $doc): bool {
        $input = Evaluator::resolveOperand($args['input'], $doc);
        $regex = $args['regex'];
        $options = $args['options'] ?? '';

        if ($input === null) {
            return false;
        }

        $pattern = self::buildPattern($regex, $options);
        return (bool)\preg_match($pattern, (string)$input);
    }

    public static function regexFind(array $args, array $doc): ?array {
        $input = Evaluator::resolveOperand($args['input'], $doc);
        $regex = $args['regex'];
        $options = $args['options'] ?? '';

        if ($input === null) {
            return null;
        }

        $pattern = self::buildPattern($regex, $options);
        if (\preg_match($pattern, (string)$input, $matches, PREG_OFFSET_CAPTURE)) {
            return [
                'match' => $matches[0][0],
                'idx' => $matches[0][1],
                'captures' => \array_slice(\array_column($matches, 0), 1)
            ];
        }
        return null;
    }

    public static function regexFindAll(array $args, array $doc): array {
        $input = Evaluator::resolveOperand($args['input'], $doc);
        $regex = $args['regex'];
        $options = $args['options'] ?? '';

        if ($input === null) {
            return [];
        }

        $pattern = self::buildPattern($regex, $options);
        $results = [];

        if (\preg_match_all($pattern, (string)$input, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $results[] = [
                    'match' => $match[0][0],
                    'idx' => $match[0][1],
                    'captures' => \array_slice(\array_column($match, 0), 1)
                ];
            }
        }
        return $results;
    }

    public static function replaceOne(array $args, array $doc): ?string {
        $input = Evaluator::resolveOperand($args['input'], $doc);
        $find = Evaluator::resolveOperand($args['find'], $doc);
        $replacement = Evaluator::resolveOperand($args['replacement'], $doc);

        if ($input === null) {
            return null;
        }
        if ($find === null || $find === '') {
            return (string)$input;
        }
        if ($replacement === null) {
            $replacement = '';
        }

        $pos = \strpos((string)$input, (string)$find);
        if ($pos === false) {
            return (string)$input;
        }

        return \substr_replace((string)$input, (string)$replacement, $pos, \strlen((string)$find));
    }

    public static function replaceAll(array $args, array $doc): ?string {
        $input = Evaluator::resolveOperand($args['input'], $doc);
        $find = Evaluator::resolveOperand($args['find'], $doc);
        $replacement = Evaluator::resolveOperand($args['replacement'], $doc);

        if ($input === null) {
            return null;
        }
        if ($find === null || $find === '') {
            return (string)$input;
        }
        if ($replacement === null) {
            $replacement = '';
        }

        return \str_replace((string)$find, (string)$replacement, (string)$input);
    }

    private static function buildPattern(string $regex, string $options = ''): string {
        // Check if regex already has delimiters (e.g., /pattern/flags)
        if (\strlen($regex) >= 2 && $regex[0] === '/') {
            // Find the last delimiter
            $lastSlash = \strrpos($regex, '/');
            if ($lastSlash > 0) {
                // Extract existing pattern and flags
                $existingFlags = \substr($regex, $lastSlash + 1);
                // Merge with options
                $allFlags = $existingFlags . self::buildRegexModifiers($options);
                // Remove duplicates
                $allFlags = \implode('', \array_unique(\str_split($allFlags)));
                return \substr($regex, 0, $lastSlash + 1) . $allFlags;
            }
        }
        // No delimiters, add them
        $modifiers = self::buildRegexModifiers($options);
        return "/{$regex}/{$modifiers}";
    }

    private static function buildRegexModifiers(string $options): string {
        $modifiers = '';
        if (\str_contains($options, 'i')) $modifiers .= 'i';
        if (\str_contains($options, 'm')) $modifiers .= 'm';
        if (\str_contains($options, 's')) $modifiers .= 's';
        return $modifiers;
    }
}
