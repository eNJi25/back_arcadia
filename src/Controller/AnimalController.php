<?php

namespace App\Controller;

use App\Entity\Animal;
use App\Entity\Image;
use App\Entity\Race;
use App\Repository\AnimalRepository;
use App\Repository\HabitatRepository;
use App\Repository\RaceRepository;
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
        $prenom = $request->request->get('prenomAnimal');
        $habitatName = $request->request->get('habitatAnimal');
        $raceName = $request->request->get('raceAnimal');
        $photo = $request->files->get('photo');

        if (!$prenom || !$habitatName || !$raceName || !$photo) {
            return new JsonResponse(['message' => 'Tous les champs sont nécessaires.'], 400);
        }

        $habitat = $habitatRepo->findOneBy(['nom' => $habitatName]);
        if (!$habitat) {
            return new JsonResponse(['message' => 'Habitat introuvable.'], 404);
        }

        $race = $raceRepo->findOneBy(['race' => $raceName]);
        if (!$race) {
            $race = new Race();
            $race->setRace($raceName);
            $em->persist($race);
            $em->flush();
        }

        if (!$photo instanceof UploadedFile || !$photo->isValid()) {
            return new JsonResponse(['message' => 'Erreur avec le fichier photo.'], 400);
        }

        $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/assets/images/animaux';

        $newFilename = '/assets/images/animaux/' . uniqid() . '.' . $photo->guessExtension();

        try {
            $photo->move($uploadsDir, $newFilename);
        } catch (FileException $e) {
            return new JsonResponse(['message' => 'Erreur lors de l\'upload de l\'image'], 500);
        }

        $animal = new Animal();
        $animal->setPrenom($prenom)
            ->setHabitat($habitat)
            ->setRace($race);

        $em->persist($animal);

        $image = new Image();
        $image->setSlug($newFilename);
        $image->setAnimal($animal);

        $em->persist($image);
        $em->flush();

        return new JsonResponse([
            'message' => 'Animal créé avec succès!',
            'animalId' => $animal->getId(),
            'imagePath' => $newFilename 
        ], 201);
    }


    #[Route('/show/{id}', name: 'show', methods: 'GET')]
    public function show(int $id): JsonResponse
    {
        $animal = $this->animalRepository->findOneBy(['id' => $id]);

        if ($animal) {
            $responseData = $this->serializer->serialize($animal, 'json', ['groups' => ['animal:read', 'habitat:read']]);
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


    #[Route('/edit/{id}', name: 'edit', methods: 'POST')]
    public function edit(Request $request, int $id): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Données invalides'], 400);
        }

        $animal = $this->animalRepository->find($id);
        if (!$animal) {
            return new JsonResponse(['error' => 'Animal non trouvé'], 404);
        }

        if (isset($data['quantite'])) {
            if (!is_numeric($data['quantite']) || $data['quantite'] < 0) {
                return new JsonResponse(['error' => 'Quantité invalide'], 400);
            }
            $animal->setQuantiteRepas((float) $data['quantite']);
        }

        if (isset($data['nourriture'])) {
            $animal->setNourriture($data['nourriture']);
        }

        if (isset($data['dateRepas'])) {
            try {
                $dateRepas = new \DateTimeImmutable($data['dateRepas']);
                $animal->setDateRepas($dateRepas);
            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'Format de date invalide'], 400);
            }
        }

        try {
            $this->manager->flush();
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de la mise à jour de l\'animal'], 500);
        }

        return new JsonResponse(['message' => 'Animal mis à jour avec succès'], 200);
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
