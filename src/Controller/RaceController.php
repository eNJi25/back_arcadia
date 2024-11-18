<?php

namespace App\Controller;

use App\Entity\Race;
use App\Repository\RaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/race', name: 'app_api_race_')]
class RaceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private RaceRepository $repository,
        private UrlGeneratorInterface $urlGenerator,
        private SerializerInterface $serializer,
    ) {}

    #[Route('/new', name: 'new', methods: ['POST'])]
    public function new(Request $request): JsonResponse
    {
        $race = $this->serializer->deserialize($request->getContent(), Race::class, 'json');
        $this->manager->persist($race);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($race, 'json');
        $location = $this->urlGenerator->generate(
            'app_api_race_show',
            ['id' => $race->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        return new JsonResponse($responseData, Response::HTTP_CREATED, ["location" => $location], true);
    }

    #[Route('/show/{id}', name: 'show', methods: 'GET')]
    public function show(int $id): JsonResponse
    {
        $race = $this->repository->findOneBy(['id' => $id]);
        if ($race) {
            $responseData = $this->serializer->serialize($race, 'json');
            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}
