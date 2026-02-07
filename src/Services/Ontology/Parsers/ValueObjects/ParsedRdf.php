<?php

namespace App\Services\Ontology\Parsers\ValueObjects;

use EasyRdf\Graph;

/**
 * Value object representing parsed RDF data
 */
class ParsedRdf
{
    public function __construct(
        public readonly Graph $graph,
        public readonly string $format,
        public readonly string $rawContent,
        public readonly array $metadata = []
    ) {}

    /**
     * Get the number of resources in the graph
     */
    public function getResourceCount(): int
    {
        return count($this->graph->resources());
    }

    /**
     * Check if the graph is empty
     */
    public function isEmpty(): bool
    {
        return $this->getResourceCount() === 0;
    }

    /**
     * Get all resources from the graph
     */
    public function getResources(): array
    {
        return $this->graph->resources();
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'format' => $this->format,
            'resource_count' => $this->getResourceCount(),
            'metadata' => $this->metadata,
        ];
    }
}
