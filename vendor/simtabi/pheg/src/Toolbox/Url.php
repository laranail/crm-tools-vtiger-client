<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox;

use Simtabi\Pheg\Toolbox\Data\DataFactory;
use Simtabi\Pheg\Toolbox\Media\File\FileSystem;
use Spatie\Url\Url as SpatieUrl;
use Exception;

/**
 * Class Url
 *
 * @package Simtabi\Pheg\Toolbox
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
final class Url
{

    private DataFactory $dataFactory;

    /**
     * URL constants as defined in the PHP Manual under "Constants usable with http_build_url()".
     *
     * @see http://us2.php.net/manual/en/http.constants.php#http.constants.url
     */
    public const URL_REPLACE        = 1;
    public const URL_JOIN_PATH      = 2;
    public const URL_JOIN_QUERY     = 4;
    public const URL_STRIP_USER     = 8;
    public const URL_STRIP_PASS     = 16;
    public const URL_STRIP_AUTH     = 32;
    public const URL_STRIP_PORT     = 64;
    public const URL_STRIP_PATH     = 128;
    public const URL_STRIP_QUERY    = 256;
    public const URL_STRIP_FRAGMENT = 512;
    public const URL_STRIP_ALL      = 1024;

    public const ARG_SEPARATOR      = '&';

    public const PORT_HTTP          = 80;
    public const PORT_HTTPS         = 443;

    public function __construct() {
        $this->dataFactory = new DataFactory();
    }

    /**
     * Add or remove query arguments to the URL.
     *
     * @param array       $newParams Either new key or an associative array
     * @param string|null $uri       URI or URL to append the query/queries to.
     * @return string
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function addArg(array $newParams, ?string $uri = null): string
    {
        $uri = $uri ?? ($_SERVER['REQUEST_URI'] ?? '');

        // Parse the URI into it's components
        $parsedUri = $this->dataFactory->data((array)\parse_url((string)$uri));

        if ($parsedQuery = $parsedUri->get('query')) {
            \parse_str($parsedQuery, $queryParams);
            $queryParams = \array_merge($queryParams, $newParams);
        } elseif ((string)$parsedUri->get('path') && false !== \strpos((string)$parsedUri['path'], '=')) {
            $parsedUri['query'] = $parsedUri['path'];
            $parsedUri->remove('path');
            \parse_str((string)$parsedUri['query'], $queryParams);
            $queryParams = \array_merge($queryParams, $newParams);
        } else {
            $queryParams = $newParams;
        }

        // Strip out any query params that are set to false.
        // Properly handle valueless parameters.
        foreach ($queryParams as $param => $value) {
            if ($value === false) {
                unset($queryParams[$param]);
            } elseif ($value === null) {
                $queryParams[$param] = '';
            }
        }

        // Re-construct the query string
        $parsedUri['query'] = $this->build($queryParams);

        // Strip = from valueless parameters.
        $parsedUri['query'] = (string)\preg_replace('/=(?=&|$)/', '', (string)$parsedUri['query']);

        // Re-construct the entire URL
        $newUri = $this->buildAll((array)$parsedUri);

        // Make the URI consistent with our input
        foreach ([':', '/', '?'] as $char) {
            if ($newUri[0] === $char && false === \strpos((string)$uri, $char)) {
                $newUri = \substr($newUri, 1);
            }
        }

        return \rtrim((string)$newUri, '?');
    }

    /**
     * Returns the current URL.
     *
     * @param bool $addAuth
     * @return string|null
     */
    public function current(bool $addAuth = false): ?string
    {
        $root = $this->root($addAuth);
        $path = $this->path();

        return \trim("{$root}{$path}") ?: null;
    }

    /**
     * Returns the current path
     *
     * @return string|null
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function path(): ?string
    {
        $url = '';

        // Get the rest of the URL
        if (!\array_key_exists('REQUEST_URI', $_SERVER)) {
            // Microsoft IIS doesn't set REQUEST_URI by default
            if ($queryString = $_SERVER['QUERY_STRING'] ?? null) {
                $url .= '?' . $queryString;
            }
        } else {
            $url .= $_SERVER['REQUEST_URI'];
        }

        return $url ?: null;
    }

    /**
     * Returns current root URL
     *
     * @param bool $addAuth
     * @return null|string
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function root(bool $addAuth = false): ?string
    {
        $url     = '';

        // Check to see if it's over https
        $isHttps = $this->isHttps();

        // Was a username or password passed?
        if ($addAuth) {
            $url .= (string)$this->getAuth();
        }

        $serverData = $this->dataFactory->data($_SERVER);

        // We want the user to stay on the same host they are currently on,
        // but beware of security issues
        // see http://shiflett.org/blog/2006/mar/server-name-versus-http-host
        $host = (string)$serverData->get('HTTP_HOST');
        $port = (int)$serverData->get('SERVER_PORT');
        $url .= \str_replace(':' . $port, '', $host);

        // Is it on a non standard port?
        if ($isHttps && $port !== $this->PORT_HTTPS) {
            $url .= $port ? ":{$port}" : '';
        } elseif (!$isHttps && $port !== $this->PORT_HTTP) {
            $url .= $port ? ":{$port}" : '';
        }

        if ($url) {
            if ($isHttps) {
                return 'https://' . $url;
            }
            return 'http://' . $url;
        }

        return null;
    }

    /**
     * Get current auth info
     *
     * @return null|string
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function getAuth(): ?string
    {
        $result = null;
        if ($user = $_SERVER['PHP_AUTH_USER'] ?? null) {
            $result .= $user;

            if ($password = $_SERVER['PHP_AUTH_PW'] ?? null) {
                $result .= ':' . $password;
            }

            $result .= '@';
        }

        return $result;
    }

    /**
     * Builds HTTP query from array
     *
     * @param array $queryParams
     * @return string
     */
    public function build(array $queryParams): string
    {
        return \http_build_query($queryParams, '', self::ARG_SEPARATOR);
    }

    /**
     * Build a URL. The parts of the second URL will be merged into the first according to the flags argument.
     *
     * @param array|string $sourceUrl (part(s) of) an URL in form of a string
     *                                or associative array like parse_url() returns
     * @param array|string $destParts Same as the first argument
     * @param int          $flags     A bitmask of binary or'ed HTTP_URL constants; HTTP_URL_REPLACE is the default
     * @param array        $newUrl    If set, it will be filled with the parts of the composed url like parse_url()
     *                                would return
     * @return string
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     *
     * @see    https://github.com/jakeasmith/http_build_url/
     * @author Jake Smith <theman@jakeasmith.com>
     */
    public function buildAll(
        $sourceUrl,
        $destParts = [],
        int $flags = self::URL_REPLACE,
        array &$newUrl = []
    ): string {
        \is_array($sourceUrl) || $sourceUrl = \parse_url($sourceUrl);
        \is_array($destParts) || $destParts = \parse_url($destParts);

        $url     = $this->dataFactory->data((array)$sourceUrl);
        $parts   = $this->dataFactory->data((array)$destParts);
        $allKeys = ['user', 'pass', 'port', 'path', 'query', 'fragment'];

        // HTTP_URL_STRIP_ALL and HTTP_URL_STRIP_AUTH cover several other flags.
        if ($flags & self::URL_STRIP_ALL) {
            $flags |= self::URL_STRIP_USER | self::URL_STRIP_PASS | self::URL_STRIP_PORT | self::URL_STRIP_PATH
                | self::URL_STRIP_QUERY | self::URL_STRIP_FRAGMENT;
        } elseif ($flags & self::URL_STRIP_AUTH) {
            $flags |= self::URL_STRIP_USER | self::URL_STRIP_PASS;
        }

        // Schema and host are always replaced
        if ($parts->has('scheme')) {
            $url['scheme'] = $parts->get('scheme');
        }

        if ($parts->has('host')) {
            $url['host'] = $parts->get('host');
        }

        if ($flags & self::URL_REPLACE) {
            foreach ($allKeys as $key) {
                if ($parts->has($key)) {
                    $url[$key] = $parts->get($key);
                }
            }
        } else {
            // PATH
            if (($flags & self::URL_JOIN_PATH) && $parts->has('path')) {
                if ($url->has('path') && $parts->get('path')[0] !== '/') {
                    $url['path'] =
                        \rtrim(\str_replace(\basename((string)$url['path']), '', (string)$url['path']), '/')
                        . '/'
                        . \ltrim((string)$parts['path'], '/');
                } else {
                    $url['path'] = $parts['path'];
                }
            }

            // QUERY
            if ($flags & self::URL_JOIN_QUERY && $parts->has('query')) {
                \parse_str($url->get('query', ''), $urlQuery);
                \parse_str($parts->get('query', ''), $partsQuery);

                $queryParams = \array_replace_recursive($urlQuery, $partsQuery);
                $url['query'] = $this->build($queryParams);
            }
        }

        if ($url->get('path')) {
            $url['path'] = '/' . \ltrim((string)$url['path'], '/');
        }

        foreach ($allKeys as $key) {
            $strip = 'URL_STRIP_' . \strtoupper($key);
            if ($flags & (int)\constant(__CLASS__ . '::' . $strip)) {
                $url->remove($key);
            }
        }

        if ($url->get('port', null, 'int') === self::PORT_HTTPS) {
            $url['scheme'] = 'https';
        } elseif ($url->get('port', null, 'int') === self::PORT_HTTP) {
            $url['scheme'] = 'http';
        }

        if (!$url->get('port')) {
            if ($url->get('scheme') === 'https') {
                $url['port'] = 443;
            } elseif ($url->get('scheme') === 'http') {
                $url['port'] = 80;
            }
        }

        $parsedString = $url->has('scheme') ? ($url['scheme'] . '://') : '';

        if ($url->get('user')) {
            $parsedString .= $url['user'];
            $parsedString .= $url->get('pass') ? (':' . $url->get('pass')) : '';
            $parsedString .= '@';
        }

        $parsedString .= $url->has('host') ? $url['host'] : '';

        if ((int)$url->get('port') !== self::PORT_HTTP && $url->get('scheme') === 'http') {
            $parsedString .= ':' . $url['port'];
        }

        if ($url->get('path')) {
            $parsedString .= $url['path'];
        } else {
            $parsedString .= '/';
        }

        if ($url->get('query')) {
            $parsedString .= '?' . $url->get('query');
        }

        if ($url->get('fragment')) {
            $parsedString .= '#' . \trim($url->get('fragment'), '#');
        }

        $newUrl = $url->getArrayCopy();

        return $parsedString;
    }

    /**
     * Checks to see if the page is being server over SSL or not
     *
     * @param bool $trustProxyHeaders
     * @return bool
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function isHttps(bool $trustProxyHeaders = false): bool
    {
        // Check standard HTTPS header
        if (\array_key_exists('HTTPS', $_SERVER)) {
            return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        }

        if ($trustProxyHeaders && \array_key_exists('X-FORWARDED-PROTO', $_SERVER)) {
            return $_SERVER['X-FORWARDED-PROTO'] === 'https';
        }

        // Default is not SSL
        return false;
    }

    /**
     * Removes an item or list from the query string.
     *
     * @param string|array $keys Query key or keys to remove.
     * @param string|null  $uri  When null uses the $_SERVER value
     * @return string
     */
    public function delArg($keys, ?string $uri = null): string
    {
        if (\is_array($keys)) {
            $params = \array_combine($keys, \array_fill(0, \count($keys), false)) ?: [];
            return $this->addArg($params, (string)$uri);
        }

        return $this->addArg([$keys => false], (string)$uri);
    }

    /**
     * Turns all of the links in a string into HTML links.
     * Part of the LinkifyURL Project <https://github.com/jmrware/LinkifyURL>
     *
     * @param string $text The string to parse
     * @return string
     */
    public function parseLink(string $text): string
    {
        $text = (string)\preg_replace('/&apos;/', '&#39;', $text); // IE does not handle &apos; entity!

        $sectionHtmlPattern = '%            # Rev:20100913_0900 github.com/jmrware/LinkifyURL
                                            # Section text into HTML <A> tags  and everything else.
             (                              # $1: Everything not HTML <A> tag.
               [^<]+(?:(?!<a\b)<[^<]*)*     # non A tag stuff starting with non-"<".
               | (?:(?!<a\b)<[^<]*)+        # non A tag stuff starting with "<".
             )                              # End $1.
             | (                            # $2: HTML <A...>...</A> tag.
                 <a\b[^>]*>                 # <A...> opening tag.
                 [^<]*(?:(?!</a\b)<[^<]*)*  # A tag contents.
                 </a\s*>                    # </A> closing tag.
             )                              # End $2:
             %ix';

        return (string)\preg_replace_callback(
            $sectionHtmlPattern,
            /**
             * @param array $matches
             * @return string
             */
            static function (array $matches): string {
                return $this->linkifyCallback($matches);
            },
            $text
        );
    }

    /**
     * Callback for the preg_replace in the linkify() method.
     * Part of the LinkifyURL Project <https://github.com/jmrware/LinkifyURL>
     *
     * @param array $matches Matches from the preg_ function
     * @return string
     */
    protected function linkifyCallback(array $matches): string
    {
        return $matches[2] ?? $this->linkifyRegex($matches[1]);
    }

    /**
     * Callback for the preg_replace in the linkify() method.
     * Part of the LinkifyURL Project <https://github.com/jmrware/LinkifyURL>
     *
     * @param string $text Matches from the preg_ function
     * @return string
     */
    protected function linkifyRegex(string $text): string
    {
        $urlPattern = '/                                            # Rev:20100913_0900 github.com\/jmrware\/LinkifyURL
                                                                    # Match http & ftp URL that is not already linkified
                                                                    # Alternative 1: URL delimited by (parentheses).
            (\()                                                    # $1 "(" start delimiter.
            ((?:ht|f)tps?:\/\/[a-z0-9\-._~!$&\'()*+,;=:\/?#[\]@%]+) # $2: URL.
            (\))                                                    # $3: ")" end delimiter.
            |                                                       # Alternative 2: URL delimited by [square brackets].
            (\[)                                                    # $4: "[" start delimiter.
            ((?:ht|f)tps?:\/\/[a-z0-9\-._~!$&\'()*+,;=:\/?#[\]@%]+) # $5: URL.
            (\])                                                    # $6: "]" end delimiter.
            |                                                       # Alternative 3: URL delimited by {curly braces}.
            (\{)                                                    # $7: "{" start delimiter.
            ((?:ht|f)tps?:\/\/[a-z0-9\-._~!$&\'()*+,;=:\/?#[\]@%]+) # $8: URL.
            (\})                                                    # $9: "}" end delimiter.
            |                                                       # Alternative 4: URL delimited by <angle brackets>.
            (<|&(?:lt|\#60|\#x3c);)                                 # $10: "<" start delimiter (or HTML entity).
            ((?:ht|f)tps?:\/\/[a-z0-9\-._~!$&\'()*+,;=:\/?#[\]@%]+) # $11: URL.
            (>|&(?:gt|\#62|\#x3e);)                                 # $12: ">" end delimiter (or HTML entity).
            |                                                       # Alt. 5: URL not delimited by (), [], {} or <>.
            (                                                       # $13: Prefix proving URL not already linked.
            (?: ^                                                   # Can be a beginning of line or string, or
             | [^=\s\'"\]]                                          # a non-"=", non-quote, non-"]", followed by
            ) \s*[\'"]?                                             # optional whitespace and optional quote;
              | [^=\s]\s+                                           # or... a non-equals sign followed by whitespace.
            )                                                       # End $13. Non-prelinkified-proof prefix.
            (\b                                                     # $14: Other non-delimited URL.
            (?:ht|f)tps?:\/\/                                       # Required literal http, https, ftp or ftps prefix.
            [a-z0-9\-._~!$\'()*+,;=:\/?#[\]@%]+                     # All URI chars except "&" (normal*).
            (?:                                                     # Either on a "&" or at the end of URI.
            (?!                                                     # Allow a "&" char only if not start of an...
            &(?:gt|\#0*62|\#x0*3e);                                 # HTML ">" entity, or
            | &(?:amp|apos|quot|\#0*3[49]|\#x0*2[27]);              # a [&\'"] entity if
            [.!&\',:?;]?                                            # followed by optional punctuation then
            (?:[^a-z0-9\-._~!$&\'()*+,;=:\/?#[\]@%]|$)              # a non-URI char or EOS.
           ) &                                                      # If neg-assertion true, match "&" (special).
            [a-z0-9\-._~!$\'()*+,;=:\/?#[\]@%]*                     # More non-& URI chars (normal*).
           )*                                                       # Unroll-the-loop (special normal*)*.
            [a-z0-9\-_~$()*+=\/#[\]@%]                              # Last char can\'t be [.!&\',;:?]
           )                                                        # End $14. Other non-delimited URL.
            /imx';

        $urlReplace = '$1$4$7$10$13<a href="$2$5$8$11$14">$2$5$8$11$14</a>$3$6$9$12';

        return (string)\preg_replace($urlPattern, $urlReplace, $text);
    }

    /**
     * Convert file path to relative URL
     *
     * @param string $path
     * @return string
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function pathToRel(string $path): string
    {
        $fs       = new FileSystem;
        $root     = $fs->clean($_SERVER['DOCUMENT_ROOT'] ?? null);
        $path     = $fs->clean($path);

        $normRoot = \str_replace(\DIRECTORY_SEPARATOR, '/', $root);
        $normPath = \str_replace(\DIRECTORY_SEPARATOR, '/', $path);

        $regExp   = '/^' . \preg_quote($normRoot, '/') . '/i';
        $relative = (string)\preg_replace($regExp, '', $normPath);

        return \ltrim($relative, '/');
    }

    /**
     * Convert file path to absolute URL
     *
     * @param string $path
     * @return string
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function pathToUrl(string $path): string
    {
        return (string)$this->root() . '/' . $this->pathToRel($path);
    }

    /**
     * Is absolute url
     *
     * @param string $path
     * @return bool
     */
    public function isAbsolute(string $path): bool
    {
        return \strpos($path, '//') === 0 || \preg_match('#^[a-z-]{3,}:\/\/#i', $path);
    }

    /**
     * Create URL from array params
     *
     * @param array $parts
     * @return string
     */
    public function create(array $parts = []): string
    {
        $parts = \array_merge([
            'scheme' => 'https',
            'query'  => [],
        ], $parts);

        if (\is_array($parts['query'])) {
            $parts['query'] = $this->build($parts['query']);
        }

        /** @noinspection ArgumentEqualsDefaultValueInspection */
        return $this->buildAll('', $parts, self::URL_REPLACE);
    }

    public function parseUrl(?string $url = null): SpatieUrl
    {
        $url = !empty($url) ? $url : $this->getBaseUrl();
        return SpatieUrl::fromString($url);
    }

    public function parseBaseUrl(): SpatieUrl
    {
        return SpatieUrl::fromString($this->getBaseUrl());
    }

    public function formatUrl($url, $formatted = true, $https = false){

        $url = $this->appendScheme($this->removeHttp($url), $https);
        if(!$formatted){
            return $this->removeHttp($url);
        }

        return $url;
    }

    public function appendScheme($url, $https = false){
        $url = SpatieUrl::fromString($url);

        // if no scheme, append
        if (!$url->getScheme() == 'https' && !$url->getScheme() == 'http'){
            $url = (!$https ? 'http://' : 'https://') . $url;
        }

        return $url;
    }

    /**
     * Function remove_http
     *
     * @param $url
     * @return mixed
     *
     * @author http://stackoverflow.com/questions/4357668/how-do-i-remove-http-https-and-slash-from-user-input-in-php
     */
    public function removeHttp($url) {
        /**
         *
         *
        $disallowed = array('http://', 'https://');
        foreach($disallowed as $d) {
        if(strpos($url, $d) === 0) {
        return str_replace($d, '', $url);
        }
        }
        return $url;
         */

        return preg_replace('#^https?://#', '', $url);
    }

    public function encodeUrl($url) {
        $reserved = [
            ':'  => '!%3A!ui',
            '/'  => '!%2F!ui',
            '?'  => '!%3F!ui',
            '#'  => '!%23!ui',
            '['  => '!%5B!ui',
            ']'  => '!%5D!ui',
            '@'  => '!%40!ui',
            '!'  => '!%21!ui',
            '$'  => '!%24!ui',
            '&'  => '!%26!ui',
            '\'' => '!%27!ui',
            '('  => '!%28!ui',
            ')'  => '!%29!ui',
            '*'  => '!%2A!ui',
            '+'  => '!%2B!ui',
            ','  => '!%2C!ui',
            ';'  => '!%3B!ui',
            '='  => '!%3D!ui',
            '%'  => '!%25!ui',
        ];

        return preg_replace(array_values($reserved), array_keys($reserved), rawurlencode($url));
    }

    /**
     * Returns the content of a page using allow_url_fopen or curl.
     *
     * @param string $url
     * @return mixed
     */
    public function getUrlContent(string $url)
    {
        if ((bool) ini_get('allow_url_fopen') === true) {
            $streamOptions = [
                'http' => [
                    'method'  => 'GET',
                    'timeout' => 2
                ]
            ];

            $streamContext = stream_context_create($streamOptions);
            return @file_get_contents($url, false, $streamContext);
        }

        if (function_exists('curl_init')) {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($curl, CURLOPT_TIMEOUT, 2);
            $output = curl_exec($curl);
            curl_close($curl);
            return $output;
        }

        return null;
    }

    public function getBaseUrl(){
        try {

            // First we need to get the protocol the website is using
            $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] === 443) ? "https://" : "http://";

            // Build URL
            $baseUrl  = $protocol.$_SERVER['HTTP_HOST'].'/';

            // Append project root folder/project-folder or yourdomain.com/foldername
            $baseUrl .= preg_replace('@/+$@','',dirname($_SERVER['SCRIPT_NAME'])).'/';
            // $baseUrl .= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') .'/';

            // Ensure we always have a trailing slash,
            // but first we trim all existing ones,
            // then we append to ensure consistency
            return trim($baseUrl, '/').'/';

        }catch (Exception $exception){
            return false;
        }
    }

    /**
     * Returns the script url parameter.
     *
     * @return string
     */
    public function getScriptUrl(): string
    {
        $url = '';

        if (!empty($_SERVER['SCRIPT_URL'])) {
            $url = $_SERVER['SCRIPT_URL'];
        } elseif (!empty($_SERVER['REDIRECT_URL'])) {
            $url = $_SERVER['REDIRECT_URL'];
        } elseif (!empty($_SERVER['REQUEST_URI'])) {
            $parse = (array) parse_url($_SERVER['REQUEST_URI']);
            $url   = $parse['path'] ?? '';
        }

        return $url;
    }

    public function getBaseUrlHost(): string
    {
        $url = $this->getBaseUrl();
        return $url ? $this->parseUrl($url)->getHost() : '';
    }

    /**
     * Parse text to find all URLs that are not linked and create A tag
     * @param  string $string     Text to parse
     * @param  array  $attributes Optional, additional key/value attributes to include in the A tag
     * @return string
     */
    public function makeClickableLinks($string, $attributes = array()) {
        $attr = "";
        foreach ($attributes as $attributeName => $attributeValue):
            $attr.= $attributeName . '="' . $attributeValue . '" ';
        endforeach;
        return preg_replace('@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@', '<a href="$1" ' . $attr . '>$1</a>', $string);
    }

    /**
     * Create HTML A Tag
     * @param  string $link       URL or Email address
     * @param  string $text       Optional, If link text is empty, $link variable value will be used by default
     * @param  array  $attributes Optional, additional key/value attributes to include in the IMG tag
     * @return string             containing complete a tag
     */
    public function createLinkTag($link, $text = "", $attributes = array()) {
        if (self::validateEmail($link)):
            $linkTag = '<a href="mailto:' . $link . '"';
        else:
            $linkTag = '<a href="' . $link . '"';
        endif;
        $attr = "";
        if (!isset($attributes['title'])):
            $linkTag.= ' title="' . str_replace('"', '', strip_tags($text)) . '"';
        endif;
        if (empty($text)):
            $text = $link;
        endif;
        foreach ($attributes as $attributeName => $attributeValue):
            $attr.= $attributeName . '="' . $attributeValue . '" ';
        endforeach;
        $linkTag.= trim($attr) . '>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . "</a>";
        return $linkTag;
    }

}
