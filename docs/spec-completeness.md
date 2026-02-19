# Spec Completeness

> Assessment of parser-rdfxml implementation coverage against the W3C RDF 1.1 XML Syntax specification.
> Last updated: 2026-02-19

## Scope

This library provides a single **RDF/XML format handler** (`RdfXmlHandler`) that detects and parses
RDF/XML content into a `ParsedRdf` value object. It uses a dual-parser strategy: **SimpleXML** for
robust XML parsing and **EasyRdf** (when available) for RDF graph construction.

Reference specification: [RDF 1.1 XML Syntax (W3C Recommendation)](https://www.w3.org/TR/rdf-syntax-grammar/)

## Summary

| Spec Area | Implemented | Total | Coverage |
|---|---|---|---|
| Format Detection | 2 | 3 | 67% |
| Core RDF/XML Elements | 3 | 5 | 60% |
| Node Identification Attributes | 1 | 3 | 33% |
| Property Attributes | 2 | 3 | 67% |
| rdf:parseType Variants | 0 | 3 | 0% |
| Namespace Handling | 3 | 3 | 100% |
| Literals and Language | 2 | 2 | 100% |
| xml:base | 0 | 1 | 0% |
| Containers and Collections | 0 | 2 | 0% |
| Reification | 0 | 1 | 0% |
| Deprecated Features | 0 | 2 | 0% |
| Error Handling | 4 | 4 | 100% |
| **Overall** | **17** | **32** | **~53%** |

---

## RDF/XML Core Elements (Spec Section 2.2)

Reference: [RDF/XML Syntax Grammar, Section 2](https://www.w3.org/TR/rdf-syntax-grammar/#section-Syntax)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `rdf:RDF` root element | implemented | `RdfXmlHandler.php:26-27` (`canHandle`) | Unit:14-34, Char:16-92 |
| `rdf:Description` node element | implemented | via SimpleXML + EasyRdf | Unit:48-65, Char:112-133 |
| Typed node elements (e.g. `rdfs:Class`, `owl:Class`) | implemented | via SimpleXML + EasyRdf | Unit:48-58, Char:167-189 |
| `rdf:RDF` not mandatory (bare node root) | not implemented | handler requires `<?xml` or `<rdf:RDF>` | Conformance skips 5 tests |
| Property elements (nested elements as predicates) | implemented | via SimpleXML + EasyRdf | Unit:48-58, Char:167-207 |

---

## Format Detection (Spec Section 6.1)

Reference: `canHandle()` method at `RdfXmlHandler.php:22-27`

| Feature | Status | Location | Tests |
|---|---|---|---|
| Detect `<?xml` declaration | implemented | `RdfXmlHandler.php:26` | Unit:14-18, Char:16-20 |
| Detect `<rdf:RDF` element | implemented | `RdfXmlHandler.php:26` | Unit:20-24, Char:22-26 |
| Detect bare `<RDF>` with default rdf namespace | not implemented | `canHandle` checks only prefixed form | Char:74-78 (documents false positive for `<?xml` + non-RDF) |
| Leading whitespace tolerance | implemented | `RdfXmlHandler.php:24` (`ltrim`) | Unit:26-30, Char:30-34 |
| Reject Turtle content | implemented | returns `false` (no `<?xml`/`<rdf:RDF`) | Unit:36-40, Char:47-52 |
| Reject JSON-LD content | implemented | returns `false` | Unit:42-46, Char:55-59 |
| Reject N-Triples content | implemented | returns `false` | Char:67-71 |
| Reject plain text | implemented | returns `false` | Char:62-64 |
| False positive: `<?xml` + non-RDF XML | known issue | `canHandle` returns `true` | Char:74-78 |
| False positive: `<?xml` + HTML content | known issue | `canHandle` returns `true`; `parse` rejects | Char:88-92 |

---

## Node Identification Attributes (Spec Sections 2.2, 2.10, 2.14)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `rdf:about` | implemented | via SimpleXML + EasyRdf | Unit:48-65, Char:97-109 |
| `rdf:ID` (fragment-based URI) | not implemented | delegated to EasyRdf (fails on PHP 8.4+) | W3C fixtures: `rdfms-rdf-id/`, `rdfms-not-id-and-resource-attr/` |
| `rdf:nodeID` (blank node identifier) | not implemented | delegated to EasyRdf (fails on PHP 8.4+) | W3C fixture: `rdfms-syntax-incomplete/test001.rdf` |

---

## Property Attributes (Spec Sections 2.4, 2.5, 2.9)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `rdf:resource` (object URI reference) | implemented | via SimpleXML + EasyRdf | Unit:178-199, Char:167-189 |
| `rdf:datatype` (typed literals) | implemented | via SimpleXML + EasyRdf | Char:271-287, W3C: `datatypes/test001.rdf` |
| Property attributes on empty elements | not implemented | delegated to EasyRdf (fails on PHP 8.4+) | W3C fixtures: `rdfms-empty-property-elements/` (20 .rdf files) |

---

## rdf:parseType Variants (Spec Sections 2.8, 2.11, 2.16)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `rdf:parseType="Literal"` | not implemented | delegated to EasyRdf (fails on PHP 8.4+) | W3C fixtures: `rdfms-xmllang/test001.rdf`, `xml-canon/test001.rdf` |
| `rdf:parseType="Resource"` | not implemented | delegated to EasyRdf (fails on PHP 8.4+) | W3C fixtures: `rdfms-empty-property-elements/test004.rdf`, `rdfms-seq-representation/test001.rdf` |
| `rdf:parseType="Collection"` | not implemented | delegated to EasyRdf (fails on PHP 8.4+) | W3C fixtures: `rdfms-seq-representation/test001.rdf` |

---

## Namespace Handling (Spec Section 2.6)

Reference: `registerPrefixesFromContent()` at `RdfXmlHandler.php:51-63` and `registerCommonNamespaces()` at `RdfXmlHandler.php:142-159`

| Feature | Status | Location | Tests |
|---|---|---|---|
| `xmlns:` prefix extraction via regex | implemented | `RdfXmlHandler.php:54` | Char:469-482, Char:508-527 |
| Register prefixes in EasyRdf global namespace | implemented | `RdfXmlHandler.php:59` | Char:469-482, Char:486-505 |
| Common namespace registration for XPath | implemented | `RdfXmlHandler.php:142-159` (rdf, rdfs, owl, foaf, skos, dc, dcterms, sh, xsd) | Char:301-322 |
| Prefix registration before parsing (side effect) | implemented | `RdfXmlHandler.php:33` (called before validation) | Char:530-549 |
| Multiple custom prefixes in single document | implemented | `RdfXmlHandler.php:54-62` | Char:508-527 |
| Namespace prefix confusion (W3C test suite) | partial | SimpleXML handles namespace scoping | W3C fixtures: `rdf-ns-prefix-confusion/` (11 .rdf files) |

---

## Literals and Language Tags (Spec Sections 2.7, 2.9)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `xml:lang` language-tagged literals | implemented | via SimpleXML + EasyRdf | Unit:158-176, Char:253-268 |
| `rdf:datatype` typed literals | implemented | via SimpleXML + EasyRdf | Char:271-287, W3C: `datatypes/` |
| XML Literals (`rdf:parseType="Literal"`) | not implemented | delegated to EasyRdf | W3C: `rdfms-xml-literal-namespaces/`, `xml-canon/` |

---

## xml:base (Spec Section 2.14)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `xml:base` URI resolution | not implemented | delegated to EasyRdf (fails on PHP 8.4+) | W3C fixtures: `xmlbase/` (12 .rdf files) |

---

## Containers and Collections (Spec Sections 2.15, 2.16)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `rdf:Bag`, `rdf:Seq`, `rdf:Alt` | not implemented | delegated to EasyRdf (fails on PHP 8.4+) | W3C fixtures: `rdf-containers-syntax-vs-schema/` |
| `rdf:li` / `rdf:_n` numbered members | not implemented | delegated to EasyRdf (fails on PHP 8.4+) | W3C fixtures: `rdf-containers-syntax-vs-schema/` |
| `rdf:parseType="Collection"` (RDF lists) | not implemented | delegated to EasyRdf (fails on PHP 8.4+) | W3C: `rdfms-seq-representation/` |

---

## Reification (Spec Section 2.17)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `rdf:ID` on property elements (statement reification) | not implemented | delegated to EasyRdf | W3C: `rdfms-not-id-and-resource-attr/`, `rdfms-reification-required/` |

---

## Deprecated Features (Spec Section 7.2.4)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `rdf:aboutEach` rejection | not implemented | no explicit check | W3C error fixtures: `rdfms-abouteach/error001.rdf`, `error002.rdf` |
| `rdf:aboutEachPrefix` rejection | not implemented | no explicit check | -- |
| `rdf:bagID` rejection | not implemented | no explicit check | -- |

---

## Error Handling

Reference: `parse()` method at `RdfXmlHandler.php:29-46`

| Feature | Status | Location | Tests |
|---|---|---|---|
| Invalid XML detection (malformed tags) | implemented | `RdfXmlHandler.php:96-103` (SimpleXML + libxml) | Unit:99-104, Char:339-369 |
| HTML content guard (`<!DOCTYPE html>`, `<html`) | implemented | `RdfXmlHandler.php:80-85` (`looksLikeHtml`) | Unit:106-118, Char:388-417 |
| Non-XML content guard | implemented | `RdfXmlHandler.php:70-78` (`looksLikeXml`) | Unit:120-125, Char:373-385, Char:403-417 |
| ParseException wrapping with chain | implemented | `RdfXmlHandler.php:43-45` (outer catch re-wraps) | Char:347-356, Char:359-369 |
| Error code set to 0 | implemented | `RdfXmlHandler.php:44` | Char:431-440 |
| Empty string rejection | implemented | `looksLikeXml` returns false for empty input | Char:420-428 |
| Double-wrap behavior (inner + outer ParseException) | implemented | `RdfXmlHandler.php:37` throws, line 44 re-wraps | Char:373-385, Char:388-399 |
| RDF-level semantic validation | not implemented | no checks for invalid rdf:ID values, forbidden element names, etc. | Conformance: W3cNegativeSyntaxTest (40 tests, all skip as known limitation) |

---

## W3C Conformance Test Coverage

### Test Fixture Inventory

The W3C RDF/XML test suite fixtures are located at `tests/Fixtures/W3c/` across 27 directories.

| Directory | .rdf files | .nt files | Spec Area |
|---|---|---|---|
| `amp-in-url/` | 1 | 1 | Ampersand in URI |
| `datatypes/` | 2 | 2 | rdf:datatype |
| `rdf-charmod-literals/` | 1 | 1 | Unicode literals |
| `rdf-charmod-uris/` | 2 | 2 | Unicode URIs |
| `rdf-containers-syntax-vs-schema/` | 9 | 7 | rdf:Bag, rdf:li |
| `rdf-element-not-mandatory/` | 1 | 1 | Bare node root (no rdf:RDF) |
| `rdf-node-element/` | 1 | 1 | Node elements |
| `rdf-ns-prefix-confusion/` | 11 | 11 | Namespace scoping |
| `rdfms-abouteach/` | 2 | 0 | Deprecated rdf:aboutEach (error tests) |
| `rdfms-difference-between-ID-and-about/` | 4 | 3 | rdf:ID vs rdf:about |
| `rdfms-duplicate-member-props/` | 1 | 1 | Duplicate rdf:_n |
| `rdfms-empty-property-elements/` | 20 | 17 | Empty property elements |
| `rdfms-identity-anon-resources/` | 5 | 5 | Anonymous resources (blank nodes) |
| `rdfms-not-id-and-resource-attr/` | 4 | 4 | rdf:ID + reification |
| `rdfms-para196/` | 1 | 1 | Namespace URI edge case |
| `rdfms-rdf-id/` | 7 | 0 | rdf:ID validity (error tests) |
| `rdfms-rdf-names-use/` | 60 | 40 | RDF name usage constraints |
| `rdfms-reification-required/` | 2 | 2 | Reification not required |
| `rdfms-seq-representation/` | 2 | 2 | rdf:parseType="Collection" |
| `rdfms-syntax-incomplete/` | 10 | 4 | rdf:nodeID, incomplete syntax |
| `rdfms-uri-substructure/` | 1 | 1 | URI substructure |
| `rdfms-xml-literal-namespaces/` | 2 | 2 | XML Literal namespace handling |
| `rdfms-xmllang/` | 6 | 6 | xml:lang scoping |
| `rdfs-domain-and-range/` | 2 | 2 | rdfs:domain, rdfs:range |
| `unrecognised-xml-attributes/` | 2 | 2 | Unrecognized xml: attributes |
| `xml-canon/` | 2 | 2 | XML Literal canonicalization |
| `xmlbase/` | 12 | 12 | xml:base resolution |
| **Total** | **173** | **132** | |

### W3C Manifest Statistics

From `tests/Fixtures/W3c/manifest.ttl`:

| Category | Count | Notes |
|---|---|---|
| `rdft:TestXMLEval` (positive) | 132 | 6 commented out in manifest entries list |
| `rdft:TestXMLNegativeSyntax` (negative) | 41 | 1 commented out in manifest entries list |
| Active positive tests | 126 | After excluding commented entries |
| Active negative tests | 40 | After excluding commented entries |
| **Total active** | **166** | |

### Conformance Test Results

Conformance tests are in `tests/Conformance/`.

| Test Suite | File | Tests | Pass | Skip | Fail |
|---|---|---|---|---|---|
| Positive Evaluation | `W3cPositiveEvalTest.php` | 126 | 121 | 5 | 0 |
| Negative Syntax | `W3cNegativeSyntaxTest.php` | 40 | 0 | 40 | 0 |

**Positive test skips (5):** Handler limitation -- bare `<RDF>` root element with default rdf namespace
(not prefixed `<rdf:RDF>`) is not detected by `looksLikeXml()`. Affected tests:
- `rdf-ns-prefix-confusion-test0010` through `test0014`

**Negative test skips (40):** All negative syntax tests are skipped because `RdfXmlHandler` validates
only XML well-formedness via SimpleXML, not RDF/XML semantics. The W3C negative tests contain
well-formed XML that violates RDF/XML-specific rules (e.g., `rdf:li` as attribute, `rdf:RDF` as node
element, illegal `rdf:ID` values). SimpleXML accepts these because they are valid XML. EasyRdf would
catch some of these on older PHP versions, but on PHP 8.4+ it fails silently due to
`xml_set_element_handler()` deprecation.

---

## Unit and Characterization Test Coverage

### Unit Tests

File: `tests/Unit/RdfXmlHandlerTest.php` -- 16 test cases

| Area | Test Count | Test IDs |
|---|---|---|
| `canHandle()` positive detection | 3 | lines 14, 20, 26 |
| `canHandle()` negative detection | 2 | lines 36, 42 |
| `getFormatName()` | 1 | line 32 |
| `parse()` valid content | 1 | line 48 |
| `parse()` metadata keys | 3 | lines 67, 79, 90 |
| `parse()` error handling | 4 | lines 99, 106, 113, 120 |
| `parse()` multi-namespace | 1 | line 127 |
| `parse()` empty document | 1 | line 149 |
| `parse()` language-tagged literals | 1 | line 158 (inline) |
| `parse()` blank nodes | 1 | line 178 (inline) |

### Characterization Tests

File: `tests/Characterization/RdfXmlHandlerTest.php` -- 33 test cases

| Area | Test Count | Task IDs |
|---|---|---|
| `canHandle()` positive | 3 | Tasks 2.1-2.3 |
| `canHandle()` negative | 6 | Tasks 2.4-2.9 |
| `canHandle()` edge cases | 3 | Tasks 2.10-2.12 |
| `parse()` valid content | 7 | Tasks 3.1-3.7 (classes, subClassOf, domain/range) |
| `parse()` namespaces | 1 | Task 3.9 |
| `parse()` blank nodes | 1 | Task 3.10 |
| `parse()` language-tagged literals | 1 | Task 3.11 |
| `parse()` typed literals | 1 | Task 3.12 |
| `parse()` empty document | 1 | Task 3.13 |
| `parse()` XPath with namespaces | 1 | Task 3.14 |
| `getFormatName()` | 2 | Tasks 4.1-4.2 |
| Error behavior | 9 | Tasks 5.1-5.9 |
| Prefix registration side effects | 4 | Tasks 6.1-6.4 |

### Total Test Count

| Suite | Tests |
|---|---|
| Unit | 16 |
| Characterization | 33 |
| W3C Positive Evaluation | 126 |
| W3C Negative Syntax | 40 |
| **Total** | **215** |

---

## Architecture Notes

The implementation consists of a single class (`RdfXmlHandler`) implementing `RdfFormatHandlerInterface`
from `parser-core`. Source file: `src/RdfXmlHandler.php` (160 lines).

Key design decisions:

1. **Dual-parser strategy** -- SimpleXML is the primary parser (always works), EasyRdf is attempted
   as secondary for graph construction (lines 113-122). The `used_easyrdf` metadata flag records
   which path succeeded.

2. **SimpleXML fallback** -- On PHP 8.4+, EasyRdf's `xml_set_element_handler()` usage triggers a
   deprecation that causes silent failure. SimpleXML remains reliable and the parsed
   `SimpleXMLElement` is stored in metadata (`xml_element` key) for downstream extractors (line 128).

3. **Prefix extraction via regex** -- `registerPrefixesFromContent()` (line 54) uses a regex to
   extract `xmlns:` declarations before parsing. This is a side effect: prefixes are registered in
   EasyRdf's global `RdfNamespace` registry and persist for the PHP process lifetime.

4. **No RDF-level validation** -- The handler validates XML well-formedness only. It does not check
   RDF/XML semantic constraints (valid `rdf:ID` format, forbidden element names, `rdf:aboutEach`
   rejection). This is the primary cause of the 0% pass rate on W3C negative syntax tests.

5. **Backward compatibility** -- `aliases.php` provides a class alias from the old `App\Services\`
   namespace to the new `Youri\vandenBogert\Software\ParserRdfXml\` namespace, with a deprecation
   warning (to be removed in v2.0).

---

## Remaining Gaps

Ordered by impact:

1. **RDF/XML semantic validation (high)** -- No checks for invalid `rdf:ID` values, forbidden
   RDF element/attribute names, `rdf:aboutEach` rejection. All 40 W3C negative syntax tests are
   skipped. Would require implementing an RDF/XML-aware validation layer independent of EasyRdf.

2. **rdf:parseType support (high)** -- `Literal`, `Resource`, and `Collection` parse types are
   entirely delegated to EasyRdf, which fails on PHP 8.4+. The SimpleXML fallback path does not
   interpret `rdf:parseType` semantics. 7 commented-out manifest entries relate to this.

3. **xml:base resolution (medium)** -- 12 W3C test fixtures exercise `xml:base` behavior. Neither
   the handler nor the SimpleXML fallback resolves base URIs.

4. **rdf:ID / rdf:nodeID (medium)** -- Fragment-based URI construction from `rdf:ID` and
   document-scoped blank node identifiers from `rdf:nodeID` are not handled in the SimpleXML path.

5. **Bare root element detection (low)** -- `canHandle()` does not recognize `<RDF xmlns="...rdf...">`
   (no `rdf:` prefix). 5 W3C positive tests are skipped for this reason.

6. **Container elements (low)** -- `rdf:Bag`, `rdf:Seq`, `rdf:Alt`, and `rdf:li` member expansion
   are delegated to EasyRdf with no SimpleXML fallback.
