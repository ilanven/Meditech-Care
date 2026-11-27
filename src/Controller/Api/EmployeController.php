<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Employe\CreateEmployeInput;
use App\Dto\Employe\EmployeOutput;
use App\Dto\Product\ProductOutput;
use App\Entity\Employe;
use App\Repository\EmployeRepository;
use Doctrine\ORM\EntityManager;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/employe', name: 'api_employe', format: 'json')]
final class EmployeController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    #[OA\Get(
        summary: 'Lister les employés en poste (actifs)',
        description: 'Retourner tous les employés actifs, triés par nom croissant.',
        tags: ['Employes'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tableau JSON de EmployeOutput, éventuellement vide (si aucun employé d\'enregistré).',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: EmployeOutput::class)),
                    example: [[
                        'id' => 1,
                        'nom' => 'DUPONT',
                        'prenom' => 'Jean',
                        'telephone' => '0612345678',
                        'email' => 'jean.dupont@email.com',
                        'poste' => 'Technicien',
                        'actif' => true,
                    ]],
                ),
            ),
        ],
    )]
    public function index(EmployeRepository $employerRepository): JsonResponse
    {
        $employes = $employerRepository->findBy([], ['nom' => 'ASC']);
        return $this->json(array_map(EmployeOutput::fromEntity(...), $employes));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(EmployeRepository $employerRepository, Request $request, SerializerInterface $serializer, ValidatorInterface $validator, EntityManager $em): JsonResponse
    {
        # Transformer le contenu du JSON de la requête en une instance de CreateEmployeInput
        $input = $serializer->deserialize($request->getContent(), CreateEmployeInput::class, 'json');

        # Vérifier la validité des données via les assert de CreateEmployeInput
        $violations = $validator->validate($input);
        if (count($violations) > 0) {
            $details = [];
            foreach ($violations as $violation) {
                $details[] = ['field' => $violation->getPropertyPath(), 'message' => $violation->getMessage()];
            }
            return $this->json(['success' => false, 'message' => 'Le payload ne respecte pas les contraintes', 'details' => $details], 422);
        }
        $employe = (new Employe())
            ->setNom($input->nom)
            ->setPrenom($input->prenom)
            ->setTelephone($input->telephone)
            ->setEmail($input->email)
            ->setPoste($input->poste)
            ->setActif($input->actif);

        $em->persist($employe);
        $em->flush();
        return $this->json(['success' => true, 'message' => 'Création réussie !', 'employé créé' => EmployeOutput::fromEntity($employe)], 201);
    }
}
