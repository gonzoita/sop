<?php

namespace App\Support\Diff;

class LineDiffer
{
    /**
     * Compute a line-by-line diff between two texts using LCS.
     *
     * @param string $oldText
     * @param string $newText
     * @return array{
     *     added_count: int,
     *     removed_count: int,
     *     lines: array<array{type: 'unchanged'|'added'|'removed', text: string}>
     * }
     */
    public static function diff(string $oldText, string $newText): array
    {
        $oldLines = $oldText === '' ? [] : explode("\n", str_replace(["\r\n", "\r"], "\n", $oldText));
        $newLines = $newText === '' ? [] : explode("\n", str_replace(["\r\n", "\r"], "\n", $newText));

        $n = count($oldLines);
        $m = count($newLines);

        // Build LCS Matrix
        $matrix = [];
        for ($i = 0; $i <= $n; $i++) {
            $matrix[$i] = array_fill(0, $m + 1, 0);
        }

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $m; $j++) {
                if ($oldLines[$i] === $newLines[$j]) {
                    $matrix[$i + 1][$j + 1] = $matrix[$i][$j] + 1;
                } else {
                    $matrix[$i + 1][$j + 1] = max($matrix[$i + 1][$j], $matrix[$i][$j + 1]);
                }
            }
        }

        // Backtrack to assemble diff lines
        $diff = [];
        $i = $n;
        $j = $m;
        $addedCount = 0;
        $removedCount = 0;

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $oldLines[$i - 1] === $newLines[$j - 1]) {
                array_unshift($diff, [
                    'type' => 'unchanged',
                    'text' => $oldLines[$i - 1],
                ]);
                $i--;
                $j--;
            } elseif ($j > 0 && ($i === 0 || $matrix[$i][$j - 1] >= $matrix[$i - 1][$j])) {
                array_unshift($diff, [
                    'type' => 'added',
                    'text' => $newLines[$j - 1],
                ]);
                $addedCount++;
                $j--;
            } elseif ($i > 0 && ($j === 0 || $matrix[$i][$j - 1] < $matrix[$i - 1][$j])) {
                array_unshift($diff, [
                    'type' => 'removed',
                    'text' => $oldLines[$i - 1],
                ]);
                $removedCount++;
                $i--;
            }
        }

        return [
            'added_count' => $addedCount,
            'removed_count' => $removedCount,
            'lines' => $diff,
        ];
    }
}
