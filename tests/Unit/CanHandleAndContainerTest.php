<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdfXml\RdfXmlHandler;

describe('canHandle() Detection and Container Element Support', function () {
    beforeEach(function () {
        $this->handler = new RdfXmlHandler();
    });

    describe('canHandle() bare RDF root element', function () {
        it('detects bare RDF element with default namespace', function () {
            $content = '<RDF xmlns="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <Description about="http://example.org/thing"/>
</RDF>';

            expect($this->handler->canHandle($content))->toBeTrue();
        });

        it('still detects prefixed rdf:RDF element', function () {
            $content = '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';

            expect($this->handler->canHandle($content))->toBeTrue();
        });

        it('detects bare RDF root with mixed prefixed children', function () {
            $content = '<RDF xmlns="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
     xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">
  <Description about="http://example.org/thing">
    <rdfs:label>Test</rdfs:label>
  </Description>
</RDF>';

            expect($this->handler->canHandle($content))->toBeTrue();
        });
    });

    describe('bare RDF root parsing', function () {
        it('parses bare RDF root element with default namespace', function () {
            $content = '<?xml version="1.0"?>
<RDF xmlns="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
     xmlns:eg="http://example.org/">
  <Description about="http://example.org/thing">
    <eg:value>test</eg:value>
  </Description>
</RDF>';

            $result = $this->handler->parse($content);

            expect($result)->toBeInstanceOf(ParsedRdf::class);
            expect($result->metadata['xml_element'])->toBeInstanceOf(SimpleXMLElement::class);
        });
    });

    describe('container elements', function () {
        it('parses rdf:Bag with rdf:li children', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:ex="http://example.org/">
  <rdf:Description rdf:about="http://example.org/item">
    <ex:fruits>
      <rdf:Bag>
        <rdf:li>Apple</rdf:li>
        <rdf:li>Banana</rdf:li>
        <rdf:li>Cherry</rdf:li>
      </rdf:Bag>
    </ex:fruits>
  </rdf:Description>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            $triples = $result->graph->serialise('ntriples');

            // rdf:Bag should have rdf:type
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#Bag');
            // rdf:li should be converted to rdf:_1, rdf:_2, rdf:_3
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#_1');
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#_2');
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#_3');
            expect($triples)->toContain('"Apple"');
            expect($triples)->toContain('"Banana"');
            expect($triples)->toContain('"Cherry"');
        });

        it('parses rdf:Seq with rdf:li children', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:ex="http://example.org/">
  <rdf:Description rdf:about="http://example.org/item">
    <ex:steps>
      <rdf:Seq>
        <rdf:li>First</rdf:li>
        <rdf:li>Second</rdf:li>
      </rdf:Seq>
    </ex:steps>
  </rdf:Description>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            $triples = $result->graph->serialise('ntriples');

            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#Seq');
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#_1');
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#_2');
        });

        it('parses rdf:Alt with rdf:li children', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:ex="http://example.org/">
  <rdf:Description rdf:about="http://example.org/item">
    <ex:options>
      <rdf:Alt>
        <rdf:li>Option A</rdf:li>
        <rdf:li>Option B</rdf:li>
      </rdf:Alt>
    </ex:options>
  </rdf:Description>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            $triples = $result->graph->serialise('ntriples');

            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#Alt');
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#_1');
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#_2');
        });

        it('resets rdf:li numbering per container', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:ex="http://example.org/">
  <rdf:Description rdf:about="http://example.org/item">
    <ex:bag1>
      <rdf:Bag>
        <rdf:li>A1</rdf:li>
        <rdf:li>A2</rdf:li>
      </rdf:Bag>
    </ex:bag1>
    <ex:bag2>
      <rdf:Bag>
        <rdf:li>B1</rdf:li>
        <rdf:li>B2</rdf:li>
      </rdf:Bag>
    </ex:bag2>
  </rdf:Description>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            $triples = $result->graph->serialise('ntriples');

            // Both containers should have rdf:_1 and rdf:_2
            // Count occurrences - should be at least 2 of each (one per container)
            $count1 = substr_count($triples, 'http://www.w3.org/1999/02/22-rdf-syntax-ns#_1');
            $count2 = substr_count($triples, 'http://www.w3.org/1999/02/22-rdf-syntax-ns#_2');
            expect($count1)->toBeGreaterThanOrEqual(2);
            expect($count2)->toBeGreaterThanOrEqual(2);
        });
    });
});
