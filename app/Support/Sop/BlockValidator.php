<?php

namespace App\Support\Sop;

use App\Exceptions\SopValidationException;

class BlockValidator
{
    public const SUPPORTED_BLOCK_TYPES = [
        'heading',
        'text',
        'checklist',
        'input',
        'media',
        'decision',
        'ai_task',
        'approval',
        'handoff',
    ];

    public const SUPPORTED_INPUT_FIELDS = [
        'text',
        'textarea',
        'number',
        'date',
        'select',
        'multiselect',
        'url',
        'file',
    ];

    /**
     * Validate an SOP blocks payload.
     *
     * @param  array  $payload  Either ['schema_version' => 1, 'blocks' => [...]] or direct array of blocks
     * @param  bool  $throw  Whether to throw a SopValidationException if validation fails
     * @return array<string> List of validation error messages
     *
     * @throws SopValidationException
     */
    public static function validate(array $payload, bool $throw = true): array
    {
        $errors = [];

        $blocks = isset($payload['blocks']) && is_array($payload['blocks'])
            ? $payload['blocks']
            : (array_is_list($payload) ? $payload : []);

        if (empty($blocks) && isset($payload['blocks']) && ! is_array($payload['blocks'])) {
            $errors[] = "El formato de bloques es inválido. Debe contener un arreglo de 'blocks'.";
            if ($throw) {
                throw new SopValidationException($errors[0], $errors);
            }

            return $errors;
        }

        // 1. First pass: Collect all defined variables across the entire SOP with their defining block index/ID
        // This allows distinguishing between an entirely undefined variable vs. a forward reference.
        $allDefinedVariables = [];
        $seenBlockIds = [];

        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                $errors[] = "El elemento en la posición {$index} no es un bloque válido.";
                continue;
            }

            $blockId = $block['id'] ?? null;
            if ($blockId) {
                if (in_array($blockId, $seenBlockIds, true)) {
                    $errors[] = "ID de bloque duplicado: '{$blockId}'. Cada bloque debe tener un ID único.";
                } else {
                    $seenBlockIds[] = $blockId;
                }
            }

            $type = $block['type'] ?? null;
            $props = $block['props'] ?? [];

            if ($type === 'input' && ! empty($props['key'])) {
                $allDefinedVariables[$props['key']] = [
                    'block_id' => $blockId ?? "bloque-{$index}",
                    'index' => $index,
                ];
            } elseif ($type === 'ai_task' && ! empty($props['output_key'])) {
                $allDefinedVariables[$props['output_key']] = [
                    'block_id' => $blockId ?? "bloque-{$index}",
                    'index' => $index,
                ];
            }
        }

        // 2. Second pass: Validate block structure, required properties and strictly chronological variable scope
        $availableVariables = [];

        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            $blockId = $block['id'] ?? null;
            if (empty($blockId)) {
                $errors[] = "El bloque en la posición {$index} no tiene un 'id' definido.";
                continue;
            }

            $type = $block['type'] ?? null;
            if (empty($type)) {
                $errors[] = "El bloque '{$blockId}' no tiene un 'type' especificado.";
                continue;
            }

            if (! in_array($type, self::SUPPORTED_BLOCK_TYPES, true)) {
                $errors[] = "Tipo de bloque desconocido '{$type}' en el bloque '{$blockId}'.";
                continue;
            }

            $props = $block['props'] ?? null;
            if (! is_array($props)) {
                $errors[] = "Las propiedades ('props') del bloque '{$blockId}' deben ser un objeto o arreglo.";
                continue;
            }

            // Type-specific validations
            switch ($type) {
                case 'heading':
                    if (! isset($props['text']) || trim((string) $props['text']) === '') {
                        $errors[] = "El bloque de encabezado '{$blockId}' requiere la propiedad 'text'.";
                    }
                    break;

                case 'text':
                    if (! isset($props['html'])) {
                        $errors[] = "El bloque de texto '{$blockId}' requiere la propiedad 'html'.";
                    } else {
                        self::validateVariableReferences(
                            VariableResolver::extractVariables($props['html']),
                            $availableVariables,
                            $allDefinedVariables,
                            $blockId,
                            $errors
                        );
                    }
                    break;

                case 'checklist':
                    if (! isset($props['items']) || ! is_array($props['items'])) {
                        $errors[] = "El bloque de checklist '{$blockId}' requiere un arreglo de 'items'.";
                    } else {
                        foreach ($props['items'] as $itemIdx => $item) {
                            if (! is_array($item) || empty($item['id']) || ! isset($item['text'])) {
                                $errors[] = "El ítem {$itemIdx} en la checklist '{$blockId}' debe contener 'id' y 'text'.";
                            }
                        }
                    }
                    break;

                case 'input':
                    if (empty($props['key'])) {
                        $errors[] = "El campo de entrada '{$blockId}' requiere una 'key' única para la variable.";
                    } elseif (! preg_match('/^[a-z0-9_]+$/', $props['key'])) {
                        $errors[] = "La variable '{$props['key']}' en el bloque '{$blockId}' debe ser snake_case (minúsculas, números y guiones bajos).";
                    } elseif (in_array($props['key'], $availableVariables, true)) {
                        $errors[] = "La variable '{$props['key']}' en el bloque '{$blockId}' ya fue definida en un bloque anterior.";
                    } else {
                        $availableVariables[] = $props['key'];
                    }

                    if (empty($props['label'])) {
                        $errors[] = "El campo de entrada '{$blockId}' requiere una 'label'.";
                    }

                    $field = $props['field'] ?? 'text';
                    if (! in_array($field, self::SUPPORTED_INPUT_FIELDS, true)) {
                        $errors[] = "El tipo de campo '{$field}' en el bloque '{$blockId}' no es válido.";
                    }
                    break;

                case 'media':
                    if (empty($props['url'])) {
                        $errors[] = "El bloque multimedia '{$blockId}' requiere una 'url'.";
                    }
                    break;

                case 'decision':
                    if (empty($props['question'])) {
                        $errors[] = "El bloque de decisión '{$blockId}' requiere una 'question'.";
                    }
                    if (! isset($props['branches']) || ! is_array($props['branches'])) {
                        $errors[] = "El bloque de decisión '{$blockId}' requiere un arreglo de 'branches'.";
                    }
                    break;

                case 'ai_task':
                    if (empty($props['skill_slug'])) {
                        $errors[] = "La tarea de IA '{$blockId}' requiere especificar 'skill_slug'.";
                    }
                    if (empty($props['model'])) {
                        $errors[] = "La tarea de IA '{$blockId}' requiere especificar un 'model'.";
                    }

                    // Validate variable references in inputs
                    if (isset($props['inputs']) && is_array($props['inputs'])) {
                        $referenced = VariableResolver::extractVariables($props['inputs']);
                        self::validateVariableReferences(
                            $referenced,
                            $availableVariables,
                            $allDefinedVariables,
                            $blockId,
                            $errors
                        );
                    }

                    // Validate output_key
                    if (empty($props['output_key'])) {
                        $errors[] = "La tarea de IA '{$blockId}' requiere una variable de salida 'output_key'.";
                    } elseif (! preg_match('/^[a-z0-9_]+$/', $props['output_key'])) {
                        $errors[] = "La variable de salida '{$props['output_key']}' en el bloque '{$blockId}' debe ser snake_case.";
                    } elseif (in_array($props['output_key'], $availableVariables, true)) {
                        $errors[] = "La variable de salida '{$props['output_key']}' en '{$blockId}' ya fue definida anteriormente.";
                    } else {
                        $availableVariables[] = $props['output_key'];
                    }
                    break;

                case 'approval':
                    if (empty($props['role'])) {
                        $errors[] = "El bloque de aprobación '{$blockId}' requiere definir un 'role'.";
                    }
                    break;

                case 'handoff':
                    if (empty($props['to']) || ! in_array($props['to'], ['team', 'client'], true)) {
                        $errors[] = "El bloque de entrega '{$blockId}' requiere 'to' con valor 'team' o 'client'.";
                    }
                    if (isset($props['message'])) {
                        self::validateVariableReferences(
                            VariableResolver::extractVariables($props['message']),
                            $availableVariables,
                            $allDefinedVariables,
                            $blockId,
                            $errors
                        );
                    }
                    break;
            }
        }

        if (! empty($errors) && $throw) {
            throw new SopValidationException($errors[0], $errors);
        }

        return $errors;
    }

    /**
     * Helper to validate variable references against available and later-defined variables.
     */
    protected static function validateVariableReferences(
        array $referencedVariables,
        array $availableVariables,
        array $allDefinedVariables,
        string $currentBlockId,
        array &$errors
    ): void {
        foreach ($referencedVariables as $varKey) {
            if (in_array($varKey, $availableVariables, true)) {
                // Variable exists and was defined prior to this block. Valid!
                continue;
            }

            if (isset($allDefinedVariables[$varKey])) {
                $laterBlock = $allDefinedVariables[$varKey]['block_id'];
                $errors[] = "Referencia hacia adelante inválida: la variable '{{{$varKey}}}' en el bloque '{$currentBlockId}' se define más adelante en el SOP (bloque '{$laterBlock}').";
            } else {
                $errors[] = "Variable no definida: la variable '{{{$varKey}}}' referenciada en el bloque '{$currentBlockId}' no existe en este SOP.";
            }
        }
    }
}