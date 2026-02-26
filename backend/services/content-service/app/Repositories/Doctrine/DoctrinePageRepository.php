<?php

namespace App\Repositories\Doctrine;

use App\Entities\Page;
use App\Entities\Site;
use App\Repositories\PageRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DoctrinePageRepository implements PageRepositoryInterface
{
    /** @var EntityRepository<Page> */
    private EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        $this->repository = $entityManager->getRepository(Page::class);
    }

    public function find(string $uid): ?Page
    {
        return $this->repository->find($uid);
    }

    public function findByPathAndSite(string $path, Site $site): ?Page
    {
        return $this->repository->findOneBy(['path' => $path, 'site' => $site]);
    }

    public function save(Page $page): void
    {
        $this->entityManager->persist($page);
        $this->entityManager->flush();
    }

    public function delete(Page $page): void
    {
        $this->entityManager->remove($page);
        $this->entityManager->flush();
    }
}
