<?php

namespace App\Entity;

use App\Repository\RapportVeterinaireRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: RapportVeterinaireRepository::class)]
class RapportVeterinaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $detail_habitat = null;

    /**
     * @MaxDepth(1)
     * @Groups(["rapportVeterinaire:read"])
     */
    #[ORM\ManyToOne(inversedBy: 'rapportVeterinaires')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Animal $animal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nourriture_propose = null;

    #[ORM\Column(nullable: true)]
    private ?float $quantite_propose = null;

    #[ORM\Column(length: 255)]
    private ?string $etat_animal = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getDetailHabitat(): ?string
    {
        return $this->detail_habitat;
    }

    public function setDetailHabitat(string $detail_habitat): static
    {
        $this->detail_habitat = $detail_habitat;

        return $this;
    }

    public function getAnimal(): ?animal
    {
        return $this->animal;
    }

    public function setAnimal(?animal $animal): static
    {
        $this->animal = $animal;

        return $this;
    }

    public function getNourriturePropose(): ?string
    {
        return $this->nourriture_propose;
    }

    public function setNourriturePropose(?string $nourriture_propose): static
    {
        $this->nourriture_propose = $nourriture_propose;

        return $this;
    }

    public function getQuantitePropose(): ?float
    {
        return $this->quantite_propose;
    }

    public function setQuantitePropose(?float $quantite_propose): static
    {
        $this->quantite_propose = $quantite_propose;

        return $this;
    }

    public function getEtatAnimal(): ?string
    {
        return $this->etat_animal;
    }

    public function setEtatAnimal(string $etat_animal): static
    {
        $this->etat_animal = $etat_animal;

        return $this;
    }
}
