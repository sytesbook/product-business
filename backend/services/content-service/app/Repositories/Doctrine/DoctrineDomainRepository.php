<?php

namespace App\Repositories\Doctrine;

use App\Entities\Domain;
use App\Entities\Site;
use App\Repositories\DomainRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DoctrineDomainRepository implements DomainRepositoryInterface
{
    /** @var EntityRepository<Domain> */
    private EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        $this->repository = $entityManager->getRepository(Domain::class);
    }

    public function find(string $uid): ?Domain
    {
        return $this->repository->find($uid);
    }

    public function findByDomain(string $domain): ?Domain
    {
        return $this->repository->findOneBy(['domain' => $domain]);
    }

    public function findPrimaryBySite(Site $site): ?Domain
    {
        return $this->repository->findOneBy(['site' => $site, 'isPrimary' => true]);
    }

    public function save(Domain $domain): void
    {
        $this->entityManager->persist($domain);
        $this->entityManager->flush();
    }

    public function delete(Domain $domain): void
    {
        $this->entityManager->remove($domain);
        $this->entityManager->flush();
    }
}
