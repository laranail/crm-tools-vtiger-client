<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\LLM;

use Illuminate\Support\Facades\Facade;

/**
 * Facade over the resolved default LLM provider (OpenAI / Gemini / Claude),
 * selected via `laranail.toolkit.llm.default_provider`.
 *
 * @method static mixed generateResponse(string $modelName, array<int, mixed> $messages, ?float $temperature = null, ?int $maxTokens = null, ?array<int, string> $stop = null, ?float $topP = null, ?float $frequencyPenalty = null, ?float $presencePenalty = null, ?array<string, float> $logitBias = null, ?string $user = null, ?bool $jsonMode = false, bool $fullResponse = false)
 *
 * @see LLMProviderInterface
 */
class LLM extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LLMProviderInterface::class;
    }
}
