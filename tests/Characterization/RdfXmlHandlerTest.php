<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdfXml\RdfXmlHandler;

describe('RdfXmlHandler', function () {
    beforeEach(function () {
        $this->handler = new RdfXmlHandler();
    });

    describe('canHandle()', function () {
        // Task 2.1
        it('returns true for content with XML declaration and rdf:RDF element', function () {
            $content = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';

            expect($this->handler->canHandle($content))->toBeTrue();
        });

        // Task 2.2
        it('returns true for content with rdf:RDF element without XML declaration', function () {
            $content = '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';

            expect($this->handler->canHandle($content))->toBeTrue();
        });

        // Task 2.3
        it('returns true for content with leading whitespace before XML declaration', function () {
            $content = "   \n\t  <?xml version=\"1.0\"?><rdf:RDF xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"></rdf:RDF>";

            expect($this->handler->canHandle($content))->toBeTrue();
        });

        // Task 2.4
        it('returns false for empty string', function () {
            expect($this->handler->canHandle(''))->toBeFalse();
        });

        // Task 2.5
        it('returns false for whitespace-only content', function () {
            expect($this->handler->canHandle("   \n\t  "))->toBeFalse();
        });

        // Task 2.6
        it('returns false for Turtle content', function () {
            $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
<http://example.org/subject> rdf:type <http://example.org/Type> .';

            expect($this->handler->canHandle($content))->toBeFalse();
        });

        // Task 2.7
        it('returns false for JSON-LD content', function () {
            $content = '{"@context": {"rdf": "http://www.w3.org/1999/02/22-rdf-syntax-ns#"}, "@type": "Person"}';

            expect($this->handler->canHandle($content))->toBeFalse();
        });

        // Task 2.8
        it('returns false for plain text content', function () {
            expect($this->handler->canHandle('This is just plain text, not RDF at all.'))->toBeFalse();
        });

        // Task 2.9
        it('returns false for N-Triples content', function () {
            $content = '<http://example.org/subject> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://example.org/Type> .';

            expect($this->handler->canHandle($content))->toBeFalse();
        });

        // Task 2.10 — potential false positive: generic XML with <?xml but no <rdf:RDF>
        it('returns true for generic XML with XML declaration but no rdf:RDF element', function () {
            $content = '<?xml version="1.0"?><root><item>Not RDF at all</item></root>';

            expect($this->handler->canHandle($content))->toBeTrue();
        });

        // Task 2.11
        it('returns false for HTML content without XML declaration or rdf:RDF', function () {
            $content = '<html><head><title>Test</title></head><body>HTML content</body></html>';

            expect($this->handler->canHandle($content))->toBeFalse();
        });

        // Task 2.12 — canHandle passes but parse would fail (canHandle/parse gap)
        it('returns true for XML declaration followed by HTML content', function () {
            $content = '<?xml version="1.0"?><!DOCTYPE html><html><body>HTML disguised as XML</body></html>';

            expect($this->handler->canHandle($content))->toBeTrue();
        });
    });

    describe('parse()', function () {
        // Task 3.1
        it('returns ParsedRdf instance for valid RDF/XML with a class declaration', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">
    <rdfs:Class rdf:about="http://example.org/Person">
        <rdfs:label>Person</rdfs:label>
    </rdfs:Class>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            expect($result)->toBeInstanceOf(ParsedRdf::class);
        });

        // Task 3.2
        it('returns ParsedRdf with format property set to rdf/xml', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
    <rdf:Description rdf:about="http://example.org/thing"/>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            expect($result->format)->toBe('rdf/xml');
        });

        // Task 3.3
        it('preserves original content in rawContent property', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
    <rdf:Description rdf:about="http://example.org/thing"/>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            expect($result->rawContent)->toBe($content);
        });

        // Task 3.4
        it('contains parser and format keys in metadata', function () {
            $content = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';

            $result = $this->handler->parse($content);

            expect($result->metadata['parser'])->toBe('rdf_xml_handler');
            expect($result->metadata['format'])->toBe('rdf/xml');
        });

        // Task 3.5
        it('contains used_easyrdf boolean in metadata', function () {
            $content = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';

            $result = $this->handler->parse($content);

            expect($result->metadata)->toHaveKey('used_easyrdf');
            // EasyRdf RDF/XML parser fails silently on PHP 8.4+ (xml_set_element_handler deprecation)
            expect($result->metadata['used_easyrdf'])->toBeFalse();
        });

        // Task 3.6
        it('contains SimpleXMLElement instance in metadata xml_element', function () {
            $content = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';

            $result = $this->handler->parse($content);

            expect($result->metadata)->toHaveKey('xml_element');
            expect($result->metadata['xml_element'])->toBeInstanceOf(\SimpleXMLElement::class);
        });

        // Task 3.7
        it('correctly parses RDF/XML with multiple classes and subClassOf relationships', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:owl="http://www.w3.org/2002/07/owl#">
    <owl:Class rdf:about="http://example.org/Animal">
        <rdfs:label>Animal</rdfs:label>
    </owl:Class>
    <owl:Class rdf:about="http://example.org/Dog">
        <rdfs:subClassOf rdf:resource="http://example.org/Animal"/>
        <rdfs:label>Dog</rdfs:label>
    </owl:Class>
    <owl:Class rdf:about="http://example.org/Cat">
        <rdfs:subClassOf rdf:resource="http://example.org/Animal"/>
        <rdfs:label>Cat</rdfs:label>
    </owl:Class>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            expect($result)->toBeInstanceOf(ParsedRdf::class);
            expect($result->metadata['xml_element'])->toBeInstanceOf(\SimpleXMLElement::class);
        });

        // Task 3.8
        it('correctly parses RDF/XML with properties and domain/range', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">
    <rdf:Property rdf:about="http://example.org/hasName">
        <rdfs:domain rdf:resource="http://example.org/Person"/>
        <rdfs:range rdf:resource="http://www.w3.org/2001/XMLSchema#string"/>
        <rdfs:label>has name</rdfs:label>
    </rdf:Property>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            expect($result)->toBeInstanceOf(ParsedRdf::class);
            expect($result->metadata['xml_element'])->toBeInstanceOf(\SimpleXMLElement::class);
        });

        // Task 3.9
        it('correctly parses RDF/XML with multiple namespace declarations', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:owl="http://www.w3.org/2002/07/owl#"
         xmlns:foaf="http://xmlns.com/foaf/0.1/"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         xmlns:skos="http://www.w3.org/2004/02/skos/core#">
    <owl:Class rdf:about="http://example.org/Person">
        <rdfs:label>Person</rdfs:label>
        <dc:creator>Test Author</dc:creator>
    </owl:Class>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            expect($result)->toBeInstanceOf(ParsedRdf::class);
            expect($result->metadata)->toHaveKeys(['parser', 'format', 'used_easyrdf', 'xml_element']);
        });

        // Task 3.10
        it('correctly parses RDF/XML with blank nodes', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:owl="http://www.w3.org/2002/07/owl#">
    <owl:Class rdf:about="http://example.org/Person">
        <rdfs:subClassOf>
            <owl:Restriction>
                <owl:onProperty rdf:resource="http://example.org/hasAge"/>
                <owl:someValuesFrom rdf:resource="http://www.w3.org/2001/XMLSchema#integer"/>
            </owl:Restriction>
        </rdfs:subClassOf>
    </owl:Class>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            expect($result)->toBeInstanceOf(ParsedRdf::class);
            expect($result->metadata['xml_element'])->toBeInstanceOf(\SimpleXMLElement::class);
        });

        // Task 3.11
        it('correctly parses RDF/XML with language-tagged literals', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">
    <rdfs:Class rdf:about="http://example.org/Person">
        <rdfs:label xml:lang="en">Person</rdfs:label>
        <rdfs:label xml:lang="nl">Persoon</rdfs:label>
        <rdfs:comment xml:lang="en">A human being</rdfs:comment>
    </rdfs:Class>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            expect($result)->toBeInstanceOf(ParsedRdf::class);
            expect($result->metadata['xml_element'])->toBeInstanceOf(\SimpleXMLElement::class);
        });

        // Task 3.12
        it('correctly parses RDF/XML with typed literals', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:ex="http://example.org/">
    <rdf:Description rdf:about="http://example.org/person1">
        <ex:age rdf:datatype="http://www.w3.org/2001/XMLSchema#integer">42</ex:age>
        <ex:height rdf:datatype="http://www.w3.org/2001/XMLSchema#decimal">1.85</ex:height>
        <ex:active rdf:datatype="http://www.w3.org/2001/XMLSchema#boolean">true</ex:active>
    </rdf:Description>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            expect($result)->toBeInstanceOf(ParsedRdf::class);
            expect($result->metadata['xml_element'])->toBeInstanceOf(\SimpleXMLElement::class);
        });

        // Task 3.13
        it('parses minimal empty rdf:RDF document', function () {
            $content = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';

            $result = $this->handler->parse($content);

            expect($result)->toBeInstanceOf(ParsedRdf::class);
            expect($result->rawContent)->toBe($content);
            expect($result->metadata['xml_element'])->toBeInstanceOf(\SimpleXMLElement::class);
        });

        // Task 3.14
        it('produces SimpleXMLElement that supports XPath queries with registered namespaces', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:owl="http://www.w3.org/2002/07/owl#">
    <owl:Class rdf:about="http://example.org/Person">
        <rdfs:label>Person</rdfs:label>
    </owl:Class>
    <owl:Class rdf:about="http://example.org/Animal">
        <rdfs:label>Animal</rdfs:label>
    </owl:Class>
</rdf:RDF>';

            $result = $this->handler->parse($content);
            $xml = $result->metadata['xml_element'];

            // XPath with registered namespace prefixes should work
            $classes = $xml->xpath('//owl:Class');

            expect($classes)->toBeArray();
            expect(count($classes))->toBe(2);
        });
    });

    describe('getFormatName()', function () {
        // Task 4.1
        it('returns rdf/xml', function () {
            expect($this->handler->getFormatName())->toBe('rdf/xml');
        });

        // Task 4.2
        it('returns a string value', function () {
            expect($this->handler->getFormatName())->toBeString();
        });
    });

    describe('error behavior', function () {
        // Task 5.1
        it('throws ParseException for malformed XML with broken tags', function () {
            $content = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><broken>content</invalid></rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class);
        });

        // Task 5.2
        it('wraps malformed XML error with RDF/XML parsing failed prefix', function () {
            $content = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><broken>content</invalid></rdf:RDF>';

            try {
                $this->handler->parse($content);
                test()->fail('Expected ParseException to be thrown');
            } catch (ParseException $e) {
                expect($e->getMessage())->toStartWith('RDF/XML parsing failed: ');
            }
        });

        // Task 5.3
        it('sets previous exception on wrapped errors', function () {
            $content = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><broken>content</invalid></rdf:RDF>';

            try {
                $this->handler->parse($content);
                test()->fail('Expected ParseException to be thrown');
            } catch (ParseException $e) {
                expect($e->getPrevious())->not->toBeNull();
                // Double-wrap: outer ParseException wraps inner ParseException
                expect($e->getPrevious())->toBeInstanceOf(ParseException::class);
            }
        });

        // Task 5.4
        it('double-wraps validation error for plain text content', function () {
            $content = 'This is just plain text, not XML at all.';

            try {
                $this->handler->parse($content);
                test()->fail('Expected ParseException to be thrown');
            } catch (ParseException $e) {
                expect($e->getMessage())->toBe('RDF/XML parsing failed: Content does not appear to be valid RDF/XML');
                // The previous exception is the inner ParseException
                expect($e->getPrevious())->toBeInstanceOf(ParseException::class);
                expect($e->getPrevious()->getMessage())->toBe('Content does not appear to be valid RDF/XML');
            }
        });

        // Task 5.5
        it('rejects HTML content disguised as XML via looksLikeHtml check', function () {
            $content = '<?xml version="1.0"?><!DOCTYPE html><html><body>HTML disguised as XML</body></html>';

            try {
                $this->handler->parse($content);
                test()->fail('Expected ParseException to be thrown');
            } catch (ParseException $e) {
                // Double-wrap: looksLikeHtml() returns true → inner throw → outer catch re-wraps
                expect($e->getMessage())->toBe('RDF/XML parsing failed: Content does not appear to be valid RDF/XML');
                expect($e->getPrevious())->toBeInstanceOf(ParseException::class);
                expect($e->getPrevious()->getMessage())->toBe('Content does not appear to be valid RDF/XML');
            }
        });

        // Task 5.6
        it('rejects bare HTML content via looksLikeXml check', function () {
            $content = '<html><head><title>Test</title></head><body>Bare HTML</body></html>';

            try {
                $this->handler->parse($content);
                test()->fail('Expected ParseException to be thrown');
            } catch (ParseException $e) {
                // Bare HTML: <html starts with < so looksLikeXml's first check passes,
                // but <?xml and <rdf:RDF both absent → looksLikeXml returns false → throws
                // Double-wrap: inner validation error → outer catch re-wraps
                expect($e->getMessage())->toBe('RDF/XML parsing failed: Content does not appear to be valid RDF/XML');
                expect($e->getPrevious())->toBeInstanceOf(ParseException::class);
                expect($e->getPrevious()->getMessage())->toBe('Content does not appear to be valid RDF/XML');
            }
        });

        // Task 5.7
        it('throws for empty string input', function () {
            try {
                $this->handler->parse('');
                test()->fail('Expected ParseException to be thrown');
            } catch (ParseException $e) {
                // Empty string fails looksLikeXml (ltrim('') doesn't start with '<')
                expect($e->getMessage())->toContain('does not appear to be valid RDF/XML');
            }
        });

        // Task 5.8
        it('uses default exception code of 0', function () {
            $content = 'Not XML content at all';

            try {
                $this->handler->parse($content);
                test()->fail('Expected ParseException to be thrown');
            } catch (ParseException $e) {
                expect($e->getCode())->toBe(0);
            }
        });

        // Task 5.9
        it('handles valid XML that is not RDF/XML structure', function () {
            $content = '<?xml version="1.0"?><root><item>Not RDF</item></root>';

            // canHandle returns true (has <?xml) but parse may succeed with SimpleXML
            // and produce a result with empty/minimal graph, OR throw if EasyRdf fails badly
            // Capture actual behavior:
            $result = $this->handler->parse($content);

            // SimpleXML can parse any valid XML, so this should succeed
            expect($result)->toBeInstanceOf(ParsedRdf::class);
            expect($result->metadata['xml_element'])->toBeInstanceOf(\SimpleXMLElement::class);
            expect($result->metadata['parser'])->toBe('rdf_xml_handler');
            // Dual-parser behavior: SimpleXML succeeds, EasyRdf fails silently (not valid RDF/XML structure)
            expect($result->metadata['used_easyrdf'])->toBeFalse();
            expect($result->isEmpty())->toBeTrue();
        });
    });

    // ORDERING DEPENDENCY: EasyRdf\RdfNamespace global state
    // Tests below register custom prefixes (customtest, myfirst, mysecond, mythird, sideeffect)
    // via EasyRdf\RdfNamespace::set(). EasyRdf has no delete/unset method for prefixes,
    // so these persist for the lifetime of the PHP process. All parse() tests above also
    // leak xmlns prefixes into global state. This is intentional — characterization tests
    // document actual side effect behavior including global state modification.
    describe('prefix registration side effect', function () {
        // Task 6.1
        it('registers xmlns prefixes from content in EasyRdf RdfNamespace', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:customtest="http://example.org/custom-test-ns/">
    <rdf:Description rdf:about="http://example.org/thing"/>
</rdf:RDF>';

            $this->handler->parse($content);

            $namespaces = \EasyRdf\RdfNamespace::namespaces();

            expect($namespaces)->toHaveKey('customtest');
            expect($namespaces['customtest'])->toBe('http://example.org/custom-test-ns/');
        });

        // Task 6.2
        it('preserves standard prefixes after parsing', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">
    <rdf:Description rdf:about="http://example.org/thing"/>
</rdf:RDF>';

            $this->handler->parse($content);

            $namespaces = \EasyRdf\RdfNamespace::namespaces();

            // Verify standard prefixes exist with their canonical URIs
            expect($namespaces)->toHaveKey('rdf');
            expect($namespaces['rdf'])->toBe('http://www.w3.org/1999/02/22-rdf-syntax-ns#');
            expect($namespaces)->toHaveKey('rdfs');
            expect($namespaces['rdfs'])->toBe('http://www.w3.org/2000/01/rdf-schema#');
            expect($namespaces)->toHaveKey('owl');
            expect($namespaces['owl'])->toBe('http://www.w3.org/2002/07/owl#');
            expect($namespaces)->toHaveKey('xsd');
            expect($namespaces['xsd'])->toBe('http://www.w3.org/2001/XMLSchema#');
        });

        // Task 6.3
        it('registers multiple custom xmlns prefixes from single content', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:myfirst="http://example.org/first-ns/"
         xmlns:mysecond="http://example.org/second-ns/"
         xmlns:mythird="http://example.org/third-ns/">
    <rdf:Description rdf:about="http://example.org/thing"/>
</rdf:RDF>';

            $this->handler->parse($content);

            $namespaces = \EasyRdf\RdfNamespace::namespaces();

            expect($namespaces)->toHaveKey('myfirst');
            expect($namespaces['myfirst'])->toBe('http://example.org/first-ns/');
            expect($namespaces)->toHaveKey('mysecond');
            expect($namespaces['mysecond'])->toBe('http://example.org/second-ns/');
            expect($namespaces)->toHaveKey('mythird');
            expect($namespaces['mythird'])->toBe('http://example.org/third-ns/');
        });

        // Task 6.4
        it('registers prefixes before SimpleXML parsing so they persist even when parse fails', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:sideeffect="http://example.org/side-effect-ns/">
    <broken>content</invalid>
</rdf:RDF>';

            // parse will fail due to malformed XML, but registerPrefixesFromContent
            // is called BEFORE the validation/parsing, so the prefix should be registered
            try {
                $this->handler->parse($content);
            } catch (ParseException) {
                // Expected to throw
            }

            $namespaces = \EasyRdf\RdfNamespace::namespaces();

            expect($namespaces)->toHaveKey('sideeffect');
            expect($namespaces['sideeffect'])->toBe('http://example.org/side-effect-ns/');
        });
    });
});
