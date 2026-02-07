<?php

namespace App\Services\Ontology\Parsers\Contracts;

use App\Services\Ontology\Parsers\ValueObjects\ParsedRdf;

/**
 * Interface for RDF format-specific handlers
 */
interface RdfFormatHandlerInterface
{
    /**
     * Check if this handler can parse the given content
     */
    public function canHandle(string $content): bool;

    /**
     * Parse RDF content and return a ParsedRdf value object
     *
     * @throws \App\Services\Ontology\Exceptions\OntologyImportException
     */
    public function parse(string $content): ParsedRdf;

    /**
     * Get the format name this handler supports
     */
    public function getFormatName(): string;
}
