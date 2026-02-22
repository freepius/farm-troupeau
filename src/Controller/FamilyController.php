<?php

namespace App\Controller;

use App\Service\AnimalFamilyViewBuilder;
use App\Service\CsvReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FamilyController extends AbstractController
{
    #[Route('/familles', name: 'app_families')]
    public function index(Request $request, CsvReader $csvReader, AnimalFamilyViewBuilder $familyViewBuilder): Response
    {
        $csvPath = 'data/animaux.csv';
        $rows = $csvReader->readAssociative($csvPath);
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
        ];
        $view = $familyViewBuilder->build($rows, $filters);

        return $this->render('family/index.html.twig', [
            'csv_path' => $csvPath,
            'filters' => $filters,
            'family_view' => $view,
        ]);
    }
}
