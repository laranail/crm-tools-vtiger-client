<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox;

use Exception;
use Simtabi\Enekia\Vanilla\Validators;
use Simtabi\Pheg\Toolbox\String\Str;

final class Email
{

    public function __construct() {}

    /**
     * Create random email
     *
     * @param int $userNameLength
     * @return string
     * @throws Exception
     */
    public function random(int $userNameLength = 10): string
    {
        return (new Str)->random($userNameLength) . '@' . (new Str)->random(5) . '.com';
    }

    /**
     * Check if email(s) is(are) valid. You can send one or an array of emails.
     *
     * @param string|array $emails
     * @return array
     */
    public function check($emails): array
    {
        $result = [];

        if (empty($emails)) {
            return $result;
        }

        $emails = $this->handleEmailsInput($emails);

        foreach ($emails as $email) {
            if (!$this->isValid($email)) {
                continue;
            }
            if (!in_array($email, $result, true)) {
                $result[] = $email;
            }
        }

        return $result;
    }

    /**
     * Check for DNS MX records of the email domain. Notice that a
     * (temporary) DNS error will have the same result as no records
     * were found. Code coverage ignored because this method requires
     * DNS requests that could not be reliable.
     *
     * @param string $email
     * @return bool
     */
    public function checkDns(string $email): bool
    {
        if (!$this->isValid($email)) {
            return false;
        }

        $domain = $this->extractDomain($email);

        return !(checkdnsrr($domain, 'MX') === false);
    }

    /**
     * Get domains from email addresses. The not valid email addresses
     * will be skipped.
     *
     * @param string|array $emails
     * @return array
     */
    public function getDomain($emails): array
    {
        $result = [];

        if (empty($emails)) {
            return $result;
        }

        $emails = $this->handleEmailsInput($emails);

        foreach ($emails as $email) {
            if (!$this->isValid($email)) {
                continue;
            }

            $domain = $this->extractDomain($email);
            if (!empty($domain) && !in_array($domain, $result, true)) {
                $result[] = $domain;
            }
        }

        return $result;
    }

    /**
     * Get domains from email addresses in alphabetical order.
     *
     * @param array $emails
     * @return array
     */
    public function getDomainSorted(array $emails): array
    {
        $domains = $this->getDomain($emails);
        sort($domains, SORT_STRING);

        return $domains;
    }

    /**
     * Generates an Gravatar URL.
     *
     * Size of the image:
     * * The default size is 32px, and it can be anywhere between 1px up to 2048px.
     * * If requested any value above the allowed range, then the maximum is applied.
     * * If requested any value bellow the minimum, then the default is applied.
     *
     * Default image:
     * * It can be an URL to an image.
     * * Or one of built in options that Gravatar has. See Email::getGravatarBuiltInImages().
     * * If none is defined then a built in default is used. See Email::getGravatarBuiltInDefaultImage().
     *
     * @param string $email
     * @param int    $size
     * @param string $defaultImage
     * @return null|string
     * @link http://en.gravatar.com/site/implement/images/
     */
    public function getGravatarUrl(string $email, int $size = 32, string $defaultImage = 'identicon'): ?string
    {
        if (empty($email) || !$this->isValid($email)) {
            return null;
        }

        $hash  = md5(strtolower(trim($email)));
        $parts = ['scheme' => 'http', 'host' => 'www.gravatar.com'];

        if ((new Url())->isHttps()) {
            $parts = ['scheme' => 'https', 'host' => 'secure.gravatar.com'];
        }

        // Get size
        $size = (new Vars())->limit((new Filter())->int($size), 32, 2048);

        // Prepare default images
        $defaultImage = trim($defaultImage);
        if (preg_match('/^(http|https)./', $defaultImage)) {
            $defaultImage = urldecode($defaultImage);
        } else {
            $defaultImage = strtolower($defaultImage);
            if (!(new Validators())->transfigure()->isInArray($defaultImage, $this->getGravatarBuiltInImages()))
            {
                $defaultImage = $this->getGravatarBuiltInImages()[2];
            }
        }

        // Build full url
        $parts['path']  = '/avatar/' . $hash . '/';
        $parts['query'] = [
            's' => $size,
            'd' => $defaultImage,
        ];

        return (new Url())->create($parts);
    }

    /**
     * Returns gravatar supported placeholders
     *
     * @return array
     */
    public function getGravatarBuiltInImages(): array
    {
        return [
            '404',
            'mm',
            'identicon',
            'monsterid',
            'wavatar',
            'retro',
            'blank',
        ];
    }

    /**
     * Returns true if string has valid email format
     *
     * @param string|null $email
     * @return bool
     */
    public function isValid(?string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        $email = filter_var($email, FILTER_SANITIZE_STRING);

        return !(filter_var($email, FILTER_VALIDATE_EMAIL) === false);
    }

    /**
     * @param string $email
     * @return string
     */
    private function extractDomain(string $email): string
    {
        $parts = explode('@', $email);
        $domain = array_pop($parts);

        if ((new System())->isFunc('idn_to_utf8')) {
            return (string)idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46);
        }

        return $domain;
    }

    /**
     * Transforms strings in array, and remove duplicates.
     * Using array_keys array_flip because is faster than array_unique:
     * array_unique O(n log n)
     * array_flip O(n)
     *
     * @link http://stackoverflow.com/questions/8321620/array-unique-vs-array-flip
     * @param string|array $emails
     * @return array
     */
    private function handleEmailsInput($emails): array
    {
        return is_array($emails) ? array_keys(array_flip($emails)) : [$emails];
    }

    /**
     * Splits a given email address into various parts
     *
     * @param string $email
     *
     * @return array
     */
    public function splitEmailToParts(string $email): array
    {
        $parts      = explode("@", $email); // Splits the email at the @ symbol
        $username   = $parts[0] ?? null; // Can be used as a username if you'd like, but we'll use it to find names anyway
        $domain     = $parts[1] ?? null; // Grab the domain part of the email
        $delimiters = [".", "-", "_"]; // List of common email name delimiters, feel free to add to it

        if (!empty($username))
        {
            foreach ($delimiters as $delimiter)
            {
                // Checks all the delimiters
                if ( strpos($username, $delimiter) )
                { // If the delimiter is found in the string
                    $nameParts = preg_replace("/\d+$/","", $username); // Remove numbers from string
                    $nameParts = explode( $delimiter, $nameParts); // Split the username at the delimiter
                    break; // If we've found a delimiter we can move on
                }
            }

            if ( isset($nameParts) && !empty($nameParts) )
            {
                // If we've found a delimiter we can use it
                $firstName = ucfirst(strtolower(($nameParts[0] ?? null))); // Let's tidy up the names so the first letter is a capital and rest lower case
                $lastName  = ucfirst(strtolower(($nameParts[1] ?? null)));

                /* This code just shows what you can do with the names, but you can use it for something more interesting! */
                echo $firstName . ' ' . $lastName;
            }
        }

        return [
            'first_name' => $firstName ?? null,
            'last_name'  => $lastName  ?? null,
            'domain'     => $domain  ?? null,
        ];
    }

    /**
     * Masks parts of an email address
     *
     * @param string $email          the email address to mask
     * @param int    $maskPercentile the percent of the username to mask
     * @param string $char           the character to use to mask with
     *
     * @return string|null $result
     */
    public function mask(string $email, int $maskPercentile = 50, string $char = '*' ): string|null
    {
        $str     = new Str();
        $data    = explode("@", $email);
        $name    = (string) $data[0] ?? '';
        $domain  = (string) $data[1] ?? '';

        if (!empty($name) && !empty($domain))
        {
            $maskedName     = $str->maskString($name, $maskPercentile, $char);
            $maskPercentile = rand(mb_strlen($email, (mb_strlen($email) / 2)) / $maskPercentile);
            $maskedDomain   = $str->maskString($domain, $maskPercentile, $char);

            //return results
            return("{$maskedName}@{$maskedDomain}");
        }

        return null;
    }

    /**
     * Masks a given email address
     *
     * @param string $email
     * @param string $delimiter
     *
     * @return string
     */
    public function maskAlt(string $email, string $delimiter = '@'): string
    {
        $data = [];
        foreach (explode($delimiter, $email) as $str) {
            $data[] = preg_replace_callback('/(?<=^.{2})[^.]*/', function ($m) {
                return str_repeat('*', strlen($m[0]));
            }, $str);
        }

        return implode($delimiter, $data);
    }

    /**
     * Obfuscate email
     *
     * @param string $string
     *
     * @return string
     */
    public static function obfuscate(string $string): string
    {

        // Safeguard string.
        $safeguard = '$%$!!$%$';

        // Safeguard some stuff before parsing.
        $prevent   = [
            '|<input [^>]*@[^>]*>|is', // <input>
            '|(<textarea(?:[^>]*)>)(.*?)(</textarea>)|is', // <textarea>
            '|(<head(?:[^>]*)>)(.*?)(</head>)|is', // <head>
            '|(<script(?:[^>]*)>)(.*?)(</script>)|is', // <script>
        ];

        foreach ($prevent as $pattern) {
            $string = preg_replace_callback($pattern, function ($matches) use ($safeguard) {
                return str_replace('@', $safeguard, $matches[0]);
            }, $string);
        }

        // Define patterns for extracting emails.
        $patterns = [
            '|\<a[^>]+href\=\"mailto\:([^">?]+)(\?[^?">]+)?\"[^>]*\>(.*?)\<\/a\>|ism', // mailto anchors
            '|[_a-z0-9-]+(?:\.[_a-z0-9-]+)*@[a-z0-9-]+(?:\.[a-z0-9-]+)*(?:\.[a-z]{2,3})|i', // plain emails
        ];

        foreach ($patterns as $pattern)
        {
            $string = preg_replace_callback($pattern, function ($parts) use ($safeguard)
            {
                // Clean up element parts.
                $parts = array_map('trim', $parts);

                // ROT13 implementation for JS-enabled browsers
                $js    = '<script type="text/javascript">
                            Rot13 = {map:null,convert:function(e){Rot13.init();var t="";for(i=0;i<e.length;i++){var n=e.charAt(i);t+=n>="A"&&n<="Z"||n>="a"&&n<="z"?Rot13.map[n]:n}return t},init:function(){if(Rot13.map!=null)return;var e=new Array;var t="abcdefghijklmnopqrstuvwxyz";for(i=0;i<t.length;i++)e[t.charAt(i)]=t.charAt((i+13)%26);for(i=0;i<t.length;i++)e[t.charAt(i).toUpperCase()]=t.charAt((i+13)%26).toUpperCase();Rot13.map=e},write:function(e){document.write(Rot13.convert(e))}}
                            
                            Rot13.write(' . "'" . str_rot13($parts[0]) . "'" . ');
                          </script>';

                // Reversed direction implementation for non-JS browsers
                if (stripos($parts[0], '<a') === 0) {
                    // Mailto tag; if link content equals the email, just display the email, otherwise display a formatted string.
                    $noJs = ($parts[1] == $parts[3]) ? $parts[1] : (' > ' . $parts[1] . ' < ' . $parts[3]);
                } else {
                    // Plain email; display the plain email.
                    $noJs = $parts[0];
                }

                $noJs = '<noscript><span style="unicode-bidi:bidi-override;direction:rtl;">' . strrev($noJs) . '</span></noscript>';

                // Safeguard the obfuscation, so it won't get picked up by the next iteration.
                return str_replace('@', $safeguard, $js . $noJs);
            }, $string);
        }

        // Revert all safeguards.
        return str_replace($safeguard, '@', $string);
    }

    public function encode(string $email = 'info@domain.com', string $linkText = 'Contact Us', string $attrs = 'class="emailencoder"' )
    {

        $email = str_replace('@', '&#64;', $email);
        $email = str_replace('.', '&#46;', $email);
        $email = str_split($email, 5);

        $linkText = str_replace('@', '&#64;', $linkText);
        $linkText = str_replace('.', '&#46;', $linkText);
        $linkText = str_split($linkText, 5);

        $part1 = '<a href="ma';
        $part2 = 'ilto&#58;';
        $part3 = '" '. $attrs .' >';
        $part4 = '</a>';

        // generamos el Javascript
        $encoded = '<script type="text/javascript">';
        $encoded .= "document.write('$part1');";
        $encoded .= "document.write('$part2');";
        foreach($email as $e)
        {
            $encoded .= "document.write('$e');";
        }
        $encoded .= "document.write('$part3');";
        foreach($linkText as $l)
        {
            $encoded .= "document.write('$l');";
        }
        $encoded .= "document.write('$part4');";
        $encoded .= '</script>';

        return $encoded;
    }

}
