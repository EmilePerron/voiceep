<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\EntryRepository")
 */
class Entry
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="text")
     * @Assert\NotBlank()
     */
    private $url;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $rawTitle;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $title;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Voiceover", mappedBy="entry")
     * @ORM\OrderBy({"creationDate" = "DESC"})
     */
    private $voiceovers;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Project", inversedBy="entries")
     * @ORM\JoinColumn(nullable=false)
     */
    private $project;

    public function __construct()
    {
        $this->voiceovers = new ArrayCollection();
    }

    public function __toString() {
        return $this->url;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = strtolower(str_replace(['https://', 'http://'], '', $url));

        return $this;
    }

    public function getRawTitle(): ?string
    {
        return $this->rawTitle;
    }

    public function setRawTitle(?string $rawTitle): self
    {
        $this->rawTitle = $rawTitle;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return Collection|Voiceover[]
     */
    public function getVoiceovers(): Collection
    {
        return $this->voiceovers;
    }

    public function addVoiceover(Voiceover $voiceover): self
    {
        if (!$this->voiceovers->contains($voiceover)) {
            $this->voiceovers[] = $voiceover;
            $voiceover->setEntry($this);
        }

        return $this;
    }

    public function removeVoiceover(Voiceover $voiceover): self
    {
        if ($this->voiceovers->contains($voiceover)) {
            $this->voiceovers->removeElement($voiceover);
            // set the owning side to null (unless already changed)
            if ($voiceover->getEntry() === $this) {
                $voiceover->setEntry(null);
            }
        }

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getLatestVoiceover($languageCode = null, $includeFailed = true, $type = null) {
        foreach ($this->getVoiceovers() as $voiceover) {
            if ($languageCode && ($voiceover->getLanguageCode() != $languageCode &&
                                !(strpos($languageCode, '-') === false && strpos($voiceover->getLanguageCode(), $languageCode . '-') !== false))) {
                continue;
            }

            if (!$includeFailed && $voiceover->getStatus() == 'failed') {
                continue;
            }

            if ($type && $voiceover->getType() != $type) {
                continue;
            }

            return $voiceover;
        }

        return null;
    }

    public function delete(&$em) {
        foreach ($this->getVoiceovers() as $voiceover) {
            $voiceover->delete($em);
        }
        $em->remove($this);
    }
}
