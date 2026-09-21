<?php

namespace App\Services\OpenRouter;

use App\Services\OpenRouter\Contracts\AiProvider;
use App\Services\OpenRouter\DTO\AiResponse;
use Closure;
use PHPUnit\Framework\Assert;
use Throwable;

class FakeAiProvider implements AiProvider
{
    /**
     * @var array<int, array{apiKey: string, model: string, systemPrompt: string, userPrompt: string, options: array}>
     */
    public array $calls = [];

    protected ?Closure $customHandler = null;

    public function __construct(
        protected string $defaultContent = 'Respuesta simulada generada por IA.',
        protected int $defaultInputTokens = 120,
        protected int $defaultOutputTokens = 350,
        protected ?float $defaultCostUsd = 0.0015
    ) {}

    public function withHandler(Closure $handler): self
    {
        $this->customHandler = $handler;

        return $this;
    }

    public function generate(
        string $apiKey,
        string $model,
        string $systemPrompt,
        string $userPrompt,
        array $options = []
    ): AiResponse {
        $this->calls[] = [
            'apiKey' => $apiKey,
            'model' => $model,
            'systemPrompt' => $systemPrompt,
            'userPrompt' => $userPrompt,
            'options' => $options,
        ];

        if ($this->customHandler) {
            $result = ($this->customHandler)($apiKey, $model, $systemPrompt, $userPrompt, $options);
            if ($result instanceof AiResponse) {
                return $result;
            }
            if ($result instanceof Throwable) {
                throw $result;
            }
        }

        return new AiResponse(
            content: $this->defaultContent,
            inputTokens: $this->defaultInputTokens,
            outputTokens: $this->defaultOutputTokens,
            totalTokens: $this->defaultInputTokens + $this->defaultOutputTokens,
            costUsd: $this->defaultCostUsd,
            latencyMs: 120,
            model: $model,
            raw: ['fake' => true]
        );
    }

    public function assertCalled(int $count = 1): void
    {
        Assert::assertCount($count, $this->calls, "Se esperaban {$count} llamadas al proveedor de IA simulado.");
    }
}
