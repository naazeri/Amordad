<?php

namespace MongoLite\Aggregation;

/**
 * Iterator for DocumentSource
 *
 * Provides iteration over pre-computed documents from native SQLite aggregation.
 */
class DocumentIterator implements \Iterator {

    protected array $documents;
    protected int $position = 0;

    public function __construct(array $documents) {
        $this->documents = \array_values($documents);
    }

    public function toArray(): array {
        return $this->documents;
    }

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
}
