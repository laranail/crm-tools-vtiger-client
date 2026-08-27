<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\LLM\Gemini;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Toolkit\Modules\LLM\LLMProviderInterface;
use Simtabi\Laranail\Toolkit\Modules\LLM\LLMRequestException;
use Simtabi\Laranail\Toolkit\Modules\LLM\RetriesHttpRequests;

final class GeminiProvider implements LLMProviderInterface
{
    use RetriesHttpRequests;

    private string $baseUrl;

    public function __construct(
        private string $apiKey,
        private int $maxRetries = 3,
        private int $retryDelay = 2,
        string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta'
    ) {
        $this->baseUrl = $this->sanitizeBaseUrl($baseUrl);
    }

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
        bool $fullResponse = false
    ): GeminiResponse {
        // Auth via header, never as a query param (which would leak into logs/proxies).
        $endpoint = $this->baseUrl . '/models/' . $modelName . ':generateContent';

        [$contents, $systemInstruction] = $this->mapMessages($messages);

        $generationConfig = [];
        if ($temperature !== null) {
            $generationConfig['temperature'] = $temperature;
        }
        if ($maxTokens !== null) {
            $generationConfig['maxOutputTokens'] = $maxTokens;
        }
        if ($topP !== null) {
            $generationConfig['topP'] = $topP;
        }
        if ($stop !== null) {
            $generationConfig['stopSequences'] = $stop;
        }
        if ($jsonMode === true) {
            $generationConfig['responseMimeType'] = 'application/json';
        }

        $payload = ['contents' => $contents];
        if ($systemInstruction !== null) {
            $payload['systemInstruction'] = $systemInstruction;
        }
        if (!empty($generationConfig)) {
            $payload['generationConfig'] = $generationConfig;
        }

        return $this->executeWithRetry(function () use ($endpoint, $payload, $fullResponse, $modelName) {
            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $this->apiKey,
                ])->post($endpoint, $payload);
            } catch (ConnectionException $e) {
                throw new LLMRequestException('Gemini API connection failed: ' . $e->getMessage(), retryable: true, previous: $e);
            }

            if (!$response->successful()) {
                $status = $response->status();
                $body = $response->json();
                $errorMessage = is_array($body) ? data_get($body, 'error.message') : null;
                $message = is_string($errorMessage) ? $errorMessage : 'Gemini API request failed';

                throw new LLMRequestException(
                    "Gemini API request failed (HTTP {$status}): {$message}",
                    retryable: $this->isRetryableStatus($status),
                    status: $status,
                );
            }

            $data = $response->json();
            $data = is_array($data) ? $data : [];

            $text = '';
            $parts = data_get($data, 'candidates.0.content.parts');
            if (is_iterable($parts)) {
                foreach ($parts as $part) {
                    $partText = is_array($part) ? ($part['text'] ?? null) : null;
                    if (is_string($partText)) {
                        $text .= $partText;
                    }
                }
            }

            $usage = $data['usageMetadata'] ?? [];

            return new GeminiResponse(
                // Gemini does not echo the model; report the requested model.
                content: $text,
                model: $modelName,
                usage: (object) (is_array($usage) ? $usage : []),
                rawResponse: $fullResponse ? (object) $data : null
            );
        }, 'Gemini');
    }

    /**
     * Map chat messages to Gemini `contents`, hoisting any system messages into
     * a `systemInstruction` payload (Gemini only accepts user/model roles).
     *
     * @param array<int, array<string, mixed>> $messages
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>|null}
     */
    private function mapMessages(array $messages): array
    {
        $contents = [];
        /** @var list<string> $system */
        $system = [];

        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $rawText = $message['content'] ?? '';
            $text = is_string($rawText) ? $rawText : '';

            if ($role === 'system') {
                $system[] = $text;

                continue;
            }

            // Gemini uses 'user' and 'model' roles.
            if ($role === 'assistant') {
                $role = 'model';
            }

            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $text]],
            ];
        }

        $systemInstruction = $system === []
            ? null
            : ['parts' => [['text' => implode("\n", $system)]]];

        return [$contents, $systemInstruction];
    }
}
