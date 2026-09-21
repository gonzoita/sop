<?php

namespace App\Support\Skills;

use InvalidArgumentException;

class SkillMarkdownParser
{
    /**
     * Parse markdown content with front-matter.
     *
     * @param string $content
     * @return array{
     *     front_matter: array,
     *     body: string,
     *     system_prompt: string,
     *     user_instructions: string
     * }
     * @throws InvalidArgumentException
     */
    public static function parse(string $content): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($content));

        if (! preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)$/s', $normalized, $matches)) {
            throw new InvalidArgumentException("El archivo no contiene un encabezado front-matter válido (debe comenzar y cerrar con '---').");
        }

        $frontMatterRaw = trim($matches[1]);
        $body = trim($matches[2]);

        $frontMatter = self::parseFrontMatter($frontMatterRaw);

        if (empty($frontMatter['slug'])) {
            throw new InvalidArgumentException("El encabezado front-matter está mal formado o no contiene la clave 'slug'.");
        }

        $sections = self::splitSections($body);

        return [
            'front_matter' => $frontMatter,
            'body' => $body,
            'system_prompt' => $sections['system_prompt'],
            'user_instructions' => $sections['user_instructions'],
        ];
    }

    /**
     * Parse YAML-like front-matter lines into an associative array.
     */
    public static function parseFrontMatter(string $yaml): array
    {
        $data = [];
        $lines = explode("\n", $yaml);
        $currentListKey = null;

        foreach ($lines as $line) {
            $line = rtrim($line);
            if ($line === '' || str_starts_with(trim($line), '#')) {
                continue;
            }

            // List item e.g. "  - nombre_marca"
            if (preg_match('/^\s*-\s*(.+)$/', $line, $listMatches)) {
                if ($currentListKey) {
                    $val = trim($listMatches[1], " \"'");
                    $data[$currentListKey][] = $val;
                }
                continue;
            }

            // Key-Value pair e.g. "slug: brief-de-marca"
            if (preg_match('/^([a-zA-Z0-9_\-]+)\s*:\s*(.*)$/', $line, $kvMatches)) {
                $key = strtolower(trim($kvMatches[1]));
                $val = trim($kvMatches[2]);

                if ($val === '' || $val === '[]') {
                    $data[$key] = [];
                    $currentListKey = $key;
                } else {
                    $currentListKey = null;
                    // Strip quotes if any
                    $val = trim($val, " \"'");
                    // Cast numbers if applicable
                    if (is_numeric($val)) {
                        $val = str_contains($val, '.') ? (float) $val : (int) $val;
                    }
                    $data[$key] = $val;
                }
            }
        }

        return $data;
    }

    /**
     * Split body into system_prompt and user_instructions sections if structured headers exist.
     */
    public static function splitSections(string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            return [
                'system_prompt' => '',
                'user_instructions' => '',
            ];
        }

        // Patterns to recognize system prompt headers and user prompt headers
        $systemHeaderPattern = '/^##\s*(?:Instrucciones\s+del\s+Sistema|System\s+Prompt|Sistema)\s*$/im';
        $userHeaderPattern = '/^##\s*(?:Prompt\s+a\s+Ejecutar|Prompt\s+del\s+Usuario|Instrucciones\s+de\s+Usuario|Prompt|Instrucciones)\s*$/im';

        $hasSystem = preg_match($systemHeaderPattern, $body);
        $hasUser = preg_match($userHeaderPattern, $body);

        if (! $hasSystem && ! $hasUser) {
            return [
                'system_prompt' => '',
                'user_instructions' => $body,
            ];
        }

        // Split by markdown h2 headers
        $sections = preg_split('/(?=^##\s+)/m', $body);
        $systemPrompt = '';
        $userInstructions = '';

        foreach ($sections as $section) {
            $section = trim($section);
            if ($section === '') continue;

            if (preg_match($systemHeaderPattern, $section)) {
                $cleaned = preg_replace($systemHeaderPattern, '', $section);
                $systemPrompt = trim($cleaned);
            } elseif (preg_match($userHeaderPattern, $section)) {
                $cleaned = preg_replace($userHeaderPattern, '', $section);
                $userInstructions = trim($cleaned);
            } else {
                // If there are other sections, append to user instructions
                $userInstructions = ($userInstructions !== '' ? $userInstructions . "\n\n" : '') . $section;
            }
        }

        return [
            'system_prompt' => $systemPrompt,
            'user_instructions' => $userInstructions !== '' ? $userInstructions : $body,
        ];
    }
}
