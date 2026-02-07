<?php

use App\Services\Ontology\Exceptions\OntologyImportException;
use App\Services\Ontology\Parsers\Handlers\RdfXmlHandler;
use App\Services\Ontology\Parsers\ValueObjects\ParsedRdf;

describe('RdfXmlHandler', function () {
    beforeEach(function () {
        $this->handler = new RdfXmlHandler;
    });

    it('detects RDF/XML format with XML declaration', function () {
        $content = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';

        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects RDF/XML format without XML declaration', function () {
        $content = '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';

        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects RDF/XML format with whitespace before XML declaration', function () {
        $content = "   \n  <?xml version=\"1.0\"?><rdf:RDF></rdf:RDF>";

        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('returns correct format name', function () {
        expect($this->handler->getFormatName())->toBe('rdf/xml');
    });

    it('does not detect non-XML content', function () {
        $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .';

        expect($this->handler->canHandle($content))->toBeFalse();
    });

    it('does not detect JSON-LD content', function () {
        $content = '{"@context": {"rdf": "http://www.w3.org/1999/02/22-rdf-syntax-ns#"}}';

        expect($this->handler->canHandle($content))->toBeFalse();
    });

    it('parses valid RDF/XML content', function () {
        $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">

    <rdfs:Class rdf:about="http://example.org/Person">
        <rdfs:label>Person</rdfs:label>
        <rdfs:comment>A human being</rdfs:comment>
    </rdfs:Class>

</rdf:RDF>';

        $result = $this->handler->parse($content);

        expect($result)->toBeInstanceOf(ParsedRdf::class);
        expect($result->format)->toBe('rdf/xml');
        expect($result->rawContent)->toBe($content);
    });

    it('stores SimpleXML element in metadata', function () {
        $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
    <rdfs:Class rdf:about="http://example.org/Test"/>
</rdf:RDF>';

        $result = $this->handler->parse($content);

        expect($result->metadata)->toHaveKey('xml_element');
        expect($result->metadata['xml_element'])->toBeInstanceOf(SimpleXMLElement::class);
    });

    it('stores parser metadata', function () {
        $content = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';

        $result = $this->handler->parse($content);

        expect($result->metadata)->toHaveKey('parser');
        expect($result->metadata['parser'])->toBe('rdf_xml_handler');
        expect($result->metadata)->toHaveKey('format');
        expect($result->metadata['format'])->toBe('rdf/xml');
    });

    it('indicates whether EasyRdf was used', function () {
        $content = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';

        $result = $this->handler->parse($content);

        expect($result->metadata)->toHaveKey('used_easyrdf');
        expect($result->metadata['used_easyrdf'])->toBeIn([true, false]); // Can be either depending on PHP version
    });

    it('throws exception on malformed XML', function () {
        $content = '<?xml version="1.0"?><rdf:RDF><tag>content</invalid>';

        expect(fn () => $this->handler->parse($content))
            ->toThrow(OntologyImportException::class);
    });

    it('throws exception on HTML content disguised as XML', function () {
        $content = '<?xml version="1.0"?><!DOCTYPE html><html><body>This is HTML</body></html>';

        expect(fn () => $this->handler->parse($content))
            ->toThrow(OntologyImportException::class, 'does not appear to be valid RDF/XML');
    });

    it('throws exception on HTML without XML declaration', function () {
        $content = '<html><head><title>Test</title></head><body>HTML content</body></html>';

        expect(fn () => $this->handler->parse($content))
            ->toThrow(OntologyImportException::class);
    });

    it('rejects content that does not look like XML', function () {
        $content = 'This is just plain text';

        expect(fn () => $this->handler->parse($content))
            ->toThrow(OntologyImportException::class);
    });

    it('parses RDF/XML with multiple namespaces', function () {
        $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:owl="http://www.w3.org/2002/07/owl#"
         xmlns:foaf="http://xmlns.com/foaf/0.1/"
         xmlns:dc="http://purl.org/dc/elements/1.1/">

    <owl:Class rdf:about="http://example.org/Person">
        <rdfs:label>Person</rdfs:label>
        <dc:creator>Test</dc:creator>
        <foaf:name>Example Person</foaf:name>
    </owl:Class>

</rdf:RDF>';

        $result = $this->handler->parse($content);

        expect($result)->toBeInstanceOf(ParsedRdf::class);
        expect($result->metadata['xml_element'])->toBeInstanceOf(SimpleXMLElement::class);
    });

    it('handles empty RDF/XML document', function () {
        $content = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';

        $result = $this->handler->parse($content);

        expect($result)->toBeInstanceOf(ParsedRdf::class);
        expect($result->rawContent)->toBe($content);
    });

    it('parses RDF/XML with language-tagged literals', function () {
        $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">

    <rdfs:Class rdf:about="http://example.org/Person">
        <rdfs:label xml:lang="en">Person</rdfs:label>
        <rdfs:label xml:lang="nl">Persoon</rdfs:label>
        <rdfs:comment xml:lang="en">A human being</rdfs:comment>
        <rdfs:comment xml:lang="nl">Een mens</rdfs:comment>
    </rdfs:Class>

</rdf:RDF>';

        $result = $this->handler->parse($content);

        expect($result)->toBeInstanceOf(ParsedRdf::class);
        expect($result->metadata['xml_element'])->toBeInstanceOf(SimpleXMLElement::class);
    });

    it('parses RDF/XML with blank nodes', function () {
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
        expect($result->metadata['xml_element'])->toBeInstanceOf(SimpleXMLElement::class);
    });
});
