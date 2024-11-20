<?php

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\RapportVeterinaire;
use App\Repository\AnimalRepository;
use App\Repository\RapportVeterinaireRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('api/rapports', name: 'app_api_rapport_')]
class RapportVeterinaireController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private RapportVeterinaireRepository $repository,
        private AnimalRepository $animalRepository,
        private UserRepository $userRepository,
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Route('/new', name: 'new', methods: 'POST')]
    public function new(Request $request): JsonResponse
    {
        $rapportVeterinaire = $this->serializer->deserialize(
            $request->getContent(),
            RapportVeterinaire::class,
            'json',
            [AbstractNormalizer::GROUPS => ['rapportVeterinaire:create']]
        );

        $data = json_decode($request->getContent(), true);

        $animalId = $data['animal'] ?? null;
        $etatAnimal = $data['etat_animal'] ?? null;
        $nourriturePropose = $data['nourriture_propose'] ?? null;
        $quantitePropose = $data['quantite_propose'] ?? null;
        $detailHabitat = $data['detail_habitat'] ?? null;

        if (!$animalId) {
            return new JsonResponse(['error' => 'Animal ID is required'], Response::HTTP_BAD_REQUEST);
        }

        $animal = $this->manager->getRepository(Animal::class)->find($animalId);
        if (!$animal) {
            return new JsonResponse(['error' => 'Animal not found'], Response::HTTP_BAD_REQUEST);
        }
        $rapportVeterinaire->setAnimal($animal);

        if (!$etatAnimal) {
            return new JsonResponse(['error' => 'Etat animal is required'], Response::HTTP_BAD_REQUEST);
        }
        $rapportVeterinaire->setEtatAnimal($etatAnimal);

        if ($nourriturePropose) {
            $rapportVeterinaire->setNourriturePropose($nourriturePropose);
        }

        if ($quantitePropose) {
            $rapportVeterinaire->setQuantitePropose($quantitePropose);
        }

        if ($detailHabitat) {
            $rapportVeterinaire->setDetailHabitat($detailHabitat);
        }

        $rapportVeterinaire->setCreatedAt(new \DateTimeImmutable());

        $this->manager->persist($rapportVeterinaire);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($rapportVeterinaire, 'json', ['groups' => ['rapportVeterinaire:read']]);

        return new JsonResponse($responseData, Response::HTTP_CREATED, [], true);
    }



    #[Route('/show', name: 'show', methods: 'GET')]
    public function show(): JsonResponse
    {
        $rapports = $this->repository->findAll();
        $data = [];
        foreach ($rapports as $rapport) {
            $data[] = [
                'id' => $rapport->getId(),
                'createdAt' => $rapport->getCreatedAt()->format('Y-m-d H:i:s'),
                'detailHabitat' => $rapport->getDetailHabitat(),
                'nourriturePropose' => $rapport->getNourriturePropose(),
                'quantitePropose' => $rapport->getQuantitePropose(),
                'etatAnimal' => $rapport->getEtatAnimal(),
            ];
        }

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/showlastRapports', name: 'show_lastRapports', methods: 'GET')]
    public function showLastRapports(): JsonResponse
    {
        $rapports = $this->repository->findBy(
            [],
            ['createdAt' => 'DESC'],
            4
        );

        $data = [];
        foreach ($rapports as $rapport) {
            $data[] = [
                'id' => $rapport->getId(),
                'createdAt' => $rapport->getCreatedAt()->format('Y-m-d H:i:s'),
                'detailHabitat' => $rapport->getDetailHabitat(),
                'nourriturePropose' => $rapport->getNourriturePropose(),
                'quantitePropose' => $rapport->getQuantitePropose(),
                'etatAnimal' => $rapport->getEtatAnimal(),
            ];
        }

        return new JsonResponse($data, Response::HTTP_OK);
    }
}
