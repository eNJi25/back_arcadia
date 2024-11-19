<?php

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\Habitat;
use App\Entity\Image;
use App\Entity\Race;
use App\Repository\AnimalRepository;
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
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/animal', name: 'app_api_animal_')]
class AnimalController extends AbstractController
{
    private $uploadDirectory;

    public function __construct(
        private EntityManagerInterface $manager,
        private AnimalRepository $animalRepository,
        private HabitatRepository $habitatRepository,
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

        return new JsonResponse(["message" => "Animal créé avec succès"], 201);
    }

    #[Route('/show/{id}', name: 'show', methods: 'GET')]
    public function show(int $id): JsonResponse
    {
        $animal = $this->animalRepository->findOneBy(['id' => $id]);
        if ($animal) {
            $responseData = $this->serializer->serialize($animal, 'json');
            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/showlastAnimals/{habitatId}', name: 'show_lastAnimal_byHabitat', methods: 'GET')]
    public function showLastAnimalsByHabitat(int $habitatId): JsonResponse
    {
        $habitat = $this->habitatRepository->find($habitatId);

        if (!$habitat) {
            return new JsonResponse(['message' => 'Habitat non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $animals = $this->animalRepository->findBy(
            ['habitat' => $habitat],
            ['id' => 'DESC'],
            4
        );

        $data = [];
        foreach ($animals as $animal) {
            $race = $animal->getRace();
            $raceName = $race ? $race->getRace() : null;

            $data[] = [
                'id' => $animal->getId(),
                'prenom' => $animal->getPrenom(),
                'etat' => $animal->getEtat(),
                'habitat' => $animal->getHabitat()->getNom(),
                'race' => $raceName,
                'images' => array_map(fn($image) => $image->getUrl(), $animal->getImages()->toArray()),
            ];
        }

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/showAnimals/{habitatId}', name: 'show_allAnimals_byHabitat', methods: 'GET')]
    public function showAllAnimalsByHabitat(int $habitatId): JsonResponse
    {
        $habitat = $this->habitatRepository->find($habitatId);

        if (!$habitat) {
            return new JsonResponse(['message' => 'Habitat non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $animals = $this->animalRepository->findBy(['habitat' => $habitat]);

        $data = [];
        foreach ($animals as $animal) {
            $race = $animal->getRace();
            $raceName = $race ? $race->getRace() : null;

            $data[] = [
                'id' => $animal->getId(),
                'prenom' => $animal->getPrenom(),
                'etat' => $animal->getEtat(),
                'habitat' => $animal->getHabitat()->getNom(),
                'race' => $raceName,
                'images' => array_map(fn($image) => $image->getUrl(), $animal->getImages()->toArray()),
            ];
        }

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/showAnimalsHome', name: 'show_animals_page', methods: 'GET')]
    public function showAllAnimals(): JsonResponse
    {
        // Récupérer tous les habitats
        $habitats = $this->habitatRepository->findAll();

        if (empty($habitats)) {
            return new JsonResponse(['message' => 'Aucun habitat trouvé'], Response::HTTP_NOT_FOUND);
        }

        $animalsData = [];

        foreach ($habitats as $habitat) {
            // Recherche un animal de cet habitat
            $animal = $this->animalRepository->findOneBy(['habitat' => $habitat]);

            if ($animal) {
                $data = [
                    'habitat' => $habitat->getNom(),
                    'animal' => [
                        'id' => $animal->getId(),
                        'prenom' => $animal->getPrenom(),
                        'etat' => $animal->getEtat(),
                        'race' => $animal->getRace() ? $animal->getRace()->getRace() : null,
                        'images' => array_map(fn($image) => $image->getUrl(), $animal->getImages()->toArray()),
                    ]
                ];

                $animalsData[] = $data;
            }
        }

        if (empty($animalsData)) {
            return new JsonResponse(['message' => 'Aucun animal trouvé pour les habitats'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($animalsData, Response::HTTP_OK);
    }


    #[Route('/edit/{id}', name: 'edit', methods: 'PUT')]
    public function edit(int $id, Request $request): JsonResponse
    {
        $animal = $this->animalRepository->findOneBy(['id' => $id]);
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
        $animal = $this->animalRepository->findOneBy(['id' => $id]);
        if ($animal) {
            $this->manager->remove($animal);
            $this->manager->flush();

            return new JsonResponse(["message" => "Animal supprimé avec succès"], Response::HTTP_OK);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}
