<?php

namespace App\Service;

final class AnimalStatsBuilder
{
    /**
     * @param list<array<string, string|null>> $rows
     * @return array{
     *   total:int,
     *   by_year:list<array{label:string,alive_count:int,dead_count:int}>,
     *   by_status:list<array{label:string,count:int}>,
     *   by_theme:list<array{label:string,alive_count:int,dead_count:int}>
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
                $byYear[$year] ??= ['alive_count' => 0, 'dead_count' => 0];
                ++$byYear[$year][$isAlive ? 'alive_count' : 'dead_count'];
            }

            if ($theme !== '') {
                $byTheme[$theme] ??= ['alive_count' => 0, 'dead_count' => 0];
                ++$byTheme[$theme][$isAlive ? 'alive_count' : 'dead_count'];
            }

            if ($isAlive) {
                ++$alive;
            } else {
                ++$dead;
            }
        }

        ksort($byYear, SORT_NATURAL);
        $byYearRows = $this->toSplitCountRows($byYear);
        $byThemeRows = $this->toSplitCountRows($byTheme);

        usort($byThemeRows, static function (array $a, array $b): int {
            return $b['alive_count'] <=> $a['alive_count']
                ?: $b['dead_count'] <=> $a['dead_count']
                ?: strcasecmp($a['label'], $b['label']);
        });

        return [
            'total' => count($rows),
            'by_status' => [
                ['label' => 'Vivants', 'count' => $alive],
                ['label' => 'Décédés', 'count' => $dead],
            ],
            'by_year' => $byYearRows,
            'by_theme' => $byThemeRows,
        ];
    }

    /**
     * @param array<string, array{alive_count:int,dead_count:int}> $countsByLabel
     * @return list<array{label:string,alive_count:int,dead_count:int}>
     */
    private function toSplitCountRows(array $countsByLabel): array
    {
        $rows = [];

        foreach ($countsByLabel as $label => $counts) {
            $rows[] = [
                'label' => $label,
                'alive_count' => $counts['alive_count'],
                'dead_count' => $counts['dead_count'],
            ];
        }

        return $rows;
    }
}
