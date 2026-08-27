<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Typography;

use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Console\Tools\Support\Style;
use Simtabi\Laranail\Console\Tools\Theme\Theme;

/**
 * A deliberately small, regex-based syntax highlighter for fenced code blocks.
 * Supports `php`, `json`, `bash`, `yaml`, `js`, `python`, `sql`, `html`, `css` and
 * `diff` (plus aliases: `sh`/`shell`/`zsh`, `yml`, `javascript`/`node`/`mjs`, `py`,
 * `xml`/`htm`, `patch`) — comments, strings, numbers, keywords/keys/variables; any
 * other language renders plain. Per-line (no cross-line string or comment state) —
 * good enough for docs, not a full lexer. Colours come from the theme.
 *
 * Each token language is a {@see tokenSpec() spec}: one regex of named groups plus an
 * ordered `group => theme-role` map; {@see applyTokens()} drives them all. Add a
 * language by adding a spec (and `diff`, which is whole-line, in {@see highlightDiff()}).
 */
final readonly class SyntaxHighlighter
{
    private const array PHP_KEYWORDS = [
        'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class',
        'clone', 'const', 'continue', 'declare', 'default', 'do', 'echo', 'else', 'elseif',
        'enum', 'extends', 'final', 'finally', 'fn', 'for', 'foreach', 'function', 'global',
        'if', 'implements', 'instanceof', 'interface', 'match', 'namespace', 'new', 'or',
        'private', 'protected', 'public', 'readonly', 'return', 'static', 'switch', 'throw',
        'trait', 'try', 'use', 'var', 'while', 'yield', 'true', 'false', 'null',
    ];

    private const array BASH_KEYWORDS = [
        'if', 'then', 'else', 'elif', 'fi', 'for', 'while', 'until', 'do', 'done',
        'case', 'esac', 'in', 'select', 'function', 'return', 'break', 'continue',
        'export', 'local', 'readonly', 'declare', 'echo', 'printf', 'cd', 'exit',
        'set', 'unset', 'source', 'eval', 'exec', 'trap', 'shift',
    ];

    private const array JS_KEYWORDS = [
        'const', 'let', 'var', 'function', 'return', 'if', 'else', 'for', 'while',
        'do', 'switch', 'case', 'break', 'continue', 'class', 'extends', 'new',
        'this', 'super', 'import', 'export', 'from', 'default', 'async', 'await',
        'yield', 'typeof', 'instanceof', 'in', 'of', 'delete', 'void', 'try',
        'catch', 'finally', 'throw', 'null', 'true', 'false', 'undefined',
    ];

    private const array PYTHON_KEYWORDS = [
        'def', 'class', 'if', 'elif', 'else', 'for', 'while', 'return', 'import',
        'from', 'as', 'with', 'try', 'except', 'finally', 'raise', 'lambda', 'pass',
        'break', 'continue', 'yield', 'global', 'nonlocal', 'async', 'await', 'and',
        'or', 'not', 'in', 'is', 'None', 'True', 'False', 'self', 'del', 'assert',
    ];

    private const array SQL_KEYWORDS = [
        'select', 'from', 'where', 'insert', 'into', 'values', 'update', 'set',
        'delete', 'create', 'alter', 'drop', 'table', 'index', 'view', 'join',
        'left', 'right', 'inner', 'outer', 'full', 'on', 'group', 'by', 'order',
        'having', 'limit', 'offset', 'distinct', 'as', 'and', 'or', 'not', 'null',
        'is', 'in', 'like', 'between', 'union', 'all', 'asc', 'desc', 'count',
        'sum', 'avg', 'min', 'max', 'primary', 'key', 'foreign', 'references',
    ];

    /**
     * Maximum line length to tokenize — beyond this a line is returned unhighlighted.
     * Real code lines are far shorter; this bounds the regex cost so a pathological
     * very-long single line (e.g. an unterminated comment/string) can't cause
     * quadratic backtracking. CodeBlock already clips to the terminal width.
     */
    private const int MAX_LINE = 4000;

    private Capabilities $capabilities;

    private Theme $theme;

    public function __construct(?Capabilities $capabilities = null, ?Theme $theme = null)
    {
        $this->capabilities = $capabilities ?? Capabilities::detect();
        $this->theme = $theme ?? Theme::resolve();
    }

    public static function make(?Capabilities $capabilities = null, ?Theme $theme = null): self
    {
        return new self($capabilities, $theme);
    }

    public function supports(string $language): bool
    {
        $language = $this->normalize($language);

        return $language === 'diff' || $this->tokenSpec($language) !== null;
    }

    /**
     * Highlight one line. Assumes plain input (no existing ANSI); returns a styled
     * string. Unknown languages — or lines longer than {@see MAX_LINE} — are
     * returned unchanged.
     */
    public function highlightLine(string $line, string $language): string
    {
        if (strlen($line) > self::MAX_LINE) {
            return $line;
        }

        $language = $this->normalize($language);

        if ($language === 'diff') {
            return $this->highlightDiff($line);
        }

        $spec = $this->tokenSpec($language);

        return $spec === null ? $line : $this->applyTokens($line, $spec['pattern'], $spec['roles']);
    }

    /**
     * Normalise common language aliases to a canonical key.
     */
    private function normalize(string $language): string
    {
        return match (strtolower(trim($language))) {
            'sh', 'shell', 'zsh' => 'bash',
            'yml' => 'yaml',
            'javascript', 'node', 'mjs' => 'js',
            'py' => 'python',
            'xml', 'htm' => 'html',
            'patch' => 'diff',
            default => strtolower(trim($language)),
        };
    }

    /**
     * The token-highlighting spec for a canonical language, or null if unsupported.
     * `pattern` is a single regex of named groups; `roles` maps each group, in
     * priority order, to a theme colour role.
     *
     * @return array{pattern: string, roles: array<string, string>}|null
     */
    private function tokenSpec(string $language): ?array
    {
        return match ($language) {
            'php' => [
                'pattern' => '/(?P<comment>\/\/.*$|#.*$|\/\*.*?\*\/)'
                    . '|(?P<string>"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\')'
                    . '|(?P<var>\$\w+)'
                    . '|(?P<num>\b\d+(?:\.\d+)?\b)'
                    . '|(?P<kw>\b(?:' . implode('|', self::PHP_KEYWORDS) . ')\b)/',
                'roles' => ['comment' => 'muted', 'string' => 'success', 'var' => 'info', 'num' => 'warning', 'kw' => 'primary'],
            ],
            'json' => [
                'pattern' => '/(?P<key>"(?:\\\\.|[^"\\\\])*"(?=\s*:))'
                    . '|(?P<string>"(?:\\\\.|[^"\\\\])*")'
                    . '|(?P<bool>\b(?:true|false|null)\b)'
                    . '|(?P<num>-?\b\d+(?:\.\d+)?\b)/',
                'roles' => ['key' => 'accent', 'string' => 'success', 'bool' => 'primary', 'num' => 'warning'],
            ],
            'bash' => [
                'pattern' => '/(?P<comment>(?<![\w$])#.*$)'
                    . '|(?P<string>"(?:\\\\.|[^"\\\\])*"|\'[^\']*\')'
                    . '|(?P<var>\$\{?\w+\}?)'
                    . '|(?P<num>\b\d+\b)'
                    . '|(?P<kw>\b(?:' . implode('|', self::BASH_KEYWORDS) . ')\b)/',
                'roles' => ['comment' => 'muted', 'string' => 'success', 'var' => 'info', 'num' => 'warning', 'kw' => 'primary'],
            ],
            'yaml' => [
                'pattern' => '/(?P<comment>(?<!\S)#.*$)'
                    . '|(?P<key>^\s*[\w.-]+(?=\s*:))'
                    . '|(?P<string>"(?:\\\\.|[^"\\\\])*"|\'[^\']*\')'
                    . '|(?P<bool>\b(?:true|false|null|yes|no|on|off)\b)'
                    . '|(?P<num>-?\b\d+(?:\.\d+)?\b)/',
                'roles' => ['comment' => 'muted', 'key' => 'accent', 'string' => 'success', 'bool' => 'primary', 'num' => 'warning'],
            ],
            'js' => [
                'pattern' => '/(?P<comment>\/\/.*$|\/\*.*?\*\/)'
                    . '|(?P<string>"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|`(?:\\\\.|[^`\\\\])*`)'
                    . '|(?P<num>\b\d+(?:\.\d+)?\b)'
                    . '|(?P<kw>\b(?:' . implode('|', self::JS_KEYWORDS) . ')\b)/',
                'roles' => ['comment' => 'muted', 'string' => 'success', 'num' => 'warning', 'kw' => 'primary'],
            ],
            'python' => [
                'pattern' => '/(?P<comment>#.*$)'
                    . '|(?P<string>"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\')'
                    . '|(?P<num>\b\d+(?:\.\d+)?\b)'
                    . '|(?P<kw>\b(?:' . implode('|', self::PYTHON_KEYWORDS) . ')\b)/',
                'roles' => ['comment' => 'muted', 'string' => 'success', 'num' => 'warning', 'kw' => 'primary'],
            ],
            'sql' => [
                'pattern' => '/(?P<comment>--.*$|\/\*.*?\*\/)'
                    . '|(?P<string>\'(?:\'\'|[^\'])*\')'
                    . '|(?P<num>\b\d+(?:\.\d+)?\b)'
                    . '|(?P<kw>\b(?:' . implode('|', self::SQL_KEYWORDS) . ')\b)/i',
                'roles' => ['comment' => 'muted', 'string' => 'success', 'num' => 'warning', 'kw' => 'primary'],
            ],
            'html' => [
                'pattern' => '/(?P<comment><!--.*?-->)'
                    . '|(?P<tag><\/?[a-zA-Z][\w-]*)'
                    . '|(?P<string>"(?:[^"]*)"|\'(?:[^\']*)\')'
                    . '|(?P<attr>[a-zA-Z-]+(?==))/',
                'roles' => ['comment' => 'muted', 'tag' => 'primary', 'string' => 'success', 'attr' => 'accent'],
            ],
            'css' => [
                'pattern' => '/(?P<comment>\/\*.*?\*\/)'
                    . '|(?P<atrule>@[\w-]+)'
                    . '|(?P<string>"(?:[^"]*)"|\'(?:[^\']*)\')'
                    . '|(?P<hex>#[0-9a-fA-F]{3,8}\b)'
                    . '|(?P<prop>[a-zA-Z-]+(?=\s*:))'
                    . '|(?P<num>\b\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw|s|ms)?\b)/',
                'roles' => ['comment' => 'muted', 'atrule' => 'primary', 'string' => 'success', 'hex' => 'accent', 'prop' => 'accent', 'num' => 'warning'],
            ],
            default => null,
        };
    }

    /**
     * Apply a spec: style the first matched named group per token by its role.
     *
     * @param array<string, string> $roles group => theme role, in priority order
     */
    private function applyTokens(string $line, string $pattern, array $roles): string
    {
        return (string) preg_replace_callback($pattern, function (array $m) use ($roles): string {
            foreach ($roles as $group => $role) {
                if (($m[$group] ?? '') !== '') {
                    return $this->style($role)->apply($m[$group]);
                }
            }

            return $m[0];
        }, $line);
    }

    /**
     * Unified-diff highlighting — colour the whole line by its leading marker.
     */
    private function highlightDiff(string $line): string
    {
        if (str_starts_with($line, '+') && ! str_starts_with($line, '+++')) {
            return $this->style('success')->apply($line);
        }

        if (str_starts_with($line, '-') && ! str_starts_with($line, '---')) {
            return $this->style('danger')->apply($line);
        }

        if (str_starts_with($line, '@@') || str_starts_with($line, '+++') || str_starts_with($line, '---')) {
            return $this->style('info')->apply($line);
        }

        return $line;
    }

    private function style(string $role): Style
    {
        return Style::make($this->capabilities)->fg($this->theme->color($role) ?? '#64748b');
    }
}
