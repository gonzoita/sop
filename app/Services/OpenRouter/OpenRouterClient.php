<?php

namespace App\Services\OpenRouter;

use App\Services\OpenRouter\Contracts\AiProvider;
use App\Services\OpenRouter\DTO\AiResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenRouterClient implements AiProvider
{
    protected string $baseUrl = 'https://openrouter.ai/api/v1';

    public function __construct(?string $baseUrl = null)
    {
        if ($baseUrl) {
            $this->baseUrl = rtrim($baseUrl, '/');
        }
    }

    /**
     * Generate content via OpenRouter Chat Completions API.
     */
    public function generate(
        string $apiKey,
        string $model,
        string $systemPrompt,
        string $userPrompt,
        array $options = []
    ): AiResponse {
        $endpoint = "{$this->baseUrl}/chat/completions";

        $messages = [];
        if (! empty(trim($systemPrompt))) {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $userPrompt,
        ];

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.4,
        ], array_diff_key($options, ['temperature' => null]));

        $startTime = hrtime(true);

        $response = Http::withToken($apiKey)
            ->withHeaders([
                'HTTP-Referer' => config('app.url', 'https://sop.briela.app'),
                'X-Title' => 'SOPForge',
            ])
            ->timeout($options['timeout'] ?? 120)
            ->retry(2, 2000, throw: false)
            ->post($endpoint, $payload);

        $endTime = hrtime(true);
        $latencyMs = (int) round(($endTime - $startTime) / 1e6);

        if ($response->failed()) {
            $errorMessage = $response->json('error.message')
                ?? $response->json('message')
                ?? "Error de OpenRouter HTTP {$response->status()}: {$response->body()}";

            throw new RuntimeException($errorMessage, $response->status());
        }

        $data = $response->json() ?? [];
        $content = $data['choices'][0]['message']['content'] ?? '';

        $usage = $data['usage'] ?? [];
        $inputTokens = (int) ($usage['prompt_tokens'] ?? 0);
        $outputTokens = (int) ($usage['completion_tokens'] ?? 0);
        $totalTokens = (int) ($usage['total_tokens'] ?? ($inputTokens + $outputTokens));

        // Extraer costo si viene reportado en el payload (total_cost de OpenRouter)
        $costUsd = null;
        if (isset($usage['total_cost']) && is_numeric($usage['total_cost'])) {
            $costUsd = (float) $usage['total_cost'];
        } elseif (isset($data['cost']) && is_numeric($data['cost'])) {
            $costUsd = (float) $data['cost'];
        }

        return new AiResponse(
            content: $content,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            totalTokens: $totalTokens,
            costUsd: $costUsd,
            latencyMs: $latencyMs,
            model: $data['model'] ?? $model,
            raw: $data
        );
    }
}
