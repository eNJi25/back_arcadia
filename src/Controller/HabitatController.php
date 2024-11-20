<?php

namespace App\Controller;

use App\Entity\Habitat;
use App\Repository\HabitatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('api/habitat', name: 'app_api_habitat_')]
class HabitatController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private HabitatRepository $repository,
        private UrlGeneratorInterface $urlGenerator,
        private SerializerInterface $serializer,
    ) {}

    #[Route('/new', name: 'new', methods: 'POST')]
    public function new(Request $request): JsonResponse
    {
        $habitat = $this->serializer->deserialize($request->getContent(), Habitat::class, 'json');
        $this->manager->persist($habitat);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($habitat, 'json');
        $location = $this->urlGenerator->generate(
            'app_api_habitat_show',
            ['id' => $habitat->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        return new JsonResponse($responseData, Response::HTTP_CREATED, ["location" => $location], true);
    }

    #[Route('/showAll', name: 'show_all', methods: 'GET')]
    public function showAll(): JsonResponse
    {
        $habitats = $this->repository->findAll();

        $data = [];
        foreach ($habitats as $habitat) {
            $data[] = [
                'id' => $habitat->getId(),
                'nom' => $habitat->getNom(),
                'description' => $habitat->getDescription(),
                'image' => $habitat->getImage(),
            ];
        }

        return new JsonResponse($data, Response::HTTP_OK);
    }


    #[Route('/show/{id}', name: 'show', methods: 'GET')]
    public function show(int $id): JsonResponse
    {
        $habitat = $this->repository->findOneBy(['id' => $id]);
        if ($habitat) {
            $responseData = $this->serializer->serialize($habitat, 'json');
            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/edit/{id}', name: 'edit', methods: 'POST')]
    public function edit(int $id, Request $request): JsonResponse
    {
        $habitat = $this->repository->findOneBy(['id' => $id]);
        if (!$habitat) {
            return new JsonResponse(['message' => 'Habitat non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->request->all();
        $file = $request->files->get('image');

        if (isset($data['nom'])) {
            $habitat->setNom($data['nom']);
        }

        if (isset($data['description'])) {
            $habitat->setDescription($data['description']);
        }

        if ($file instanceof UploadedFile) {
            $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/assets/images/habitats';

            // Supprimer l'ancienne image
            $oldImagePath = $uploadsDir . '/' . $habitat->getImage();
            if (file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }

            // Générer un nouveau nom unique pour le fichier
            $newFilename = '/assets/images/habitats/' . uniqid() . '.' . $file->guessExtension();

            try {
                $file->move($uploadsDir, $newFilename);
                $habitat->setImage($newFilename);
            } catch (FileException $e) {
                return new JsonResponse(['message' => 'Erreur lors de l\'upload de l\'image'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $this->manager->flush();

        return new JsonResponse(['message' => 'Habitat modifié avec succès'], Response::HTTP_ACCEPTED);
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
