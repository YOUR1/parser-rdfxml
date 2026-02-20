<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdfXml\RdfXmlHandler;

describe('rdf:parseType Support', function () {
    beforeEach(function () {
        $this->handler = new RdfXmlHandler();
    });

    describe('parseType="Resource"', function () {
        it('creates implicit blank node with nested properties', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:ex="http://example.org/">
  <rdf:Description rdf:about="http://example.org/item">
    <ex:prop rdf:parseType="Resource">
      <ex:name>Nested</ex:name>
      <ex:value>42</ex:value>
    </ex:prop>
  </rdf:Description>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            $triples = $result->graph->serialise('ntriples');

            // Subject links to a blank node via ex:prop
            expect($triples)->toContain('http://example.org/item');
            expect($triples)->toContain('http://example.org/prop');
            // Blank node has nested properties
            expect($triples)->toContain('http://example.org/name');
            expect($triples)->toContain('"Nested"');
            expect($triples)->toContain('http://example.org/value');
            expect($triples)->toContain('"42"');
        });

        it('handles multiple levels of parseType="Resource" nesting', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:ex="http://example.org/">
  <rdf:Description rdf:about="http://example.org/item">
    <ex:outer rdf:parseType="Resource">
      <ex:inner rdf:parseType="Resource">
        <ex:deep>value</ex:deep>
      </ex:inner>
    </ex:outer>
  </rdf:Description>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            $triples = $result->graph->serialise('ntriples');

            expect($triples)->toContain('http://example.org/outer');
            expect($triples)->toContain('http://example.org/inner');
            expect($triples)->toContain('http://example.org/deep');
            expect($triples)->toContain('"value"');
        });
    });

    describe('parseType="Collection"', function () {
        it('creates RDF list from multiple child elements', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:ex="http://example.org/">
  <rdf:Description rdf:about="http://example.org/item">
    <ex:list rdf:parseType="Collection">
      <rdf:Description rdf:about="http://example.org/a"/>
      <rdf:Description rdf:about="http://example.org/b"/>
      <rdf:Description rdf:about="http://example.org/c"/>
    </ex:list>
  </rdf:Description>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            $triples = $result->graph->serialise('ntriples');

            // Collection structure: rdf:first/rdf:rest chain
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#first');
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#rest');
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#nil');
            expect($triples)->toContain('http://example.org/a');
            expect($triples)->toContain('http://example.org/b');
            expect($triples)->toContain('http://example.org/c');
        });

        it('produces rdf:nil for empty collection', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:ex="http://example.org/">
  <rdf:Description rdf:about="http://example.org/item">
    <ex:emptyList rdf:parseType="Collection"/>
  </rdf:Description>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            $triples = $result->graph->serialise('ntriples');

            // Empty collection links directly to rdf:nil
            expect($triples)->toContain('http://example.org/item');
            expect($triples)->toContain('http://example.org/emptyList');
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#nil');
        });

        it('handles single child collection', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:ex="http://example.org/">
  <rdf:Description rdf:about="http://example.org/item">
    <ex:singleList rdf:parseType="Collection">
      <rdf:Description rdf:about="http://example.org/only"/>
    </ex:singleList>
  </rdf:Description>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            $triples = $result->graph->serialise('ntriples');

            expect($triples)->toContain('http://example.org/only');
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#first');
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#rest');
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#nil');
        });
    });

    describe('parseType="Literal"', function () {
        it('preserves XML content as XMLLiteral', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:ex="http://example.org/">
  <rdf:Description rdf:about="http://example.org/item">
    <ex:content rdf:parseType="Literal">
      <em>Hello</em>
    </ex:content>
  </rdf:Description>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            $triples = $result->graph->serialise('ntriples');

            expect($triples)->toContain('http://example.org/item');
            expect($triples)->toContain('http://example.org/content');
            // Should contain XMLLiteral datatype
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#XMLLiteral');
        });

        it('preserves nested XML elements and namespaces', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:ex="http://example.org/">
  <rdf:Description rdf:about="http://example.org/item">
    <ex:markup rdf:parseType="Literal">
      <div xmlns="http://www.w3.org/1999/xhtml"><p>Hello <em>world</em></p></div>
    </ex:markup>
  </rdf:Description>
</rdf:RDF>';

            $result = $this->handler->parse($content);

            $triples = $result->graph->serialise('ntriples');

            expect($triples)->toContain('http://example.org/markup');
            expect($triples)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#XMLLiteral');
        });
    });

    it('does not emit deprecation warnings on PHP 8.4+', function () {
        $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:ex="http://example.org/">
  <rdf:Description rdf:about="http://example.org/item">
    <ex:prop rdf:parseType="Resource">
      <ex:name>Test</ex:name>
    </ex:prop>
  </rdf:Description>
</rdf:RDF>';

        $deprecations = [];
        set_error_handler(function (int $errno, string $errstr) use (&$deprecations): bool {
            if ($errno === E_DEPRECATED) {
                $deprecations[] = $errstr;
                return true;
            }
            return false;
        });

        try {
            $result = $this->handler->parse($content);
        } finally {
            restore_error_handler();
        }

        expect($deprecations)->toBeEmpty();
        expect($result)->toBeInstanceOf(ParsedRdf::class);
        expect($result->graph->countTriples())->toBeGreaterThan(0);
    });
});
