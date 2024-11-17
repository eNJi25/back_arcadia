<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class RapportVeterinaireController extends AbstractController
{
    #[Route('/rapport/veterinaire', name: 'app_rapport_veterinaire')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/RapportVeterinaireController.php',
        ]);
    }
}
