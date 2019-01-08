<?php

namespace App\Repository;

use App\Entity\Voiceover;
use App\Entity\Entry;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Voiceover|null find($id, $lockMode = null, $lockVersion = null)
 * @method Voiceover|null findOneBy(array $criteria, array $orderBy = null)
 * @method Voiceover[]    findAll()
 * @method Voiceover[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VoiceoverRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Voiceover::class);
    }

    public function findPollyScheduled()
    {
        $voiceovers = $this->createQueryBuilder('v')
            ->andWhere('v.type = :type')
            ->andWhere('v.status = :status')
            ->orderBy('v.creationDate', 'DESC')
            ->setParameter('type', 'polly')
            ->setParameter('status', 'scheduled')
            ->getQuery()
            ->getResult();

        $em = $this->getEntityManager();
        $flushDeletions = false;
        $filteredVoiceovers = new ArrayCollection();
        $selectedEntries = [];
        foreach ($voiceovers as $voiceover) {
            if (in_array($voiceover->getEntry()->getId(), $selectedEntries)) {
                $flushDeletions = true;
                $em->remove($voiceover);
                continue;
            }

            $selectedEntries[] = $voiceover->getEntry()->getId();
            $filteredVoiceovers->add($voiceover);
        }

        if ($flushDeletions) {
            $em->flush();
        }

        return $voiceovers;
    }

    public function findLatestByUrl($url): ?Voiceover
    {
        # AFAIK, this is not used anymore.
        return $this->createQueryBuilder('v')
            ->andWhere('v.url = :url')
            ->setParameter('url', $url)
            ->orderBy('v.creationDate', 'DESC')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
