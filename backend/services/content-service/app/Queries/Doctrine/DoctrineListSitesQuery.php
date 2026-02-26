<?php

namespace App\Queries\Doctrine;

use App\Entities\Site;
use App\Queries\ListSitesQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DoctrineListSitesQuery implements ListSitesQuery
{
    /** @var EntityRepository<Site> */
    private EntityRepository $repository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->repository = $entityManager->getRepository(Site::class);
    }

    public function execute(): array
    {
        $sites = $this->repository->findAll();

        return [
            'archetype' => 'document-collection',
            'items'     => array_map(static fn(Site $site) => [
                'archetype'   => 'document',
                'identifiers' => ['uid' => $site->getUid()],
                'header'      => ['status' => $site->getStatus()],
                'body'        => ['title' => $site->getTitle()],
            ], $sites),
        ];
    }
}
