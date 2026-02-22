<?php

namespace App\Service;

use League\Csv\Reader;
use Symfony\Component\HttpKernel\KernelInterface;

final class CsvReader
{
    public function __construct(private KernelInterface $kernel)
    {
    }

    /**
     * @return list<array<string, string|null>>
     */
    public function readAssociative(string $relativePath): array
    {
        $projectDir = $this->kernel->getProjectDir();
        $path = $projectDir.'/'.ltrim($relativePath, '/');

        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $reader = Reader::from($path, 'r');
        $reader->setDelimiter($this->detectDelimiter($path));
        $reader->setHeaderOffset(0);

        $rows = [];

        foreach ($reader->getRecords() as $record) {
            $rows[] = array_map(
                static fn (mixed $value): ?string => $value === '' ? null : (string) $value,
                $record
            );
        }

        return $rows;
    }

    private function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open CSV file.');
        }

        $firstLine = fgets($handle);
        fclose($handle);

        if ($firstLine === false) {
            return ',';
        }

        $candidates = [';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ','), "\t" => substr_count($firstLine, "\t")];
        arsort($candidates);

        $delimiter = (string) array_key_first($candidates);

        return $candidates[$delimiter] > 0 ? $delimiter : ',';
    }
}
