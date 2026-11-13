<?php

namespace MongoLite\Aggregation;

/**
 * Document source adapter for partial aggregation optimization
 *
 * This class wraps pre-computed documents from SQLite so they can be
 * passed to MongoLite\Aggregation\Cursor for remaining pipeline stages.
 */
class DocumentSource {

    protected array $documents;

    public function __construct(array $documents) {
        $this->documents = $documents;
    }

    /**
     * Find method for MongoLite Aggregation compatibility
     *
     * @param mixed $criteria Not used - documents are pre-filtered
     * @return DocumentIterator
     */
    public function find(mixed $criteria = null): DocumentIterator {
        return new DocumentIterator($this->documents);
    }
}
