<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Services\Log;

use DateTimeInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;
use Override;

/**
 * The per-package log line: a fixed, human-scannable bracket prefix
 * followed by a compact machine-parseable JSON context tail (only when
 * context remains after the reserved keys are lifted out):
 *
 *   [2026-07-08 14:03:22.512] [acme/blog] [INFO] Blog routes registered
 *   [2026-07-08 14:03:22.514] [acme/blog] [SUCCESS] [Install] Migrations published | {"count":3}
 *   [2026-07-08 14:03:22.518] [acme/blog] [ERROR] [Seeder] CountrySeeder failed | {"exception":"RuntimeException"}
 *
 * Reserved context keys (consumed, never emitted in the tail):
 *   _label        the optional fourth bracket
 *   _level_label  overrides the printed level token (e.g. SUCCESS)
 *   _time         original DateTimeInterface for buffered early records
 */
final class PackageLogFormatter extends LineFormatter
{
    public const string LABEL_KEY = '_label';

    public const string LEVEL_LABEL_KEY = '_level_label';

    public const string TIME_KEY = '_time';

    public function __construct(private readonly string $package)
    {
        parent::__construct(allowInlineLineBreaks: false, ignoreEmptyContextAndExtra: true);
    }

    #[Override]
    public function format(LogRecord $record): string
    {
        $context = $record->context;

        $label = $context[self::LABEL_KEY] ?? null;
        $levelLabel = $context[self::LEVEL_LABEL_KEY] ?? $record->level->getName();
        $time = $context[self::TIME_KEY] ?? $record->datetime;
        unset($context[self::LABEL_KEY], $context[self::LEVEL_LABEL_KEY], $context[self::TIME_KEY]);

        $line = sprintf(
            '[%s] [%s] [%s]%s %s',
            $time instanceof DateTimeInterface ? $time->format('Y-m-d H:i:s.v') : (string) $time,
            $this->package,
            strtoupper((string) $levelLabel),
            is_string($label) && $label !== '' ? " [{$label}]" : '',
            $this->stripInlineBreaks($record->message),
        );

        if ($context !== []) {
            // normalize() renders Throwables and objects safely; the tail
            // stays one line so `grep`/`jq -R` pipelines keep working.
            $line .= ' | ' . $this->toJson($this->normalize($context), true);
        }

        return $line . "\n";
    }

    private function stripInlineBreaks(string $message): string
    {
        return str_replace(["\r\n", "\r", "\n"], ' ', $message);
    }
}
