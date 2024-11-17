<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/registration', name: 'app_api_registration_')]
class SecurityController extends AbstractController
{
    private $userRepository;
    public function __construct(private EntityManagerInterface $manager, private SerializerInterface $serializer)
    {
        $this->userRepository = $manager->getRepository(User::class);
    }

    #[Route('/veterinaire', name: 'veterinaire', methods: 'POST')]
    public function registerVet(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'])) {
            return new JsonResponse(['error' => 'Le mail et le mot de passe sont nécesaire'], 400);
        }

        $user = $this->serializer->deserialize($request->getContent(), User::class, 'json');
        $user->setEmail($data['email']);
        $user->setPassword($passwordHasher->hashPassword($user, $user->getPassword()));
        $user->setRoles(['veterinaire']);

        $this->manager->persist($user);
        $this->manager->flush();

        return new JsonResponse(["message" => "L'utilisateur vétérinaire a été crée avec succès"], 201);
    }

    #[Route('/employee', name: 'employee', methods: 'POST')]
    public function registerEmp(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || !isset($data['password'])) {
            return new JsonResponse(['error' => 'Le mail et le mot de passe sont nécessaires'], 400);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
        $user->setRoles(['employee']);

        $this->manager->persist($user);
        $this->manager->flush();

        return new JsonResponse(["message" => "L'utilisateur employé a été créé avec succès"], 201);
    }


    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return new JsonResponse([
                'message' => 'L\'email et mot de passe sont requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            return new JsonResponse([
                'message' => 'Utilisateur non trouvé'
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$passwordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse([
                'message' => 'Mot de passe incorrect'
            ], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'user' => $user->getUserIdentifier(),
            'apiToken' => $user->getApiToken(),  // En pratique, ici tu générerais un token JWT ou autre mécanisme
            'roles' => $user->getRoles()
        ], Response::HTTP_OK);
    }
}
