<?php

namespace MongoLite\Expression;

/**
 * Date expression operators
 */
class DateOps {

    public static function year($operand, array $doc): ?int {
        $date = Evaluator::resolveOperand($operand, $doc);
        return self::extractDatePart($date, 'Y');
    }

    public static function month($operand, array $doc): ?int {
        $date = Evaluator::resolveOperand($operand, $doc);
        return self::extractDatePart($date, 'n');
    }

    public static function dayOfMonth($operand, array $doc): ?int {
        $date = Evaluator::resolveOperand($operand, $doc);
        return self::extractDatePart($date, 'j');
    }

    public static function dayOfWeek($operand, array $doc): ?int {
        $date = Evaluator::resolveOperand($operand, $doc);
        $dow = self::extractDatePart($date, 'w');
        // MongoDB: Sunday=1, Saturday=7
        return $dow !== null ? $dow + 1 : null;
    }

    public static function dayOfYear($operand, array $doc): ?int {
        $date = Evaluator::resolveOperand($operand, $doc);
        $doy = self::extractDatePart($date, 'z');
        // PHP is 0-indexed, MongoDB is 1-indexed
        return $doy !== null ? $doy + 1 : null;
    }

    public static function hour($operand, array $doc): ?int {
        $date = Evaluator::resolveOperand($operand, $doc);
        return self::extractDatePart($date, 'G');
    }

    public static function minute($operand, array $doc): ?int {
        $date = Evaluator::resolveOperand($operand, $doc);
        return self::extractDatePart($date, 'i');
    }

    public static function second($operand, array $doc): ?int {
        $date = Evaluator::resolveOperand($operand, $doc);
        return self::extractDatePart($date, 's');
    }

    public static function week($operand, array $doc): ?int {
        $date = Evaluator::resolveOperand($operand, $doc);
        return self::extractDatePart($date, 'W');
    }

    public static function millisecond($operand, array $doc): int {
        $date = Evaluator::resolveOperand($operand, $doc);
        if ($date === null) {
            return 0;
        }
        // If it's a millisecond timestamp, extract ms part
        if (\is_numeric($date) && $date > 9999999999) {
            return (int)($date % 1000);
        }
        // For string dates or second timestamps, milliseconds are 0
        return 0;
    }

    public static function isoWeek($operand, array $doc): ?int {
        $date = Evaluator::resolveOperand($operand, $doc);
        return self::extractDatePart($date, 'W');
    }

    public static function isoWeekYear($operand, array $doc): ?int {
        $date = Evaluator::resolveOperand($operand, $doc);
        return self::extractDatePart($date, 'o');
    }

    public static function isoDayOfWeek($operand, array $doc): ?int {
        $date = Evaluator::resolveOperand($operand, $doc);
        return self::extractDatePart($date, 'N');
    }

    public static function dateToString(array $args, array $doc): ?string {
        $date = Evaluator::resolveOperand($args['date'], $doc);
        $format = $args['format'] ?? '%Y-%m-%dT%H:%M:%S.%LZ';
        $onNull = $args['onNull'] ?? null;

        if ($date === null) {
            return $onNull;
        }

        $timestamp = self::parseTimestamp($date);
        if ($timestamp === null) {
            return $onNull;
        }

        // Convert MongoDB format specifiers to PHP date format
        $phpFormat = \strtr($format, [
            '%Y' => 'Y', '%m' => 'm', '%d' => 'd',
            '%H' => 'H', '%M' => 'i', '%S' => 's',
            '%L' => '000', // Milliseconds (simplified)
            '%j' => 'z', '%w' => 'w', '%U' => 'W',
            '%Z' => 'e', '%z' => 'O',
            '%%' => '%'
        ]);

        return \date($phpFormat, $timestamp);
    }

    public static function dateFromString(array $args, array $doc): ?int {
        $dateString = Evaluator::resolveOperand($args['dateString'], $doc);
        $onNull = $args['onNull'] ?? null;
        $onError = $args['onError'] ?? null;

        if ($dateString === null) {
            return $onNull;
        }

        $timestamp = \strtotime((string)$dateString);
        if ($timestamp === false) {
            return $onError;
        }

        return $timestamp * 1000; // Return as milliseconds (MongoDB ISODate style)
    }

    public static function dateAdd(array $args, array $doc): ?int {
        $startDate = Evaluator::resolveOperand($args['startDate'], $doc);
        $unit = $args['unit'];
        $amount = (int)Evaluator::resolveOperand($args['amount'], $doc);

        $timestamp = self::parseTimestamp($startDate);
        if ($timestamp === null) {
            return null;
        }

        return self::addToTimestamp($timestamp, $unit, $amount);
    }

    public static function dateSubtract(array $args, array $doc): ?int {
        $startDate = Evaluator::resolveOperand($args['startDate'], $doc);
        $unit = $args['unit'];
        $amount = (int)Evaluator::resolveOperand($args['amount'], $doc);

        $timestamp = self::parseTimestamp($startDate);
        if ($timestamp === null) {
            return null;
        }

        return self::addToTimestamp($timestamp, $unit, -$amount);
    }

    public static function dateDiff(array $args, array $doc): ?int {
        $startDate = Evaluator::resolveOperand($args['startDate'], $doc);
        $endDate = Evaluator::resolveOperand($args['endDate'], $doc);
        $unit = $args['unit'];

        $startTs = self::parseTimestamp($startDate);
        $endTs = self::parseTimestamp($endDate);

        if ($startTs === null || $endTs === null) {
            return null;
        }

        $diffSeconds = $endTs - $startTs;

        switch ($unit) {
            case 'millisecond':
                return (int)($diffSeconds * 1000);
            case 'second':
                return (int)$diffSeconds;
            case 'minute':
                return (int)($diffSeconds / 60);
            case 'hour':
                return (int)($diffSeconds / 3600);
            case 'day':
                return (int)($diffSeconds / 86400);
            case 'week':
                return (int)($diffSeconds / 604800);
            case 'month':
                $startDt = new \DateTime("@{$startTs}");
                $endDt = new \DateTime("@{$endTs}");
                $diff = $startDt->diff($endDt);
                $months = ($diff->y * 12) + $diff->m;
                return $diff->invert ? -$months : $months;
            case 'year':
                $startDt = new \DateTime("@{$startTs}");
                $endDt = new \DateTime("@{$endTs}");
                $diff = $startDt->diff($endDt);
                return $diff->invert ? -$diff->y : $diff->y;
            default:
                return null;
        }
    }

    /**
     * Extract a date part from various date formats
     */
    private static function extractDatePart($date, string $format): ?int {
        if ($date === null) {
            return null;
        }

        $timestamp = self::parseTimestamp($date);
        if ($timestamp === null) {
            return null;
        }

        $result = \date($format, $timestamp);
        return (int)$result;
    }

    /**
     * Parse a date value into a Unix timestamp (seconds)
     */
    private static function parseTimestamp($date): ?int {
        if ($date === null) {
            return null;
        }

        if (\is_numeric($date)) {
            // Unix timestamp (seconds or milliseconds)
            return $date > 9999999999 ? (int)($date / 1000) : (int)$date;
        } elseif (\is_string($date)) {
            $timestamp = \strtotime($date);
            return $timestamp !== false ? $timestamp : null;
        } elseif ($date instanceof \DateTime || $date instanceof \DateTimeInterface) {
            return $date->getTimestamp();
        }

        return null;
    }

    /**
     * Add time to a timestamp and return result in milliseconds
     */
    private static function addToTimestamp(int $timestamp, string $unit, int $amount): int {
        $dt = new \DateTime("@{$timestamp}");

        switch ($unit) {
            case 'millisecond':
                // PHP doesn't have millisecond precision, return in ms
                return ($timestamp * 1000) + $amount;
            case 'second':
                $dt->modify("{$amount} seconds");
                break;
            case 'minute':
                $dt->modify("{$amount} minutes");
                break;
            case 'hour':
                $dt->modify("{$amount} hours");
                break;
            case 'day':
                $dt->modify("{$amount} days");
                break;
            case 'week':
                $dt->modify("{$amount} weeks");
                break;
            case 'month':
                $dt->modify("{$amount} months");
                break;
            case 'quarter':
                $dt->modify(($amount * 3) . " months");
                break;
            case 'year':
                $dt->modify("{$amount} years");
                break;
            default:
                return $timestamp * 1000;
        }

        return $dt->getTimestamp() * 1000;
    }
}
