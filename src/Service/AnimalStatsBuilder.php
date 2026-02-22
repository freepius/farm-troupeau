<?php

namespace App\Service;

final class AnimalStatsBuilder
{
    /**
     * @param list<array<string, string|null>> $rows
     * @return array{
     *   total:int,
     *   by_year:list<array{label:string,count:int}>,
     *   by_status:list<array{label:string,count:int}>,
     *   by_theme:list<array{label:string,count:int}>
     * }
     */
    public function build(array $rows): array
    {
        $byYear = [];
        $byTheme = [];
        $alive = 0;
        $dead = 0;

        foreach ($rows as $row) {
            $year = trim((string) ($row['Année'] ?? ''));
            $theme = trim((string) ($row['Thème'] ?? ''));
            $isAlive = trim((string) ($row['Mort'] ?? '')) === '';

            if ($year !== '') {
                $byYear[$year] = ($byYear[$year] ?? 0) + 1;
            }

            if ($theme !== '') {
                $byTheme[$theme] = ($byTheme[$theme] ?? 0) + 1;
            }

            if ($isAlive) {
                ++$alive;
            } else {
                ++$dead;
            }
        }

        ksort($byYear, SORT_NATURAL);
        $byThemeRows = [];
        foreach ($byTheme as $label => $count) {
            $byThemeRows[] = ['label' => $label, 'count' => $count];
        }

        usort($byThemeRows, static function (array $a, array $b): int {
            return $b['count'] <=> $a['count'] ?: strcasecmp($a['label'], $b['label']);
        });

        return [
            'total' => count($rows),
            'by_year' => array_map(
                static fn (string $label, int $count): array => ['label' => $label, 'count' => $count],
                array_keys($byYear),
                array_values($byYear),
            ),
            'by_status' => [
                ['label' => 'Vivants', 'count' => $alive],
                ['label' => 'Décédés', 'count' => $dead],
            ],
            'by_theme' => $byThemeRows,
        ];
    }
}
