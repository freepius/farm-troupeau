<?php

namespace App\Controller;

use App\Dto\AnimalRecord;
use App\Service\AnimalStatsBuilder;
use App\Service\AnimalProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(Request $request, AnimalProvider $animalProvider, AnimalStatsBuilder $animalStatsBuilder): Response
    {
        $csvPath = 'data/animaux.csv';
        $animals = $animalProvider->all($csvPath);
        $sidebarStats = $animalStatsBuilder->build($animals);

        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'annee' => trim((string) $request->query->get('annee', '')),
            'theme' => trim((string) $request->query->get('theme', '')),
            'statut' => (string) $request->query->get('statut', 'vivants'),
            'tri' => (string) $request->query->get('tri', 'annee_asc_nom_asc'),
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
            $filters['tri'] = 'annee_asc_nom_asc';
        }

        $yearOptions = $this->uniqueSortedYears($animals);
        $themeOptions = $this->uniqueSortedThemes($animals);

        $filteredAnimals = array_values(array_filter(
            $animals,
            fn (AnimalRecord $animal): bool => $this->matchesFilters($animal, $filters)
        ));

        usort($filteredAnimals, fn (AnimalRecord $a, AnimalRecord $b): int => $this->compareAnimals($a, $b, $filters['tri']));

        $stats = [
            'total' => count($animals),
            'filtered' => count($filteredAnimals),
            'alive' => count(array_filter($filteredAnimals, fn (AnimalRecord $animal): bool => $animal->isAlive())),
            'dead' => count(array_filter($filteredAnimals, fn (AnimalRecord $animal): bool => !$animal->isAlive())),
        ];

        return $this->render('home/index.html.twig', [
            'csv_path' => $csvPath,
            'animals' => $filteredAnimals,
            'filters' => $filters,
            'year_options' => $yearOptions,
            'theme_options' => $themeOptions,
            'stats' => $stats,
            'sidebar_stats' => $sidebarStats,
        ]);
    }

    /**
     * @param list<AnimalRecord> $animals
     * @return list<string>
     */
    private function uniqueSortedYears(array $animals): array
    {
        $values = [];

        foreach ($animals as $animal) {
            $year = $animal->getYearLabel();
            if ($year !== '') {
                $values[$year] = true;
            }
        }

        $result = array_keys($values);
        sort($result, SORT_NATURAL);

        return $result;
    }

    /**
     * @param list<AnimalRecord> $animals
     * @return list<string>
     */
    private function uniqueSortedThemes(array $animals): array
    {
        $values = [];

        foreach ($animals as $animal) {
            $theme = $animal->getThemeLabel();
            if ($theme !== '') {
                $values[$theme] = true;
            }
        }

        $result = array_keys($values);
        usort($result, fn (string $a, string $b): int => strcasecmp($a, $b));

        return $result;
    }

    /**
     * @param array{q:string,annee:string,theme:string,statut:string,tri:string} $filters
     */
    private function matchesFilters(AnimalRecord $animal, array $filters): bool
    {
        if ($filters['annee'] !== '' && $animal->getYearLabel() !== $filters['annee']) {
            return false;
        }

        if ($filters['theme'] !== '' && $animal->getThemeLabel() !== $filters['theme']) {
            return false;
        }

        if ($filters['statut'] === 'vivants' && !$animal->isAlive()) {
            return false;
        }

        if ($filters['statut'] === 'decedes' && $animal->isAlive()) {
            return false;
        }

        if ($filters['q'] !== '') {
            $needle = $this->normalize($filters['q']);
            $haystack = $this->normalize($animal->getSearchText());

            if (!str_contains($haystack, $needle)) {
                return false;
            }
        }

        return true;
    }

    /**
     */
    private function compareAnimals(AnimalRecord $a, AnimalRecord $b, string $sort): int
    {
        return match ($sort) {
            'annee_asc_nom_asc' => $this->cmpYear($a, $b)
                ?: $this->cmpAnimalName($a, $b),
            'nom_asc' => $this->cmpAnimalName($a, $b)
                ?: $this->cmpYear($a, $b),
            'nom_desc' => $this->cmpAnimalName($b, $a)
                ?: $this->cmpYear($a, $b),
            'boucle_asc' => $this->cmpText($a->boucle, $b->boucle)
                ?: $this->cmpText($a->marquage, $b->marquage),
            'marquage_boucle_asc' => $this->cmpText($a->marquage, $b->marquage)
                ?: $this->cmpText($a->boucle, $b->boucle),
            'mort_recent' => $this->cmpDeathDateDesc($a->deathDate, $b->deathDate)
                ?: $this->cmpAnimalName($a, $b),
            default => $this->cmpYear($b, $a)
                ?: $this->cmpAnimalName($a, $b),
        };
    }

    private function cmpAnimalName(AnimalRecord $a, AnimalRecord $b): int
    {
        return $this->cmpText($a->getDisplayName(), $b->getDisplayName());
    }

    private function cmpYear(AnimalRecord $a, AnimalRecord $b): int
    {
        return ($a->year ?? 0) <=> ($b->year ?? 0);
    }

    private function cmpText(string $a, string $b): int
    {
        return strcasecmp($a, $b);
    }

    private function cmpDeathDateDesc(?\DateTimeImmutable $a, ?\DateTimeImmutable $b): int
    {
        $ta = $a?->getTimestamp();
        $tb = $b?->getTimestamp();

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

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
