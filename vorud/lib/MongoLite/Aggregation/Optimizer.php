<?php

namespace MongoLite\Aggregation;

use PDO;
use MongoLite\Query\Optimizer as QueryOptimizer;

/**
 * Aggregation Pipeline Optimizer for SQLite JSONB
 *
 * Converts MongoDB-style aggregation pipelines to SQLite SQL.
 * Uses partial optimization - optimizes leading stages, falls back to PHP for the rest.
 */
class Optimizer {

    protected PDO $connection;
    protected QueryOptimizer $queryOptimizer;
    protected string $tableName;
    protected array $unwoundFields = []; // Track fields unwound in current pipeline

    /**
     * Stages that can be fully optimized to SQLite
     */
    protected const OPTIMIZABLE_STAGES = [
        '$match', '$group', '$sort', '$limit', '$skip', '$count', '$project', '$unwind', '$addFields'
    ];

    /**
     * Accumulator operators supported in $group
     */
    protected const GROUP_ACCUMULATORS = [
        '$sum', '$avg', '$min', '$max', '$first', '$last', '$push', '$addToSet', '$count'
    ];

    public function __construct(PDO $connection, QueryOptimizer $queryOptimizer) {
        $this->connection = $connection;
        $this->queryOptimizer = $queryOptimizer;
    }

    /**
     * Set the table name for the current aggregation
     */
    public function setTableName(string $tableName): void {
        $this->tableName = $tableName;
    }

    /**
     * Attempt to fully optimize a pipeline to SQL.
     * Returns null if the pipeline cannot be fully optimized.
     *
     * @param array $pipeline
     * @return string|null SQL query or null if cannot optimize
     */
    public function optimize(array $pipeline): ?string {
        if (empty($pipeline)) {
            return "SELECT document FROM `{$this->tableName}`";
        }

        try {
            return $this->buildQuery($pipeline);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Partial optimization - optimize leading stages, return remaining for PHP.
     *
     * @param array $pipeline
     * @return array [sql, remainingPipeline, optimizedStageCount]
     */
    public function optimizePartial(array $pipeline): array {
        if (empty($pipeline)) {
            return [
                "SELECT document FROM `{$this->tableName}`",
                [],
                0
            ];
        }

        // Find how many leading stages we can optimize
        $optimizableCount = 0;
        $hasGroup = false;
        $hasSkipOrLimit = false;

        foreach ($pipeline as $stage) {
            $stageOp = array_key_first($stage);

            if (!in_array($stageOp, self::OPTIMIZABLE_STAGES)) {
                break;
            }

            // Check if the specific stage can be optimized
            if (!$this->canOptimizeStage($stage, $hasGroup, $hasSkipOrLimit)) {
                break;
            }

            // Track if we've seen a $group (affects subsequent $match handling)
            if ($stageOp === '$group') {
                $hasGroup = true;
            }

            // Track if we've seen $skip or $limit (affects subsequent $group handling)
            if ($stageOp === '$skip' || $stageOp === '$limit') {
                $hasSkipOrLimit = true;
            }

            $optimizableCount++;
        }

        if ($optimizableCount === 0) {
            // Can't optimize anything - return base query
            return [
                "SELECT document FROM `{$this->tableName}`",
                $pipeline,
                0
            ];
        }

        $optimizedPipeline = array_slice($pipeline, 0, $optimizableCount);
        $remainingPipeline = array_slice($pipeline, $optimizableCount);

        try {
            $sql = $this->buildQuery($optimizedPipeline);
            return [$sql, $remainingPipeline, $optimizableCount];
        } catch (\Exception $e) {
            // Optimization failed - return base query
            return [
                "SELECT document FROM `{$this->tableName}`",
                $pipeline,
                0
            ];
        }
    }

    /**
     * Check if a specific stage can be optimized
     *
     * @param array $stage The pipeline stage
     * @param bool $hasGroup Whether a $group stage has already been processed
     * @param bool $hasSkipOrLimit Whether a $skip or $limit stage has already been processed
     */
    protected function canOptimizeStage(array $stage, bool $hasGroup = false, bool $hasSkipOrLimit = false): bool {
        $stageOp = array_key_first($stage);
        $stageValue = $stage[$stageOp];

        switch ($stageOp) {
            case '$match':
                // $match after $group requires HAVING and column references - fall back to PHP
                if ($hasGroup) {
                    return false;
                }
                // Use QueryOptimizer to check if filter can be optimized
                return $this->queryOptimizer->optimize($stageValue) !== null;

            case '$group':
                // $group after $skip/$limit requires subquery - fall back to PHP for now
                if ($hasSkipOrLimit) {
                    return false;
                }
                return $this->canOptimizeGroup($stageValue);

            case '$project':
                return $this->canOptimizeProject($stageValue);

            case '$sort':
            case '$limit':
            case '$skip':
            case '$count':
                return true;

            case '$unwind':
                // Only simple $unwind can be optimized
                // preserveNullAndEmptyArrays and includeArrayIndex are complex
                if (is_string($stageValue)) {
                    return true;
                }
                if (is_array($stageValue)) {
                    // Fall back to PHP for complex $unwind options
                    if (!empty($stageValue['preserveNullAndEmptyArrays']) ||
                        isset($stageValue['includeArrayIndex'])) {
                        return false;
                    }
                    return isset($stageValue['path']);
                }
                return false;

            case '$addFields':
                return $this->canOptimizeAddFields($stageValue);

            default:
                return false;
        }
    }

    /**
     * Check if $group stage can be optimized
     */
    protected function canOptimizeGroup(array $group): bool {
        if (!isset($group['_id'])) {
            return false;
        }

        // Compound _id is complex - fall back to PHP for now
        $groupId = $group['_id'];
        if (is_array($groupId)) {
            return false;
        }

        foreach ($group as $field => $expr) {
            if ($field === '_id') continue;

            if (!is_array($expr)) {
                return false;
            }

            $accumulator = array_key_first($expr);

            // $first and $last are complex in SQLite - fall back to PHP
            if (in_array($accumulator, ['$first', '$last'])) {
                return false;
            }

            if (!in_array($accumulator, self::GROUP_ACCUMULATORS)) {
                return false;
            }

            // Check if the accumulator expression is simple enough
            $accValue = $expr[$accumulator];
            if ($accumulator === '$count') {
                continue; // $count doesn't need a field
            }
            if (!$this->isSimpleFieldExpression($accValue)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if $project stage can be optimized
     */
    protected function canOptimizeProject(array $project): bool {
        $hasExclusion = false;
        $hasInclusion = false;

        foreach ($project as $field => $expr) {
            if ($field === '_id') continue;

            if ((is_int($expr) || is_bool($expr)) && !$expr) {
                $hasExclusion = true;
            } elseif ((is_int($expr) || is_bool($expr)) && $expr) {
                $hasInclusion = true;
            } elseif (is_string($expr) && str_starts_with($expr, '$')) {
                $hasInclusion = true;
            } elseif (is_array($expr)) {
                // Check for simple expressions
                $op = array_key_first($expr);
                if (!in_array($op, ['$concat', '$substr', '$toLower', '$toUpper', '$ifNull'])) {
                    return false;
                }
                $hasInclusion = true;
            }
        }

        // Field exclusion is complex in SQLite - fall back to PHP
        if ($hasExclusion && !$hasInclusion) {
            return false;
        }

        return true;
    }

    /**
     * Check if $addFields stage can be optimized
     */
    protected function canOptimizeAddFields(array $fields): bool {
        foreach ($fields as $field => $expr) {
            if (!$this->isSimpleFieldExpression($expr) && !is_scalar($expr)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if expression is a simple field reference
     */
    protected function isSimpleFieldExpression(mixed $expr): bool {
        if (is_string($expr) && str_starts_with($expr, '$')) {
            return true;
        }
        if (is_int($expr) || is_float($expr)) {
            return true; // Literal number (for $sum: 1)
        }
        return false;
    }

    /**
     * Build the SQL query from the pipeline
     */
    protected function buildQuery(array $pipeline): string {
        // Reset unwound fields for new query
        $this->unwoundFields = [];
        $context = new Context($this->tableName);

        foreach ($pipeline as $stage) {
            $stageOp = array_key_first($stage);
            $stageValue = $stage[$stageOp];

            switch ($stageOp) {
                case '$match':
                    $this->applyMatch($context, $stageValue);
                    break;

                case '$group':
                    $this->applyGroup($context, $stageValue);
                    break;

                case '$sort':
                    $this->applySort($context, $stageValue);
                    break;

                case '$limit':
                    $context->limit = (int)$stageValue;
                    break;

                case '$skip':
                    $context->offset = (int)$stageValue;
                    break;

                case '$count':
                    $this->applyCount($context, $stageValue);
                    break;

                case '$project':
                    $this->applyProject($context, $stageValue);
                    break;

                case '$unwind':
                    $this->applyUnwind($context, $stageValue);
                    break;

                case '$addFields':
                    $this->applyAddFields($context, $stageValue);
                    break;

                default:
                    throw new \Exception("Unsupported stage: {$stageOp}");
            }
        }

        return $context->toSQL($this->connection);
    }

    /**
     * Apply $match stage
     */
    protected function applyMatch(Context $context, array $filter): void {
        $whereSql = $this->queryOptimizer->optimize($filter);
        if ($whereSql === null) {
            throw new \Exception('Cannot optimize $match filter');
        }
        $context->addWhere($whereSql);
    }

    /**
     * Apply $group stage
     */
    protected function applyGroup(Context $context, array $group): void {
        $groupId = $group['_id'];

        // Handle _id expression
        if ($groupId === null) {
            $context->groupBy = null;
            $context->addSelect("NULL as \"_id\"");
        } elseif (is_string($groupId) && str_starts_with($groupId, '$')) {
            $field = substr($groupId, 1);
            $jsonPath = $this->toJsonExtract($field);
            $context->groupBy = $jsonPath;
            $context->addSelect("{$jsonPath} as \"_id\"");
        } elseif (is_array($groupId)) {
            // Compound _id - build as JSON object
            $parts = [];
            $selectParts = [];
            foreach ($groupId as $alias => $fieldExpr) {
                if (is_string($fieldExpr) && str_starts_with($fieldExpr, '$')) {
                    $field = substr($fieldExpr, 1);
                    $jsonPath = $this->toJsonExtract($field);
                    $parts[] = $jsonPath;
                    $selectParts[] = "'" . $this->escapeJsonPath($alias) . "', {$jsonPath}";
                }
            }
            $context->groupBy = implode(', ', $parts);
            $context->addSelect("json_object(" . implode(', ', $selectParts) . ") as \"_id\"");
        } else {
            throw new \Exception('Unsupported $group _id expression');
        }

        // Handle accumulators
        foreach ($group as $outputField => $accumulator) {
            if ($outputField === '_id') continue;

            $accOp = array_key_first($accumulator);
            $accValue = $accumulator[$accOp];

            $sqlExpr = $this->buildAccumulator($accOp, $accValue, $context);
            $safeField = $this->escapeIdentifier($outputField);
            $context->addSelect("{$sqlExpr} as \"{$safeField}\"");
        }

        $context->isGrouped = true;
    }

    /**
     * Build SQL for accumulator
     */
    protected function buildAccumulator(string $op, mixed $value, Context $context): string {
        switch ($op) {
            case '$sum':
                if ($value === 1 || $value === '1') {
                    return 'COUNT(*)';
                }
                $field = $this->toJsonExtractNumeric(substr($value, 1));
                return "SUM({$field})";

            case '$avg':
                $field = $this->toJsonExtractNumeric(substr($value, 1));
                return "AVG({$field})";

            case '$min':
                $field = $this->toJsonExtractNumeric(substr($value, 1));
                return "MIN({$field})";

            case '$max':
                $field = $this->toJsonExtractNumeric(substr($value, 1));
                return "MAX({$field})";

            case '$first':
                $field = $this->toJsonExtract(substr($value, 1));
                // SQLite doesn't have FIRST_VALUE as aggregate, use MIN with ROWID trick
                return "{$field}"; // Will get first due to implicit ordering

            case '$last':
                $field = $this->toJsonExtract(substr($value, 1));
                return "{$field}"; // Will get last

            case '$push':
                $field = $this->toJsonExtract(substr($value, 1));
                return "json_group_array({$field})";

            case '$addToSet':
                $field = $this->toJsonExtract(substr($value, 1));
                return "json_group_array(DISTINCT {$field})";

            case '$count':
                return 'COUNT(*)';

            default:
                throw new \Exception("Unsupported accumulator: {$op}");
        }
    }

    /**
     * Apply $sort stage
     */
    protected function applySort(Context $context, array $sort): void {
        $orderParts = [];

        foreach ($sort as $field => $direction) {
            $dir = $direction === -1 ? 'DESC' : 'ASC';

            if ($context->isGrouped && $field === '_id') {
                // Sort by group _id
                $orderParts[] = "\"_id\" {$dir}";
            } elseif ($context->isGrouped && isset($context->selectAliases[$field])) {
                // Sort by computed field
                $safeField = $this->escapeIdentifier($field);
                $orderParts[] = "\"{$safeField}\" {$dir}";
            } else {
                // Sort by document field
                $jsonPath = $this->toJsonExtract($field);
                $orderParts[] = "{$jsonPath} {$dir}";
            }
        }

        $context->orderBy = implode(', ', $orderParts);
    }

    /**
     * Apply $count stage
     */
    protected function applyCount(Context $context, string $countField): void {
        // $count replaces the document with {field: count}
        $safeField = $this->escapeIdentifier($countField);
        $context->selectFields = ["COUNT(*) as \"{$safeField}\""];
        $context->isCount = true;
    }

    /**
     * Apply $project stage
     */
    protected function applyProject(Context $context, array $project): void {
        $fields = [];
        $includeId = true;

        foreach ($project as $field => $expr) {
            if ($field === '_id') {
                $includeId = (bool)$expr;
                continue;
            }

            if ($expr === 1 || $expr === true) {
                // Include field
                $jsonPath = $this->toJsonExtract($field);
                $fields[$field] = $jsonPath;
            } elseif ($expr === 0 || $expr === false) {
                // Exclude - handled differently
                continue;
            } elseif (is_string($expr) && str_starts_with($expr, '$')) {
                // Field reference
                $refField = substr($expr, 1);
                $jsonPath = $this->toJsonExtract($refField);
                $fields[$field] = $jsonPath;
            } elseif (is_array($expr)) {
                // Expression
                $sqlExpr = $this->buildProjectionExpression($expr, $context);
                $fields[$field] = $sqlExpr;
            } elseif (is_scalar($expr)) {
                // Literal value
                $fields[$field] = $this->quoteLiteral($expr);
            }
        }

        $context->projection = $fields;
        $context->projectIncludeId = $includeId;
    }

    /**
     * Build SQL for projection expression
     */
    protected function buildProjectionExpression(array $expr, Context $context): string {
        $op = array_key_first($expr);
        $value = $expr[$op];

        switch ($op) {
            case '$concat':
                $parts = array_map(function($v) {
                    if (is_string($v) && str_starts_with($v, '$')) {
                        return $this->toJsonExtract(substr($v, 1));
                    }
                    return $this->connection->quote($v);
                }, $value);
                return '(' . implode(' || ', $parts) . ')';

            case '$toLower':
                $field = $this->resolveFieldExpression($value);
                return "LOWER({$field})";

            case '$toUpper':
                $field = $this->resolveFieldExpression($value);
                return "UPPER({$field})";

            case '$ifNull':
                $field = $this->resolveFieldExpression($value[0]);
                $default = is_string($value[1]) && str_starts_with($value[1], '$')
                    ? $this->resolveFieldExpression($value[1])
                    : $this->quoteLiteral($value[1]);
                return "COALESCE({$field}, {$default})";

            default:
                throw new \Exception("Unsupported projection operator: {$op}");
        }
    }

    /**
     * Apply $unwind stage
     */
    protected function applyUnwind(Context $context, mixed $unwind): void {
        $path = is_string($unwind) ? $unwind : ($unwind['path'] ?? null);
        $preserveNull = $unwind['preserveNullAndEmptyArrays'] ?? false;

        if (!$path || !str_starts_with($path, '$')) {
            throw new \Exception('Invalid $unwind path');
        }

        $field = substr($path, 1);
        $jsonPath = $this->toJsonExtractRaw($field); // Use raw, not checking unwound

        $unwindAlias = 'unwound_' . count($context->unwinds);
        $context->addUnwind($field, $jsonPath, $preserveNull);

        // Track that this field is now unwound - subsequent references should use the unwound value
        $this->unwoundFields[$field] = "{$unwindAlias}.value";
    }

    /**
     * Apply $addFields stage
     */
    protected function applyAddFields(Context $context, array $fields): void {
        foreach ($fields as $field => $expr) {
            if (is_string($expr) && str_starts_with($expr, '$')) {
                $refField = substr($expr, 1);
                $context->addField($field, $this->toJsonExtract($refField));
            } elseif (is_scalar($expr)) {
                $context->addField($field, $this->quoteLiteral($expr));
            }
        }
    }

    /**
     * Resolve a field expression to SQL
     */
    protected function resolveFieldExpression(mixed $expr): string {
        if (is_string($expr) && str_starts_with($expr, '$')) {
            return $this->toJsonExtract(substr($expr, 1));
        }
        return $this->quoteLiteral($expr);
    }

    /**
     * Convert field name to SQLite json_extract (checks for unwound fields first)
     * e.g., "user.profile.name" → json_extract(document, '$.user.profile.name')
     */
    protected function toJsonExtract(string $field): string {
        // Check if this field has been unwound
        if (isset($this->unwoundFields[$field])) {
            return $this->unwoundFields[$field];
        }
        return $this->toJsonExtractRaw($field);
    }

    /**
     * Convert field name to SQLite json_extract (raw, no unwound check)
     */
    protected function toJsonExtractRaw(string $field): string {
        $path = '$.' . $this->escapeJsonPath($field);
        return "json_extract(document, '{$path}')";
    }

    /**
     * Convert field to numeric json_extract for aggregation
     */
    protected function toJsonExtractNumeric(string $field): string {
        // Check if this field has been unwound
        if (isset($this->unwoundFields[$field])) {
            return "CAST({$this->unwoundFields[$field]} AS REAL)";
        }
        $path = '$.' . $this->escapeJsonPath($field);
        return "CAST(json_extract(document, '{$path}') AS REAL)";
    }

    /**
     * Escape a field name for use inside a single-quoted SQL JSON path.
     * Matches the escaping in Query\Optimizer::toJsonPath().
     */
    protected function escapeJsonPath(string $field): string {
        return str_replace(['\\', "'"], ['\\\\', "''"], $field);
    }

    /**
     * Escape a field name for use as a double-quoted SQL identifier.
     */
    protected function escapeIdentifier(string $name): string {
        return str_replace('"', '""', $name);
    }

    /**
     * Quote a literal value
     */
    protected function quoteLiteral(mixed $value): string {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        return $this->connection->quote((string)$value);
    }
}


/**
 * Context for building aggregation queries
 */
class Context {

    public string $tableName;
    public array $whereConditions = [];
    public array $selectFields = [];
    public array $selectAliases = [];
    public ?string $groupBy = null;
    public ?string $orderBy = null;
    public ?int $limit = null;
    public ?int $offset = null;
    public bool $isGrouped = false;
    public bool $isCount = false;
    public array $projection = [];
    public bool $projectIncludeId = true;
    public array $unwinds = [];
    public array $addedFields = [];
    public array $unwoundFields = []; // Track fields that have been unwound

    public function __construct(string $tableName) {
        $this->tableName = $tableName;
    }

    public function addWhere(string $condition): void {
        $this->whereConditions[] = $condition;
    }

    public function addSelect(string $select): void {
        // Extract alias for later reference
        if (preg_match('/as\s+"([^"]+)"$/i', $select, $m)) {
            $this->selectAliases[$m[1]] = true;
        }
        $this->selectFields[] = $select;
    }

    public function addUnwind(string $field, string $jsonPath, bool $preserveNull): void {
        $index = count($this->unwinds);
        $this->unwinds[] = [
            'field' => $field,
            'jsonPath' => $jsonPath,
            'preserveNull' => $preserveNull,
            'alias' => "unwound_{$index}"
        ];
        // Track that this field has been unwound
        $this->unwoundFields[$field] = "unwound_{$index}.value";
    }

    /**
     * Check if a field has been unwound and get its expression
     */
    public function getUnwoundFieldExpr(string $field): ?string {
        return $this->unwoundFields[$field] ?? null;
    }

    public function addField(string $name, string $expression): void {
        $this->addedFields[$name] = $expression;
    }

    public function toSQL(PDO $connection): string {
        $quotedTable = "`{$this->tableName}`";

        // Handle $unwind with json_each
        $fromClause = $quotedTable;
        $selectDoc = 'document';

        if (!empty($this->unwinds)) {
            foreach ($this->unwinds as $i => $unwind) {
                $alias = "unwound_{$i}";
                $joinType = $unwind['preserveNull'] ? 'LEFT JOIN' : 'JOIN';

                $field = $unwind['field'];
                $jsonPath = $unwind['jsonPath'];

                // Use json_each to expand array
                $fromClause .= ", json_each({$jsonPath}) AS {$alias}";

                // Update document to include unwound value
                $safeField = str_replace(['\\', "'"], ['\\\\', "''"], $field);
                $selectDoc = "json_set(document, '\$.{$safeField}', {$alias}.value)";
            }
        }

        // Handle $addFields
        if (!empty($this->addedFields)) {
            foreach ($this->addedFields as $name => $expr) {
                $safeName = str_replace(['\\', "'"], ['\\\\', "''"], $name);
                $selectDoc = "json_set({$selectDoc}, '\$.{$safeName}', {$expr})";
            }
        }

        // Build SELECT clause
        if ($this->isCount) {
            $selectClause = implode(', ', $this->selectFields);
        } elseif ($this->isGrouped) {
            $selectClause = implode(', ', $this->selectFields);
        } elseif (!empty($this->projection)) {
            // Build projected document using json_object
            $projParts = [];
            if ($this->projectIncludeId) {
                $projParts[] = "'_id', json_extract(document, '\$._id')";
            }
            foreach ($this->projection as $field => $expr) {
                $safeField = str_replace(['\\', "'"], ['\\\\', "''"], $field);
                $projParts[] = "'{$safeField}', {$expr}";
            }
            $selectClause = "json_object(" . implode(', ', $projParts) . ") as document";
        } else {
            $selectClause = "{$selectDoc} as document";
        }

        // Build query
        $sql = "SELECT {$selectClause} FROM {$fromClause}";

        // WHERE
        if (!empty($this->whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $this->whereConditions);
        }

        // GROUP BY
        if ($this->isGrouped && $this->groupBy !== null) {
            $sql .= " GROUP BY {$this->groupBy}";
        }

        // ORDER BY
        if ($this->orderBy) {
            $sql .= " ORDER BY {$this->orderBy}";
        }

        // LIMIT
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        // OFFSET
        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }
}
