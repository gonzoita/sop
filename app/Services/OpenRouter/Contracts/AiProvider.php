<?php

namespace App\Services\OpenRouter\Contracts;

use App\Services\OpenRouter\DTO\AiResponse;

interface AiProvider
{
    /**
     * Generate content using an AI model.
     *
     * @param string $apiKey Plaintext API key
     * @param string $model Model identifier (e.g. 'anthropic/claude-3.5-sonnet', 'openai/gpt-4o-mini')
     * @param string $systemPrompt System instructions
     * @param string $userPrompt User prompt with resolved variables
     * @param array $options Additional parameters like temperature, max_tokens
     * @return AiResponse
     * @throws \Throwable
     */
    public function generate(
        string $apiKey,
        string $model,
        string $systemPrompt,
        string $userPrompt,
        array $options = []
    ): AiResponse;
}
