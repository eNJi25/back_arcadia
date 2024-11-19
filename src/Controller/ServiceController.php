<?php

namespace App\Controller;

use App\Entity\Service;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/services', name: 'app_api_service_')]
class ServiceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private ServiceRepository $repository,
        private SerializerInterface $serializer
    ) {}

    #[Route('/new', name: 'new', methods: 'POST')]
    public function new(Request $request): JsonResponse
    {
        $service = $this->serializer->deserialize($request->getContent(), Service::class, 'json');
        $this->manager->persist($service);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($service, 'json');
        return new JsonResponse($responseData,Response::HTTP_CREATED, [], true);
    }

    #[Route('/showAll', name: 'show_all', methods: 'GET')]
    public function showAll(): JsonResponse
    {
        $services = $this->repository->findAll();
        $responseData = $this->serializer->serialize($services, 'json', ['json_encode_options' => JSON_PRETTY_PRINT]);
        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    #[Route('/show/{id}', name: 'show', methods: 'GET')]
    public function show(int $id): JsonResponse
    {
        $service = $this->repository->find($id);

        if (!$service) {
            return new JsonResponse(['error' => 'Service non trouvé.'],Response::HTTP_NOT_FOUND);
        }

        $responseData = $this->serializer->serialize($service, 'json');
        return new JsonResponse($responseData,Response::HTTP_OK, [], true);
    }

    #[Route('/update/{id}', name: 'update', methods: 'PUT')]
    public function update(int $id, Request $request): JsonResponse
    {
        $service = $this->repository->find($id);

        if (!$service) {
            return new JsonResponse(['error' => 'Service non trouvé.'],Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $service->setNom($data['nom'] ?? $service->getNom())
            ->setDescription($data['description'] ?? $service->getDescription())
            ->setImage($data['image'] ?? $service->getImage())
            ->setImageDirection($data['imageDirection'] ?? $service->getImageDirection());

        $this->manager->flush();
        $responseData = $this->serializer->serialize($service, 'json');
        return new JsonResponse($responseData,Response::HTTP_OK, [], true);
    }

    #[Route('/delete/{id}', name: 'delete', methods: 'DELETE')]
    public function delete(int $id): JsonResponse
    {
        $service = $this->repository->find($id);

        if (!$service) {
            return new JsonResponse(['error' => 'Service non trouvé.'],Response::HTTP_NOT_FOUND);
        }

        $this->manager->remove($service);
        $this->manager->flush();
        return new JsonResponse(['message' => 'Service supprimé avec succès.'],Response::HTTP_OK);
    }
}
