<?php

namespace App\Entity;

use App\Repository\AnimalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

#[ORM\Entity(repositoryClass: AnimalRepository::class)]
class Animal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['animal:read'])]
    private ?string $prenom = null;

    #[ORM\ManyToOne(targetEntity: Habitat::class, inversedBy: 'animals')]
    #[ORM\JoinColumn(nullable: false)]
    #[MaxDepth(1)]
    #[Groups(['animal:read'])]
    private ?Habitat $habitat = null;

    #[ORM\ManyToOne(inversedBy: 'animals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Race $race = null;

    #[ORM\OneToMany(targetEntity: Image::class, mappedBy: 'animal')]
    private Collection $images;

    #[ORM\OneToMany(mappedBy: 'animal', targetEntity: RapportVeterinaire::class)]
    #[MaxDepth(1)]
    #[Groups(['animal:read', 'rapportVeterinaire:read'])]
    private Collection $rapportVeterinaires;

    #[ORM\Column(nullable: true)]
    #[Groups(['animal:read'])]
    private ?\DateTimeImmutable $date_repas = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['animal:read'])]
    private ?int $quantite_repas = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['animal:read'])]
    private ?string $nourriture = null;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->rapportVeterinaires = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getHabitat(): ?Habitat
    {
        return $this->habitat;
    }

    public function setHabitat(?Habitat $habitat): static
    {
        $this->habitat = $habitat;
        return $this;
    }

    public function getRace(): ?Race
    {
        return $this->race;
    }

    public function setRace(?Race $race): static
    {
        $this->race = $race;
        return $this;
    }

    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(Image $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setAnimal($this);
        }
        return $this;
    }

    public function removeImage(Image $image): static
    {
        if ($this->images->removeElement($image)) {
            if ($image->getAnimal() === $this) {
                $image->setAnimal(null);
            }
        }
        return $this;
    }

    public function getRapportVeterinaires(): Collection
    {
        return $this->rapportVeterinaires;
    }

    public function addRapportVeterinaire(RapportVeterinaire $rapportVeterinaire): static
    {
        if (!$this->rapportVeterinaires->contains($rapportVeterinaire)) {
            $this->rapportVeterinaires->add($rapportVeterinaire);
            $rapportVeterinaire->setAnimal($this);
        }
        return $this;
    }

    public function removeRapportVeterinaire(RapportVeterinaire $rapportVeterinaire): static
    {
        if ($this->rapportVeterinaires->removeElement($rapportVeterinaire)) {
            if ($rapportVeterinaire->getAnimal() === $this) {
                $rapportVeterinaire->setAnimal(null);
            }
        }
        return $this;
    }

    public function getDateRepas(): ?\DateTimeImmutable
    {
        return $this->date_repas;
    }

    public function setDateRepas(?\DateTimeImmutable $date_repas): static
    {
        $this->date_repas = $date_repas;
        return $this;
    }

    public function getQuantiteRepas(): ?int
    {
        return $this->quantite_repas;
    }

    public function setQuantiteRepas(?int $quantite_repas): static
    {
        $this->quantite_repas = $quantite_repas;
        return $this;
    }

    public function getNourriture(): ?string
    {
        return $this->nourriture;
    }

    public function setNourriture(?string $nourriture): static
    {
        $this->nourriture = $nourriture;
        return $this;
    }
}
