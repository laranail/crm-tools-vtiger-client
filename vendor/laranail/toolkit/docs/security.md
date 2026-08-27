# Security helpers

Security-focused validation and credential helpers: the `RejectCommonPasswords`
validation rule plus three fluent, immutable CSPRNG generators under
`Simtabi\Laranail\Toolkit\Modules\Security` — `Token`, `Password` and
`Passphrase`.

All three generators draw randomness **only** from PHP's native cryptographically
secure primitives — `random_bytes()` / `random_int()` for entropy, `hash_hmac()`
(SHA-256) for signing, and `hash_equals()` for constant-time comparison. They
never touch `rand`, `mt_rand`, `uniqid` or `Str::random` for the security core.
Each follows the same clone-and-mutate builder shape as `Username`, so a
configured builder is immutable and reusable, and each implements `Stringable`.

They are usable as static-fluent builders directly, or via the `Toolkit` /
`Laranail` facades, which return fresh builders:

```php
use Simtabi\Laranail\Toolkit\Modules\Security\Token;
use Simtabi\Laranail\Toolkit\Facades\Toolkit;

$token = Token::unsigned()->encoding('base64url')->length(32)->generate();
$token = Toolkit::token()->encoding('base64url')->length(32)->generate(); // same builder
```

## `RejectCommonPasswords`

`Simtabi\Laranail\Toolkit\Rules\RejectCommonPasswords` is a Laravel
[`ValidationRule`](https://laravel.com/docs/validation#using-rule-objects) that
screens passwords against weak-credential signals. It follows NIST SP 800-63B
and OWASP ASVS guidance: screen new passwords against known-common and
known-breached values, and prefer length over composition rules.

By default the rule is **fully offline** and case-insensitive — it only checks
the bundled common-password denylist, exactly as before. Every additional gate
is opt-in.

```php
use Simtabi\Laranail\Toolkit\Rules\RejectCommonPasswords;

$request->validate([
    'password' => ['required', 'string', new RejectCommonPasswords()],
]);
```

### Common-password denylist

The rule ships a denylist of the most common passwords, served by
`SecurityData::commonPasswords()` from the merged
[`config/security.php`](#merged-security-data-configsecurityphp) file
(section `passwords.common`) — a `list<string>` derived from the public SecLists
`xato-net-10-million-passwords` corpus (HIBP-aligned by prevalence), augmented
with a few historically common app credentials. Every entry is **lowercased and
the whole list is deduplicated**; comparison is case-insensitive (`Str::lower`)
and ignores surrounding whitespace.

The list is **static-cached** at two levels — `SecurityData` reads the config
file once per process, and the rule caches the returned list in its own static
property — so repeated validations never re-read the file.

### Optional gates

The constructor takes opt-in flags; all default to the original behaviour:

```php
new RejectCommonPasswords(
    minLength:      12,      // 0 = off  — reject shorter than N characters
    minEntropy:     60,      // 0 = off  — reject below N bits of estimated entropy
    checkHibp:      false,   //          — opt into the HIBP breach check (see below)
    hibpApiKey:     null,    //          — optional HIBP API key for the range request
    minZxcvbnScore: 0,       // 0 = off  — reject below this zxcvbn score 0–4 (see below)
);
```

Or via the fluent factory:

```php
$rule = RejectCommonPasswords::config()
    ->minLength(12)
    ->minEntropy(60)
    ->withHibp($apiKey)   // pass null/omit to skip the key — the range API needs none
    ->minZxcvbnScore(3)   // require at least a zxcvbn "good" rating
    ->rule();
```

**`minZxcvbnScore` (opt-in, off by default).** When set to `1–4` *and*
`bjeavons/zxcvbn-php` is installed, the rule additionally rejects any password
whose realistic zxcvbn strength score falls below the threshold, surfacing the
zxcvbn warning and suggestions in the failure message. If the optional package
is **absent** the gate is silently skipped (no behaviour change); a value
outside `0–4` throws `InvalidArgumentException`. See
[zxcvbn strength estimation](#zxcvbn-strength-estimation) below.

**Entropy estimate.** The entropy gate uses a Shannon-style estimate:

```
bits = length * log2(charsetPoolSize)
```

where the pool size is the sum of the character-class pools that actually appear
in the password — lowercase (26), uppercase (26), digits (10), and everything
else / symbols (33). A 12-char password mixing all four classes scores
`12 * log2(95) ≈ 78.8` bits; eight lowercase letters score
`8 * log2(26) ≈ 37.6` bits.

### HIBP k-anonymity breach check (opt-in)

With `checkHibp: true`, the rule queries the
[Have I Been Pwned Pwned Passwords range API](https://haveibeenpwned.com/API/v3#PwnedPasswords)
using **k-anonymity**:

1. SHA-1 the password **locally**.
2. Send **only the first five hex characters** of the hash to
   `https://api.pwnedpasswords.com/range/{prefix}`.
3. The API returns all hash suffixes sharing that prefix with breach counts; the
   rule matches the remaining 35-char suffix **locally**.

The plaintext password is **never transmitted** — only the 5-char SHA-1 prefix
leaves the process. An `Add-Padding: true` header is sent so responses are a
uniform size regardless of matches.

**Fail-open by design.** The breach check **never blocks a sign-up on API
trouble**: any non-200 response (429 rate limit, 5xx), timeout, or transport
error is treated as "not breached" and the password is accepted. The request
uses a short 3-second timeout. This keeps registration available even when HIBP
is down or unreachable.

```php
$rule = new RejectCommonPasswords(checkHibp: true);
// Offline / API down -> password is accepted (fail open).
// API reachable + suffix matches a breached hash -> password is rejected.
```

## `Token` — secure tokens, API keys & OTP codes

`Simtabi\Laranail\Toolkit\Modules\Security\Token` generates opaque, high-entropy
tokens (API keys, password-reset / verification tokens, CSRF nonces) and numeric
OTP codes. The random body comes from `random_bytes()`; signed tokens are
authenticated with `hash_hmac('sha256', …)` and verified in constant time with
`hash_equals()`.

```php
use Simtabi\Laranail\Toolkit\Modules\Security\Token;

// Unsigned, opaque key (Stripe-style prefix, RFC 4648 base64url body):
$key = Token::unsigned()->prefix('sk_live_')->encoding('base64url')->length(32)->generate();

// A 6-digit OTP:
$otp = Token::unsigned()->encoding('numeric')->length(6)->generate();

// A self-verifying, typed, expiring reset token:
$builder = Token::signed($appSecret)->type('reset')->expiresIn(3600)->encoding('hex')->length(32);
$token   = $builder->generate();
$builder->verify($token);          // true within the hour, false after / if tampered
```

### Entry points & chain

| Method | Effect |
|---|---|
| `Token::unsigned()` | Opaque token, no integrity tag. `verify()` throws on it. |
| `Token::signed(string $secret)` | HMAC-SHA256 signed; throws on an empty secret. |
| `prefix(string)` | Stripe-style identifier; signed into the MAC. |
| `length(int $bytes)` | Random body size, guarded to **8..1024** bytes. |
| `encoding(string)` | `hex`, `base64url`, `base32`, `alphanum` or `numeric` (OTP). |
| `expiresIn(int $seconds)` | Bind an expiry into a signed token (`0` = none). |
| `type(string)` | Purpose label (e.g. `reset`); signed into the MAC. |
| `generate(): string` / `verify(string): bool` / `__toString()` | Terminals. |

The encodings map to fixed alphabets: `hex` → `[0-9a-f]`, `base64url` → RFC 4648
§5 URL-safe with no `=` padding (`[A-Za-z0-9_-]`), `base32` → RFC 4648 §6
(`[A-Z2-7]`), `alphanum` → `[A-Za-z0-9]`, `numeric` → `[0-9]`. The `alphanum` and
`numeric` encodings fold bytes onto their alphabet with an **unbiased** modulo —
bytes in the skewed tail are redrawn with `random_int()` so the distribution
stays uniform.

### Token format

A signed token is the dot-joined:

```
prefix . encoded [ . expiry ] [ . type ] . hmac
```

- `prefix` — optional identifier (covered by the MAC, so it can't be swapped).
- `encoded` — the random body in the chosen encoding.
- `expiry` — Unix timestamp, present only when `expiresIn()` was set.
- `type` — present only when `type()` was set.
- `hmac` — base64url of `hash_hmac('sha256', signedBody, secret, raw)`, where
  `signedBody` is everything before the final `.`.

An **unsigned** token is just `prefix . encoded`. `verify()` re-derives the HMAC
over the presented `signedBody`, compares with `hash_equals()` (constant time),
then checks any embedded expiry. Any tampering — to the prefix, body, type or
expiry — breaks the MAC and yields `false`; an elapsed expiry also yields
`false`. Verifying on an `unsigned()` builder throws a `LogicException`. Store
tokens **hashed**, never log a full token (OWASP Cryptographic Storage).

## `Password` — random passwords

`Simtabi\Laranail\Toolkit\Modules\Security\Password` builds random passwords from
a configurable character-class pool using `random_int()`. Defaults follow NIST SP
800-63B / OWASP ASVS: prefer length and draw uniformly, with optional class
coverage and an entropy floor.

```php
use Simtabi\Laranail\Toolkit\Modules\Security\Password;

Password::strong()->generate();                       // 20 chars, all 4 classes, no ambiguous glyphs
Password::alphanumeric()->length(24)->generate();     // [A-Za-z0-9], 24 chars
Password::numeric()->generate();                      // 6-digit PIN
Password::basic()->generate();                        // 12 chars, lowercase + digits

$meta = Password::strong()->generateWithMetadata();
// ['password' => '…', 'entropy' => 127.15, 'charset_size' => 82, 'length' => 20,
//  'zxcvbn_score' => 4, 'zxcvbn_guesses' => 1.0e16, …]   ← zxcvbn keys present only when installed
```

| Method | Effect |
|---|---|
| `strong()` / `alphanumeric()` / `numeric()` / `basic()` | Presets. |
| `length(int)` | Password length (≥ 1). |
| `uppercase()` / `lowercase()` / `digits()` / `symbols()` | Toggle each class. |
| `excludeAmbiguous(bool = true)` | Remove confusable glyphs `0 O 1 l I`. |
| `requireEachClass(bool = true)` | Guarantee ≥ 1 char from each selected class. |
| `minEntropy(float $bits)` | Require a minimum estimated entropy. |
| `minStrength(int $score)` | Require a minimum zxcvbn score `0–4` (opt-in; see below). |
| `generate()` / `generateWithMetadata()` / `__toString()` | Terminals. |
| `Password::strength(string, array $userInputs = [])` | Static zxcvbn estimator. |

**Entropy** is the worst-case `length * log2(poolSize)` (bits) — the information
content of a uniform draw. `requireEachClass` and `minEntropy` are enforced by a
bounded retry loop. If the entropy target is **mathematically unreachable** for
the configured pool and length (e.g. `minEntropy(128)` on eight lowercase-only
chars, which caps at `8 * log2(26) ≈ 37.6` bits), `generate()` throws a
`RuntimeException` immediately rather than spinning. Selecting **no** character
class throws a `LogicException`.

### zxcvbn strength estimation

The optional [`bjeavons/zxcvbn-php`](https://github.com/bjeavons/zxcvbn-php)
package adds **realistic, guess-based** password-strength estimation (Dropbox's
zxcvbn) on top of the worst-case Shannon entropy above. Every call is guarded by
`class_exists(\ZxcvbnPhp\Zxcvbn::class)`, so the package is a soft dependency —
nothing breaks when it is absent; the zxcvbn features simply turn off.

**`minStrength(int $score)`** — require the *generated* password to reach a
minimum zxcvbn score on the `0–4` scale (`0` = trivially guessable, `4` = very
strong). When the gate is on and zxcvbn is installed, `generate()` regenerates
candidates (bounded at 50 attempts, as zxcvbn is comparatively slow) until the
score is met, throwing `RuntimeException` if the target is effectively
unreachable (e.g. `Password::numeric()->minStrength(4)` — a 6-digit PIN can
never score 4). A score outside `0–4` throws `InvalidArgumentException`. When
zxcvbn is **absent** the gate is a no-op.

```php
Password::strong()->minStrength(4)->generate();   // regenerates until zxcvbn score ≥ 4
```

**`generateWithMetadata()`** adds four extra keys **only when zxcvbn is
installed**: `zxcvbn_score` (int 0–4), `zxcvbn_guesses` (float),
`zxcvbn_crack_times_seconds` (array), and `zxcvbn_feedback`
(`['warning' => string, 'suggestions' => string[]]`).

**`Password::strength(string $password, array $userInputs = [])`** is a static
estimator returning the full zxcvbn result (`score`, `guesses`,
`crack_times_seconds`, `feedback`). Pass `$userInputs` (names, email, etc.) to
penalise site-specific tokens. It throws a `LogicException` with the
`composer require bjeavons/zxcvbn-php` hint when the package is not installed.

```php
$result = Password::strength('correct horse battery staple');
// $result['score'] (0–4), $result['feedback']['suggestions'], …
```

## `Passphrase` — EFF diceware

`Simtabi\Laranail\Toolkit\Modules\Security\Passphrase` builds memorable,
high-entropy passphrases by drawing words uniformly (`random_int()`) from the
**EFF Large Wordlist** — 7776 public-domain (CC0) words served by
`SecurityData::passphraseWords()` from the merged
[`config/security.php`](#merged-security-data-configsecurityphp) file
(section `passphrases.wordlist`). The list is **static-cached**: loaded once per
process and asserted by `SecurityData` to contain exactly 7776 entries, never
re-read per `generate()`.

```php
use Simtabi\Laranail\Toolkit\Modules\Security\Passphrase;

Passphrase::memorable()->generate();                  // correct-horse-battery-staple-…  (6 words)
Passphrase::default()->wordCount(4)->separator('_')->capitalize('title')->generate();
Passphrase::memorable()->withNumber(2)->withSymbol('!')->generate();

$meta = Passphrase::memorable()->generateWithMetadata();
// ['passphrase' => '…', 'entropy' => 77.55, 'word_count' => 6, 'words' => [...]]
```

| Method | Effect |
|---|---|
| `memorable()` / `default()` | Presets (6 hyphenated words). |
| `wordCount(int)` | Number of words, guarded to **1..20**. |
| `separator(string)` | `-`, `_`, ` ` (space) or `''` (none). |
| `capitalize(string)` | `none`, `first`, `all` or `title` (title-cases every word, incl. each segment of a hyphenated compound — `felt-tip` → `Felt-Tip`). |
| `withNumber(int $digits)` | Append a random decimal token. |
| `withSymbol(?string)` | Append a symbol (`null` = random from a safe set). |
| `generate()` / `generateWithMetadata()` / `__toString()` | Terminals. |

**Entropy** is `wordCount * log2(7776) ≈ 12.925 bits/word` — so the 6-word
default scores ≈ **77.5 bits**, the EFF's recommended memorable-but-strong point.

## Merged security data (`config/security.php`)

All three bundled datasets live in a single, well-organized
`config/security.php` file:

| Section | Used by | Notes |
|---|---|---|
| `passwords.common` | `RejectCommonPasswords` | 560 lowercased, deduplicated entries (SecLists / HIBP-aligned). |
| `passphrases.wordlist` | `Passphrase` | Exactly 7776 EFF CC0 words (diceware). |
| `redact_keys` | `AccessLogMiddleware` | Default request-data redaction keys (overridable). |

They are read through a single lazy accessor,
`Simtabi\Laranail\Toolkit\Modules\Security\SecurityData`:

```php
use Simtabi\Laranail\Toolkit\Modules\Security\SecurityData;

SecurityData::commonPasswords(); // list<string> (560)
SecurityData::passphraseWords(); // list<string> (exactly 7776; throws RuntimeException otherwise)
SecurityData::redactKeys();      // list<string>
```

The datasets are merged under `laranail.toolkit.security`, so `SecurityData`
reads `config('laranail.toolkit.security.*')` when an app is booted. It also works
**without a booted Laravel app** (the Security generators are pure value objects):
with no app it falls back to a `__DIR__`-relative read of the package default.
The data is resolved at most once per process and statically cached.

To customise the datasets, publish the configs and edit the published
`config/laranail/toolkit/security.php`:

```bash
php artisan vendor:publish --tag=laranail::toolkit-config
```

The edited file is merged over the package default like any other namespaced
config (no separate publish tag).

> **`SecurityData` is static-by-design.** It is a `final` class with only
> **static** accessors (`commonPasswords()`, `passphraseWords()`,
> `redactKeys()`) and is **deliberately NOT fronted by the `Toolkit` facade** —
> the generators that consume it are pure value objects with standalone unit
> tests that must run without a booted app, so the accessor never calls
> `config()` or `config_path()` unguarded. Call it statically.

## Access log

`Modules\Security\AccessLog` holds the terminate-phase request-logging
middleware and its Eloquent model. The middleware is registered under the
`laranail-toolkit.access-log` alias; the full request-lifecycle and configuration walkthrough
lives in [access-log.md](access-log.md). The security-relevant pieces:

### `AccessLog` model

`Modules\Security\AccessLog\AccessLog` is the Eloquent model the middleware
writes to. Fillable: `ip`, `method`, `url`, `user_agent`, `request_data`
(cast to `array`). The migration ships under the `laranail::toolkit-migrations`
publish tag.

### `AccessLogMiddleware` redaction

`Modules\Security\AccessLog\AccessLogMiddleware` persists the request **after**
the response is sent (`terminate()`), wrapped in a `try/catch` so logging never
adds latency to — nor breaks — the request. Before storing, it **recursively,
case-insensitively redacts** sensitive request keys to `[REDACTED]`, and drops
the query string from the stored URL so secrets passed as query params are never
persisted.

The redaction deny-list is resolved in this order:

1. `config('laranail.toolkit.access_log.redact')` when set;
2. otherwise `SecurityData::redactKeys()` (the publishable
   [`redact_keys`](#merged-security-data-configsecurityphp) section); and
3. a built-in `DEFAULT_REDACT` const fallback (`password`,
   `password_confirmation`, `current_password`, `token`, `_token`, `secret`,
   `authorization`, `api_key`, `access_token`, `refresh_token`, `credit_card`,
   `card_number`, `cvv`, `ssn`) when the security data is empty.

Set `access_log.enabled => false` to disable persistence entirely.

[← Docs index](../README.md#documentation)
