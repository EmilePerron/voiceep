<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ProjectRepository")
 */
class Project
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank()
     */
    private $title;

    /**
     * @ORM\Column(type="string", length=45)
     */
    private $identifier;

    /**
     * @ORM\Column(type="string", length=45, nullable=true)
     */
    private $apiKey;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $defaultLanguageCode;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $defaultContentSelector;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Entry", mappedBy="project")
     */
    private $entries;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="projects")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\Column(type="array")
     */
    private $allowedDomains = [];

    public function __construct()
    {
        $this->entries = new ArrayCollection();
        $this->identifier = uniqid();
        $this->allowedDomains = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function generateApiKey(): string
    {
        return md5($this->id) . $this->getIdentifier();
    }

    public function getDefaultLanguageCode(): ?string
    {
        return $this->defaultLanguageCode;
    }

    public function setDefaultLanguageCode(?string $defaultLanguageCode): self
    {
        $this->defaultLanguageCode = $defaultLanguageCode;

        return $this;
    }

    public function getDefaultContentSelector(): ?string
    {
        return $this->defaultContentSelector;
    }

    public function setDefaultContentSelector(?string $defaultContentSelector): self
    {
        $this->defaultContentSelector = $defaultContentSelector;

        return $this;
    }

    /**
     * @return Collection|Entry[]
     */
    public function getEntries(): Collection
    {
        return $this->entries;
    }

    public function addEntry(Entry $entry): self
    {
        if (!$this->entries->contains($entry)) {
            $this->entries[] = $entry;
            $entry->setProject($this);
        }

        return $this;
    }

    public function removeEntry(Entry $entry): self
    {
        if ($this->entries->contains($entry)) {
            $this->entries->removeElement($entry);
            // set the owning side to null (unless already changed)
            if ($entry->getProject() === $this) {
                $entry->setProject(null);
            }
        }

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getAllowedDomains(): ?array
    {
        return $this->allowedDomains;
    }

    public function setAllowedDomains($allowedDomains): self
    {
        if (!$allowedDomains) {
            $allowedDomains = [];
        } else if (is_string($allowedDomains)) {
            $allowedDomains = json_decode($allowedDomains, true);
        }

        foreach ($allowedDomains as $index => $domain) {
            $parts = parse_url($domain);
            if ($domain = ($parts['host'] ?? null)) {
                $allowedDomains[$index] = $domain;
            }
        }
        $allowedDomains = array_unique($allowedDomains);

        $this->allowedDomains = $allowedDomains;

        return $this;
    }

    public function delete(&$em) {
        foreach ($this->getEntries() as $entry) {
            $entry->delete($em);
        }
        $em->remove($this);
    }

    public function checkIfUrlMatchesAllowedDomains($url) {
        $domain = str_replace(['https://', 'http://'], '', strtolower($url));

        if (strpos($domain, '/') !== false) {
            $domain = substr($domain, 0, strpos($domain, '/'));
        }

        foreach ($this->getAllowedDomains() as $allowedDomain) {
            if ($domain == strtolower($allowedDomain)) {
                return true;
            }
        }

        return false;
    }

    public function getLatestVoiceoverLanguage($fallbackLanguage = 'en-US') {
        # Fetch the language code from this project's latest voiceover
        foreach ($this->getEntries() as $entry) {
            $previousVoiceover = $entry->getVoiceovers()->first();
            if ($previousVoiceover && $previousVoiceover->getLanguageCode()) {
                $defaultLanguageCode = $previousVoiceover->getLanguageCode();
                break;
            }
        }

        return $previousVoiceover ? $previousVoiceover->getLanguageCode() : $fallbackLanguage;
    }
}
