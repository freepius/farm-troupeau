<?php

namespace App\Controller;

use App\Service\CsvReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(Request $request, CsvReader $csvReader): Response
    {
        $csvPath = 'data/animaux.csv';
        $rows = $csvReader->readAssociative($csvPath);
        $columns = $rows !== [] ? array_keys($rows[0]) : [];

        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'annee' => trim((string) $request->query->get('annee', '')),
            'theme' => trim((string) $request->query->get('theme', '')),
            'statut' => (string) $request->query->get('statut', 'tous'),
            'tri' => (string) $request->query->get('tri', 'annee_desc_nom_asc'),
        ];

        if (!in_array($filters['statut'], ['tous', 'vivants', 'decedes'], true)) {
            $filters['statut'] = 'tous';
        }

        $allowedSorts = [
            'annee_desc_nom_asc',
            'annee_asc_nom_asc',
            'nom_asc',
            'nom_desc',
            'boucle_asc',
            'marquage_boucle_asc',
            'mort_recent',
        ];

        if (!in_array($filters['tri'], $allowedSorts, true)) {
            $filters['tri'] = 'annee_desc_nom_asc';
        }

        $yearOptions = $this->uniqueSortedValues($rows, 'Année');
        $themeOptions = $this->uniqueSortedValues($rows, 'Thème');

        $filteredRows = array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->matchesFilters($row, $filters)
        ));

        usort($filteredRows, fn (array $a, array $b): int => $this->compareRows($a, $b, $filters['tri']));

        $stats = [
            'total' => count($rows),
            'filtered' => count($filteredRows),
            'alive' => count(array_filter($filteredRows, fn (array $row): bool => $this->isAlive($row))),
            'dead' => count(array_filter($filteredRows, fn (array $row): bool => !$this->isAlive($row))),
        ];

        return $this->render('home/index.html.twig', [
            'csv_path' => $csvPath,
            'rows' => $filteredRows,
            'columns' => $columns,
            'filters' => $filters,
            'year_options' => $yearOptions,
            'theme_options' => $themeOptions,
            'stats' => $stats,
        ]);
    }

    /**
     * @param list<array<string, string|null>> $rows
     * @return list<string>
     */
    private function uniqueSortedValues(array $rows, string $column): array
    {
        $values = [];

        foreach ($rows as $row) {
            $value = trim((string) ($row[$column] ?? ''));

            if ($value !== '') {
                $values[$value] = true;
            }
        }

        $result = array_keys($values);
        usort($result, fn (string $a, string $b): int => strcasecmp($a, $b));

        return $result;
    }

    /**
     * @param array<string, string|null> $row
     * @param array{q:string,annee:string,theme:string,statut:string,tri:string} $filters
     */
    private function matchesFilters(array $row, array $filters): bool
    {
        if ($filters['annee'] !== '' && (string) ($row['Année'] ?? '') !== $filters['annee']) {
            return false;
        }

        if ($filters['theme'] !== '' && (string) ($row['Thème'] ?? '') !== $filters['theme']) {
            return false;
        }

        if ($filters['statut'] === 'vivants' && !$this->isAlive($row)) {
            return false;
        }

        if ($filters['statut'] === 'decedes' && $this->isAlive($row)) {
            return false;
        }

        if ($filters['q'] !== '') {
            $needle = $this->normalize($filters['q']);
            $haystack = $this->normalize(implode(' ', [
                (string) ($row['Nom'] ?? ''),
                (string) ($row['Thème'] ?? ''),
                (string) ($row['Nom de la mère'] ?? ''),
                (string) ($row['N° marquage'] ?? ''),
                (string) ($row['N° boucle'] ?? ''),
            ]));

            if (!str_contains($haystack, $needle)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, string|null> $a
     * @param array<string, string|null> $b
     */
    private function compareRows(array $a, array $b, string $sort): int
    {
        return match ($sort) {
            'annee_asc_nom_asc' => $this->cmpInt($a['Année'] ?? null, $b['Année'] ?? null)
                ?: $this->cmpText($a['Nom'] ?? null, $b['Nom'] ?? null),
            'nom_asc' => $this->cmpText($a['Nom'] ?? null, $b['Nom'] ?? null)
                ?: $this->cmpInt($a['Année'] ?? null, $b['Année'] ?? null),
            'nom_desc' => $this->cmpText($b['Nom'] ?? null, $a['Nom'] ?? null)
                ?: $this->cmpInt($a['Année'] ?? null, $b['Année'] ?? null),
            'boucle_asc' => $this->cmpText($a['N° boucle'] ?? null, $b['N° boucle'] ?? null)
                ?: $this->cmpText($a['N° marquage'] ?? null, $b['N° marquage'] ?? null),
            'marquage_boucle_asc' => $this->cmpText($a['N° marquage'] ?? null, $b['N° marquage'] ?? null)
                ?: $this->cmpText($a['N° boucle'] ?? null, $b['N° boucle'] ?? null),
            'mort_recent' => $this->cmpDeathDateDesc($a['Mort'] ?? null, $b['Mort'] ?? null)
                ?: $this->cmpText($a['Nom'] ?? null, $b['Nom'] ?? null),
            default => $this->cmpInt($b['Année'] ?? null, $a['Année'] ?? null)
                ?: $this->cmpText($a['Nom'] ?? null, $b['Nom'] ?? null),
        };
    }

    /**
     * @param array<string, string|null> $row
     */
    private function isAlive(array $row): bool
    {
        return trim((string) ($row['Mort'] ?? '')) === '';
    }

    private function cmpText(?string $a, ?string $b): int
    {
        return strcasecmp((string) $a, (string) $b);
    }

    private function cmpInt(?string $a, ?string $b): int
    {
        return (int) $a <=> (int) $b;
    }

    private function cmpDeathDateDesc(?string $a, ?string $b): int
    {
        $ta = $this->deathDateTimestamp($a);
        $tb = $this->deathDateTimestamp($b);

        if ($ta === null && $tb === null) {
            return 0;
        }

        if ($ta === null) {
            return 1;
        }

        if ($tb === null) {
            return -1;
        }

        return $tb <=> $ta;
    }

    private function deathDateTimestamp(?string $value): ?int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $parts = explode('/', $value);

        if (count($parts) !== 3) {
            return null;
        }

        [$day, $month, $year] = $parts;

        if (!checkdate((int) $month, (int) $day, (int) $year)) {
            return null;
        }

        return (int) strtotime(sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day));
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
