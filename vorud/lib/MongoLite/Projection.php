<?php

namespace MongoLite;

class Projection {

    public static function onDocuments(array $documents, array $fields): array {

        $hasInclusion = self::hasInclusion($fields);
        $projection = self::normalizeProjection($fields);

        foreach ($documents as &$document) {

            if (!\is_array($document)) {
                continue;
            }

            $id = $document['_id'] ?? null;
            $document = self::process($document, $projection, $hasInclusion);

            if ($id && ($fields['_id'] ?? true)) {
                $document = ['_id' => $id] + $document;
            }
        }

        return $documents;
    }

    public static function onDocument(array $document, array $fields): array {
        return self::onDocuments([$document], $fields)[0];
    }

    public static function hasInclusion(array $fields): bool {
        $hasInclusion = false;
        $hasConflict = false;

        $stack = [$fields];
        while (!empty($stack)) {
            $current = \array_pop($stack);
            foreach ($current as $key => $value) {
                if (\is_array($value)) {
                    $stack[] = $value;
                } elseif (\is_string($value) && \str_starts_with($value, '$')) {
                    // Field expression like '$name' is treated as inclusion
                    $hasInclusion = true;
                } elseif ((bool)$value) {
                    $hasInclusion = true;
                } elseif (!$value && $key !== '_id') {
                    $hasConflict = true;
                }

                if ($hasInclusion && $hasConflict) {
                    throw new \InvalidArgumentException("Projection cannot have a mix of inclusion and exclusion.");
                }
            }
        }

        return $hasInclusion;
    }

    protected static function normalizeProjection($fields): array {

        $projection = [];

        foreach ($fields as $field => $value) {

            if (\str_contains($field, '.')) {
                $projection = \array_replace_recursive($projection, self::dotNotationToArray($field, $value));
            } else {
                $projection[$field] = $value;
            }
        }

        return $projection;
    }

    protected static function process(array $document, array $fields, bool $hasInclusion, ?array $rootDocument = null): array {

        // Keep reference to root document for field expressions
        if ($rootDocument === null) {
            $rootDocument = $document;
        }

        $result = [];

        if (self::is_sequential($document)) {
            foreach ($document as $key => $value) {

                if (\is_array($value)) {
                    $result[] = self::process($value, $fields, $hasInclusion, $rootDocument);
                } else {
                    $result[] = $value;
                }
            }
            return $result;
        }

        // First handle field expressions (e.g., 'newField' => '$existingField')
        foreach ($fields as $targetField => $fieldValue) {
            if ($targetField === '_id') continue; // _id is handled separately

            // Handle field reference expressions like '$name'
            if (\is_string($fieldValue) && \str_starts_with($fieldValue, '$')) {
                $sourceField = \substr($fieldValue, 1);
                $result[$targetField] = self::getNestedValue($rootDocument, $sourceField);
            }
        }

        // Then handle regular inclusion/exclusion
        foreach ($document as $field => $value) {

            if (\is_array($value) && isset($fields[$field]) && \is_array($fields[$field])) {

                if (\is_array($fields[$field])) {
                    $result[$field] = self::process($value, $fields[$field], $hasInclusion, $rootDocument);
                } else {
                    $result[$field] = $value;
                }

            } else {
                // Skip fields that are expressions (already handled above)
                if (isset($fields[$field]) && \is_string($fields[$field]) && \str_starts_with($fields[$field], '$')) {
                    continue;
                }

                if ($hasInclusion && isset($fields[$field]) && $fields[$field] == 1) {
                    $result[$field] = $value;
                } elseif (!$hasInclusion && (!isset($fields[$field]) || $fields[$field] != 0)) {
                    $result[$field] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Get nested value from document using dot notation
     */
    protected static function getNestedValue(array $document, string $path): mixed {
        $keys = \explode('.', $path);
        $current = $document;

        foreach ($keys as $key) {
            if (\is_array($current) && \array_key_exists($key, $current)) {
                $current = $current[$key];
            } else {
                return null;
            }
        }

        return $current;
    }

    protected static function dotNotationToArray(string $dotNotation, mixed $value = 1): array {

        $result = [];
        $parts = \explode('.', $dotNotation);
        $valPos = \count($parts) - 1;
        $pointer = &$result;

        foreach ($parts as $i => $part) {
            $pointer[$part] = $i === $valPos  ? $value : [];
            $pointer = &$pointer[$part];
        }
        return $result;
    }

    protected static function is_sequential(array $arr): bool {

        $i = 0;

        foreach ($arr as $key => $value) {
            if ($key !== $i) return false;
            $i++;
        }

        return true;
    }
}
