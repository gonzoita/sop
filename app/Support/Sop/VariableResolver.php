<?php

namespace App\Support\Sop;

use App\Exceptions\VariableUnresolvedException;

class VariableResolver
{
    /**
     * Regex pattern for {{variable_name}}.
     */
    public const VARIABLE_REGEX = '/\{\{\s*([a-z0-9_]+)\s*\}\}/';

    /**
     * Extract all variable keys referenced in a string or array.
     *
     * @param  string|array  $content
     * @return array<string> Unique variable names referenced
     */
    public static function extractVariables(string|array $content): array
    {
        if (is_array($content)) {
            $variables = [];
            foreach ($content as $item) {
                if (is_string($item) || is_array($item)) {
                    $variables = array_merge($variables, self::extractVariables($item));
                }
            }

            return array_values(array_unique($variables));
        }

        if (preg_match_all(self::VARIABLE_REGEX, $content, $matches)) {
            return array_values(array_unique($matches[1]));
        }

        return [];
    }

    /**
     * Check if a string contains any variable tags.
     */
    public static function hasVariables(string $content): bool
    {
        return (bool) preg_match(self::VARIABLE_REGEX, $content);
    }

    /**
     * Resolve a template string with given values map.
     *
     * @param  string  $template
     * @param  array<string, mixed>  $values
     * @param  bool  $strict  If true, throws VariableUnresolvedException on missing variables
     * @return string
     *
     * @throws VariableUnresolvedException
     */
    public static function resolveString(string $template, array $values, bool $strict = true): string
    {
        return self::resolve($template, $values, $strict);
    }

    /**
     * Resolve a template string with given values map.
     *
     * @param  string  $template
     * @param  array<string, mixed>  $values
     * @param  bool  $strict  If true, throws VariableUnresolvedException on missing variables
     * @return string
     *
     * @throws VariableUnresolvedException
     */
    public static function resolve(string $template, array $values, bool $strict = true): string
    {
        return preg_replace_callback(self::VARIABLE_REGEX, function ($matches) use ($values, $strict) {
            $key = $matches[1];

            if (array_key_exists($key, $values) && $values[$key] !== null) {
                $val = $values[$key];

                if (is_array($val)) {
                    return json_encode($val, JSON_UNESCAPED_UNICODE);
                }

                return (string) $val;
            }

            if ($strict) {
                throw new VariableUnresolvedException($key);
            }

            // Keep original placeholder if not strict
            return $matches[0];
        }, $template);
    }

    /**
     * Recursively resolve an array containing strings.
     *
     * @param  array  $data
     * @param  array<string, mixed>  $values
     * @param  bool  $strict
     * @return array
     */
    public static function resolveArray(array $data, array $values, bool $strict = true): array
    {
        $resolved = [];

        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $resolved[$k] = self::resolve($v, $values, $strict);
            } elseif (is_array($v)) {
                $resolved[$k] = self::resolveArray($v, $values, $strict);
            } else {
                $resolved[$k] = $v;
            }
        }

        return $resolved;
    }
}