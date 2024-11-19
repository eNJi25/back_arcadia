<?php

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\Habitat;
use App\Entity\Image;
use App\Entity\Race;
use App\Repository\AnimalRepository;
use App\Repository\HabitatRepository;
use App\Repository\RaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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

    public function __construct(
        private EntityManagerInterface $manager,
        private AnimalRepository $animalRepository,
        private HabitatRepository $habitatRepository,
        private UrlGeneratorInterface $urlGenerator,
        private SerializerInterface $serializer,
    ) {}

    #[Route('/new', name: 'new_animal', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $em, HabitatRepository $habitatRepo, RaceRepository $raceRepo): JsonResponse
    {
        // Récupérer les données de la requête
        $prenom = $request->request->get('prenomAnimal');
        $etat = $request->request->get('etatAnimal');
        $habitatName = $request->request->get('habitatAnimal');
        $raceName = $request->request->get('raceAnimal');
        $photo = $request->files->get('photo');  // Photo de l'animal

        // Vérification des données
        if (!$prenom || !$etat || !$habitatName || !$raceName || !$photo) {
            return new JsonResponse(['message' => 'Tous les champs sont nécessaires.'], 400);
        }

        // Recherche de l'habitat
        $habitat = $habitatRepo->findOneBy(['nom' => $habitatName]);
        if (!$habitat) {
            return new JsonResponse(['message' => 'Habitat introuvable.'], 404);
        }

        // Vérification et création de la race si elle n'existe pas
        $race = $raceRepo->findOneBy(['race' => $raceName]);
        if (!$race) {
            $race = new Race();
            $race->setRace($raceName);
            $em->persist($race);
            $em->flush();
        }

        // Vérification de la validité de l'image
        if (!$photo instanceof UploadedFile || !$photo->isValid()) {
            return new JsonResponse(['message' => 'Erreur avec le fichier photo.'], 400);
        }

        // Spécifier le répertoire de destination pour l'image
        $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/assets/images/animaux';

        // Générer un nom unique pour l'image
        $newFilename = '/assets/images/animaux/' . uniqid() . '.' . $photo->guessExtension();

        try {
            // Déplacer l'image dans le répertoire des animaux
            $photo->move($uploadsDir, $newFilename);
        } catch (FileException $e) {
            return new JsonResponse(['message' => 'Erreur lors de l\'upload de l\'image'], 500);
        }

        // Créer l'animal
        $animal = new Animal();
        $animal->setPrenom($prenom)
            ->setEtat($etat)
            ->setHabitat($habitat)
            ->setRace($race);

        $em->persist($animal);

        // Créer l'image associée
        $image = new Image();
        $image->setSlug($newFilename);  // Assigner le chemin de l'image à l'entité Image
        $image->setAnimal($animal);

        $em->persist($image);
        $em->flush();

        // Retourner une réponse JSON de succès avec le chemin de l'image
        return new JsonResponse([
            'message' => 'Animal créé avec succès!',
            'animalId' => $animal->getId(),
            'imagePath' => $newFilename  // Retourner le chemin relatif de l'image
        ], 201);
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
