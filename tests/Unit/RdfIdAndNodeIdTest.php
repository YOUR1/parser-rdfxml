<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdfXml\RdfXmlHandler;

describe('rdf:ID and rdf:nodeID Support', function () {
    beforeEach(function () {
        $this->handler = new RdfXmlHandler();
    });

    describe('rdf:ID on description elements', function () {
        it('constructs subject URI as base + #ID', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:ex="http://example.org/"
         xml:base="http://example.org/doc">
  <rdf:Description rdf:ID="thing">
    <ex:name>Test</ex:name>
  </rdf:Description>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            $triples = $result->graph->serialise('ntriples');
            expect($triples)->toContain('http://example.org/doc#thing');
            expect($triples)->toContain('"Test"');
        });
    });

    describe('rdf:ID on property elements (reification)', function () {
        it('creates reification quad for property with rdf:ID', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:ex="http://example.org/"
         xml:base="http://example.org/dir/file">
  <rdf:Description>
    <ex:value rdf:ID="frag">v</ex:value>
  </rdf:Description>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            $triples = $result->graph->serialise('ntriples');

            // The original triple should exist
            expect($triples)->toContain('"v"');

            // Reification triples should exist
            expect($triples)->toContain('http://example.org/dir/file#frag');
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#Statement');
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#subject');
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#predicate');
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#object');
        });
    });

    describe('rdf:nodeID', function () {
        it('uses blank node for rdf:nodeID as subject', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:ex="http://example.org/">
  <rdf:Description rdf:nodeID="node1">
    <ex:name>Named Blank</ex:name>
  </rdf:Description>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            $triples = $result->graph->serialise('ntriples');
            expect($triples)->toContain('_:node1');
            expect($triples)->toContain('"Named Blank"');
        });

        it('uses blank node for rdf:nodeID as object reference', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:ex="http://example.org/">
  <rdf:Description rdf:about="http://example.org/item">
    <ex:ref rdf:nodeID="target"/>
  </rdf:Description>
  <rdf:Description rdf:nodeID="target">
    <ex:label>Target</ex:label>
  </rdf:Description>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            $triples = $result->graph->serialise('ntriples');
            // Both references should point to the same blank node
            expect($triples)->toContain('_:target');
            expect($triples)->toContain('http://example.org/item');
            expect($triples)->toContain('"Target"');
        });

        it('maintains document-scoped blank node identity', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:ex="http://example.org/">
  <rdf:Description rdf:about="http://example.org/a">
    <ex:link rdf:nodeID="shared"/>
  </rdf:Description>
  <rdf:Description rdf:about="http://example.org/b">
    <ex:link rdf:nodeID="shared"/>
  </rdf:Description>
  <rdf:Description rdf:nodeID="shared">
    <ex:type>Common</ex:type>
  </rdf:Description>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            $triples = $result->graph->serialise('ntriples');

            // Count occurrences of _:shared - should appear in multiple triples
            $sharedCount = substr_count($triples, '_:shared');
            // At least 3: as object of a's link, as object of b's link, as subject of type
            expect($sharedCount)->toBeGreaterThanOrEqual(3);
        });
    });
});
