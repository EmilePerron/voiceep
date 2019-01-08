<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\VoiceoverRepository")
 * @ORM\HasLifecycleCallbacks
 */
class Voiceover
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $creationDate;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $text;

    /**
     * @ORM\Column(type="string", length=10)
     */
    private $languageCode;

    /**
     * @ORM\Column(type="string", length=25)
     */
    private $type;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $details;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $length;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $status;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Entry", inversedBy="voiceovers")
     * @ORM\JoinColumn(nullable=false)
     */
    private $entry;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $modifiedDate;

    public function __construct() {
        $this->creationDate = new \DateTime();
        $this->details = json_encode([]);
        $this->length = null;
        $this->status = 'awaiting_processing';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreationDate(): ?\DateTimeInterface
    {
        return $this->creationDate;
    }

    public function setCreationDate(\DateTimeInterface $creationDate): self
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function getLanguageCode(): ?string
    {
        return $this->languageCode;
    }

    public function setLanguageCode(string $languageCode): self
    {
        $this->languageCode = $languageCode;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    static public function getAllowedTypes() {
        return ['polly', 'human', 'professional', 'manual'];
    }

    public function getTypeName() {
        switch (strtolower($this->getType())) {
            case 'polly':
                return 'Computer generated';
            case 'human':
                return 'Human';
            case 'professional':
                return 'Professional';
            case 'manual':
                return 'Manually uploaded';
        }

        return 'Unknown';
    }

    static public function getTypeSelectOptions($pollyDescription = "I would like the server to automatically synthesize my text (text-to-speech technology)") {
        return [$pollyDescription => 'polly',
                "I will upload an audio file manually" => "manual",
                "I would like for someone to narrate the text" => "human",
                "I would like for a professional voice actor to narrate the text" => "professional"];
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): self
    {
        $this->details = $details;

        return $this;
    }

    public function getLength(): ?int
    {
        return $this->length;
    }

    public function setLength(?int $length): self
    {
        $this->length = $length;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getOption(string $key) {
        $details = $this->getDetails();
        $details = $details ? json_decode($details, true) : [];

        return $details[$key] ?? null;
    }

    public function setOption(string $key, $value) {
        $details = $this->getDetails();
        $details = $details ? json_decode($details, true) : [];
        $details[$key] = $value;
        $this->setDetails(json_encode($details));

        return $this;
    }

    public function getEntry(): ?Entry
    {
        return $this->entry;
    }

    public function setEntry(?Entry $entry): self
    {
        $this->entry = $entry;

        return $this;
    }

    public function getModifiedDate(): ?\DateTimeInterface
    {
        return $this->modifiedDate;
    }

    public function setModifiedDate(?\DateTimeInterface $modifiedDate): self
    {
        $this->modifiedDate = $modifiedDate;

        return $this;
    }
    /**
     * @ORM\PrePersist()
     * @ORM\PreUpdate()
     */
    public function updateModifiedDate()
    {
        $this->setModifiedDate(new \DateTime());
    }

    public function getPreferredVoice() {
        return $this->getOption('voice');
    }

    public function setPreferredVoice(?String $voice) {
        return $this->setOption('voice', $voice);
    }

    public function getPreferredVoiceGender() {
        return $this->getOption('gender');
    }

    public function setPreferredVoiceGender(?String $gender) {
        return $this->setOption('gender', $gender);
    }

    public function delete(&$em) {
        $em->remove($this);
    }

    static public function getAcceptedFileTypes() {
        return ['audio/webm', 'audio/ogg', 'audio/mpeg', 'audio/mp3', 'audio/wave', 'audio/wav', 'audio/x-wav', 'audio/flac'];
    }
}
