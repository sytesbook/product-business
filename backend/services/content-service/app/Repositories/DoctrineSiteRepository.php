<?php

namespace App\Repositories;

use App\Entities\Site;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DoctrineSiteRepository implements SiteRepositoryInterface
{
    /** @var EntityRepository<Site> */
    private EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        $this->repository = $entityManager->getRepository(Site::class);
    }

    public function find(string $uid): ?Site
    {
        return $this->repository->find($uid);
    }

    public function save(Site $site): void
    {
        $this->entityManager->persist($site);
        $this->entityManager->flush();
    }

    public function delete(Site $site): void
    {
        $this->entityManager->remove($site);
        $this->entityManager->flush();
    }
}
