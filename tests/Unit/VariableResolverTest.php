<?php

namespace Tests\Unit;

use App\Exceptions\VariableUnresolvedException;
use App\Support\Sop\VariableResolver;
use PHPUnit\Framework\TestCase;

class VariableResolverTest extends TestCase
{
    public function test_extracts_variables_from_string(): void
    {
        $text = 'Hola {{nombre_cliente}}, tu plan es {{tipo_plan}} y tu meta es {{objetivo}}.';
        $vars = VariableResolver::extractVariables($text);

        $this->assertEquals(['nombre_cliente', 'tipo_plan', 'objetivo'], $vars);
    }

    public function test_extracts_variables_from_nested_array(): void
    {
        $payload = [
            'titulo' => 'Reporte para {{empresa}}',
            'config' => [
                'prompt' => 'Analiza a {{empresa}} con presupuesto {{presupuesto}}',
                'destinatario' => '{{correo_contacto}}',
            ],
            'tags' => ['fijo', '{{tag_dinamico}}'],
        ];

        $vars = VariableResolver::extractVariables($payload);

        $this->assertEquals(['empresa', 'presupuesto', 'correo_contacto', 'tag_dinamico'], $vars);
    }

    public function test_resolves_string_with_variables(): void
    {
        $template = 'Estimado {{cliente}}, su saldo es {{saldo}} USD.';
        $values = [
            'cliente' => 'Acme Corp',
            'saldo' => 500,
        ];

        $resolved = VariableResolver::resolveString($template, $values);

        $this->assertEquals('Estimado Acme Corp, su saldo es 500 USD.', $resolved);
    }

    public function test_resolves_array_recursively(): void
    {
        $payload = [
            'titulo' => 'Reporte {{mes}}',
            'metricas' => [
                'clics' => '{{total_clics}}',
            ],
        ];

        $values = [
            'mes' => 'Septiembre',
            'total_clics' => 1250,
        ];

        $resolved = VariableResolver::resolveArray($payload, $values);

        $this->assertEquals([
            'titulo' => 'Reporte Septiembre',
            'metricas' => [
                'clics' => 1250,
            ],
        ], $resolved);
    }

    public function test_missing_variable_in_strict_mode_throws_exception(): void
    {
        $this->expectException(VariableUnresolvedException::class);
        $this->expectExceptionMessage("La variable '{{variable_faltante}}' no pudo ser resuelta");

        $template = 'Bienvenido {{cliente}}, su token es {{variable_faltante}}.';
        $values = ['cliente' => 'Juan'];

        VariableResolver::resolveString($template, $values, strict: true);
    }

    public function test_missing_variable_in_non_strict_mode_keeps_placeholder(): void
    {
        $template = 'Bienvenido {{cliente}}, su token es {{variable_faltante}}.';
        $values = ['cliente' => 'Juan'];

        $resolved = VariableResolver::resolveString($template, $values, strict: false);

        $this->assertEquals('Bienvenido Juan, su token es {{variable_faltante}}.', $resolved);
    }
}
