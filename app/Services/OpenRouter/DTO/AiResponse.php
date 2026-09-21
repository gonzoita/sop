<?php

namespace App\Services\OpenRouter\DTO;

class AiResponse
{
    public function __construct(
        public readonly string $content,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $totalTokens,
        public readonly ?float $costUsd,
        public readonly int $latencyMs,
        public readonly string $model,
        public readonly array $raw = []
    ) {}

    /**
     * Convert the response object to an array for storage or JSON serialization.
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'total_tokens' => $this->totalTokens,
            'cost_usd' => $this->costUsd,
            'latency_ms' => $this->latencyMs,
            'model' => $this->model,
        ];
    }
}
