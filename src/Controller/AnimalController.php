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
    public function new(Request $request, EntityManagerInterface $manager, HabitatRepository $habitatRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Récupération des données envoyées par le frontend
        $raceName = $data['race'];
        $prenom = $data['prenom'];
        $etat = $data['etat'];
        $habitatId = $data['habitat_id']; // L'ID de l'habitat, au lieu du nom
        $photoAnimal = $request->files->get('photo'); // Le fichier de l'image envoyé

        // Vérification des données obligatoires
        if (!$raceName || !$prenom || !$etat || !$habitatId || !$photoAnimal) {
            return new JsonResponse(['message' => 'Données manquantes'], Response::HTTP_BAD_REQUEST);
        }

        // Récupérer la race et l'habitat
        $race = $manager->getRepository(Race::class)->findOneBy(['race' => $raceName]);
        $habitat = $habitatRepository->find($habitatId); // On récupère l'habitat par son ID

        if (!$race) {
            $race = new Race();
            $race->setRace($raceName);
            $manager->persist($race);
            $manager->flush();
        }

        if (!$habitat) {
            return new JsonResponse(['message' => 'Habitat non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Création de l'animal
        $animal = new Animal();
        $animal->setPrenom($prenom);
        $animal->setEtat($etat);
        $animal->setHabitat($habitat);
        $animal->setRace($race);

        // Gestion de l'image
        if ($photoAnimal->isValid()) {
            // Vérification du type de fichier et de la taille
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($photoAnimal->getClientMimeType(), $allowedTypes)) {
                return new JsonResponse(['message' => 'Type de fichier non autorisé'], Response::HTTP_BAD_REQUEST);
            }

            if ($photoAnimal->getSize() > 5 * 1024 * 1024) { // 5MB max
                return new JsonResponse(['message' => 'L\'image est trop grande'], Response::HTTP_BAD_REQUEST);
            }

            // Générer un nom unique pour l'image
            $imageName = uniqid() . '.' . $photoAnimal->guessExtension();
            $imagePath = $this->getParameter('kernel.project_dir') . '/public/assets/images/habitats/' . $habitatId;

            // Créer le dossier de l'habitat si nécessaire
            if (!is_dir($imagePath)) {
                mkdir($imagePath, 0777, true);
            }

            // Déplacer l'image dans le bon dossier
            $photoAnimal->move($imagePath, $imageName);

            // Enregistrer l'image dans la base de données
            $image = new Image();
            $image->setSlug($imageName);  // Le slug est le nom du fichier
            $image->setAnimal($animal);

            $manager->persist($image);
        }

        // Sauvegarde de l'animal
        $manager->persist($animal);
        $manager->flush();

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

            $imageSlug = null;
            $images = $animal->getImages();
            if ($images->count() > 0) {
                $imageSlug = $images->first()->getSlug();
            }

            $data[] = [
                'id' => $animal->getId(),
                'prenom' => $animal->getPrenom(),
                'etat' => $animal->getEtat(),
                'habitat' => $animal->getHabitat()->getNom(),
                'race' => $raceName,
                'imageSlug' => $imageSlug,
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
        $habitats = $this->habitatRepository->findAll();

        if (empty($habitats)) {
            return new JsonResponse(['message' => 'Aucun habitat trouvé'], Response::HTTP_NOT_FOUND);
        }

        $animalsData = [];

        foreach ($habitats as $habitat) {
            $animal = $this->animalRepository->findOneBy(['habitat' => $habitat]);

            if ($animal) {
                $images = $animal->getImages();
                $imageUrls = array_map(fn($image) => $image->getSlug(), $images->toArray());

                $data = [
                    'habitat' => $habitat->getNom(),
                    'animal' => [
                        'id' => $animal->getId(),
                        'prenom' => $animal->getPrenom(),
                        'etat' => $animal->getEtat(),
                        'race' => $animal->getRace() ? $animal->getRace()->getRace() : null,
                        'images' => $imageUrls,
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
