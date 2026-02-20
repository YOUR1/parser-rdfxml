<?php

declare(strict_types=1);

namespace Youri\vandenBogert\Software\ParserRdfXml;

use EasyRdf\Graph;
use EasyRdf\Literal;
use EasyRdf\Resource;
use Youri\vandenBogert\Software\ParserCore\Contracts\RdfFormatHandlerInterface;
use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;

/**
 * Handler for RDF/XML format parsing.
 *
 * Implements a complete RDF/XML parser using SimpleXML with support for:
 * - xml:base URI resolution (RFC 3986)
 * - rdf:about, rdf:resource, rdf:ID, rdf:nodeID attributes
 * - rdf:parseType (Literal, Resource, Collection)
 * - rdf:Bag, rdf:Seq, rdf:Alt containers with rdf:li auto-numbering
 * - Typed and language-tagged literals
 * - Reification via rdf:ID on property elements
 *
 * Falls back to EasyRdf on PHP versions where it works (< 8.4).
 * Registers xmlns prefix declarations in EasyRdf's global namespace
 * registry before parsing.
 */
final class RdfXmlHandler implements RdfFormatHandlerInterface
{
    private const RDF_NS = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

    private const XML_NS = 'http://www.w3.org/XML/1998/namespace';

    private int $blankNodeCounter = 0;

    /**
     * Track used rdf:ID resolved URIs for duplicate detection within a document.
     *
     * Per the RDF/XML specification, two rdf:ID values that resolve to the same
     * URI (after xml:base resolution) are duplicates and must be rejected.
     * Two rdf:ID values that happen to be the same string but resolve to different
     * URIs (due to different xml:base contexts) are allowed.
     *
     * @var array<string, true>
     */
    private array $usedRdfIds = [];

    /**
     * Core syntax terms forbidden as node element names.
     * Per RDF/XML Spec Section 7.2.11 (nodeElementURIs).
     */
    private const FORBIDDEN_NODE_ELEMENT_NAMES = [
        'RDF',
        'ID',
        'about',
        'bagID',
        'parseType',
        'resource',
        'nodeID',
        'datatype',
        'li',
        'aboutEach',
        'aboutEachPrefix',
    ];

    /**
     * Core syntax terms forbidden as property element names.
     * Per RDF/XML Spec Section 7.2.12 (propertyElementURIs).
     */
    private const FORBIDDEN_PROPERTY_ELEMENT_NAMES = [
        'Description',
        'RDF',
        'ID',
        'about',
        'bagID',
        'parseType',
        'resource',
        'nodeID',
        'datatype',
        'aboutEach',
        'aboutEachPrefix',
    ];

    public function canHandle(string $content): bool
    {
        $trimmed = ltrim($content);

        if (str_starts_with($trimmed, '<?xml') || str_contains($trimmed, '<rdf:RDF')) {
            return true;
        }

        // Detect bare <RDF> root element with RDF namespace as default namespace
        if (str_contains($trimmed, '<RDF') && str_contains($trimmed, self::RDF_NS)) {
            return true;
        }

        return false;
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

        if (str_starts_with($trimmed, '<?xml') || str_contains($trimmed, '<rdf:RDF')) {
            return true;
        }

        // Detect bare <RDF> with RDF namespace as default namespace
        if (str_contains($trimmed, '<RDF') && str_contains($trimmed, self::RDF_NS)) {
            return true;
        }

        return false;
    }

    private function looksLikeHtml(string $content): bool
    {
        $head = strtolower(substr(ltrim($content), 0, 1024));

        return str_contains($head, '<!doctype html') || str_contains($head, '<html');
    }

    /**
     * Parse RDF/XML using SimpleXML with native triple extraction.
     *
     * This method parses the RDF/XML content using SimpleXML, extracts triples
     * into an EasyRdf Graph, and stores the parsed SimpleXML element in metadata
     * for later use by extractors.
     */
    private function parseWithFallback(string $content): ParsedRdf
    {
        // Parse XML content using SimpleXML with security protections
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NONET);
        $xmlErrors = libxml_get_errors();
        libxml_clear_errors();

        if ($xml === false) {
            $errorMessages = array_map(fn (\LibXMLError $error): string => trim($error->message), $xmlErrors);
            throw new ParseException('Invalid RDF/XML content: ' . implode(', ', $errorMessages));
        }

        // Register common namespaces for XPath
        $this->registerCommonNamespaces($xml);

        // Create EasyRdf Graph
        $graph = new Graph();
        $usedEasyRdf = false;

        // First try EasyRdf (works on PHP < 8.4)
        try {
            $previousHandler = set_error_handler(static function (int $errno, string $errstr): bool {
                if ($errno === E_DEPRECATED && str_contains($errstr, 'xml_set_')) {
                    return true;
                }

                return false;
            });

            try {
                $graph->parse($content, 'rdfxml');
                $usedEasyRdf = true;
            } finally {
                restore_error_handler();
            }
        } catch (\Throwable) {
            // EasyRdf failed (PHP 8.4+ ValueError), use SimpleXML extraction
        }

        // If EasyRdf failed, extract triples from SimpleXML
        if (! $usedEasyRdf) {
            $this->blankNodeCounter = 0;
            $this->extractTriplesFromXml($xml, $graph);
        }

        $metadata = [
            'parser' => 'rdf_xml_handler',
            'format' => 'rdf/xml',
            'used_easyrdf' => $usedEasyRdf,
            'xml_element' => $xml,
        ];

        return new ParsedRdf(
            graph: $graph,
            format: 'rdf/xml',
            rawContent: $content,
            metadata: $metadata,
        );
    }

    /**
     * Extract RDF triples from a SimpleXML element into an EasyRdf Graph.
     *
     * Only extracts triples if the root element is an RDF container element
     * (rdf:RDF or bare RDF with the RDF namespace as default namespace).
     * Non-RDF XML documents are left as-is with an empty graph.
     */
    private function extractTriplesFromXml(\SimpleXMLElement $xml, Graph $graph): void
    {
        // Reset document-scoped state
        $this->usedRdfIds = [];

        // Determine base URI from xml:base on root element
        $baseUri = $this->getXmlBase($xml) ?? '';

        // Get the RDF namespace to check if root is rdf:RDF
        $rootName = $this->getExpandedName($xml);

        if ($rootName === self::RDF_NS . 'RDF') {
            // Standard rdf:RDF wrapper -- process children as node elements
            $children = $this->getAllChildren($xml);
            foreach ($children as $child) {
                $this->processNodeElement($child, $graph, $baseUri);
            }
        }
        // If root is not rdf:RDF but contains the RDF namespace, try processing as node element
        // Otherwise, this is not RDF/XML -- leave the graph empty
    }

    /**
     * Process a node element (rdf:Description, typed nodes, etc.).
     *
     * @return string The subject URI or blank node identifier
     */
    private function processNodeElement(\SimpleXMLElement $element, Graph $graph, string $parentBase): string
    {
        // Validate node element name
        $this->validateNodeElementName($element);

        // Validate no rdf:li attribute
        $this->validateNoLiAttribute($element);

        // Get RDF attributes and validate
        $rdfAttrs = $this->getRdfAttributes($element);
        $this->validateNoDeprecatedAttributes($rdfAttrs);
        $this->validateNoConflictingNodeAttributes($rdfAttrs);

        // Validate rdf:ID and rdf:nodeID NCName values
        if (isset($rdfAttrs['ID'])) {
            $this->validateRdfId($rdfAttrs['ID']);
        }
        if (isset($rdfAttrs['nodeID'])) {
            $this->validateRdfNodeId($rdfAttrs['nodeID']);
        }

        // Resolve xml:base for this element
        $elementBase = $this->resolveElementBase($element, $parentBase);

        // Track rdf:ID resolved URI for duplicate detection (must happen after base resolution)
        if (isset($rdfAttrs['ID'])) {
            $resolvedUri = $this->resolveUri('#' . $rdfAttrs['ID'], $elementBase);
            $this->trackRdfIdUri($rdfAttrs['ID'], $resolvedUri);
        }

        // Determine subject
        $subject = $this->determineSubject($element, $elementBase);

        // Get the element's expanded name for rdf:type
        $elementName = $this->getExpandedName($element);
        if ($elementName !== self::RDF_NS . 'Description') {
            // Typed node element: the element name is the rdf:type
            $graph->addResource($subject, 'rdf:type', $elementName);
        }

        // Process property attributes on the node element
        // Non-RDF, non-XML attributes are treated as property-value pairs with literal objects
        $propertyAttrs = $this->getNonRdfAttributes($element);
        foreach ($propertyAttrs as $attr) {
            $graph->addLiteral($subject, $attr['uri'], $attr['value']);
        }

        // Process property elements (children)
        $this->processPropertyElements($element, $graph, $subject, $elementBase);

        return $subject;
    }

    /**
     * Process property elements (children of a node element).
     */
    private function processPropertyElements(\SimpleXMLElement $nodeElement, Graph $graph, string $subject, string $baseUri): void
    {
        $children = $this->getAllChildren($nodeElement);
        $liCounter = 1;

        foreach ($children as $propElement) {
            $this->processPropertyElement($propElement, $graph, $subject, $baseUri, $liCounter);
        }
    }

    /**
     * Process a single property element.
     */
    private function processPropertyElement(
        \SimpleXMLElement $propElement,
        Graph $graph,
        string $subject,
        string $baseUri,
        int &$liCounter,
    ): void {
        // Validate property element name
        $this->validatePropertyElementName($propElement);

        $propBase = $this->resolveElementBase($propElement, $baseUri);
        $propertyName = $this->getExpandedName($propElement);

        // Handle rdf:li auto-numbering
        if ($propertyName === self::RDF_NS . 'li') {
            $propertyName = self::RDF_NS . '_' . $liCounter;
            $liCounter++;
        }

        // Get RDF attributes and validate
        $rdfAttrs = $this->getRdfAttributes($propElement);
        $this->validateNoDeprecatedAttributes($rdfAttrs);
        $this->validateNoConflictingPropertyAttributes($rdfAttrs);

        // Validate rdf:ID NCName on property elements
        if (isset($rdfAttrs['ID'])) {
            $this->validateRdfId($rdfAttrs['ID']);
            $resolvedUri = $this->resolveUri('#' . $rdfAttrs['ID'], $propBase);
            $this->trackRdfIdUri($rdfAttrs['ID'], $resolvedUri);
        }

        // Validate rdf:nodeID NCName on property elements
        if (isset($rdfAttrs['nodeID'])) {
            $this->validateRdfNodeId($rdfAttrs['nodeID']);
        }

        // Handle rdf:parseType
        $parseType = $rdfAttrs['parseType'] ?? null;
        if ($parseType !== null) {
            $this->processParseType($propElement, $graph, $subject, $propertyName, $parseType, $propBase);

            return;
        }

        // Handle rdf:resource attribute (object is a resource)
        if (isset($rdfAttrs['resource'])) {
            $objectUri = $this->resolveUri($rdfAttrs['resource'], $propBase);
            $graph->addResource($subject, $propertyName, $objectUri);
            $this->handlePropertyReification($propElement, $graph, $subject, $propertyName, $objectUri, $propBase, false);

            return;
        }

        // Handle rdf:nodeID attribute (object is a blank node)
        if (isset($rdfAttrs['nodeID'])) {
            $blankNode = '_:' . $rdfAttrs['nodeID'];
            $graph->addResource($subject, $propertyName, $blankNode);
            $this->handlePropertyReification($propElement, $graph, $subject, $propertyName, $blankNode, $propBase, false);

            return;
        }

        // Check for child elements (nested node element)
        $children = $this->getAllChildren($propElement);
        if ($children !== []) {
            // First child is a node element
            $childSubject = $this->processNodeElement($children[0], $graph, $propBase);
            $graph->addResource($subject, $propertyName, $childSubject);
            $this->handlePropertyReification($propElement, $graph, $subject, $propertyName, $childSubject, $propBase, false);

            return;
        }

        // Otherwise it's a literal value
        $value = (string) $propElement;

        // Check for rdf:datatype
        $datatype = $rdfAttrs['datatype'] ?? null;
        if ($datatype !== null) {
            $datatype = $this->resolveUri($datatype, $propBase);
        }

        // Check for xml:lang
        $lang = $this->getXmlLang($propElement);

        if ($lang !== null) {
            $graph->addLiteral($subject, $propertyName, new Literal($value, $lang));
        } elseif ($datatype !== null) {
            $graph->addLiteral($subject, $propertyName, new Literal($value, null, $datatype));
        } else {
            $graph->addLiteral($subject, $propertyName, $value);
        }

        $this->handlePropertyReification($propElement, $graph, $subject, $propertyName, $value, $propBase, true);
    }

    /**
     * Handle reification when rdf:ID is present on a property element.
     */
    private function handlePropertyReification(
        \SimpleXMLElement $propElement,
        Graph $graph,
        string $subject,
        string $propertyName,
        string $object,
        string $baseUri,
        bool $isLiteral,
    ): void {
        $rdfAttrs = $this->getRdfAttributes($propElement);
        if (! isset($rdfAttrs['ID'])) {
            return;
        }

        $statementUri = $this->resolveUri('#' . $rdfAttrs['ID'], $baseUri);

        $graph->addResource($statementUri, 'rdf:type', self::RDF_NS . 'Statement');
        $graph->addResource($statementUri, self::RDF_NS . 'subject', $subject);
        $graph->addResource($statementUri, self::RDF_NS . 'predicate', $propertyName);

        if ($isLiteral) {
            $graph->addLiteral($statementUri, self::RDF_NS . 'object', $object);
        } else {
            $graph->addResource($statementUri, self::RDF_NS . 'object', $object);
        }
    }

    /**
     * Process rdf:parseType attribute.
     */
    private function processParseType(
        \SimpleXMLElement $propElement,
        Graph $graph,
        string $subject,
        string $propertyName,
        string $parseType,
        string $baseUri,
    ): void {
        switch ($parseType) {
            case 'Resource':
                $blankNode = $this->generateBlankNodeId();
                $graph->addResource($subject, $propertyName, $blankNode);
                $this->processPropertyElements($propElement, $graph, $blankNode, $baseUri);
                break;

            case 'Collection':
                $this->processCollection($propElement, $graph, $subject, $propertyName, $baseUri);
                break;

            case 'Literal':
                $xmlContent = $this->getInnerXml($propElement);
                $graph->addLiteral(
                    $subject,
                    $propertyName,
                    new Literal($xmlContent, null, self::RDF_NS . 'XMLLiteral')
                );
                break;

            default:
                // Unknown parseType: treat as Literal per spec
                $xmlContent = $this->getInnerXml($propElement);
                $graph->addLiteral(
                    $subject,
                    $propertyName,
                    new Literal($xmlContent, null, self::RDF_NS . 'XMLLiteral')
                );
        }
    }

    /**
     * Process rdf:parseType="Collection".
     */
    private function processCollection(
        \SimpleXMLElement $propElement,
        Graph $graph,
        string $subject,
        string $propertyName,
        string $baseUri,
    ): void {
        $children = $this->getAllChildren($propElement);

        if ($children === []) {
            $graph->addResource($subject, $propertyName, self::RDF_NS . 'nil');

            return;
        }

        $firstNode = $this->generateBlankNodeId();
        $graph->addResource($subject, $propertyName, $firstNode);

        $currentNode = $firstNode;
        $lastIndex = count($children) - 1;

        foreach ($children as $index => $child) {
            $childSubject = $this->processNodeElement($child, $graph, $baseUri);
            $graph->addResource($currentNode, self::RDF_NS . 'first', $childSubject);

            if ($index < $lastIndex) {
                $nextNode = $this->generateBlankNodeId();
                $graph->addResource($currentNode, self::RDF_NS . 'rest', $nextNode);
                $currentNode = $nextNode;
            } else {
                $graph->addResource($currentNode, self::RDF_NS . 'rest', self::RDF_NS . 'nil');
            }
        }
    }

    /**
     * Determine the subject URI of a node element.
     */
    private function determineSubject(\SimpleXMLElement $element, string $baseUri): string
    {
        $rdfAttrs = $this->getRdfAttributes($element);

        // rdf:about takes precedence
        if (isset($rdfAttrs['about'])) {
            return $this->resolveUri($rdfAttrs['about'], $baseUri);
        }

        // rdf:ID creates a fragment URI
        if (isset($rdfAttrs['ID'])) {
            $id = $rdfAttrs['ID'];
            $uri = $this->resolveUri('#' . $id, $baseUri);

            return $uri;
        }

        // rdf:nodeID creates a document-scoped blank node
        if (isset($rdfAttrs['nodeID'])) {
            return '_:' . $rdfAttrs['nodeID'];
        }

        // Anonymous blank node
        return $this->generateBlankNodeId();
    }

    /**
     * Get the expanded name (namespace URI + local name) of an element.
     */
    private function getExpandedName(\SimpleXMLElement $element): string
    {
        $dom = dom_import_simplexml($element);

        return ($dom->namespaceURI ?? '') . $dom->localName;
    }

    /**
     * Get RDF-specific attributes from an element.
     *
     * @return array<string, string>
     */
    private function getRdfAttributes(\SimpleXMLElement $element): array
    {
        $attrs = [];

        // Get attributes from the RDF namespace
        $rdfAttributes = $element->attributes(self::RDF_NS);
        if ($rdfAttributes !== null) {
            foreach ($rdfAttributes as $name => $value) {
                $attrs[(string) $name] = (string) $value;
            }
        }

        // Also check non-namespaced attributes (some RDF/XML uses unqualified rdf attributes)
        $plainAttributes = $element->attributes();
        if ($plainAttributes !== null) {
            foreach ($plainAttributes as $name => $value) {
                $nameStr = (string) $name;
                // Only pick up rdf-specific attributes that weren't already namespaced
                if (in_array($nameStr, ['about', 'resource', 'ID', 'nodeID', 'parseType', 'datatype', 'bagID', 'aboutEach', 'aboutEachPrefix'], true) && ! isset($attrs[$nameStr])) {
                    $attrs[$nameStr] = (string) $value;
                }
            }
        }

        return $attrs;
    }

    /**
     * Get all non-RDF attributes from an element (property attributes on node elements).
     *
     * @return list<array{uri: string, value: string}>
     */
    private function getNonRdfAttributes(\SimpleXMLElement $element): array
    {
        $attrs = [];
        // Use getNamespaces(true) to include inherited namespaces
        $namespaces = $element->getNamespaces(true);

        foreach ($namespaces as $prefix => $nsUri) {
            if ($prefix === '' || $nsUri === self::RDF_NS || $nsUri === self::XML_NS) {
                continue;
            }

            $nsAttrs = $element->attributes($nsUri);
            if ($nsAttrs !== null) {
                foreach ($nsAttrs as $name => $value) {
                    $attrs[] = ['uri' => $nsUri . $name, 'value' => (string) $value];
                }
            }
        }

        return $attrs;
    }

    /**
     * Get xml:base value from an element.
     */
    private function getXmlBase(\SimpleXMLElement $element): ?string
    {
        $xmlAttrs = $element->attributes(self::XML_NS);
        if ($xmlAttrs !== null) {
            $base = $xmlAttrs['base'];
            if ($base !== null) {
                return (string) $base;
            }
        }

        return null;
    }

    /**
     * Get xml:lang value from an element, checking ancestors if needed.
     */
    private function getXmlLang(\SimpleXMLElement $element): ?string
    {
        $xmlAttrs = $element->attributes(self::XML_NS);
        if ($xmlAttrs !== null) {
            $lang = $xmlAttrs['lang'];
            if ($lang !== null) {
                $langStr = (string) $lang;

                return $langStr !== '' ? $langStr : null;
            }
        }

        return null;
    }

    /**
     * Resolve xml:base for an element, inheriting from parent if needed.
     */
    private function resolveElementBase(\SimpleXMLElement $element, string $parentBase): string
    {
        $elementBase = $this->getXmlBase($element);
        if ($elementBase !== null) {
            return $this->resolveUri($elementBase, $parentBase);
        }

        return $parentBase;
    }

    /**
     * Resolve a URI reference against a base URI per RFC 3986.
     */
    private function resolveUri(string $reference, string $base): string
    {
        // Empty reference returns the base URI (stripped of fragment)
        if ($reference === '') {
            $fragmentPos = strpos($base, '#');

            return $fragmentPos !== false ? substr($base, 0, $fragmentPos) : $base;
        }

        // Already absolute
        if (str_contains($reference, '://')) {
            return $reference;
        }

        // Parse the base URI
        $baseParts = parse_url($base);
        if ($baseParts === false) {
            return $reference;
        }

        $scheme = $baseParts['scheme'] ?? '';
        $authority = '';
        if (isset($baseParts['host'])) {
            $authority = $baseParts['host'];
            if (isset($baseParts['port'])) {
                $authority .= ':' . $baseParts['port'];
            }
            if (isset($baseParts['user'])) {
                $authority = $baseParts['user'] . '@' . $authority;
            }
        }
        $basePath = $baseParts['path'] ?? '';

        // Fragment-only reference
        if (str_starts_with($reference, '#')) {
            $baseWithoutFragment = $scheme . '://' . $authority . $basePath;

            return $baseWithoutFragment . $reference;
        }

        // Network path reference (//authority/path)
        if (str_starts_with($reference, '//')) {
            return $scheme . ':' . $reference;
        }

        // Absolute path reference (/path)
        if (str_starts_with($reference, '/')) {
            return $scheme . '://' . $authority . $reference;
        }

        // Relative path reference
        // Remove the last segment from the base path
        $lastSlash = strrpos($basePath, '/');
        if ($lastSlash !== false) {
            $mergedPath = substr($basePath, 0, $lastSlash + 1) . $reference;
        } else {
            $mergedPath = '/' . $reference;
        }

        // Resolve . and .. segments
        $mergedPath = $this->removeDotSegments($mergedPath);

        return $scheme . '://' . $authority . $mergedPath;
    }

    /**
     * Remove dot segments from a path per RFC 3986 Section 5.2.4.
     */
    private function removeDotSegments(string $path): string
    {
        $input = $path;
        $output = '';

        while ($input !== '') {
            // A: If the input buffer begins with a prefix of "../" or "./"
            if (str_starts_with($input, '../')) {
                $input = substr($input, 3);
            } elseif (str_starts_with($input, './')) {
                $input = substr($input, 2);
            }
            // B: If the input buffer begins with a prefix of "/./" or "/."
            elseif (str_starts_with($input, '/./')) {
                $input = '/' . substr($input, 3);
            } elseif ($input === '/.') {
                $input = '/';
            }
            // C: If the input buffer begins with a prefix of "/../" or "/.."
            elseif (str_starts_with($input, '/../')) {
                $input = '/' . substr($input, 4);
                $lastSlash = strrpos($output, '/');
                $output = $lastSlash !== false ? substr($output, 0, $lastSlash) : '';
            } elseif ($input === '/..') {
                $input = '/';
                $lastSlash = strrpos($output, '/');
                $output = $lastSlash !== false ? substr($output, 0, $lastSlash) : '';
            }
            // D: if the input buffer consists only of "." or ".."
            elseif ($input === '.' || $input === '..') {
                $input = '';
            }
            // E: move the first path segment (including initial "/" if any) to output
            else {
                if (str_starts_with($input, '/')) {
                    $segEnd = strpos($input, '/', 1);
                    if ($segEnd === false) {
                        $segEnd = strlen($input);
                    }
                } else {
                    $segEnd = strpos($input, '/');
                    if ($segEnd === false) {
                        $segEnd = strlen($input);
                    }
                }
                $output .= substr($input, 0, $segEnd);
                $input = substr($input, $segEnd);
            }
        }

        return $output;
    }

    /**
     * Generate a unique blank node identifier.
     */
    private function generateBlankNodeId(): string
    {
        return '_:genid' . ++$this->blankNodeCounter;
    }

    /**
     * Get all child elements of an element across all namespaces.
     *
     * Uses getNamespaces(true) to find all namespaces in scope (including inherited),
     * then iterates children in each namespace to collect all child elements.
     *
     * @return list<\SimpleXMLElement>
     */
    private function getAllChildren(\SimpleXMLElement $element): array
    {
        $children = [];
        $seen = [];

        // Get all namespaces in scope for this element and its descendants
        $namespaces = $element->getNamespaces(true);
        // Also include the default (empty) namespace and the element's own namespace
        $namespacesToCheck = array_values($namespaces);
        $namespacesToCheck[] = ''; // default namespace

        $namespacesToCheck = array_unique($namespacesToCheck);

        foreach ($namespacesToCheck as $nsUri) {
            foreach ($element->children($nsUri) as $child) {
                // Use DOM node identity for deduplication.
                // SimpleXML can return the same XML node with different spl_object_id
                // when accessed via different namespace strings that map to the same URI
                // (e.g., default namespace "" vs explicit "http://...rdf-syntax-ns#").
                $domNode = dom_import_simplexml($child);
                $id = spl_object_id($domNode);
                if (! isset($seen[$id])) {
                    $seen[$id] = true;
                    $children[] = $child;
                }
            }
        }

        return $children;
    }

    /**
     * Get the inner XML content of an element (for parseType="Literal").
     */
    private function getInnerXml(\SimpleXMLElement $element): string
    {
        $xml = $element->asXML();
        if ($xml === false) {
            return '';
        }

        // Strip the outer element tags
        $xml = (string) preg_replace('/^<[^>]+>/', '', $xml);
        $xml = (string) preg_replace('/<\/[^>]+>$/', '', $xml);

        return trim($xml);
    }

    /**
     * Validate that a value is a valid NCName per XML Namespaces specification.
     *
     * NCName (Non-Colonized Name) must start with a letter or underscore,
     * followed by letters, digits, hyphens, underscores, periods, or
     * combining characters. Colons are explicitly forbidden.
     *
     * @see https://www.w3.org/TR/xml-names/#NT-NCName
     */
    private function isValidNcName(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        // NCName must match: starts with NameStartChar (minus colon), followed by NameChar*
        // NameStartChar (without colon): [A-Z] | "_" | [a-z] | [#xC0-#xD6] | [#xD8-#xF6] |
        //   [#xF8-#x2FF] | [#x370-#x37D] | [#x37F-#x1FFF] | [#x200C-#x200D] |
        //   [#x2070-#x218F] | [#x2C00-#x2FEF] | [#x3001-#xD7FF] | [#xF900-#xFDCF] |
        //   [#xFDF0-#xFFFD] | [#x10000-#xEFFFF]
        // NameChar: NameStartChar | "-" | "." | [0-9] | #xB7 | [#x0300-#x036F] | [#x203F-#x2040]
        $nameStartChar = 'A-Z_a-z\x{C0}-\x{D6}\x{D8}-\x{F6}\x{F8}-\x{2FF}\x{370}-\x{37D}\x{37F}-\x{1FFF}\x{200C}-\x{200D}\x{2070}-\x{218F}\x{2C00}-\x{2FEF}\x{3001}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFFD}';
        $nameChar = $nameStartChar . '\\-\\.0-9\x{B7}\x{0300}-\x{036F}\x{203F}-\x{2040}';

        $pattern = '/^[' . $nameStartChar . '][' . $nameChar . ']*$/u';

        return preg_match($pattern, $value) === 1;
    }

    /**
     * Validate rdf:ID value (NCName format).
     *
     * @throws ParseException if the rdf:ID value is not a valid NCName
     */
    private function validateRdfId(string $id): void
    {
        if (! $this->isValidNcName($id)) {
            throw new ParseException("RDF/XML: Invalid rdf:ID value '{$id}' - must be a valid NCName");
        }
    }

    /**
     * Track rdf:ID resolved URI for duplicate detection.
     *
     * The duplicate check uses the fully resolved URI (base + #ID) so that
     * the same rdf:ID string with different xml:base contexts is allowed.
     *
     * @throws ParseException if the resolved URI was already used
     */
    private function trackRdfIdUri(string $id, string $resolvedUri): void
    {
        if (isset($this->usedRdfIds[$resolvedUri])) {
            throw new ParseException("RDF/XML: Duplicate rdf:ID '{$id}' - each rdf:ID must be unique within a document");
        }

        $this->usedRdfIds[$resolvedUri] = true;
    }

    /**
     * Validate rdf:nodeID value.
     *
     * @throws ParseException if the rdf:nodeID value is not a valid NCName
     */
    private function validateRdfNodeId(string $nodeId): void
    {
        if (! $this->isValidNcName($nodeId)) {
            throw new ParseException("RDF/XML: Invalid rdf:nodeID value '{$nodeId}' - must be a valid NCName");
        }
    }

    /**
     * Check for deprecated RDF attributes and throw if found.
     *
     * @param array<string, string> $rdfAttrs
     *
     * @throws ParseException if a deprecated attribute is found
     */
    private function validateNoDeprecatedAttributes(array $rdfAttrs): void
    {
        $deprecated = ['aboutEach', 'aboutEachPrefix', 'bagID'];

        foreach ($deprecated as $attr) {
            if (isset($rdfAttrs[$attr])) {
                throw new ParseException("RDF/XML: Deprecated attribute 'rdf:{$attr}' is not supported (removed in RDF/XML specification)");
            }
        }
    }

    /**
     * Check for rdf:li used as an attribute (forbidden).
     *
     * @throws ParseException if rdf:li is used as attribute
     */
    private function validateNoLiAttribute(\SimpleXMLElement $element): void
    {
        $rdfAttributes = $element->attributes(self::RDF_NS);
        if ($rdfAttributes !== null) {
            foreach ($rdfAttributes as $name => $value) {
                if ((string) $name === 'li') {
                    throw new ParseException("RDF/XML: Forbidden attribute 'rdf:li' - rdf:li cannot be used as an attribute");
                }
            }
        }
    }

    /**
     * Validate that a node element name is not forbidden.
     *
     * @throws ParseException if the element name is forbidden in node position
     */
    private function validateNodeElementName(\SimpleXMLElement $element): void
    {
        $expandedName = $this->getExpandedName($element);

        foreach (self::FORBIDDEN_NODE_ELEMENT_NAMES as $forbidden) {
            if ($expandedName === self::RDF_NS . $forbidden) {
                throw new ParseException("RDF/XML: Forbidden element 'rdf:{$forbidden}' encountered as node element");
            }
        }
    }

    /**
     * Validate that a property element name is not forbidden.
     *
     * @throws ParseException if the element name is forbidden in property position
     */
    private function validatePropertyElementName(\SimpleXMLElement $element): void
    {
        $expandedName = $this->getExpandedName($element);

        foreach (self::FORBIDDEN_PROPERTY_ELEMENT_NAMES as $forbidden) {
            if ($expandedName === self::RDF_NS . $forbidden) {
                throw new ParseException("RDF/XML: Forbidden element 'rdf:{$forbidden}' encountered as property element");
            }
        }
    }

    /**
     * Validate that conflicting subject-determining attributes are not combined.
     *
     * On a node element: rdf:about, rdf:ID, and rdf:nodeID are mutually exclusive.
     *
     * @param array<string, string> $rdfAttrs
     *
     * @throws ParseException if conflicting attributes are found
     */
    private function validateNoConflictingNodeAttributes(array $rdfAttrs): void
    {
        $subjectAttrs = array_intersect_key($rdfAttrs, array_flip(['about', 'ID', 'nodeID']));
        if (count($subjectAttrs) > 1) {
            $names = array_map(fn (string $k): string => 'rdf:' . $k, array_keys($subjectAttrs));
            throw new ParseException(
                'RDF/XML: Conflicting attributes ' . implode(' and ', $names) . ' on node element - only one is allowed'
            );
        }
    }

    /**
     * Validate that conflicting attributes are not combined on a property element.
     *
     * rdf:resource, rdf:nodeID, and rdf:parseType are mutually exclusive.
     * rdf:parseType with rdf:resource is explicitly forbidden.
     *
     * @param array<string, string> $rdfAttrs
     *
     * @throws ParseException if conflicting attributes are found
     */
    private function validateNoConflictingPropertyAttributes(array $rdfAttrs): void
    {
        $objectAttrs = array_intersect_key($rdfAttrs, array_flip(['resource', 'nodeID']));
        if (count($objectAttrs) > 1) {
            throw new ParseException(
                'RDF/XML: Conflicting attributes rdf:resource and rdf:nodeID on property element - only one is allowed'
            );
        }

        // parseType is incompatible with resource and nodeID
        if (isset($rdfAttrs['parseType']) && (isset($rdfAttrs['resource']) || isset($rdfAttrs['nodeID']))) {
            $conflicting = isset($rdfAttrs['resource']) ? 'rdf:resource' : 'rdf:nodeID';
            throw new ParseException(
                "RDF/XML: Conflicting attributes rdf:parseType and {$conflicting} on property element - cannot be combined"
            );
        }
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
