# LLM providers

A single contract, `LLMProviderInterface`, abstracts three drivers — OpenAI,
Claude, and Gemini. The driver bound in the container is chosen by
`config('laranail.toolkit.llm.default_provider')` (`openai` by default).

## Contract

```php
namespace Simtabi\Laranail\Toolkit\Modules\LLM;

interface LLMProviderInterface
{
    public function generateResponse(
        string $modelName,
        array $messages,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?array $stop = null,
        ?float $topP = null,
        ?float $frequencyPenalty = null,
        ?float $presencePenalty = null,
        ?array $logitBias = null,
        ?string $user = null,
        ?bool $jsonMode = false,
        bool $fullResponse = false,
    );
}
```

`$messages` follows the chat format: `[['role' => 'user', 'content' => '...']]`.

## Usage

Inject the contract; the configured provider is resolved for you:

```php
use Simtabi\Laranail\Toolkit\Modules\LLM\LLMProviderInterface;

public function __construct(private LLMProviderInterface $llm) {}

$response = $this->llm->generateResponse(
    modelName: 'gpt-4o-mini',
    messages: [
        ['role' => 'system', 'content' => 'You are concise.'],
        ['role' => 'user', 'content' => 'Summarize Laravel queues.'],
    ],
    temperature: 0.4,
    maxTokens: 200,
);

echo $response->getContent();
```

The module is wired by the deferred `Modules\LLM\LLMServiceProvider`, which binds
`LLMProviderInterface` to the configured default driver. A `LLM` facade
(alias `LLM`) fronts the same resolved provider for quick, non-injected calls:

```php
use Simtabi\Laranail\Toolkit\Modules\LLM\LLM;

$response = LLM::generateResponse(modelName: 'gpt-4o-mini', messages: [
    ['role' => 'user', 'content' => 'Summarize Laravel queues.'],
]);
```

Pass `jsonMode: true` to request a JSON object (OpenAI sets
`response_format = {type: json_object}`), and `fullResponse: true` to populate
the response object's `model`, `usage`, and raw payload.

## Response objects

Each driver returns its own response DTO — `OpenAI\OpenAIResponse`,
`Claude\ClaudeResponse`, `Gemini\GeminiResponse` — all **`final readonly`**
classes implementing `JsonSerializable`, with an identical constructor
(`string $content, ?string $model = null, ?object $usage = null, ?object
$rawResponse = null`) and the same shape:

| Member | |
|--------|---|
| `getContent(): string` | The generated text. |
| `getModel(): ?string` | Model id (populated when `fullResponse`). |
| `getUsage(): ?object` | Token usage (populated when `fullResponse`). |
| `getRawResponse(): ?object` | The underlying SDK/HTTP payload. |
| `toArray()` / `toJson()` / `jsonSerialize()` | Serialization helpers (`toArray()` emits `content` / `model` / `usage`). |

The public `content` / `model` / `usage` / `rawResponse` promoted properties are
also readable directly. As `readonly` DTOs they are immutable once constructed.

## Drivers

| Provider | Constructor | Default model (config) |
|----------|-------------|------------------------|
| OpenAI | `__construct(string $apiKey, int $maxRetries = 3, int $retryDelay = 2)` | `gpt-3.5-turbo` |
| Claude | `__construct(string $apiKey, int $maxRetries = 3, int $retryDelay = 2, string $baseUrl = 'https://api.anthropic.com')` | `claude-3-5-sonnet-20241022` |
| Gemini | `__construct(string $apiKey, int $maxRetries = 3, int $retryDelay = 2, string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta')` | `gemini-2.0-flash` |

OpenAI uses the `openai-php/client` SDK. Claude and Gemini call their HTTP APIs
directly through the shared `RetriesHttpRequests` concern. Each provider class
(`OpenAIProvider`, `ClaudeProvider`, `GeminiProvider`) and each response DTO is
declared **`final`** — extend the behaviour by composing a new
`LLMProviderInterface` implementation rather than subclassing a driver.

### Configuration keys

The G14a alignment nests **every** provider's credentials and tuning under its
own key beneath `laranail.toolkit.llm.<provider>` (NOT as siblings of `llm`):

```php
// config/toolkit.php (the package file) → merged into config('laranail.toolkit.llm.*')
'default_provider' => env('LLM_DEFAULT_PROVIDER', 'openai'),   // openai | gemini | claude
'openai' => ['api_key' => env('OPENAI_API_KEY'), 'max_retries' => 3, 'retry_delay' => 2,
             'default_model' => 'gpt-3.5-turbo', 'default_temperature' => 0.7,
             'default_max_tokens' => 300, 'default_top_p' => 1.0],
'gemini' => ['api_key' => env('GEMINI_API_KEY'), 'max_retries' => 3, 'retry_delay' => 2,
             'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
             'default_model' => 'gemini-2.0-flash', ...],
'claude' => ['api_key' => env('CLAUDE_API_KEY'), 'max_retries' => 3, 'retry_delay' => 2,
             'base_url' => 'https://api.anthropic.com',
             'default_model' => 'claude-3-5-sonnet-20241022', ...],
```

You can also resolve a specific driver directly when you need to bypass the
configured default:

```php
use Simtabi\Laranail\Toolkit\Modules\LLM\Claude\ClaudeProvider;

$claude = new ClaudeProvider(apiKey: config('laranail.toolkit.llm.claude.api_key'));
```

## Retries & errors

Requests are retried on transient failures up to `max_retries`, sleeping
`retry_delay` seconds between attempts. The shared HTTP concern retries only on
retryable statuses (HTTP 429 and 5xx) and fails fast on 4xx. A non-retryable or
exhausted failure surfaces as `LLMRequestException`, whose `isRetryable()`
reports whether the failure was transient. `RetriesHttpRequests::sanitizeBaseUrl()`
rejects non-HTTP(S) base URLs.

## Configuration

See [configuration](../configuration.md) for the full key reference. At minimum
set the API key and (optionally) the default provider:

```dotenv
LLM_DEFAULT_PROVIDER=openai
OPENAI_API_KEY=sk-...
```

[← Docs index](../../README.md#documentation)
