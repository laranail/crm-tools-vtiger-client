<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\LLM\Claude;

use JsonSerializable;

final readonly class ClaudeResponse implements JsonSerializable
{
    public function __construct(
        public string $content,
        public ?string $model = null,
        public ?object $usage = null,
        public ?object $rawResponse = null
    ) {}

    public function getContent(): string
    {
        return $this->content;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getUsage(): ?object
    {
        return $this->usage;
    }

    public function getRawResponse(): ?object
    {
        return $this->rawResponse;
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'model' => $this->model,
            'usage' => $this->usage,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
