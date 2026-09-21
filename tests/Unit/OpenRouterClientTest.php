<?php

namespace Tests\Unit;

use App\Services\OpenRouter\Contracts\AiProvider;
use App\Services\OpenRouter\FakeAiProvider;
use App\Services\OpenRouter\OpenRouterClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenRouterClientTest extends TestCase
{
    public function test_open_router_client_sends_correct_payload_and_headers(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'id' => 'gen-123456',
                'model' => 'anthropic/claude-3.5-sonnet',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Entregable generado con éxito.',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 150,
                    'completion_tokens' => 420,
                    'total_tokens' => 570,
                    'total_cost' => 0.00255,
                ],
            ], 200),
        ]);

        $client = new OpenRouterClient();
        $response = $client->generate(
            apiKey: 'sk-or-v1-testkey123456789',
            model: 'anthropic/claude-3.5-sonnet',
            systemPrompt: 'Eres un estratega experto.',
            userPrompt: 'Crea un brief para Marca X.',
            options: ['temperature' => 0.3]
        );

        $this->assertEquals('Entregable generado con éxito.', $response->content);
        $this->assertEquals(150, $response->inputTokens);
        $this->assertEquals(420, $response->outputTokens);
        $this->assertEquals(570, $response->totalTokens);
        $this->assertEquals(0.00255, $response->costUsd);
        $this->assertEquals('anthropic/claude-3.5-sonnet', $response->model);
        $this->assertGreaterThanOrEqual(0, $response->latencyMs);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer sk-or-v1-testkey123456789') &&
                   $request->hasHeader('X-Title', 'SOPForge') &&
                   $request['model'] === 'anthropic/claude-3.5-sonnet' &&
                   $request['messages'][0]['role'] === 'system' &&
                   $request['messages'][1]['role'] === 'user' &&
                   $request['temperature'] === 0.3;
        });
    }

    public function test_open_router_client_handles_cost_null_when_not_provided(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'model' => 'meta-llama/llama-3.1-8b-instruct',
                'choices' => [
                    ['message' => ['content' => 'Texto sin costo devuelto.']],
                ],
                'usage' => [
                    'prompt_tokens' => 80,
                    'completion_tokens' => 120,
                    'total_tokens' => 200,
                ],
            ], 200),
        ]);

        $client = new OpenRouterClient();
        $response = $client->generate(
            apiKey: 'sk-or-v1-test',
            model: 'meta-llama/llama-3.1-8b-instruct',
            systemPrompt: '',
            userPrompt: 'Hola'
        );

        $this->assertEquals('Texto sin costo devuelto.', $response->content);
        $this->assertNull($response->costUsd);
        $this->assertEquals(200, $response->totalTokens);
    }

    public function test_open_router_client_throws_exception_on_api_error(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Clave de API no válida o expirada.',
                    'code' => 401,
                ],
            ], 401),
        ]);

        $client = new OpenRouterClient();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Clave de API no válida o expirada.');

        $client->generate(
            apiKey: 'sk-or-v1-invalid',
            model: 'openai/gpt-4o-mini',
            systemPrompt: '',
            userPrompt: 'Test'
        );
    }

    public function test_fake_ai_provider_works_deterministically_for_tests(): void
    {
        $fake = new FakeAiProvider(
            defaultContent: 'Resultado simulado específico',
            defaultInputTokens: 50,
            defaultOutputTokens: 100,
            defaultCostUsd: 0.0008
        );

        $response = $fake->generate(
            apiKey: 'key',
            model: 'test-model',
            systemPrompt: 'Sys',
            userPrompt: 'User'
        );

        $this->assertEquals('Resultado simulado específico', $response->content);
        $this->assertEquals(50, $response->inputTokens);
        $this->assertEquals(100, $response->outputTokens);
        $this->assertEquals(150, $response->totalTokens);
        $this->assertEquals(0.0008, $response->costUsd);

        $fake->assertCalled(1);
    }

    public function test_app_service_provider_resolves_ai_provider(): void
    {
        $resolved = app(AiProvider::class);
        $this->assertInstanceOf(OpenRouterClient::class, $resolved);
    }
}
