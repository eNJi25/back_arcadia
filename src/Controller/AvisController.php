<?php

namespace App\Controller;

use App\Entity\Avis;
use App\Repository\AvisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/avis', name: 'app_api_avis_')]
class AvisController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private AvisRepository $repository,
        private UrlGeneratorInterface $urlGenerator,
        private SerializerInterface $serializer,
    ) {}

    #[Route('/new', name: 'new', methods: 'POST')]
    public function new(Request $request): JsonResponse
    {
        $avis = $this->serializer->deserialize($request->getContent(), Avis::class, 'json');

        // Ajouter la date actuelle automatiquement
        $avis->setCreatedAt(new \DateTimeImmutable());

        $this->manager->persist($avis);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($avis, 'json');
        return new JsonResponse($responseData, Response::HTTP_CREATED, [], true);
    }

    #[Route('/toValidate', name: 'toValidate', methods: 'GET')]
    public function listAValider(): JsonResponse
    {
        $avis = $this->repository->findBy(['isVisible' => false]);

        if (!empty($avis)) {
            // Utilisation du serializer Symfony
            $responseData = $this->serializer->serialize($avis, 'json', ['json_encode_options' => JSON_PRETTY_PRINT]);
            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse([], Response::HTTP_OK);
    }

    #[Route('/valides', name: 'valides', methods: 'GET')]
    public function getValidAvis(): JsonResponse
    {
        $validAvis = $this->repository->findBy(
            ['isVisible' => true],
            ['createdAt' => 'DESC'],
            3
        );

        // Sérialiser les données pour les transformer en JSON
        $responseData = $this->serializer->serialize($validAvis, 'json', ['json_encode_options' => JSON_PRETTY_PRINT]);

        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }


    #[Route('/accept/{id}', name: 'accept', methods: 'PUT')]
    public function accept(int $id, Request $request): JsonResponse
    {
        // Récupérer l'avis par son ID
        $avis = $this->repository->find($id);

        if (!$avis) {
            return new JsonResponse(['error' => 'Avis non trouvé.'], 404);
        }

        // Mettre à jour la propriété isVisible à true
        $avis->setVisible(true);

        // Persister l'entité et effectuer la mise à jour dans la base de données
        $this->manager->flush();

        // Retourner une réponse JSON avec un message de succès
        return new JsonResponse(['message' => 'Avis accepté et visible maintenant.'], 200);
    }

    #[Route('/delete/{id}', name: 'delete', methods: 'DELETE')]
    public function delete(int $id): JsonResponse
    {
        $habitat = $this->repository->findOneBy(['id' => $id]);
        if ($habitat) {
            $this->manager->remove($habitat);
            $this->manager->flush();

            return new JsonResponse(["message" => "Habitat supprimé avec succès"], Response::HTTP_OK);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}
