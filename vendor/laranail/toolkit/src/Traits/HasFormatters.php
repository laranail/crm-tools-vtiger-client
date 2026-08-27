<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Simtabi\Laranail\Toolkit\Support\Username;

/**
 * Convenience presentation helpers for common model attributes (timestamps,
 * names, content excerpts).
 *
 * These delegate straight to native Carbon / Str rather than to a formatter
 * service — the legacy `ModelFormatterService` only wrapped these same calls.
 * Anything covered by Eloquent attribute casts (date casting, etc.) is left to
 * the native cast system and is intentionally not duplicated here.
 *
 * @phpstan-require-extends Model
 */
trait HasFormatters
{
    /**
     * Default display format for date-time attributes.
     */
    protected function defaultDateTimeFormat(): string
    {
        return 'm/d/Y h:i:s a';
    }

    /**
     * Format the model's `created_at` timestamp.
     */
    public function formattedCreatedAt(?string $format = null): ?string
    {
        return $this->formatTimestamp($this->getAttribute('created_at'), $format);
    }

    /**
     * Format the model's `updated_at` timestamp.
     */
    public function formattedUpdatedAt(?string $format = null): ?string
    {
        return $this->formatTimestamp($this->getAttribute('updated_at'), $format);
    }

    /**
     * Format an arbitrary timestamp value using the model's default format.
     */
    protected function formatTimestamp(mixed $value, ?string $format = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format($format ?? $this->defaultDateTimeFormat());
    }

    /**
     * Build a full name from the model's `first_name` / `last_name` attributes.
     */
    public function formattedFullName(): string
    {
        $first = (string) ($this->getAttribute('first_name') ?? '');
        $last = (string) ($this->getAttribute('last_name') ?? '');

        return trim(Str::ucfirst($first) . ' ' . Str::ucfirst($last));
    }

    /**
     * Format the model's `username` with a leading `@`, or false when empty.
     */
    public function formattedUsername(): string|false
    {
        $username = $this->getAttribute('username');

        if (empty($username)) {
            return false;
        }

        return '@' . Str::lower((string) $username);
    }

    /**
     * Produce a truncated excerpt of the model's `content` attribute.
     */
    public function excerpt(int $length = 150): string
    {
        return Str::limit((string) ($this->getAttribute('content') ?? ''), $length);
    }

    /**
     * Format the model's `content` attribute, optionally stripping HTML and/or
     * truncating.
     *
     * Folded from the legacy `ModelFormatterService` (the only formatters there
     * with real bodies — its address/display-name methods returned `''`).
     *
     * @param array{strip_html?: bool, truncate?: int} $options
     */
    public function formattedContent(array $options = []): string
    {
        $content = (string) ($this->getAttribute('content') ?? '');

        if (($options['strip_html'] ?? false) === true) {
            $content = strip_tags($content);
        }

        if (array_key_exists('truncate', $options)) {
            $content = Str::limit($content, (int) $options['truncate']);
        }

        return $content;
    }

    /**
     * Join non-empty address components into a single comma-separated line.
     *
     * @param array<array-key, string|null> $components
     */
    public function formatAddress(array $components): string
    {
        $parts = array_filter(
            array_map(static fn (?string $value): string => trim((string) $value), $components),
            static fn (string $value): bool => $value !== '',
        );

        return implode(', ', $parts);
    }

    /**
     * Join two address lines, omitting the second when empty.
     */
    public function formatAddressLine(string $line1, ?string $line2 = null): string
    {
        return $line2 === null || trim($line2) === ''
            ? $line1
            : $line1 . ', ' . $line2;
    }

    /**
     * Format a "City, State ZIP" line with consistent casing.
     */
    public function formatCityStateZip(string $city, string $state, string $zip): string
    {
        return Str::ucwords($city) . ', ' . Str::ucwords($state) . ' ' . Str::upper($zip);
    }

    /**
     * Suggest a username derived from a person's name that is not already taken
     * in the given column.
     *
     * Delegates name → handle generation to {@see Username}, with the model's
     * own {@see usernameIsAvailable()} wired in as the uniqueness checker. The
     * builder walks its deterministic candidates and, when they are all taken,
     * appends a bounded random suffix — so the caller always gets a free value.
     */
    public function suggestUsername(
        string $firstName,
        ?string $lastName = null,
        string $column = 'username'
    ): string {
        $builder = Username::fromName($firstName, $lastName)
            ->unique(fn (string $username): bool => $this->usernameIsAvailable($username, $column));

        foreach ($builder->candidates() as $candidate) {
            if ($this->usernameIsAvailable($candidate, $column)) {
                return $candidate;
            }
        }

        return $builder->generate();
    }

    /**
     * Whether no existing row holds the given username in the given column.
     */
    protected function usernameIsAvailable(string $username, string $column = 'username'): bool
    {
        return !$this->newQuery()->where($column, $username)->exists();
    }
}
