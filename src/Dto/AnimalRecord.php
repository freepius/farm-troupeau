<?php

namespace App\Dto;

final readonly class AnimalRecord
{
    public function __construct(
        public ?int $year,
        public string $marquage,
        public string $boucle,
        public string $name,
        public ?string $theme,
        public ?string $motherName,
        public ?\DateTimeImmutable $deathDate,
        public ?string $characteristics,
    ) {
    }

    public function getDisplayName(): string
    {
        return $this->name !== '' ? $this->name : 'Sans nom';
    }

    public function getYearLabel(): string
    {
        return $this->year !== null ? (string) $this->year : '';
    }

    public function getThemeLabel(): string
    {
        return $this->theme ?? '';
    }

    public function getFamilyThemeLabel(): string
    {
        return $this->theme ?? 'Sans thème';
    }

    public function getMotherNameLabel(): string
    {
        return $this->motherName ?? '';
    }

    public function getCharacteristicsLabel(): string
    {
        return $this->characteristics ?? '';
    }

    public function isAlive(): bool
    {
        return $this->deathDate === null;
    }

    public function getDeathDateLabel(): ?string
    {
        return $this->deathDate?->format('d/m/Y');
    }

    public function getSearchText(): string
    {
        return implode(' ', array_filter([
            $this->getDisplayName(),
            $this->getThemeLabel(),
            $this->getMotherNameLabel(),
            $this->marquage,
            $this->boucle,
            $this->getCharacteristicsLabel(),
        ], static fn (?string $value): bool => $value !== null && $value !== ''));
    }
}
