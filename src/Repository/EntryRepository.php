<?php

namespace App\Repository;

use App\Entity\Entry;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Entry|null find($id, $lockMode = null, $lockVersion = null)
 * @method Entry|null findOneBy(array $criteria, array $orderBy = null)
 * @method Entry[]    findAll()
 * @method Entry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EntryRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Entry::class);
    }

    public function findOneFromProjectByUrl(Project $project, String $url): ?Entry
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.project = :project')
            ->andWhere('e.url = :url')
            ->setParameter('project', $project)
            ->setParameter('url', $url)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findAllFromProjectByUrl(Project $project, String $url): ?ArrayCollection
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.project = :project')
            ->andWhere('e.url = :url')
            ->setParameter('project', $project)
            ->setParameter('url', $url)
            ->getQuery()
            ->getResult()
        ;
    }
}
