<?php

namespace App\Queries\Doctrine;

use App\Entities\Site;
use App\Queries\GetSiteQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DoctrineGetSiteQuery implements GetSiteQuery
{
    /** @var EntityRepository<Site> */
    private EntityRepository $repository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->repository = $entityManager->getRepository(Site::class);
    }

    public function execute(string $uid): ?array
    {
        $site = $this->repository->find($uid);

        if ($site === null) {
            return null;
        }

        return [
            'archetype'   => 'document',
            'identifiers' => ['uid' => $site->getUid()],
            'header'      => ['status' => $site->getStatus()],
            'body'        => ['title' => $site->getTitle()],
        ];
    }
}
