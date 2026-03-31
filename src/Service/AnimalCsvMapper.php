<?php

namespace App\Service;

use App\Dto\AnimalRecord;

final class AnimalCsvMapper
{
    /**
     * @param array<string, string|null> $row
     */
    public function map(array $row): AnimalRecord
    {
        return new AnimalRecord(
            year: $this->parseYear($row['Année'] ?? null),
            marquage: $this->clean($row['N° marquage'] ?? null),
            boucle: $this->clean($row['N° boucle'] ?? null),
            name: $this->clean($row['Nom'] ?? null),
            theme: $this->nullable($row['Thème'] ?? null),
            motherName: $this->nullable($row['Nom de la mère'] ?? null),
            deathDate: $this->parseFrenchDate($row['Mort'] ?? null),
            characteristics: $this->nullable($row['Caractéristiques'] ?? null),
        );
    }

    private function clean(?string $value): string
    {
        return trim((string) $value);
    }

    private function nullable(?string $value): ?string
    {
        $value = $this->clean($value);

        return $value !== '' ? $value : null;
    }

    private function parseYear(?string $value): ?int
    {
        $value = $this->clean($value);

        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }

    private function parseFrenchDate(?string $value): ?\DateTimeImmutable
    {
        $value = $this->clean($value);

        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!d/m/Y', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date;
    }
}
