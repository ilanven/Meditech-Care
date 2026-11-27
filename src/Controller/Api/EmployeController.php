<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Employe\EmployeOutput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/employe', name: 'api_employe', format: 'json')]
final class EmployeController extends AbstractController
{
    #[Route('', name: 'index', methods:['GET'])]
     #[OA\Get(
        summary: 'Lister les employés en poste (actifs)',
        description: 'Retourner tous les employés actifs, t1riés par nom croissant.',
        tags: ['Employes'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tableau JSON de EmployeOutput, éventuellement vide (si aucun employé d\'enregistré.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: EmployeOutput::class)),
                    example: [[
                        'id' => 1, 'name' => 'Clavier mécanique', 'description' => 'AZERTY compact',
                        'price' => '49.90', 'stock' => 12, 'isActive' => true,
                    ]],
                ),
            ),
        ],
    )]
    public function index(): JsonResponse
    {
        return $this->render('employe/index.html.twig', [
            'controller_name' => 'EmployeController',
        ]);
    }
}
