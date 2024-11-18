<?php

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\Habitat;
use App\Entity\Image;
use App\Entity\Race;
use App\Repository\AnimalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/animal', name: 'app_api_animal_')]
class AnimalController extends AbstractController
{
    private $uploadDirectory;

    public function __construct(
        private EntityManagerInterface $manager,
        private AnimalRepository $repository,
        private UrlGeneratorInterface $urlGenerator,
        private SerializerInterface $serializer,
    ) {
        $this->uploadDirectory = __DIR__ . '/../../public/assets/images/animaux';
    }

    #[Route('/new', name: 'new', methods: ['POST'])]
    public function new(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $raceName = $data['race'];
        $prenom = $data['prenom'];
        $etat = $data['etat'];
        $habitatName = $data['habitat'];

        $race = $this->manager->getRepository(Race::class)->findOneBy(['race' => $raceName]);
        $habitat = $this->manager->getRepository(Habitat::class)->findOneBy(['nom' => $habitatName]);

        if (!$race) {
            $race = new Race();
            $race->setRace($raceName);
            $this->manager->persist($race);
            $this->manager->flush();
        }



        $animal = new Animal();
        $animal->setPrenom($prenom);
        $animal->setEtat($etat);
        $animal->setHabitat($habitat);
        $animal->setRace($race);

        if ($request->files->get('image')) {
            $imageFile = $request->files->get('image');

            if ($imageFile instanceof UploadedFile) {
                $filename = uniqid() . '.' . $imageFile->guessExtension();
                try {

                    $imageFile->move($this->uploadDirectory, $filename);

                    $image = new Image();
                    $image->setSlug('/uploads/images/' . $filename);
                    $image->setAnimal($animal);

                    // Persiste l'image
                    $this->manager->persist($image);
                } catch (FileException $e) {
                    return new Response('Erreur lors du téléchargement de l\'image.', 500);
                }
            }
        }

        $this->manager->persist($animal);
        $this->manager->flush();

        // Retourner une réponse de succès
        return new JsonResponse(["message" => "Animal créé avec succès"], 201);
    }

    #[Route('/show/{id}', name: 'show', methods: 'GET')]
    public function show(int $id): JsonResponse
    {
        $animal = $this->repository->findOneBy(['id' => $id]);
        if ($animal) {
            $responseData = $this->serializer->serialize($animal, 'json');
            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/edit/{id}', name: 'edit', methods: 'PUT')]
    public function edit(int $id, Request $request): JsonResponse
    {
        $animal = $this->repository->findOneBy(['id' => $id]);
        if ($animal) {
            $animal = $this->serializer->deserialize(
                $request->getContent(),
                Animal::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $animal]
            );

            $this->manager->flush();

            return new JsonResponse(['message' => 'Modifier avec succès'], 202);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/delete/{id}', name: 'delete', methods: 'DELETE')]
    public function delete(int $id): JsonResponse
    {
        $animal = $this->repository->findOneBy(['id' => $id]);
        if ($animal) {
            $this->manager->remove($animal);
            $this->manager->flush();

            return new JsonResponse(["message" => "Animal supprimé avec succès"], Response::HTTP_OK);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}
