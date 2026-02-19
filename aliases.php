<?php

declare(strict_types=1);

// Class alias bridge for backward compatibility with App\ namespace.
// Maps old App\Services\Ontology\Parsers\Handlers\RdfXmlHandler to the new
// Youri\vandenBogert\Software\ParserRdfXml\RdfXmlHandler class.
// This alias will be removed in v2.0.
//
// Deprecation warnings are triggered ONLY when the old namespace class is actually
// used (via autoload), not on every request.

spl_autoload_register(function (string $class): void {
    /** @var array<string, class-string> $aliases */
    $aliases = [
        'App\Services\Ontology\Parsers\Handlers\RdfXmlHandler' => \Youri\vandenBogert\Software\ParserRdfXml\RdfXmlHandler::class,
    ];

    if (isset($aliases[$class])) {
        @trigger_error(
            sprintf(
                'Using "%s" is deprecated, use "%s" instead. This alias will be removed in v2.0.',
                $class,
                $aliases[$class],
            ),
            E_USER_DEPRECATED,
        );
        class_alias($aliases[$class], $class);
    }
});
