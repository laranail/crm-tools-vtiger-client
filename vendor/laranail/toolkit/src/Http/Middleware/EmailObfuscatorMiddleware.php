<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Obfuscate email addresses in HTML responses by HTML-entity-encoding every
 * character of each address (e.g. `a@b.com` => `&#97;&#64;&#98;...`). Browsers
 * render the entities as the original text, but naive scrapers that read raw
 * bytes see only entities — a lightweight anti-harvesting measure.
 *
 * Native: it does the encoding itself (no third-party dependency). JSON
 * responses are skipped so API payloads are never mangled.
 */
class EmailObfuscatorMiddleware
{
    /**
     * Matches the local@domain.tld shape of an email address.
     */
    private const EMAIL_PATTERN = '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (!$this->shouldObfuscate($response)) {
            return $response;
        }

        $content = $response->getContent();

        if ($content === false || $content === '') {
            return $response;
        }

        $obfuscated = preg_replace_callback(
            self::EMAIL_PATTERN,
            static fn (array $matches): string => self::encode($matches[0]),
            $content,
        );

        if ($obfuscated !== null) {
            $response->setContent($obfuscated);
        }

        return $response;
    }

    /**
     * Whether the response body should be obfuscated. Skips JSON responses
     * (by Content-Type) so structured API payloads are left untouched.
     */
    private function shouldObfuscate(Response $response): bool
    {
        $contentType = (string) $response->headers->get('Content-Type', '');

        return !str_contains(strtolower($contentType), 'json');
    }

    /**
     * HTML-entity-encode every character of the given string.
     */
    private static function encode(string $value): string
    {
        $encoded = '';

        foreach (mb_str_split($value) as $character) {
            $encoded .= '&#' . mb_ord($character) . ';';
        }

        return $encoded;
    }
}
