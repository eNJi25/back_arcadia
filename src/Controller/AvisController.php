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

        $responseData = $this->serializer->serialize($validAvis, 'json', ['json_encode_options' => JSON_PRETTY_PRINT]);

        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }


    #[Route('/accept/{id}', name: 'accept', methods: 'PUT')]
    public function accept(int $id, Request $request): JsonResponse
    {

        $avis = $this->repository->find($id);

        if (!$avis) {
            return new JsonResponse(['error' => 'Avis non trouvé.'], 404);
        }

        $avis->setVisible(true);

        $this->manager->flush();


        return new JsonResponse(['message' => 'Avis accepté et visible maintenant.'], 200);
    }

    #[Route('/delete/{id}', name: 'delete', methods: 'DELETE')]
    public function delete(int $id): JsonResponse
    {
        $avis = $this->repository->findOneBy(['id' => $id]);
        if ($avis) {
            $this->manager->remove($avis);
            $this->manager->flush();

            return new JsonResponse(["message" => "Avis supprimé avec succès"], Response::HTTP_OK);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}
