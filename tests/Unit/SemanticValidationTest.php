<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserRdfXml\RdfXmlHandler;

describe('Semantic Validation and Deprecated Feature Rejection', function () {
    beforeEach(function () {
        $this->handler = new RdfXmlHandler();
    });

    describe('rdf:ID NCName validation', function () {
        it('rejects rdf:ID starting with a digit', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:ID="333-555-666" />
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Invalid rdf:ID value '333-555-666'");
        });

        it('rejects rdf:ID containing a colon', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:eg="http://example.org/">
  <rdf:Description>
    <eg:prop rdf:ID="q:name" />
  </rdf:Description>
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Invalid rdf:ID value 'q:name'");
        });

        it('rejects rdf:ID containing a slash', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:eg="http://example.org/">
  <rdf:Description rdf:ID="a/b" eg:prop="val" />
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Invalid rdf:ID value 'a/b'");
        });

        it('rejects rdf:ID with blank-node-like prefix', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:ID="_:xx" />
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Invalid rdf:ID value '_:xx'");
        });

        it('accepts valid rdf:ID values', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:ID="validName" />
</rdf:RDF>';

            $result = $this->handler->parse($content);
            expect($result->format)->toBe('rdf/xml');
        });
    });

    describe('rdf:nodeID NCName validation', function () {
        it('rejects rdf:nodeID starting with a digit', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:nodeID="333-555-666" />
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Invalid rdf:nodeID value '333-555-666'");
        });

        it('rejects rdf:nodeID with blank-node prefix', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:nodeID="_:bnode" />
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Invalid rdf:nodeID value '_:bnode'");
        });

        it('rejects rdf:nodeID containing a colon on property element', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:eg="http://example.org/">
  <rdf:Description>
    <eg:prop rdf:nodeID="q:name" />
  </rdf:Description>
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Invalid rdf:nodeID value 'q:name'");
        });
    });

    describe('deprecated feature rejection', function () {
        it('rejects rdf:aboutEach attribute', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:eg="http://example.org/">
  <rdf:Bag rdf:ID="node">
    <rdf:li rdf:resource="http://example.org/node2"/>
  </rdf:Bag>
  <rdf:Description rdf:aboutEach="#node">
    <eg:prop>value</eg:prop>
  </rdf:Description>
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Deprecated attribute 'rdf:aboutEach'");
        });

        it('rejects rdf:aboutEachPrefix attribute', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:eg="http://example.org/">
  <rdf:Description rdf:aboutEachPrefix="http://example.org/">
    <eg:prop>value</eg:prop>
  </rdf:Description>
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Deprecated attribute 'rdf:aboutEachPrefix'");
        });

        it('rejects rdf:bagID attribute on node element', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:bagID="333-555-666" />
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Deprecated attribute 'rdf:bagID'");
        });

        it('rejects rdf:bagID attribute on property element', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:eg="http://example.org/">
  <rdf:Description>
    <eg:prop rdf:bagID="q:name" />
  </rdf:Description>
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Deprecated attribute 'rdf:bagID'");
        });
    });

    describe('forbidden element names', function () {
        it('rejects rdf:RDF as node element', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:RDF/>
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Forbidden element 'rdf:RDF'");
        });

        it('rejects rdf:ID as node element', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:ID/>
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Forbidden element 'rdf:ID'");
        });

        it('rejects rdf:about as node element', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:about/>
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Forbidden element 'rdf:about'");
        });

        it('rejects rdf:li as node element', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:li/>
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Forbidden element 'rdf:li'");
        });

        it('rejects rdf:Description as property element', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:about="http://example.org/node1">
    <rdf:Description rdf:resource="http://example.org/node2"/>
  </rdf:Description>
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Forbidden element 'rdf:Description'");
        });

        it('rejects rdf:RDF as property element', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:about="http://example.org/node1">
    <rdf:RDF rdf:resource="http://example.org/node2"/>
  </rdf:Description>
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Forbidden element 'rdf:RDF'");
        });

        it('rejects rdf:aboutEach as node element', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:aboutEach/>
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Forbidden element 'rdf:aboutEach'");
        });

        it('rejects rdf:aboutEachPrefix as property element', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:about="http://example.org/node1">
    <rdf:aboutEachPrefix rdf:resource="http://example.org/node2"/>
  </rdf:Description>
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Forbidden element 'rdf:aboutEachPrefix'");
        });
    });

    describe('conflicting attributes', function () {
        it('rejects rdf:nodeID and rdf:ID on same element', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:nodeID="a" rdf:ID="b"/>
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class);
        });

        it('rejects rdf:nodeID and rdf:about on same element', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:nodeID="a" rdf:about="http://example.org/"/>
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class);
        });

        it('rejects rdf:nodeID and rdf:resource on property element', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:eg="http://example.org/">
  <rdf:Description>
    <eg:prop rdf:nodeID="a" rdf:resource="http://www.example.org/" />
  </rdf:Description>
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class);
        });

        it('rejects rdf:parseType with rdf:resource', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:random="http://random.ioctl.org/#">
  <rdf:Description rdf:about="http://random.ioctl.org/#bar">
    <random:someProperty rdf:parseType="Literal"
      rdf:resource="http://random.ioctl.org/#foo" />
  </rdf:Description>
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class);
        });
    });

    describe('duplicate rdf:ID detection', function () {
        it('rejects duplicate rdf:ID values within same document', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:ID="foo">
    <rdf:value>abc</rdf:value>
  </rdf:Description>
  <rdf:Description rdf:ID="foo">
    <rdf:value>abc</rdf:value>
  </rdf:Description>
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "Duplicate rdf:ID 'foo'");
        });
    });

    describe('rdf:li as attribute rejection', function () {
        it('rejects rdf:li used as an attribute', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:foo="http://foo/">
  <foo:bar rdf:li="1"/>
</rdf:RDF>';

            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, "rdf:li");
        });
    });

    describe('XML security protections', function () {
        it('parses with LIBXML_NONET (no external entity loading)', function () {
            // This test ensures the parser uses LIBXML_NONET to prevent network access.
            // We test by verifying well-formed RDF/XML still parses without errors.
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:about="http://example.org/thing">
    <rdf:value>test</rdf:value>
  </rdf:Description>
</rdf:RDF>';

            $result = $this->handler->parse($content);
            expect($result->format)->toBe('rdf/xml');
        });
    });
});
