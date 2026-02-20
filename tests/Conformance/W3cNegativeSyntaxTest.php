<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserRdfXml\RdfXmlHandler;

/**
 * W3C RDF/XML Negative Syntax Tests (TestXMLNegativeSyntax).
 *
 * These tests exercise RdfXmlHandler against all W3C RDF/XML negative test cases.
 * The parser MUST reject each input .rdf file by throwing a ParseException.
 *
 * RdfXmlHandler implements semantic validation for:
 * - rdf:ID and rdf:nodeID NCName validation
 * - Forbidden element names (node and property positions)
 * - Deprecated attribute rejection (rdf:aboutEach, rdf:aboutEachPrefix, rdf:bagID)
 * - Conflicting attribute detection (rdf:about+rdf:nodeID, rdf:resource+rdf:nodeID, etc.)
 * - Duplicate rdf:ID detection within a document
 * - rdf:li as attribute rejection
 * - rdf:parseType + rdf:resource conflict
 *
 * Test cases sourced from: https://w3c.github.io/rdf-tests/rdf/rdf11/rdf-xml/
 * Manifest: tests/Fixtures/W3c/manifest.ttl
 *
 * Total active negative syntax tests in manifest: 40
 * All 40 tests pass (parser correctly rejects all invalid input).
 */

$fixtureBase = __DIR__ . '/../Fixtures/W3c';

/**
 * Parse manifest.ttl to extract active TestXMLNegativeSyntax test entries.
 *
 * @return array<string, array{action: string}>
 */
function getNegativeSyntaxTests(string $manifestPath): array
{
    $content = file_get_contents($manifestPath);
    if ($content === false) {
        throw new RuntimeException("Cannot read manifest: {$manifestPath}");
    }

    $tests = [];

    $blocks = preg_split('/\n(?=<#)/', $content);
    if ($blocks === false) {
        return $tests;
    }

    foreach ($blocks as $block) {
        $block = trim($block);
        if (! str_starts_with($block, '<#')) {
            continue;
        }

        if (! str_contains($block, 'rdft:TestXMLNegativeSyntax')) {
            continue;
        }

        if (! preg_match('/^<#([^>]+)>/', $block, $idMatch)) {
            continue;
        }
        $testId = $idMatch[1];

        if (! preg_match('/mf:action\s+<([^>]+)>/', $block, $actionMatch)) {
            continue;
        }
        $action = $actionMatch[1];

        $tests[$testId] = [
            'action' => $action,
        ];
    }

    return $tests;
}

dataset('w3c-negative-syntax-tests', function () use ($fixtureBase) {
    $tests = getNegativeSyntaxTests($fixtureBase . '/manifest.ttl');

    foreach ($tests as $testId => $testInfo) {
        yield $testId => [
            'inputPath' => $fixtureBase . '/' . $testInfo['action'],
        ];
    }
});

describe('W3C RDF/XML Negative Syntax Tests (TestXMLNegativeSyntax)', function () {

    beforeEach(function () {
        $this->handler = new RdfXmlHandler();
    });

    it('W3C TestXMLNegativeSyntax — should reject invalid input', function (string $inputPath) {
        $input = file_get_contents($inputPath);
        expect($input)->not->toBeFalse("Fixture file not readable: {$inputPath}");

        expect(fn () => $this->handler->parse($input))
            ->toThrow(ParseException::class);
    })->with('w3c-negative-syntax-tests');
});
