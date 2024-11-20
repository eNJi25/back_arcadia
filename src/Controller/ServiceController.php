<?php

namespace App\Controller;

use App\Entity\Service;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
        $service = new Service();

        $nom = $request->request->get('nom');
        $description = $request->request->get('description');
        $imageDirection = $request->request->get('imageDirection');

        /** @var UploadedFile $photo */
        $photo = $request->files->get('image');

        if (!$nom || !$description || !$imageDirection || !$photo instanceof UploadedFile || !$photo->isValid()) {
            return new JsonResponse(['message' => 'Données invalides ou fichier manquant.'], 400);
        }

        $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/assets/images/services';

        $newFilename = uniqid() . '.' . $photo->guessExtension();

        try {
            $photo->move($uploadsDir, $newFilename);
            $service->setImage('assets/images/services/' . $newFilename);
        } catch (FileException $e) {
            return new JsonResponse(['message' => 'Erreur lors de l\'upload de l\'image.'], 500);
        }

        $service->setNom($nom);
        $service->setDescription($description);
        $service->setImageDirection($imageDirection);

        $this->manager->persist($service);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($service, 'json');
        return new JsonResponse($responseData, Response::HTTP_CREATED, [], true);
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
            return new JsonResponse(['error' => 'Service non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $responseData = $this->serializer->serialize($service, 'json');
        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    #[Route('/update/{id}', name: 'update_service', methods: ['PUT', 'POST'])]
    public function update(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        ServiceRepository $serviceRepository
    ): JsonResponse {
        $service = $serviceRepository->find($id);

        if (!$service) {
            return new JsonResponse(['error' => 'Service non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $data = [];
        if ($request->getMethod() === 'PUT') {
            $content = $request->getContent();
            parse_str($content, $data);
        } else {
            $data = $request->request->all();
        }

        $file = $request->files->get('image');

        if (!empty($data['nom'])) {
            $service->setNom($data['nom']);
        }

        if (!empty($data['description'])) {
            $service->setDescription($data['description']);
        }

        if (!empty($data['imageDirection'])) {
            $service->setImageDirection($data['imageDirection']);
        }

        if ($file instanceof UploadedFile && $file->isValid()) {
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/assets/images/services';

            $oldImagePath = $this->getParameter('kernel.project_dir') . '/public/' . $service->getImage();
            if ($service->getImage() && file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }

            $newFilename = uniqid() . '.' . $file->guessExtension();
            try {
                $file->move($uploadDir, $newFilename);
                $service->setImage('assets/images/services/' . $newFilename);
            } catch (FileException $e) {
                return new JsonResponse(['error' => 'Erreur lors de l\'upload de l\'image.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        try {
            $entityManager->flush();
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de la mise à jour du service.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['message' => 'Service mis à jour avec succès.'], Response::HTTP_OK);
    }


    #[Route('/delete/{id}', name: 'delete', methods: 'DELETE')]
    public function delete(int $id): JsonResponse
    {
        $service = $this->repository->find($id);

        if (!$service) {
            return new JsonResponse(['error' => 'Service non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $this->manager->remove($service);
        $this->manager->flush();
        return new JsonResponse(['message' => 'Service supprimé avec succès.'], Response::HTTP_OK);
    }
}
