<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserRdfXml\RdfXmlHandler;

describe('class_alias bridge', function () {

    describe('alias resolution', function () {
        it('resolves RdfXmlHandler from old namespace', function () {
            expect(class_exists('App\Services\Ontology\Parsers\Handlers\RdfXmlHandler'))->toBeTrue();
        });
    });

    describe('instanceof compatibility', function () {
        it('new RdfXmlHandler is instanceof old namespace name', function () {
            $handler = new RdfXmlHandler();
            expect($handler)->toBeInstanceOf('App\Services\Ontology\Parsers\Handlers\RdfXmlHandler');
        });

        it('old namespace resolves to same class as new namespace', function () {
            $oldReflection = new \ReflectionClass('App\Services\Ontology\Parsers\Handlers\RdfXmlHandler');
            $newReflection = new \ReflectionClass(RdfXmlHandler::class);
            expect($oldReflection->getName())->toBe($newReflection->getName());
        });
    });

    describe('deprecation warnings', function () {
        $captureDeprecations = function (): array {
            static $cache = null;
            if ($cache !== null) {
                return $cache;
            }

            $projectRoot = dirname(__DIR__, 2);
            $script = <<<'PHP'
<?php
$deprecations = [];
set_error_handler(function (int $errno, string $errstr) use (&$deprecations) {
    if ($errno === E_USER_DEPRECATED) {
        $deprecations[] = $errstr;
    }
    return true;
});
require $argv[1] . '/vendor/autoload.php';
class_exists('App\Services\Ontology\Parsers\Handlers\RdfXmlHandler');
echo json_encode($deprecations);
PHP;
            $tempFile = tempnam(sys_get_temp_dir(), 'alias_test_');
            if ($tempFile === false) {
                throw new \RuntimeException('Failed to create temp file');
            }
            file_put_contents($tempFile, $script);
            $output = shell_exec('php ' . escapeshellarg($tempFile) . ' ' . escapeshellarg($projectRoot)) ?? '[]';
            unlink($tempFile);

            $cache = json_decode($output, true) ?? [];

            return $cache;
        };

        it('triggers E_USER_DEPRECATED when old RdfXmlHandler class is referenced', function () use ($captureDeprecations) {
            expect($captureDeprecations())->toBeArray()->toHaveCount(1);
        });

        it('deprecation message contains old and new FQCN', function () use ($captureDeprecations) {
            $deprecations = $captureDeprecations();
            expect($deprecations[0])
                ->toContain('App\Services\Ontology\Parsers\Handlers\RdfXmlHandler')
                ->toContain('Youri\vandenBogert\Software\ParserRdfXml\RdfXmlHandler');
        });

        it('deprecation message mentions v2.0 removal', function () use ($captureDeprecations) {
            $deprecations = $captureDeprecations();
            expect($deprecations[0])->toContain('v2.0');
        });

        it('does NOT trigger deprecation at autoload time', function () {
            $projectRoot = dirname(__DIR__, 2);
            $script = <<<'PHP'
<?php
$deprecations = [];
set_error_handler(function (int $errno, string $errstr) use (&$deprecations) {
    if ($errno === E_USER_DEPRECATED) {
        $deprecations[] = $errstr;
    }
    return true;
});
require $argv[1] . '/vendor/autoload.php';
echo json_encode($deprecations);
PHP;
            $tempFile = tempnam(sys_get_temp_dir(), 'alias_test_');
            if ($tempFile === false) {
                throw new \RuntimeException('Failed to create temp file');
            }
            file_put_contents($tempFile, $script);
            $output = shell_exec('php ' . escapeshellarg($tempFile) . ' ' . escapeshellarg($projectRoot)) ?? '[]';
            unlink($tempFile);

            $deprecations = json_decode($output, true) ?? [];
            expect($deprecations)->toBeArray()->toHaveCount(0);
        });
    });

    describe('no aliases for internal classes', function () {
        it('does not eagerly alias RdfFormatHandlerInterface', function () {
            // Parser-core owns this alias. The handler package should not eagerly resolve it.
            // Using false = don't trigger autoloading, just check if already loaded in memory
            expect(class_exists('App\Services\Ontology\Parsers\Contracts\RdfFormatHandlerInterface', false))->toBeFalse();
        });

        it('does not eagerly alias ParsedRdf', function () {
            expect(class_exists('App\Services\Ontology\Parsers\ValueObjects\ParsedRdf', false))->toBeFalse();
        });

        it('does not eagerly alias OntologyImportException', function () {
            expect(class_exists('App\Services\Ontology\Exceptions\OntologyImportException', false))->toBeFalse();
        });
    });
});
