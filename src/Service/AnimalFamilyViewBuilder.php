<?php

namespace App\Service;

final class AnimalFamilyViewBuilder
{
    /**
     * @param list<array<string, string|null>> $rows
     * @param array{q:string} $filters
     * @return array{
     *   themes:list<array{
     *     name:string,
     *     alive_count:int,
     *     dead_count:int,
     *     roots:list<array{
     *       key:string,
     *       name:string,
     *       year:string,
     *       marquage:string,
     *       boucle:string,
     *       is_alive:bool,
     *       death_date:?string,
     *       children:list<mixed>
     *     }>
     *   }>,
     *   counts:array{themes:int,animals:int}
     * }
     */
    public function build(array $rows, array $filters = ['q' => '']): array
    {
        $themeBuckets = [];
        $query = trim((string) ($filters['q'] ?? ''));

        foreach ($rows as $row) {
            $theme = trim((string) ($row['Thème'] ?? ''));
            $theme = $theme !== '' ? $theme : 'Sans thème';

            $animal = $this->normalizeAnimal($row);
            $themeBuckets[$theme][] = $animal;
        }

        $themeNames = array_keys($themeBuckets);

        $themes = [];
        $animalCount = 0;

        foreach ($themeNames as $themeName) {
            $animals = $themeBuckets[$themeName];
            $roots = $this->buildThemeTrees($animals);

            if ($query !== '') {
                $roots = $this->filterTreeByQuery($roots, $query);
            }

            if ($roots === []) {
                continue;
            }

            [$aliveCount, $deadCount, $themeAnimalCount] = $this->countTree($roots);
            $animalCount += $themeAnimalCount;

            $themes[] = [
                'name' => $themeName,
                'alive_count' => $aliveCount,
                'dead_count' => $deadCount,
                'roots' => $roots,
            ];
        }

        usort($themes, static function (array $a, array $b): int {
            $aTotal = $a['alive_count'] + $a['dead_count'];
            $bTotal = $b['alive_count'] + $b['dead_count'];

            return ($bTotal <=> $aTotal)
                ?: strcasecmp($a['name'], $b['name']);
        });

        return [
            'themes' => $themes,
            'counts' => [
                'themes' => count($themes),
                'animals' => $animalCount,
            ],
        ];
    }

    /**
     * @param list<array{
     *   key:string,
     *   name:string,
     *   year:string,
     *   marquage:string,
     *   boucle:string,
     *   is_alive:bool,
     *   death_date:?string,
     *   children:list<mixed>
     * }> $nodes
     * @return list<array{
     *   key:string,
     *   name:string,
     *   year:string,
     *   marquage:string,
     *   boucle:string,
     *   is_alive:bool,
     *   death_date:?string,
     *   children:list<mixed>
     * }>
     */
    private function filterTreeByQuery(array $nodes, string $query): array
    {
        $result = [];
        $queryKey = $this->normalizeNameKey($query);

        foreach ($nodes as $node) {
            $children = $this->filterTreeByQuery($node['children'], $query);
            $matches = $this->nodeMatchesQuery($node, $queryKey);

            if (!$matches && $children === []) {
                continue;
            }

            $node['children'] = $children;
            $result[] = $node;
        }

        return $result;
    }

    /**
     * @param array{
     *   name:string,
     *   year:string,
     *   marquage:string,
     *   boucle:string,
     *   death_date:?string
     * } $node
     */
    private function nodeMatchesQuery(array $node, string $queryKey): bool
    {
        if ($queryKey === '') {
            return true;
        }

        $haystack = $this->normalizeNameKey(implode(' ', [
            $node['name'],
            $node['year'],
            $node['marquage'],
            $node['boucle'],
            (string) ($node['death_date'] ?? ''),
        ]));

        return str_contains($haystack, $queryKey);
    }

    /**
     * @param list<array{is_alive:bool,children:list<mixed>}> $nodes
     * @return array{0:int,1:int,2:int}
     */
    private function countTree(array $nodes): array
    {
        $alive = 0;
        $dead = 0;
        $total = 0;

        foreach ($nodes as $node) {
            ++$total;
            if ($node['is_alive']) {
                ++$alive;
            } else {
                ++$dead;
            }

            [$childAlive, $childDead, $childTotal] = $this->countTree($node['children']);
            $alive += $childAlive;
            $dead += $childDead;
            $total += $childTotal;
        }

        return [$alive, $dead, $total];
    }

    /**
     * @param array<string, string|null> $row
     * @return array{
     *   key:string,
     *   name:string,
     *   mother_name:string,
     *   year:string,
     *   marquage:string,
     *   boucle:string,
     *   is_alive:bool,
     *   death_date:?string
     * }
     */
    private function normalizeAnimal(array $row): array
    {
        $marquage = trim((string) ($row['N° marquage'] ?? ''));
        $boucle = trim((string) ($row['N° boucle'] ?? ''));
        $name = trim((string) ($row['Nom'] ?? ''));
        $motherName = trim((string) ($row['Nom de la mère'] ?? ''));
        $deathDate = trim((string) ($row['Mort'] ?? ''));

        return [
            'key' => $this->animalKey($marquage, $boucle, $name),
            'name' => $name !== '' ? $name : 'Sans nom',
            'mother_name' => $motherName,
            'year' => trim((string) ($row['Année'] ?? '')),
            'marquage' => $marquage,
            'boucle' => $boucle,
            'is_alive' => $deathDate === '',
            'death_date' => $deathDate !== '' ? $deathDate : null,
        ];
    }

    /**
     * @param list<array{
     *   key:string,
     *   name:string,
     *   mother_name:string,
     *   year:string,
     *   marquage:string,
     *   boucle:string,
     *   is_alive:bool,
     *   death_date:?string
     * }> $animals
     * @return list<array{
     *   key:string,
     *   name:string,
     *   year:string,
     *   marquage:string,
     *   boucle:string,
     *   is_alive:bool,
     *   death_date:?string,
     *   children:list<mixed>
     * }>
     */
    private function buildThemeTrees(array $animals): array
    {
        $byKey = [];
        $childrenByMotherName = [];
        $namesInTheme = [];

        foreach ($animals as $animal) {
            $byKey[$animal['key']] = $animal;
            $namesInTheme[$this->normalizeNameKey($animal['name'])] = true;

            $motherKey = $this->normalizeNameKey($animal['mother_name']);
            if ($motherKey !== '') {
                $childrenByMotherName[$motherKey][] = $animal['key'];
            }
        }

        $rootKeys = [];
        foreach ($animals as $animal) {
            $motherKey = $this->normalizeNameKey($animal['mother_name']);
            if ($motherKey === '' || !isset($namesInTheme[$motherKey])) {
                $rootKeys[] = $animal['key'];
            }
        }

        $built = [];
        $roots = [];

        foreach ($rootKeys as $rootKey) {
            if (!isset($byKey[$rootKey]) || isset($built[$rootKey])) {
                continue;
            }

            $roots[] = $this->buildNode($rootKey, $byKey, $childrenByMotherName, $built, []);
        }

        // Fallback: if some nodes are not attached (e.g. circular/ambiguous links), show them as roots.
        foreach (array_keys($byKey) as $key) {
            if (isset($built[$key])) {
                continue;
            }

            $roots[] = $this->buildNode($key, $byKey, $childrenByMotherName, $built, []);
        }

        usort($roots, fn (array $a, array $b): int => $this->compareTreeNodes($a, $b));

        return $roots;
    }

    /**
     * @param array<string, array{
     *   key:string,
     *   name:string,
     *   mother_name:string,
     *   year:string,
     *   marquage:string,
     *   boucle:string,
     *   is_alive:bool,
     *   death_date:?string
     * }> $byKey
     * @param array<string, list<string>> $childrenByMotherName
     * @param array<string, true> $built
     * @param array<string, true> $path
     * @return array{
     *   key:string,
     *   name:string,
     *   year:string,
     *   marquage:string,
     *   boucle:string,
     *   is_alive:bool,
     *   death_date:?string,
     *   children:list<mixed>
     * }
     */
    private function buildNode(
        string $key,
        array $byKey,
        array $childrenByMotherName,
        array &$built,
        array $path
    ): array {
        $animal = $byKey[$key];
        $path[$key] = true;
        $built[$key] = true;

        $children = [];
        $childKeys = $childrenByMotherName[$this->normalizeNameKey($animal['name'])] ?? [];

        foreach ($childKeys as $childKey) {
            if (!isset($byKey[$childKey])) {
                continue;
            }

            if (isset($path[$childKey])) {
                continue;
            }

            $children[] = $this->buildNode($childKey, $byKey, $childrenByMotherName, $built, $path);
        }

        usort($children, fn (array $a, array $b): int => $this->compareTreeNodes($a, $b));

        return [
            'key' => $animal['key'],
            'name' => $animal['name'],
            'year' => $animal['year'],
            'marquage' => $animal['marquage'],
            'boucle' => $animal['boucle'],
            'is_alive' => $animal['is_alive'],
            'death_date' => $animal['death_date'],
            'children' => $children,
        ];
    }

    /**
     * @param array{name:string,year:string,marquage:string,boucle:string} $a
     * @param array{name:string,year:string,marquage:string,boucle:string} $b
     */
    private function compareTreeNodes(array $a, array $b): int
    {
        return ((int) $a['year'] <=> (int) $b['year'])
            ?: strcasecmp($a['name'], $b['name'])
            ?: strcasecmp($a['marquage'], $b['marquage'])
            ?: strcasecmp($a['boucle'], $b['boucle']);
    }

    private function animalKey(string $marquage, string $boucle, string $fallbackName): string
    {
        $key = trim($marquage.'-'.$boucle, '-');

        if ($key !== '') {
            return $key;
        }

        return 'name:'.mb_strtolower(trim($fallbackName));
    }

    private function normalizeNameKey(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
