<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdfXml\RdfXmlHandler;

describe('xml:base URI Resolution', function () {
    beforeEach(function () {
        $this->handler = new RdfXmlHandler();
    });

    it('resolves rdf:ID against document-level xml:base', function () {
        // W3C xmlbase test001: xml:base applies to rdf:ID on rdf:Description
        $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:eg="http://example.org/"
         xml:base="http://example.org/dir/file">
 <rdf:Description rdf:ID="frag" eg:value="v" />
</rdf:RDF>';

        $result = $this->handler->parse($content);

        expect($result)->toBeInstanceOf(ParsedRdf::class);
        expect($result->graph->countTriples())->toBeGreaterThan(0);

        // rdf:ID="frag" with xml:base="http://example.org/dir/file" -> http://example.org/dir/file#frag
        $triples = $result->graph->serialise('ntriples');
        expect($triples)->toContain('http://example.org/dir/file#frag');
        expect($triples)->toContain('http://example.org/value');
        expect($triples)->toContain('"v"');
    });

    it('resolves rdf:resource against document-level xml:base', function () {
        // W3C xmlbase test002: xml:base applies to rdf:resource
        $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:eg="http://example.org/"
         xml:base="http://example.org/dir/file">
 <rdf:Description>
   <eg:value rdf:resource="relFile" />
 </rdf:Description>
</rdf:RDF>';

        $result = $this->handler->parse($content);

        expect($result->graph->countTriples())->toBeGreaterThan(0);
        // Check that relative URI "relFile" is resolved against base
        $triples = $result->graph->serialise('ntriples');
        expect($triples)->toContain('http://example.org/dir/relFile');
    });

    it('resolves rdf:about against document-level xml:base', function () {
        // W3C xmlbase test003: xml:base applies to rdf:about
        $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:eg="http://example.org/"
         xml:base="http://example.org/dir/file">
 <eg:type rdf:about="relfile" />
</rdf:RDF>';

        $result = $this->handler->parse($content);

        expect($result->graph->countTriples())->toBeGreaterThan(0);
        $triples = $result->graph->serialise('ntriples');
        expect($triples)->toContain('http://example.org/dir/relfile');
        expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#type');
    });

    it('resolves element-level xml:base overriding document-level', function () {
        // W3C xmlbase test006: xml:base scoping
        $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:eg="http://example.org/"
         xml:base="http://example.org/dir/file">
 <rdf:Description rdf:ID="frag" eg:value="v" xml:base="http://example.org/file2"/>
 <eg:type rdf:about="relFile" />
</rdf:RDF>';

        $result = $this->handler->parse($content);

        $triples = $result->graph->serialise('ntriples');
        // rdf:ID="frag" with element xml:base="http://example.org/file2" -> http://example.org/file2#frag
        expect($triples)->toContain('http://example.org/file2#frag');
        // rdf:about="relFile" uses parent xml:base -> http://example.org/dir/relFile
        expect($triples)->toContain('http://example.org/dir/relFile');
    });

    it('resolves parent path relative URI (..) against xml:base', function () {
        // W3C xmlbase test007: relative URI with ..
        $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:eg="http://example.org/"
         xml:base="http://example.org/dir/file">
 <eg:type rdf:about="../relfile" />
</rdf:RDF>';

        $result = $this->handler->parse($content);

        $triples = $result->graph->serialise('ntriples');
        expect($triples)->toContain('http://example.org/relfile');
    });

    it('resolves empty rdf:about as base URI itself', function () {
        // W3C xmlbase test008: empty same-document reference
        $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:eg="http://example.org/"
         xml:base="http://example.org/dir/file">
 <eg:type rdf:about="" />
</rdf:RDF>';

        $result = $this->handler->parse($content);

        $triples = $result->graph->serialise('ntriples');
        expect($triples)->toContain('<http://example.org/dir/file>');
    });

    it('resolves absolute path against xml:base', function () {
        // W3C xmlbase test009: relative uri with absolute path
        $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:eg="http://example.org/"
         xml:base="http://example.org/dir/file">
 <eg:type rdf:about="/absfile" />
</rdf:RDF>';

        $result = $this->handler->parse($content);

        $triples = $result->graph->serialise('ntriples');
        expect($triples)->toContain('http://example.org/absfile');
    });

    it('resolves network path against xml:base', function () {
        // W3C xmlbase test010: relative uri with net path
        $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:eg="http://example.org/"
         xml:base="http://example.org/dir/file">
 <eg:type rdf:about="//another.example.org/absfile" />
</rdf:RDF>';

        $result = $this->handler->parse($content);

        $triples = $result->graph->serialise('ntriples');
        expect($triples)->toContain('http://another.example.org/absfile');
    });

    it('resolves relative URI against base with no path', function () {
        // W3C xmlbase test011: base with no path component
        $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:eg="http://example.org/"
         xml:base="http://example.org">
 <eg:type rdf:about="relfile" />
</rdf:RDF>';

        $result = $this->handler->parse($content);

        $triples = $result->graph->serialise('ntriples');
        expect($triples)->toContain('http://example.org/relfile');
    });

    it('strips fragment from xml:base before resolving', function () {
        // W3C xmlbase test013: xml:base with fragment is ignored
        $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:eg="http://example.org/"
         xml:base="http://example.org/dir/file#frag">
 <eg:type rdf:about="" />
 <rdf:Description rdf:ID="foo">
   <eg:value rdf:resource="relpath" />
 </rdf:Description>
</rdf:RDF>';

        $result = $this->handler->parse($content);

        $triples = $result->graph->serialise('ntriples');
        // Empty rdf:about with base "http://example.org/dir/file#frag" -> "http://example.org/dir/file"
        expect($triples)->toContain('<http://example.org/dir/file>');
        // rdf:ID="foo" -> "http://example.org/dir/file#foo"
        expect($triples)->toContain('http://example.org/dir/file#foo');
        // rdf:resource="relpath" -> "http://example.org/dir/relpath"
        expect($triples)->toContain('http://example.org/dir/relpath');
    });

    it('does not regress parsing without xml:base', function () {
        $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">
    <rdfs:Class rdf:about="http://example.org/Person">
        <rdfs:label>Person</rdfs:label>
    </rdfs:Class>
</rdf:RDF>';

        $result = $this->handler->parse($content);

        expect($result)->toBeInstanceOf(ParsedRdf::class);
        expect($result->graph->countTriples())->toBeGreaterThan(0);
    });
});
