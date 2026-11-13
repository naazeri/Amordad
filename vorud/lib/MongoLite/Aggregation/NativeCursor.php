<?php

namespace MongoLite\Aggregation;

/**
 * Cursor for native SQLite aggregation results
 *
 * This cursor wraps pre-computed aggregation results from SQLite.
 * It implements the same interface as MongoLite\Aggregation\Cursor for compatibility.
 */
class NativeCursor implements \Iterator, \Countable {

    protected array $documents;
    protected int $position = 0;

    /**
     * Constructor
     *
     * @param array $documents Pre-computed aggregation results
     */
    public function __construct(array $documents) {
        $this->documents = \array_values($documents);
    }

    /**
     * Get all documents as array
     *
     * @return array
     */
    public function toArray(): array {
        return $this->documents;
    }

    /**
     * Count results
     *
     * @return int
     */
    public function count(): int {
        return \count($this->documents);
    }

    // Iterator implementation

    public function current(): mixed {
        return $this->documents[$this->position] ?? null;
    }

    public function key(): int {
        return $this->position;
    }

    public function next(): void {
        $this->position++;
    }

    public function rewind(): void {
        $this->position = 0;
    }

    public function valid(): bool {
        return isset($this->documents[$this->position]);
    }

    /**
     * Array access for indexed retrieval
     */
    public function offsetExists(mixed $offset): bool {
        return isset($this->documents[$offset]);
    }

    public function offsetGet(mixed $offset): mixed {
        return $this->documents[$offset] ?? null;
    }
}
