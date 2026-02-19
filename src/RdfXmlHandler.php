<?php

declare(strict_types=1);

namespace Youri\vandenBogert\Software\ParserRdfXml;

use EasyRdf\Graph;
use Youri\vandenBogert\Software\ParserCore\Contracts\RdfFormatHandlerInterface;
use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;

/**
 * Handler for RDF/XML format parsing.
 *
 * Includes fallback SimpleXML parser for better property extraction.
 * Detects and parses RDF/XML content, returning a ParsedRdf value object.
 * Registers xmlns prefix declarations in EasyRdf's global namespace
 * registry before parsing.
 */
final class RdfXmlHandler implements RdfFormatHandlerInterface
{
    public function canHandle(string $content): bool
    {
        $trimmed = ltrim($content);

        return str_starts_with($trimmed, '<?xml') || str_contains($trimmed, '<rdf:RDF');
    }

    public function parse(string $content): ParsedRdf
    {
        try {
            // Extract and register xmlns prefixes from content BEFORE parsing
            $this->registerPrefixesFromContent($content);

            // Guard against servers returning HTML pages despite RDF/XML content-type
            if (! $this->looksLikeXml($content) || $this->looksLikeHtml($content)) {
                throw new ParseException('Content does not appear to be valid RDF/XML');
            }

            // Use fallback parser for better property extraction
            return $this->parseWithFallback($content);

        } catch (\Throwable $e) {
            throw new ParseException('RDF/XML parsing failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Register xmlns prefixes from RDF/XML content.
     */
    private function registerPrefixesFromContent(string $content): void
    {
        // Extract xmlns declarations from content
        if (preg_match_all('/xmlns:([^=]+)="([^"]+)"/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $prefix = trim($match[1]);
                $namespace = trim($match[2]);
                if ($prefix !== '' && $namespace !== '') {
                    \EasyRdf\RdfNamespace::set($prefix, $namespace);
                }
            }
        }
    }

    public function getFormatName(): string
    {
        return 'rdf/xml';
    }

    private function looksLikeXml(string $content): bool
    {
        $trimmed = ltrim($content);
        if (! str_starts_with($trimmed, '<')) {
            return false;
        }

        return str_starts_with($trimmed, '<?xml') || str_contains($trimmed, '<rdf:RDF');
    }

    private function looksLikeHtml(string $content): bool
    {
        $head = strtolower(substr(ltrim($content), 0, 1024));

        return str_contains($head, '<!doctype html') || str_contains($head, '<html');
    }

    /**
     * Parse RDF/XML using SimpleXML fallback for better property extraction.
     *
     * This method stores the parsed SimpleXML element in metadata for later use
     * by extractors, while also creating an EasyRdf Graph for standard extraction.
     */
    private function parseWithFallback(string $content): ParsedRdf
    {
        // Parse XML content using SimpleXML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        $xmlErrors = libxml_get_errors();
        libxml_clear_errors();

        if ($xml === false) {
            $errorMessages = array_map(fn (\LibXMLError $error): string => trim($error->message), $xmlErrors);
            throw new ParseException('Invalid RDF/XML content: ' . implode(', ', $errorMessages));
        }

        // Register common namespaces for XPath
        $this->registerCommonNamespaces($xml);

        // Also create an EasyRdf Graph for compatibility
        $graph = new Graph();
        $usedEasyRdf = false;

        try {
            // Try to parse with EasyRdf, but don't fail if it doesn't work
            // EasyRdf has some compatibility issues with modern PHP versions
            @$graph->parse($content, 'rdfxml');
            $usedEasyRdf = true;
        } catch (\Throwable $e) {
            // If EasyRdf fails, continue with SimpleXML parsing only
            // The graph will be empty but extractors can use the XML element
            // This is expected for some RDF/XML files on PHP 8+
        }

        $metadata = [
            'parser' => 'rdf_xml_handler',
            'format' => 'rdf/xml',
            'used_easyrdf' => $usedEasyRdf,
            'xml_element' => $xml, // Store for extractors to use
        ];

        return new ParsedRdf(
            graph: $graph,
            format: 'rdf/xml',
            rawContent: $content,
            metadata: $metadata,
        );
    }

    /**
     * Register common RDF namespaces for XPath queries.
     */
    private function registerCommonNamespaces(\SimpleXMLElement $xml): void
    {
        $namespaces = [
            'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'owl' => 'http://www.w3.org/2002/07/owl#',
            'foaf' => 'http://xmlns.com/foaf/0.1/',
            'skos' => 'http://www.w3.org/2004/02/skos/core#',
            'dc' => 'http://purl.org/dc/elements/1.1/',
            'dcterms' => 'http://purl.org/dc/terms/',
            'sh' => 'http://www.w3.org/ns/shacl#',
            'xsd' => 'http://www.w3.org/2001/XMLSchema#',
        ];

        foreach ($namespaces as $prefix => $uri) {
            @$xml->registerXPathNamespace($prefix, $uri);
        }
    }
}
