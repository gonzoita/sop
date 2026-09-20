<?php

namespace Tests\Unit;

use App\Exceptions\SopValidationException;
use App\Support\Sop\BlockValidator;
use PHPUnit\Framework\TestCase;

class BlockValidatorTest extends TestCase
{
    public function test_valid_blocks_pass_validation(): void
    {
        $blocks = [
            'schema_version' => 1,
            'blocks' => [
                [
                    'id' => 'b1',
                    'type' => 'heading',
                    'props' => ['text' => 'Fase 1: Información inicial'],
                ],
                [
                    'id' => 'b2',
                    'type' => 'input',
                    'props' => [
                        'key' => 'nombre_marca',
                        'label' => 'Nombre de la marca',
                        'field' => 'text',
                        'required' => true,
                    ],
                ],
                [
                    'id' => 'b3',
                    'type' => 'text',
                    'props' => [
                        'html' => '<p>Bienvenido a la marca {{nombre_marca}}</p>',
                    ],
                ],
                [
                    'id' => 'b4',
                    'type' => 'ai_task',
                    'props' => [
                        'skill_slug' => 'generar-brief',
                        'model' => 'openrouter/auto',
                        'inputs' => [
                            'marca' => '{{nombre_marca}}',
                        ],
                        'output_key' => 'brief_resultado',
                    ],
                ],
                [
                    'id' => 'b5',
                    'type' => 'approval',
                    'props' => [
                        'role' => 'editor',
                    ],
                ],
                [
                    'id' => 'b6',
                    'type' => 'handoff',
                    'props' => [
                        'to' => 'client',
                        'message' => 'Por favor revisa el brief: {{brief_resultado}}',
                    ],
                ],
            ],
        ];

        $errors = BlockValidator::validate($blocks, throw: true);
        $this->assertEmpty($errors);
    }

    public function test_duplicate_block_id_throws_exception(): void
    {
        $this->expectException(SopValidationException::class);
        $this->expectExceptionMessage("ID de bloque duplicado: 'b1'");

        $blocks = [
            [
                'id' => 'b1',
                'type' => 'heading',
                'props' => ['text' => 'Primer encabezado'],
            ],
            [
                'id' => 'b1',
                'type' => 'text',
                'props' => ['html' => '<p>Texto</p>'],
            ],
        ];

        BlockValidator::validate($blocks);
    }

    public function test_undefined_variable_throws_exception(): void
    {
        $this->expectException(SopValidationException::class);
        $this->expectExceptionMessage("Variable no definida: la variable '{{variable_inexistente}}' referenciada en el bloque 'b2' no existe en este SOP.");

        $blocks = [
            [
                'id' => 'b1',
                'type' => 'heading',
                'props' => ['text' => 'Paso 1'],
            ],
            [
                'id' => 'b2',
                'type' => 'text',
                'props' => [
                    'html' => '<p>Hola {{variable_inexistente}}</p>',
                ],
            ],
        ];

        BlockValidator::validate($blocks);
    }

    public function test_forward_reference_variable_throws_exception(): void
    {
        $this->expectException(SopValidationException::class);
        $this->expectExceptionMessage("Referencia hacia adelante inválida: la variable '{{nombre_cliente}}' en el bloque 'b1' se define más adelante en el SOP (bloque 'b2').");

        $blocks = [
            [
                'id' => 'b1',
                'type' => 'text',
                'props' => [
                    'html' => '<p>Bienvenido {{nombre_cliente}}</p>',
                ],
            ],
            [
                'id' => 'b2',
                'type' => 'input',
                'props' => [
                    'key' => 'nombre_cliente',
                    'label' => 'Nombre del cliente',
                    'field' => 'text',
                ],
            ],
        ];

        BlockValidator::validate($blocks);
    }

    public function test_unknown_block_type_throws_exception(): void
    {
        $this->expectException(SopValidationException::class);
        $this->expectExceptionMessage("Tipo de bloque desconocido 'robot_task' en el bloque 'b1'.");

        $blocks = [
            [
                'id' => 'b1',
                'type' => 'robot_task',
                'props' => [],
            ],
        ];

        BlockValidator::validate($blocks);
    }

    public function test_invalid_snake_case_input_key_throws_exception(): void
    {
        $this->expectException(SopValidationException::class);
        $this->expectExceptionMessage("La variable 'Nombre Invalido!' en el bloque 'b1' debe ser snake_case");

        $blocks = [
            [
                'id' => 'b1',
                'type' => 'input',
                'props' => [
                    'key' => 'Nombre Invalido!',
                    'label' => 'Campo',
                    'field' => 'text',
                ],
            ],
        ];

        BlockValidator::validate($blocks);
    }

    public function test_unsupported_input_field_throws_exception(): void
    {
        $this->expectException(SopValidationException::class);
        $this->expectExceptionMessage("El tipo de campo 'matrix' en el bloque 'b1' no es válido.");

        $blocks = [
            [
                'id' => 'b1',
                'type' => 'input',
                'props' => [
                    'key' => 'campo_valido',
                    'label' => 'Campo',
                    'field' => 'matrix',
                ],
            ],
        ];

        BlockValidator::validate($blocks);
    }

    public function test_validation_without_throwing_returns_errors_array(): void
    {
        $blocks = [
            [
                'id' => 'b1',
                'type' => 'input',
                'props' => [
                    'key' => 'variable_uno',
                    'label' => 'Uno',
                    'field' => 'text',
                ],
            ],
            [
                'id' => 'b2',
                'type' => 'text',
                'props' => [
                    'html' => '<p>{{no_existe}}</p>',
                ],
            ],
        ];

        $errors = BlockValidator::validate($blocks, throw: false);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('no_existe', $errors[0]);
    }
}
