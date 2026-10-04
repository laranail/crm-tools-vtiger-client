<?php

declare(strict_types=1);

/**
 * Every class a reader is told to import must exist.
 *
 * The README once opened with `use Simtabi\Laranail\CrmTools\VtigerClient;` -- a namespace, not a
 * class -- followed by `new VtWsClient()`, so the first example a reader copied could not run.
 * This reads the PHP fences in README.md and docs/**, resolves every `use` import, `new X`, `X::`
 * and fully-qualified name against the autoloader, and does the same for backticked `Simtabi\...`
 * names in prose.
 *
 * Exempt, with a reason each. A stale entry (a file that no longer exists) fails.
 */
const DOCUMENTED_CLASSES_EXEMPT = [
    // Legacy long-form examples awaiting an owner decision on whether the page is kept, relocated or
    // retired. Measured 2026-10-04: its one `use` imports the namespace `Simtabi\Laranail\CrmTools\VtigerClient`,
    // which is not a class. Remove this entry once that page is decided.
    'docs/index.md',
];

/** @return list<string> repository-relative markdown files to scan */
function documentedClassesFiles(string $root): array
{
    $files = ['README.md'];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/docs', FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->getExtension() === 'md') {
            $files[] = ltrim(str_replace($root, '', $file->getPathname()), '/');
        }
    }

    sort($files);

    return array_values(array_diff($files, DOCUMENTED_CLASSES_EXEMPT));
}

/**
 * Class names a markdown document refers to: in PHP fences (imports, `new`, static access, FQNs)
 * and in inline code spans naming a `Simtabi\` class.
 *
 * @return list<string> fully-qualified names, without a leading backslash
 */
function documentedClassNames(string $markdown): array
{
    $names = [];

    preg_match_all('/^```php\s*\n(.*?)^```/ms', $markdown, $fences);

    foreach ($fences[1] as $code) {
        $imports = [];

        preg_match_all('/^\s*use\s+\\\\?([A-Za-z_][\w\\\\]*)(?:\s+as\s+(\w+))?\s*;/m', $code, $uses, PREG_SET_ORDER);
        foreach ($uses as $use) {
            $fqn = $use[1];
            $alias = $use[2] ?? '';
            $imports[$alias !== '' ? $alias : substr(strrchr('\\' . $fqn, '\\'), 1)] = $fqn;
            $names[] = $fqn;
        }

        // Strip the import lines so their names are not re-read below as relative references.
        $body = preg_replace('/^\s*use\s+[^;]+;/m', '', $code);

        preg_match_all('/\bnew\s+(\\\\?[A-Za-z_]\w*(?:\\\\\w+)*)/', $body, $created);
        preg_match_all('/(?<![\w\\\\$>:])(\\\\?[A-Z]\w*(?:\\\\\w+)*)::/', $body, $statics);
        foreach ([...$created[1], ...$statics[1]] as $ref) {
            if (in_array(strtolower($ref), ['self', 'static', 'parent'], true)) {
                continue;
            }

            if (str_starts_with($ref, '\\')) {
                $names[] = substr($ref, 1);

                continue;
            }

            $head = explode('\\', $ref, 2);
            $names[] = isset($imports[$head[0]])
                ? $imports[$head[0]] . (isset($head[1]) ? '\\' . $head[1] : '')
                : $ref;
        }
    }

    // Inline code spans in prose (outside fences) that name a class in this vendor's namespace.
    $prose = preg_replace('/^```.*?^```/ms', '', $markdown);
    preg_match_all('/`\\\\?(Simtabi\\\\[\w\\\\]+)`/', $prose, $inline);
    array_push($names, ...$inline[1]);

    return $names;
}

/** Whether a name resolves to a class, interface, trait or enum through the autoloader. */
function documentedNameExists(string $name): bool
{
    return class_exists($name) || interface_exists($name) || trait_exists($name) || enum_exists($name);
}

it('keeps every exemption pointing at a real file', function (): void {
    $root = dirname(__DIR__, 2);

    // A ceiling, not a licence: adding a second exemption is a decision, not a fix.
    expect(count(DOCUMENTED_CLASSES_EXEMPT))->toBeLessThanOrEqual(1);

    foreach (DOCUMENTED_CLASSES_EXEMPT as $exempt) {
        expect(is_file($root . '/' . $exempt))->toBeTrue("Stale exemption: {$exempt} no longer exists");
    }
});

it('names only classes that exist in README and docs', function (): void {
    $root = dirname(__DIR__, 2);
    $files = documentedClassesFiles($root);

    // README plus nine docs pages, measured 2026-10-04. A glob that stops matching must not pass.
    expect(count($files))->toBeGreaterThanOrEqual(10);

    $checked = 0;
    $missing = [];

    foreach ($files as $file) {
        foreach (documentedClassNames((string) file_get_contents($root . '/' . $file)) as $name) {
            $checked++;

            if (! documentedNameExists($name)) {
                $missing[] = "{$file}: {$name}";
            }
        }
    }

    // Five references measured 2026-10-04 (README import + ::class, getting-started import, three inline FQNs).
    expect($checked)->toBeGreaterThanOrEqual(5)
        ->and($missing)->toBe([]);
});

it('catches a namespace imported as if it were a class', function (): void {
    $names = documentedClassNames(<<<'MD'
        ```php
        use Simtabi\Laranail\CrmTools\VtigerClient;

        $client = new VtWsClient();
        ```
        MD);

    expect($names)->toContain('Simtabi\Laranail\CrmTools\VtigerClient')
        ->and(documentedNameExists('Simtabi\Laranail\CrmTools\VtigerClient'))->toBeFalse();
});
