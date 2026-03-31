<?php

namespace App\Service;

use App\Dto\AnimalRecord;

final class AnimalProvider
{
    public function __construct(
        private CsvReader $csvReader,
        private AnimalCsvMapper $mapper,
    ) {
    }

    /**
     * @return list<AnimalRecord>
     */
    public function all(string $relativePath): array
    {
        $animals = [];

        foreach ($this->csvReader->readAssociative($relativePath) as $row) {
            $animals[] = $this->mapper->map($row);
        }

        return $animals;
    }
}
